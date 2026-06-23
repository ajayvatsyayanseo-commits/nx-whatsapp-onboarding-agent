<?php

declare(strict_types=1);

/**
 * NXtutors WhatsApp Onboarding Agent — production HTTP entrypoint.
 *
 * Architecture (shared Meta WhatsApp number):
 *
 *   Meta WhatsApp Cloud API
 *     -> Lead Intake Agent (public Meta webhook owner)
 *     -> Lead Intake detects a signup/onboarding message
 *     -> Lead Intake POSTs an internal handoff here with X-NXTUTORS-INTERNAL-SECRET
 *     -> Onboarding validates the internal secret, advances the conversation, and
 *        returns reply_text
 *     -> Lead Intake sends exactly ONE WhatsApp reply
 *
 * This agent is NOT the public Meta webhook for the shared number. It still
 * keeps genuine Meta signature verification for any direct Meta webhook request
 * so the endpoint is safe if it is ever pointed at directly.
 *
 * Conversation state:
 *   This standalone runtime keeps a small, file-backed session per WhatsApp phone
 *   so the flow advances (role -> fields -> review -> terms -> done) instead of
 *   restarting on every message. State lives under ONBOARDING_SESSION_DIR.
 *
 *   Messages that arrive when the user is NOT in an onboarding flow and are not a
 *   signup intent are NOT handled here; they are returned to lead-intake with
 *   handled=false / forward_to_lead_intake=true so lead-intake answers them.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/**
 * Load a `.env` file into the process environment for this standalone runtime.
 *
 * `getenv()` only sees variables that were actually exported into the PHP-FPM /
 * CLI process. On a single-server deployment (the live setup) the operator edits
 * a `.env` file and expects it to take effect, so we read it here.
 *
 * Real environment variables ALWAYS win: a key already present in the process
 * environment is never overwritten. This keeps platform-injected secrets
 * (e.g. ONBOARDING_AGENT_INTERNAL_SECRET on ECS) authoritative while letting the
 * `.env` file fill in anything the runtime did not set.
 */
function load_onboarding_env(): void
{
    $explicit = getenv('ONBOARDING_ENV_FILE');
    $candidates = [];
    if (is_string($explicit) && $explicit !== '') {
        $candidates[] = $explicit;
    }
    // Search the common locations relative to public/index.php.
    $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . '.env';
    $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'nx-whatsapp-onboarding-agent' . DIRECTORY_SEPARATOR . '.env';

    $file = '';
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            $file = $candidate;
            break;
        }
    }
    if ($file === '') {
        return;
    }

    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eq));
        if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            continue;
        }

        // Real environment wins — never override an already-exported variable.
        if (getenv($key) !== false) {
            continue;
        }

        $value = trim(substr($line, $eq + 1));
        // Strip an inline trailing comment for unquoted values.
        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $hash = strpos($value, ' #');
            if ($hash !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
        }
        // Strip matching surrounding quotes.
        $len = strlen($value);
        if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

load_onboarding_env();

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function request_header(string $name): string
{
    $serverName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

    return (string) ($_SERVER[$serverName] ?? '');
}

function env_value(string ...$names): string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }
    }

    return '';
}

function app_env(): string
{
    return strtolower(env_value('APP_ENV') ?: 'production');
}

function is_production(): bool
{
    return app_env() === 'production';
}

function correlation_id(): string
{
    $id = request_header('X-Correlation-Id') ?: request_header('X-Request-Id');

    return $id !== '' ? $id : bin2hex(random_bytes(8));
}

function normalized_text(string $text): string
{
    return trim((string) preg_replace('/\s+/', ' ', u_lower($text)));
}

/**
 * UTF-8 aware-ish helpers that degrade gracefully when the mbstring extension
 * is not installed (the production Dockerfile does not bundle mbstring).
 */
function u_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function u_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

/**
 * Return the first non-empty scalar among the given payload keys.
 *
 * @param array<string, mixed> $payload
 * @param list<string>         $keys
 */
function first_scalar(array $payload, array $keys): string
{
    foreach ($keys as $key) {
        $value = $payload[$key] ?? null;
        if (is_scalar($value) && (string) $value !== '') {
            return (string) $value;
        }
    }

    return '';
}

/** @param array<string, mixed> $payload */
function nested_message(array $payload): ?array
{
    $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    return is_array($message) ? $message : null;
}

/**
 * Normalize the inbound phone across lead-intake aliases and Meta payloads.
 *
 * Aliases: wa_phone | phone | from
 *
 * @param array<string, mixed> $payload
 */
function normalize_phone(array $payload): string
{
    $value = first_scalar($payload, ['wa_phone', 'phone', 'from']);
    if ($value !== '') {
        return $value;
    }

    $message = nested_message($payload);

    return $message !== null ? (string) ($message['from'] ?? '') : '';
}

/**
 * Normalize the inbound message text across aliases and Meta payloads.
 *
 * Aliases: message_text | text | body
 *
 * @param array<string, mixed> $payload
 */
function normalize_text(array $payload): string
{
    $value = first_scalar($payload, ['message_text', 'text', 'body']);
    if ($value !== '') {
        return $value;
    }

    $message = nested_message($payload);
    if ($message === null) {
        return '';
    }

    return (string) (
        $message['text']['body']
        ?? $message['button']['text']
        ?? $message['interactive']['button_reply']['title']
        ?? $message['interactive']['list_reply']['title']
        ?? ''
    );
}

/**
 * Normalize the WhatsApp message id across aliases and Meta payloads.
 *
 * Aliases: wa_message_id | message_id | id
 *
 * @param array<string, mixed> $payload
 */
function normalize_message_id(array $payload): string
{
    $value = first_scalar($payload, ['wa_message_id', 'message_id', 'id']);
    if ($value !== '') {
        return $value;
    }

    $message = nested_message($payload);

    return $message !== null ? (string) ($message['id'] ?? '') : '';
}

/**
 * Detect the onboarding role the user is asking for.
 *
 * Detection categories: student, parent/student, tutor/teacher, unknown.
 * The returned role is normalized to the response contract: student|tutor|unknown.
 */
function detect_role(string $text): string
{
    $text = normalized_text($text);

    // Explicit keywords take priority over the numbered menu so a phrase like
    // "I want to register as tutor" is never mis-read as a menu number.
    if (str_contains($text, 'tutor') || str_contains($text, 'teacher') || str_contains($text, 'teach')) {
        return 'tutor';
    }

    if (str_contains($text, 'student') || str_contains($text, 'parent') || str_contains($text, 'learner') || str_contains($text, 'study')) {
        return 'student';
    }

    // Numbered menu answers: "1" => Student, "2" => Tutor. Accept a bare number
    // or light decoration ("1", "1.", "1)", "option 1") so a quick reply works.
    if (preg_match('/^(?:option\s*)?1[.):]?$/', $text) === 1) {
        return 'student';
    }

    if (preg_match('/^(?:option\s*)?2[.):]?$/', $text) === 1) {
        return 'tutor';
    }

    return 'unknown';
}

/**
 * Does this message look like a request to start signup/onboarding?
 *
 * A role keyword (student/tutor/1/2) also starts the flow and is detected
 * separately via detect_role(); this covers the generic intents.
 */
function is_signup_intent(string $text): bool
{
    $t = normalized_text($text);
    if ($t === '') {
        return false;
    }

    foreach (['signup', 'sign up', 'register', 'registration', 'enroll', 'enrol', 'admission', 'join nxtutors', 'create account', 'create profile', 'new account', 'i want to register', 'i want to signup', 'i want to sign up'] as $needle) {
        if (str_contains($t, $needle)) {
            return true;
        }
    }

    return false;
}

function role_selection_message(): string
{
    return "👋 *Welcome to NXtutors!*\n\n"
        . "Who are you signing up as?\n\n"
        . "*1.* 🎓 _Student_\n"
        . "      • Find expert tutors\n"
        . "      • Book classes that fit you\n\n"
        . "*2.* 👨‍🏫 _Tutor_\n"
        . "      • Create your teaching profile\n"
        . "      • Get student enquiries\n\n"
        . "👉 _Reply 1 or 2_";
}

function invalid_role_message(): string
{
    return "🤔 Sorry, I didn't catch that.\n\n"
        . "Who are you signing up as?\n\n"
        . "*1.* 🎓 Student\n"
        . "*2.* 👨‍🏫 Tutor\n\n"
        . "👉 _Reply 1 or 2_";
}

function mask_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) <= 4) {
        return $digits === '' ? '' : '****';
    }

    return '+' . substr($digits, 0, 2) . str_repeat('*', max(2, strlen($digits) - 6)) . substr($digits, -4);
}

function mask_email(string $email): string
{
    $at = strpos($email, '@');
    if ($at === false || $at < 1) {
        return $email === '' ? '' : '***';
    }

    return substr($email, 0, 1) . str_repeat('*', max(1, $at - 1)) . substr($email, $at);
}

function mask_document(string $value): string
{
    $value = trim($value);
    if (strlen($value) <= 4) {
        return $value === '' ? '' : '****';
    }

    return str_repeat('*', strlen($value) - 4) . substr($value, -4);
}

/* -------------------------------------------------------------------------- */
/* Conversation field definitions (mirror of the package flow definitions)    */
/* -------------------------------------------------------------------------- */

/** @return list<array{key:string,q:string,optional:bool,type:string}> */
function student_fields(): array
{
    return [
        ['key' => 'name', 'q' => "Great, let's create your student profile. What is your full name?", 'optional' => false, 'type' => 'name'],
        ['key' => 'email', 'q' => 'What email should we use for login and updates?', 'optional' => false, 'type' => 'email'],
        ['key' => 'dob', 'q' => 'Please enter your date of birth as YYYY-MM-DD, or type SKIP.', 'optional' => true, 'type' => 'dob'],
        ['key' => 'gender', 'q' => 'Please enter gender: male, female, or other. You can type SKIP.', 'optional' => true, 'type' => 'gender'],
        ['key' => 'class_type', 'q' => 'What type of class do you want? Example: online, home tuition, group.', 'optional' => false, 'type' => 'class'],
        ['key' => 'for_class', 'q' => 'Which class or course do you need tutoring for?', 'optional' => false, 'type' => 'class'],
        ['key' => 'budget', 'q' => 'What is your monthly tutoring budget? Example: 5000 monthly. Or type SKIP.', 'optional' => true, 'type' => 'budget'],
        ['key' => 'address', 'q' => 'Please share your address, or type SKIP.', 'optional' => true, 'type' => 'address'],
        ['key' => 'city', 'q' => 'Please share your city, or type SKIP.', 'optional' => true, 'type' => 'location'],
        ['key' => 'district', 'q' => 'Please share your district, or type SKIP.', 'optional' => true, 'type' => 'location'],
        ['key' => 'state', 'q' => 'Please share your state, or type SKIP.', 'optional' => true, 'type' => 'location'],
        ['key' => 'pincode', 'q' => 'Please enter your 6 digit pincode, or type SKIP.', 'optional' => true, 'type' => 'pincode'],
        ['key' => 'profile_desc', 'q' => 'Anything else tutors should know? Type SKIP if not.', 'optional' => true, 'type' => 'profile_text'],
    ];
}

/** @return list<array{key:string,q:string,optional:bool,type:string}> */
function tutor_fields(): array
{
    return [
        ['key' => 'name', 'q' => "Great, let's create your tutor profile. What is your full name?", 'optional' => false, 'type' => 'name'],
        ['key' => 'email', 'q' => 'Please enter your email address.', 'optional' => false, 'type' => 'email'],
        ['key' => 'dob', 'q' => 'Please enter your date of birth as YYYY-MM-DD, or type SKIP.', 'optional' => true, 'type' => 'dob'],
        ['key' => 'gender', 'q' => 'Please enter gender: male, female, or other. You can type SKIP.', 'optional' => true, 'type' => 'gender'],
        ['key' => 'education', 'q' => 'Please enter your highest education.', 'optional' => false, 'type' => 'education'],
        ['key' => 'other_education', 'q' => 'Any other education or certification? Reply NA if none.', 'optional' => true, 'type' => 'any'],
        ['key' => 'experience', 'q' => 'How many years of teaching experience do you have? Example: 3 years.', 'optional' => false, 'type' => 'experience'],
        ['key' => 'degree', 'q' => 'Please share your degree / qualification name, or type SKIP.', 'optional' => true, 'type' => 'any'],
        ['key' => 'class_type', 'q' => 'What class type can you teach? Example: online, home tuition, group.', 'optional' => false, 'type' => 'class'],
        ['key' => 'for_class', 'q' => 'Which classes or subjects can you teach?', 'optional' => false, 'type' => 'class'],
        ['key' => 'budget', 'q' => 'What fee do you charge? You can type a number or range, or SKIP.', 'optional' => true, 'type' => 'budget'],
        ['key' => 'address', 'q' => 'Please enter your full address, or type SKIP.', 'optional' => true, 'type' => 'address'],
        ['key' => 'city', 'q' => 'Please enter your city, or type SKIP.', 'optional' => true, 'type' => 'location'],
        ['key' => 'district', 'q' => 'Please enter your district, or type SKIP.', 'optional' => true, 'type' => 'location'],
        ['key' => 'state', 'q' => 'Please enter your state, or type SKIP.', 'optional' => true, 'type' => 'location'],
        ['key' => 'pincode', 'q' => 'Please enter your 6 digit pincode, or type SKIP.', 'optional' => true, 'type' => 'pincode'],
        ['key' => 'document_type', 'q' => 'Please select document type: Aadhaar / PAN / Passport / Driving License / Voter ID / Other.', 'optional' => false, 'type' => 'document_type'],
        ['key' => 'document_number', 'q' => 'Please enter the selected document number.', 'optional' => false, 'type' => 'document_number'],
        ['key' => 'front_image', 'q' => 'Please share the front image of your document (send the photo), or type SKIP.', 'optional' => true, 'type' => 'any'],
        ['key' => 'back_image', 'q' => 'Please share the back image of your document (send the photo), or type SKIP.', 'optional' => true, 'type' => 'any'],
        ['key' => 'profile', 'q' => 'Write a short tutor profile title.', 'optional' => false, 'type' => 'profile_text'],
        ['key' => 'profile_desc', 'q' => 'Please write a short tutor profile description for students.', 'optional' => false, 'type' => 'profile_text'],
        ['key' => 'pro_desc', 'q' => 'Write a detailed professional description for your tutor profile.', 'optional' => false, 'type' => 'profile_text'],
    ];
}

/** @return list<array{key:string,q:string,optional:bool,type:string}> */
function role_fields(string $role): array
{
    return $role === 'tutor' ? tutor_fields() : student_fields();
}

/**
 * Validate a single field value.
 *
 * @return array{0:bool,1:string} [ok, error_message]
 */
function validate_field(string $type, string $value): array
{
    $value = trim($value);

    switch ($type) {
        case 'name':
            return [u_len($value) >= 2 && u_len($value) <= 255, 'Name must be 2 to 255 characters.'];
        case 'email':
            return [(bool) filter_var($value, FILTER_VALIDATE_EMAIL), 'Please enter a valid email address.'];
        case 'dob':
            return [is_valid_dob($value), 'Please enter DOB as YYYY-MM-DD and not a future date.'];
        case 'gender':
            return [in_array(u_lower($value), ['male', 'female', 'other'], true), 'Gender must be male, female, or other.'];
        case 'address':
            return [u_len($value) >= 5 && u_len($value) <= 500, 'Please enter a complete address.'];
        case 'location':
            return [u_len($value) >= 2 && u_len($value) <= 120, 'Please enter a valid location.'];
        case 'pincode':
            return [preg_match('/^[1-9][0-9]{5}$/', $value) === 1, 'Please enter a valid 6 digit pincode.'];
        case 'class':
            return [u_len($value) >= 2 && u_len($value) <= 255, 'Please enter a valid class or course.'];
        case 'budget':
            $ok = u_len($value) <= 80
                && preg_match('/[0-9]{2,8}/', $value) === 1
                && preg_match('/^[0-9A-Za-z ,.+\-\/]+$/', $value) === 1;
            return [$ok, 'Please enter a valid budget or fee.'];
        case 'profile_text':
            return [u_len($value) >= 2 && u_len($value) <= 1000, 'Please keep this text between 2 and 1000 characters.'];
        case 'education':
            return [u_len($value) >= 2 && u_len($value) <= 255, 'Please enter qualification details.'];
        case 'experience':
            return [preg_match('/^[0-9]{1,2}([.][0-9])?(\s*(years?|yrs?))?$/i', $value) === 1, 'Please enter experience in years, for example 3 years.'];
        case 'document_type':
            return [in_array(u_lower($value), ['aadhaar', 'aadhar', 'pan', 'passport', 'driving license', 'driving licence', 'voter id', 'other'], true), 'Document type must be Aadhaar, PAN, Passport, Driving License, Voter ID, or Other.'];
        case 'document_number':
            return [preg_match('/^[A-Za-z0-9 -]{4,255}$/', $value) === 1, 'Please enter a valid document number.'];
        case 'any':
        default:
            return [$value !== '', 'Please reply with the requested detail, or type SKIP if allowed.'];
    }
}

function is_valid_dob(string $value): bool
{
    $value = trim($value);
    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return false;
    }

    return $date <= new \DateTimeImmutable('today');
}

/* -------------------------------------------------------------------------- */
/* Session store (file-backed, per WhatsApp phone)                            */
/* -------------------------------------------------------------------------- */

function session_dir(): string
{
    $dir = env_value('ONBOARDING_SESSION_DIR');
    if ($dir === '') {
        $base = env_value('ONBOARDING_IDEMPOTENCY_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nxtutors_onboarding');
        $dir = $base . DIRECTORY_SEPARATOR . 'sessions';
    }
    if (! is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir;
}

function session_path(string $phone): string
{
    return session_dir() . DIRECTORY_SEPARATOR . 'sess_' . sha1($phone) . '.json';
}

function session_ttl_seconds(): int
{
    $minutes = (int) (env_value('WHATSAPP_ONBOARDING_CONVERSATION_TTL_MINUTES') ?: 10080);

    return $minutes > 0 ? $minutes * 60 : 604800;
}

/** @return array<string, mixed>|null */
function load_session(string $phone): ?array
{
    $file = session_path($phone);
    if (! is_file($file)) {
        return null;
    }

    if ((time() - (int) @filemtime($file)) > session_ttl_seconds()) {
        @unlink($file);

        return null;
    }

    $decoded = json_decode((string) @file_get_contents($file), true);

    return is_array($decoded) ? $decoded : null;
}

/** @param array<string, mixed> $session */
function save_session(string $phone, array $session): void
{
    $session['updated_at'] = time();
    @file_put_contents(session_path($phone), json_encode($session, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function clear_session(string $phone): void
{
    @unlink(session_path($phone));
}

/* -------------------------------------------------------------------------- */
/* Lead capture (completed signups)                                           */
/* -------------------------------------------------------------------------- */

function leads_dir(): string
{
    $dir = env_value('ONBOARDING_LEADS_DIR');
    if ($dir === '') {
        $base = env_value('ONBOARDING_IDEMPOTENCY_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nxtutors_onboarding');
        $dir = $base . DIRECTORY_SEPARATOR . 'leads';
    }
    if (! is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir;
}

/** @param array<string, mixed> $data */
function capture_lead(string $role, string $phone, array $data): string
{
    $lead = [
        'role' => $role,
        'wa_phone' => $phone,
        'data' => $data,
        'captured_at' => date('c'),
        'source' => 'whatsapp_onboarding_agent',
    ];

    $file = leads_dir() . DIRECTORY_SEPARATOR . 'lead_' . time() . '_' . sha1($phone . microtime()) . '.json';
    @file_put_contents($file, json_encode($lead, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);

    // Also append to a single JSONL stream for easy bulk export.
    @file_put_contents(leads_dir() . DIRECTORY_SEPARATOR . 'leads.jsonl', json_encode($lead, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

    return $file;
}

/* -------------------------------------------------------------------------- */
/* Real account creation in the website `register` table (PDO)                */
/* -------------------------------------------------------------------------- */

/**
 * Is direct creation of the legacy `register` row enabled? Requires both the
 * feature flag AND a configured database connection. Keeps the file-capture
 * fallback whenever either is missing or the write fails.
 */
function real_profile_enabled(): bool
{
    if (strtolower(env_value('WHATSAPP_CREATE_REAL_PROFILE') ?: 'false') !== 'true') {
        return false;
    }

    return env_value('DB_HOST') !== '' || env_value('DB_CONNECTION') === 'sqlite';
}

function register_table_name(): string
{
    $table = env_value('WHATSAPP_ONBOARDING_REGISTER_TABLE') ?: 'register';

    // Guard against injection — only a plain identifier is ever used.
    return preg_match('/^[A-Za-z0-9_]+$/', $table) === 1 ? $table : 'register';
}

function quote_ident(string $driver, string $name): string
{
    return $driver === 'mysql' ? '`' . $name . '`' : '"' . $name . '"';
}

/**
 * Open a PDO connection to the website database, or return [null, ''] when it is
 * not configured / unreachable. Supports mysql (the live website), pgsql, sqlite.
 *
 * @return array{0:?PDO,1:string} [pdo, driver]
 */
function pdo_connect(): array
{
    if (! class_exists('PDO')) {
        return [null, ''];
    }

    $connection = env_value('DB_CONNECTION');
    $driver = $connection === 'pgsql' ? 'pgsql' : ($connection === 'sqlite' ? 'sqlite' : 'mysql');

    try {
        if ($driver === 'sqlite') {
            $database = env_value('DB_DATABASE', 'DB_NAME');
            if ($database === '') {
                return [null, ''];
            }
            $pdo = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 4]);

            return [$pdo, $driver];
        }

        $host = env_value('DB_HOST');
        if ($host === '') {
            return [null, ''];
        }
        $port = env_value('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
        $database = env_value('DB_DATABASE', 'DB_NAME');
        $dsn = $driver === 'pgsql'
            ? sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database)
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $pdo = new PDO(
            $dsn,
            env_value('DB_USERNAME', 'DB_USER'),
            env_value('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 4],
        );

        return [$pdo, $driver];
    } catch (\Throwable $e) {
        error_log(json_encode(['event' => 'register_db_connect_failed', 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES));

        return [null, ''];
    }
}

/**
 * Introspect the table and return a map of lowercased column name => actual
 * column name, so the insert only ever touches columns that really exist in the
 * legacy schema (which we cannot see at build time).
 *
 * @return array<string, string>
 */
function register_columns(PDO $pdo, string $driver, string $table): array
{
    $columns = [];
    try {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query('PRAGMA table_info(' . quote_ident($driver, $table) . ')');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '') {
                    $columns[strtolower($name)] = $name;
                }
            }
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_name = ?');
            $stmt->execute([$table]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
                $columns[strtolower((string) $name)] = (string) $name;
            }
        } else {
            $stmt = $pdo->query('SHOW COLUMNS FROM ' . quote_ident($driver, $table));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string) ($row['Field'] ?? '');
                if ($name !== '') {
                    $columns[strtolower($name)] = $name;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log(json_encode(['event' => 'register_columns_failed', 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES));
    }

    return $columns;
}

/** Does a row exist where $column = $value? Returns false if the column is absent. */
function register_exists(PDO $pdo, string $driver, string $table, array $columns, string $column, string $value): bool
{
    if (! isset($columns[$column]) || $value === '') {
        return false;
    }

    try {
        $sql = 'SELECT 1 FROM ' . quote_ident($driver, $table) . ' WHERE ' . quote_ident($driver, $columns[$column]) . ' = ? LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$value]);

        return $stmt->fetchColumn() !== false;
    } catch (\Throwable $e) {
        return false;
    }
}

function generate_user_id(string $role): string
{
    $prefix = $role === 'tutor'
        ? (env_value('WHATSAPP_USER_ID_PREFIX_TUTOR') ?: 'NXT')
        : (env_value('WHATSAPP_USER_ID_PREFIX_STUDENT') ?: 'NXS');
    $year = date('Y');
    $length = max(4, (int) (env_value('WHATSAPP_USER_ID_RANDOM_LENGTH') ?: 6));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $random = '';
    for ($i = 0; $i < $length; $i++) {
        $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return sprintf('%s-%s-%s', $prefix, $year, $random);
}

function generate_temp_password(): string
{
    $length = max(8, (int) (env_value('WHATSAPP_ONBOARDING_TEMP_PASSWORD_LENGTH') ?: 12));
    // Avoid visually ambiguous characters for a password read off a phone.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $password;
}

/**
 * Build the candidate column => value map. Required identity columns are always
 * present; optional columns are only included when non-empty so we never push an
 * empty string into a typed (DATE/INT) legacy column under strict SQL mode.
 *
 * @param array<string, mixed> $data
 * @return array<string, string|int>
 */
function register_candidate_values(string $role, string $phone, array $data, string $userId, string $passwordHash): array
{
    $now = date('Y-m-d H:i:s');

    $values = [
        'user_id' => $userId,
        'name' => trim((string) ($data['name'] ?? '')),
        'email' => trim((string) ($data['email'] ?? '')),
        'phone' => $phone,
        'password' => $passwordHash,
        'user_type' => $role === 'tutor'
            ? (env_value('WHATSAPP_USER_TYPE_TUTOR') ?: 'Individual')
            : (env_value('WHATSAPP_USER_TYPE_STUDENT') ?: 'student'),
        'join_as' => $role === 'tutor' ? 'teacher' : 'student',
        'otp_status' => env_value('WHATSAPP_OTP_STATUS_VERIFIED') ?: 't',
        'status' => $role === 'tutor'
            ? (env_value('WHATSAPP_TUTOR_STATUS') ?: 't')
            : (env_value('WHATSAPP_STUDENT_STATUS') ?: 't'),
        'date' => $now,
    ];

    foreach (['dob', 'gender', 'class_type', 'for_class', 'budget', 'address', 'city', 'district', 'state', 'pincode', 'education', 'other_education', 'experience', 'degree', 'document_type', 'document_number', 'profile', 'profile_desc', 'pro_desc'] as $key) {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value !== '') {
            $values[$key] = $value;
        }
    }

    // Legacy media columns. The current website uses the misspelled `frount_image`;
    // cover the corrected `front_image` too in case the schema was fixed.
    $front = trim((string) ($data['front_image'] ?? ''));
    if ($front !== '') {
        $values['frount_image'] = $front;
        $values['front_image'] = $front;
    }
    $back = trim((string) ($data['back_image'] ?? ''));
    if ($back !== '') {
        $values['back_image'] = $back;
    }

    // Only applied when the column actually exists in the table.
    $values['force_password_reset'] = 1;
    $values['c_password'] = '';
    $values['created_at'] = $now;
    $values['updated_at'] = $now;

    return $values;
}

/**
 * Create the real `register` row.
 *
 * @param array<string, mixed> $data
 * @return array{status:string,user_id?:string,email?:string,temp_password?:string,error?:string}
 */
function create_register_profile(string $role, string $phone, array $data): array
{
    [$pdo, $driver] = pdo_connect();
    if ($pdo === null) {
        return ['status' => 'error', 'error' => 'db_unavailable'];
    }

    $table = register_table_name();
    $columns = register_columns($pdo, $driver, $table);
    if ($columns === []) {
        return ['status' => 'error', 'error' => 'table_introspection_failed'];
    }

    $email = trim((string) ($data['email'] ?? ''));

    // The NXtutors website builds the tutor profile URL from city/district and
    // returns a 500 (missing route parameter) when either is empty. Guarantee
    // non-empty values for every tutor created here — covers a manually skipped
    // city and a Pro-mode CV with no address.
    if ($role === 'tutor') {
        $city = trim((string) ($data['city'] ?? ''));
        if ($city === '') {
            $city = trim((string) ($data['state'] ?? '')) ?: 'India';
            $data['city'] = $city;
        }
        if (trim((string) ($data['district'] ?? '')) === '') {
            $data['district'] = $city;
        }
    }

    // Duplicate guards against the existing website accounts.
    if (register_exists($pdo, $driver, $table, $columns, 'phone', $phone)
        || register_exists($pdo, $driver, $table, $columns, 'email', $email)) {
        return ['status' => 'duplicate'];
    }

    // Unique user_id (retry a few times against the existing table).
    $userId = generate_user_id($role);
    for ($attempt = 0; $attempt < 5 && register_exists($pdo, $driver, $table, $columns, 'user_id', $userId); $attempt++) {
        $userId = generate_user_id($role);
    }

    $tempPassword = generate_temp_password();
    $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

    $candidates = register_candidate_values($role, $phone, $data, $userId, (string) $passwordHash);

    $cols = [];
    $placeholders = [];
    $args = [];
    foreach ($candidates as $key => $value) {
        if (isset($columns[$key])) {
            $cols[] = quote_ident($driver, $columns[$key]);
            $placeholders[] = '?';
            $args[] = $value;
        }
    }

    if ($cols === []) {
        return ['status' => 'error', 'error' => 'no_matching_columns'];
    }

    try {
        $sql = 'INSERT INTO ' . quote_ident($driver, $table) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
    } catch (\Throwable $e) {
        error_log(json_encode(['event' => 'register_insert_failed', 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES));

        return ['status' => 'error', 'error' => 'insert_failed'];
    }

    return ['status' => 'created', 'user_id' => $userId, 'email' => $email, 'temp_password' => $tempPassword];
}

/* -------------------------------------------------------------------------- */
/* Conversation helpers                                                       */
/* -------------------------------------------------------------------------- */

function terms_url(string $role): string
{
    return $role === 'tutor'
        ? (env_value('TERMS_TUTOR_URL') ?: 'https://www.nxtutors.com/terms-conditions')
        : (env_value('TERMS_STUDENT_URL') ?: 'https://www.nxtutors.com/terms-conditions');
}

function privacy_url(string $role): string
{
    return $role === 'tutor'
        ? (env_value('PRIVACY_TUTOR_URL') ?: 'https://www.nxtutors.com/privacy-policy')
        : (env_value('PRIVACY_STUDENT_URL') ?: 'https://www.nxtutors.com/privacy-policy');
}

function terms_prompt(string $role): string
{
    return "Almost done! Please read NXtutors Terms and Privacy Policy before we finish:\n\n"
        . 'Terms: ' . terms_url($role) . "\n"
        . 'Privacy: ' . privacy_url($role) . "\n\n"
        . 'Reply I AGREE to continue.';
}

/** @param array<string, mixed> $data */
function review_summary(string $role, array $data): string
{
    $lines = ['Here is your ' . $role . ' profile summary:'];
    foreach (role_fields($role) as $field) {
        $key = $field['key'];
        $value = (string) ($data[$key] ?? '');
        if ($value === '') {
            continue;
        }
        if ($key === 'email') {
            $value = mask_email($value);
        } elseif ($key === 'document_number') {
            $value = mask_document($value);
        }
        $label = ucwords(str_replace('_', ' ', $key));
        $lines[] = '• ' . $label . ': ' . $value;
    }
    $lines[] = '';
    $lines[] = 'Reply CONFIRM to continue, or EDIT <field> to change something (e.g. EDIT email).';

    return implode("\n", $lines);
}

/** @param array<string, mixed> $data */
function completion_message(string $role, array $data): string
{
    $name = trim((string) ($data['name'] ?? ''));
    $hi = $name !== '' ? (' ' . explode(' ', $name)[0]) : '';

    return "✅ Thanks{$hi}! We've received your NXtutors " . $role . " signup details.\n\n"
        . "Our team will set up your account and reach out to you shortly on WhatsApp with your login details.\n\n"
        . 'Reply *signup* anytime to start again.';
}

function role_checklist(string $role): string
{
    return $role === 'tutor'
        ? "Next steps:\n1. Login to dashboard\n2. Complete profile photo\n3. Check document verification status\n4. Add courses/subjects\n5. Set availability\n6. Respond to student enquiries"
        : "Next steps:\n1. Login to dashboard\n2. Complete profile photo\n3. Add learning goals\n4. Browse tutors\n5. Book demo/session";
}

function login_url(): string
{
    return env_value('WHATSAPP_ONBOARDING_LOGIN_URL') ?: 'https://www.nxtutors.com/login';
}

function dashboard_url(string $role): string
{
    return $role === 'tutor'
        ? (env_value('TUTOR_DASHBOARD_URL') ?: 'https://www.nxtutors.com/teacher/dashboard')
        : (env_value('STUDENT_DASHBOARD_URL') ?: 'https://www.nxtutors.com/user/dashboard');
}

function credentials_message(string $role, string $email, string $tempPassword): string
{
    $pending = $role === 'tutor'
        ? "\nYour tutor profile is created and pending document review."
        : '';

    return "✅ Your NXtutors " . $role . " account is ready." . $pending . "\n\n"
        . 'Login page: ' . login_url() . "\n"
        . 'Login email: ' . mask_email($email) . "\n"
        . 'Temporary password: ' . $tempPassword . "\n"
        . 'Dashboard after login: ' . dashboard_url($role) . "\n\n"
        . "Please change your password after your first login.\n\n"
        . role_checklist($role);
}

function duplicate_account_message(): string
{
    return 'An NXtutors account already exists with these details. Please login here: ' . login_url()
        . '. If you need help, reply HUMAN.';
}

/** Shown when the WhatsApp number already has an account. */
function duplicate_phone_message(): string
{
    return "✅ *You already have an NXtutors account* registered with this number.\n\n"
        . "🔐 Please log in here:\n" . login_url() . "\n\n"
        . "Need help? Reply *HUMAN*.";
}

/** Shown when the email the user typed already has an account. */
function duplicate_email_message(string $email): string
{
    return "⚠️ An account already exists with *" . mask_email($email) . "*.\n\n"
        . "🔐 Log in here:\n" . login_url() . "\n\n"
        . "Or reply with a *different email* to continue your signup.";
}

function tutor_mode_menu(): string
{
    $price = (int) (env_value('PRO_MODE_PRICE_INR') ?: 2);

    return "You chose *Tutor* 👨‍🏫\n\n"
        . "How would you like to build your profile?\n\n"
        . "*1️⃣  Fill manually*\n"
        . "      • Answer a few quick questions\n"
        . "      • You control every detail\n\n"
        . "*2️⃣  Pro mode — ₹{$price}* ⚡\n"
        . "      • Just upload your CV\n"
        . "      • Our AI writes a full, SEO-rich profile\n"
        . "      • Ready in about a minute\n\n"
        . "👉 _Reply 1 or 2_";
}

function pro_link_message(string $url): string
{
    $price = (int) (env_value('PRO_MODE_PRICE_INR') ?: 2);

    return "🚀 *Pro mode — ₹{$price}*\n\n"
        . "Tap your private link to pay & upload your CV:\n" . $url . "\n\n"
        . "*What happens next:*\n"
        . "      1. Pay ₹{$price} securely (UPI / card)\n"
        . "      2. Upload your CV + name + subject\n"
        . "      3. Our AI writes your full profile ✍️\n"
        . "      4. Get your login details 🔐\n\n"
        . "_Reply 1 to fill manually instead._";
}

/* -------------------------------------------------------------------------- */
/* Idle session timeout + duplicate-account helpers                           */
/* -------------------------------------------------------------------------- */

function session_idle_timeout_seconds(): int
{
    // Defaults to 60s (the requested "1 minute"); floored at 5s to avoid a
    // misconfigured value timing out every single message.
    return max(5, (int) (env_value('SESSION_IDLE_TIMEOUT_SECONDS') ?: 60));
}

function session_timeout_message(): string
{
    return "⏰ *Session paused*\n\n"
        . "You were away for a little while, so I paused your signup to keep it safe.\n\n"
        . "▶️ Reply *continue* to pick up exactly where you left off\n"
        . "✖️ Reply *cancel* to stop";
}

/**
 * Does an account already exist where the given register column matches the
 * value? Uses a prepared statement and only the existing columns. Fails open
 * (returns false) when no DB is configured so signup is never blocked by an
 * outage — the final insert re-checks duplicates anyway.
 */
function account_exists(string $column, string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    [$pdo, $driver] = pdo_connect();
    if ($pdo === null) {
        return false;
    }

    $table = register_table_name();
    $columns = register_columns($pdo, $driver, $table);

    return register_exists($pdo, $driver, $table, $columns, $column, $value);
}

/** Re-issue the prompt for the conversation's current step (used after resume). */
function resume_current_step(string $phone, array $session): array
{
    $state = (string) ($session['state'] ?? '');
    $role = (string) ($session['role'] ?? 'unknown');

    if ($state === 'TUTOR_MODE') {
        return ['status' => 'accepted', 'reply' => tutor_mode_menu(), 'role' => $role, 'forward' => false];
    }
    if ($state === 'PRO_PENDING') {
        $url = pro_base_url() . '/pro/' . (string) ($session['data']['pro_token'] ?? '');

        return ['status' => 'accepted', 'reply' => "▶️ Continue your Pro signup here:\n" . $url, 'role' => $role, 'forward' => false];
    }
    if ($state === 'REVIEW') {
        return ['status' => 'accepted', 'reply' => review_summary($role, (array) $session['data']), 'role' => $role, 'forward' => false];
    }
    if ($state === 'TERMS') {
        return ['status' => 'accepted', 'reply' => terms_prompt($role), 'role' => $role, 'forward' => false];
    }
    if ($state === 'FIELDS') {
        return ['status' => 'accepted', 'reply' => current_field_question($session), 'role' => $role, 'forward' => false];
    }

    return ['status' => 'accepted', 'reply' => role_selection_message(), 'role' => $role, 'forward' => false];
}

function max_invalid_attempts(): int
{
    return (int) (env_value('WHATSAPP_ONBOARDING_MAX_INVALID_ATTEMPTS') ?: 8);
}

/**
 * Detect a global command. Returns [command, argument].
 *
 * @return array{0:string,1:string}
 */
function detect_command(string $text): array
{
    $t = normalized_text($text);

    if (in_array($t, ['cancel', 'stop', 'quit', 'exit'], true)) {
        return ['cancel', ''];
    }
    if ($t === 'restart' || $t === 'reset') {
        return ['restart', ''];
    }
    if ($t === 'review') {
        return ['review', ''];
    }
    if ($t === 'help') {
        return ['help', ''];
    }
    if ($t === 'human' || $t === 'agent' || $t === 'support' || $t === 'talk to human') {
        return ['human', ''];
    }
    if ($t === 'back') {
        return ['back', ''];
    }
    if ($t === 'skip') {
        return ['skip', ''];
    }
    if (in_array($t, ['i agree', 'agree', 'yes i agree', 'iagree'], true)) {
        return ['agree', ''];
    }
    if (in_array($t, ['confirm', 'yes', 'y', 'ok', 'okay'], true)) {
        return ['confirm', ''];
    }
    if (str_starts_with($t, 'edit ')) {
        return ['edit', trim(substr($t, 5))];
    }
    if ($t === 'signup' || $t === 'sign up') {
        return ['signup', ''];
    }

    return ['', ''];
}

/**
 * Build the question/reply for the conversation's current field state.
 *
 * @param array<string, mixed> $session
 */
function current_field_question(array $session): string
{
    $fields = role_fields((string) $session['role']);
    $index = (int) ($session['field'] ?? 0);
    $field = $fields[$index] ?? null;

    return $field['q'] ?? 'Please reply with the requested detail.';
}

/* -------------------------------------------------------------------------- */
/* The conversation engine                                                    */
/* -------------------------------------------------------------------------- */

/**
 * Advance the conversation for one inbound message.
 *
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function process_message(string $phone, string $text): array
{
    $session = load_session($phone);
    [$command, $argument] = detect_command($text);

    // ---------------------------------------------------------------------- //
    // No active conversation (or a finished one).                            //
    // ---------------------------------------------------------------------- //
    if ($session === null || ($session['state'] ?? '') === 'COMPLETED') {
        $role = detect_role($text);
        $startingSignup = $role !== 'unknown' || is_signup_intent($text) || $command === 'signup';

        // Duplicate guard: this WhatsApp number already has an NXtutors account.
        if ($startingSignup && account_exists('phone', $phone)) {
            save_session($phone, [
                'role' => 'unknown',
                'state' => 'EXISTING_ACCOUNT',
                'existing_account_shown' => false,
                'data' => ['wa_phone' => $phone],
            ]);
            return ['status' => 'accepted', 'reply' => duplicate_phone_message(), 'role' => 'unknown', 'forward' => false];
        }

        if ($role !== 'unknown') {
            return start_role($phone, $role);
        }

        if (is_signup_intent($text) || $command === 'signup') {
            save_session($phone, [
                'role' => null,
                'state' => 'ROLE',
                'field' => 0,
                'data' => ['wa_phone' => $phone],
                'invalid' => 0,
                'pending_restart' => false,
                'return_to_review' => false,
            ]);

            return ['status' => 'accepted', 'reply' => role_selection_message(), 'role' => 'unknown', 'forward' => false];
        }

        // Out of context: not in a flow and not a signup intent.
        // Let lead-intake handle this message.
        return ['status' => 'forwarded', 'reply' => null, 'role' => $role, 'forward' => true];
    }

    // ---------------------------------------------------------------------- //
    // Idle timeout: pause an inactive session and offer to continue, instead //
    // of treating this message as the answer to the current step.            //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') !== 'TIMED_OUT'
        && (time() - (int) ($session['updated_at'] ?? time())) > session_idle_timeout_seconds()) {
        $session['resume_state'] = $session['state'] ?? 'ROLE';
        $session['resume_field'] = $session['field'] ?? 0;
        $session['state'] = 'TIMED_OUT';
        save_session($phone, $session);

        return ['status' => 'accepted', 'reply' => session_timeout_message(), 'role' => (string) ($session['role'] ?? 'unknown'), 'forward' => false];
    }

    // ---------------------------------------------------------------------- //
    // State: paused (idle timeout) — wait for continue / cancel.             //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'TIMED_OUT') {
        $t = normalized_text($text);
        if ($command === 'cancel' || in_array($t, ['cancel', 'stop', 'no', 'end'], true)) {
            clear_session($phone);

            return ['status' => 'accepted', 'reply' => "Your signup was cancelled. Reply *signup* anytime to start again. 👋", 'role' => (string) ($session['role'] ?? 'unknown'), 'forward' => false];
        }

        if ($command === 'confirm' || in_array($t, ['continue', 'resume', 'yes', 'y', 'start', '1'], true) || str_contains($t, 'continue')) {
            $session['state'] = (string) ($session['resume_state'] ?? 'ROLE');
            $session['field'] = (int) ($session['resume_field'] ?? 0);
            unset($session['resume_state'], $session['resume_field']);
            save_session($phone, $session);

            return resume_current_step($phone, $session);
        }

        return ['status' => 'accepted', 'reply' => session_timeout_message(), 'role' => (string) ($session['role'] ?? 'unknown'), 'forward' => false];
    }

    // ---------------------------------------------------------------------- //
    // State: existing account detected — handle user response (PRIORITY).    //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'EXISTING_ACCOUNT') {
        $t = normalized_text($text);

        if ($command === 'human' || str_contains($t, 'human') || str_contains($t, 'help') || str_contains($t, 'support')) {
            $reply = "🔐 *Your Account Details*\n\n"
                . "What would you like to do?\n\n"
                . "Reply with:\n"
                . "*1* - 🔗 Login to Dashboard\n"
                . "*2* - 📞 Contact Support Team";
            $session['state'] = 'EXISTING_ACCOUNT_OPTIONS';
            save_session($phone, $session);
            return ['status' => 'accepted', 'reply' => $reply, 'role' => 'unknown', 'forward' => false];
        }

        if ($t === 'yes' || str_contains($t, 'confirm') || str_contains($t, 'correct')) {
            $reply = "✅ Great! Please login here:\n" . login_url();
            return ['status' => 'accepted', 'reply' => $reply, 'role' => 'unknown', 'forward' => false];
        }

        if ($t === 'no' || str_contains($t, 'not') || str_contains($t, 'different')) {
            $reply = "We understand!\n\nIf you need to create a new account, please contact support.\n\n📞 Email: support@nxtutors.com";
            clear_session($phone);
            return ['status' => 'accepted', 'reply' => $reply, 'role' => 'unknown', 'forward' => false];
        }

        return register_invalid($phone, $session, "Please reply YES, HUMAN, or NO");
    }

    // ---------------------------------------------------------------------- //
    // State: existing account with options — handle choice.                  //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'EXISTING_ACCOUNT_OPTIONS') {
        $t = normalized_text($text);

        if ($t === '1' || str_contains($t, 'login') || str_contains($t, 'dashboard')) {
            $reply = "✅ *Dashboard Access*\n\n"
                . "🔗 Login here:\n" . login_url() . "\n\n"
                . "You'll be automatically logged in.\n\n"
                . "Need help? Reply SUPPORT";
            return ['status' => 'accepted', 'reply' => $reply, 'role' => 'unknown', 'forward' => false];
        }

        if ($t === '2' || str_contains($t, 'support')) {
            $reply = "📞 *Support Team Available*\n\n"
                . "Our support team will help you shortly.\n\n"
                . "📧 Email: support@nxtutors.com\n\n"
                . "⏱️ Response time: Usually within 2-4 hours\n"
                . "🕐 Hours: Monday-Friday, 9AM-6PM";
            clear_session($phone);
            return ['status' => 'forwarded', 'reply' => $reply, 'role' => 'unknown', 'forward' => true];
        }

        return register_invalid($phone, $session, "Please reply 1 (Login) or 2 (Support)");
    }

    // ---------------------------------------------------------------------- //
    // Pending restart confirmation.                                          //
    // ---------------------------------------------------------------------- //
    if (($session['pending_restart'] ?? false) === true) {
        if ($command === 'confirm') {
            save_session($phone, [
                'role' => null,
                'state' => 'ROLE',
                'field' => 0,
                'data' => ['wa_phone' => $phone],
                'invalid' => 0,
                'pending_restart' => false,
                'return_to_review' => false,
            ]);

            return ['status' => 'accepted', 'reply' => "🔄 Okay, let's restart.\n\n" . role_selection_message(), 'role' => 'unknown', 'forward' => false];
        }

        $session['pending_restart'] = false;
        save_session($phone, $session);

        return ['status' => 'accepted', 'reply' => 'Restart cancelled. You can continue from where you left off.', 'role' => (string) ($session['role'] ?? 'unknown'), 'forward' => false];
    }

    // ---------------------------------------------------------------------- //
    // Global commands available at any point in an active flow.              //
    // ---------------------------------------------------------------------- //
    $role = (string) ($session['role'] ?? 'unknown');

    if ($command === 'cancel') {
        clear_session($phone);

        return ['status' => 'accepted', 'reply' => "🛑 Your signup is cancelled and this session is cleared.\n\nReply *signup* anytime to start fresh. 👋", 'role' => $role, 'forward' => false];
    }

    if ($command === 'human') {
        clear_session($phone);

        // Hand the user back to lead-intake / a human.
        return ['status' => 'forwarded', 'reply' => null, 'role' => $role, 'forward' => true];
    }

    if ($command === 'restart') {
        $session['pending_restart'] = true;
        save_session($phone, $session);

        return ['status' => 'accepted', 'reply' => 'Restart will clear this draft. Reply CONFIRM to restart, or any other message to continue.', 'role' => $role, 'forward' => false];
    }

    if ($command === 'review' && in_array($session['state'], ['FIELDS', 'REVIEW'], true) && $role !== 'unknown') {
        return ['status' => 'accepted', 'reply' => review_summary($role, (array) $session['data']), 'role' => $role, 'forward' => false];
    }

    // ---------------------------------------------------------------------- //
    // State: waiting for role selection.                                     //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'ROLE') {
        $picked = detect_role($text);
        if ($picked !== 'unknown') {
            return start_role($phone, $picked);
        }

        return register_invalid($phone, $session, invalid_role_message());
    }

    // ---------------------------------------------------------------------- //
    // State: tutor chose between manual and Pro mode.                        //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'TUTOR_MODE') {
        $t = normalized_text($text);
        $wantsManual = preg_match('/^(?:option\s*)?1[.):]?$/', $t) === 1 || str_contains($t, 'manual');
        $wantsPro = preg_match('/^(?:option\s*)?2[.):]?$/', $t) === 1 || str_contains($t, 'pro');

        if ($wantsManual) {
            return start_manual_flow($phone, 'tutor');
        }

        if ($wantsPro) {
            if (! pro_mode_enabled()) {
                return start_manual_flow($phone, 'tutor');
            }
            $token = pro_create_token($phone);
            $url = pro_base_url() . '/pro/' . $token;
            $session['state'] = 'PRO_PENDING';
            $session['data']['pro_token'] = $token;
            $session['invalid'] = 0;
            save_session($phone, $session);

            return ['status' => 'accepted', 'reply' => pro_link_message($url), 'role' => 'tutor', 'forward' => false];
        }

        return register_invalid($phone, $session, tutor_mode_menu());
    }

    // ---------------------------------------------------------------------- //
    // State: tutor is finishing Pro mode on the web link.                    //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'PRO_PENDING') {
        $t = normalized_text($text);
        if (preg_match('/^(?:option\s*)?1[.):]?$/', $t) === 1 || str_contains($t, 'manual')) {
            return start_manual_flow($phone, 'tutor');
        }

        $url = pro_base_url() . '/pro/' . (string) ($session['data']['pro_token'] ?? '');

        return ['status' => 'accepted', 'reply' => "Please continue on your secure link to finish Pro signup:\n" . $url . "\n\nOr reply 1 to fill your profile manually instead.", 'role' => 'tutor', 'forward' => false];
    }

    // ---------------------------------------------------------------------- //
    // State: collecting fields.                                              //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'FIELDS') {
        return handle_field_input($phone, $session, $text, $command, $argument);
    }

    // ---------------------------------------------------------------------- //
    // State: review confirmation.                                            //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'REVIEW') {
        if ($command === 'confirm') {
            $session['state'] = 'TERMS';
            $session['invalid'] = 0;
            save_session($phone, $session);

            return ['status' => 'accepted', 'reply' => terms_prompt($role), 'role' => $role, 'forward' => false];
        }

        if ($command === 'edit') {
            return edit_field($phone, $session, $argument);
        }

        return register_invalid($phone, $session, 'Please reply CONFIRM to continue, or EDIT <field> to change a detail (e.g. EDIT email).');
    }

    // ---------------------------------------------------------------------- //
    // State: waiting for terms acceptance.                                   //
    // ---------------------------------------------------------------------- //
    if (($session['state'] ?? '') === 'TERMS') {
        if ($command === 'agree') {
            $data = (array) $session['data'];

            // Always keep a file record of the completed signup (audit/fallback).
            capture_lead($role, $phone, $data);
            $session['state'] = 'COMPLETED';
            save_session($phone, $session);

            // When enabled and a DB is configured, create the real website
            // `register` row and return real login credentials. Any failure
            // degrades gracefully to the captured-lead confirmation.
            if (real_profile_enabled()) {
                $result = create_register_profile($role, $phone, $data);

                if ($result['status'] === 'created') {
                    return ['status' => 'accepted', 'reply' => credentials_message($role, (string) $result['email'], (string) $result['temp_password']), 'role' => $role, 'forward' => false];
                }

                if ($result['status'] === 'duplicate') {
                    return ['status' => 'accepted', 'reply' => duplicate_account_message(), 'role' => $role, 'forward' => false];
                }
                // 'error' → fall through to the captured-lead confirmation below.
            }

            return ['status' => 'accepted', 'reply' => completion_message($role, $data), 'role' => $role, 'forward' => false];
        }

        return register_invalid($phone, $session, 'Please read the terms link and reply exactly I AGREE to continue.');
    }

    // Fallback: unknown state — restart cleanly.
    clear_session($phone);

    return ['status' => 'accepted', 'reply' => role_selection_message(), 'role' => 'unknown', 'forward' => false];
}

/**
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function start_role(string $phone, string $role): array
{
    // Tutors get a choice between the manual flow and paid "Pro mode" (AI
    // profile) when Pro mode is enabled. Students always go straight in.
    if ($role === 'tutor' && pro_mode_enabled()) {
        save_session($phone, [
            'role' => 'tutor',
            'state' => 'TUTOR_MODE',
            'field' => 0,
            'data' => ['wa_phone' => $phone, 'role' => 'tutor'],
            'invalid' => 0,
            'pending_restart' => false,
            'return_to_review' => false,
        ]);

        return ['status' => 'accepted', 'reply' => tutor_mode_menu(), 'role' => 'tutor', 'forward' => false];
    }

    return start_manual_flow($phone, $role);
}

/**
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function start_manual_flow(string $phone, string $role): array
{
    $fields = role_fields($role);
    save_session($phone, [
        'role' => $role,
        'state' => 'FIELDS',
        'field' => 0,
        'data' => ['wa_phone' => $phone, 'role' => $role],
        'invalid' => 0,
        'pending_restart' => false,
        'return_to_review' => false,
    ]);

    return ['status' => 'accepted', 'reply' => $fields[0]['q'], 'role' => $role, 'forward' => false];
}

/**
 * @param array<string, mixed> $session
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function handle_field_input(string $phone, array $session, string $text, string $command, string $argument): array
{
    $role = (string) $session['role'];
    $fields = role_fields($role);
    $index = (int) $session['field'];
    $field = $fields[$index] ?? null;

    if ($field === null) {
        return enter_review($phone, $session);
    }

    if ($command === 'signup') {
        // Treat as "resume" — re-ask the current question.
        return ['status' => 'accepted', 'reply' => $field['q'], 'role' => $role, 'forward' => false];
    }

    if ($command === 'back') {
        if ($index <= 0) {
            return ['status' => 'accepted', 'reply' => 'There is no previous question to go back to. ' . $field['q'], 'role' => $role, 'forward' => false];
        }
        $session['field'] = $index - 1;
        $session['invalid'] = 0;
        save_session($phone, $session);

        return ['status' => 'accepted', 'reply' => $fields[$index - 1]['q'], 'role' => $role, 'forward' => false];
    }

    if ($command === 'edit') {
        return edit_field($phone, $session, $argument);
    }

    if ($command === 'skip') {
        if (! $field['optional']) {
            return register_invalid($phone, $session, 'This field is required, so SKIP is not available here.');
        }
        $session['data'][$field['key']] = '';

        return advance_after_field($phone, $session);
    }

    [$ok, $error] = validate_field($field['type'], $text);
    if (! $ok) {
        return register_invalid($phone, $session, $error);
    }

    // Duplicate guard: stop here if this email already has an account.
    if ($field['key'] === 'email' && account_exists('email', trim($text))) {
        save_session($phone, $session); // refresh activity so it does not idle-timeout

        return ['status' => 'accepted', 'reply' => duplicate_email_message(trim($text)), 'role' => $role, 'forward' => false];
    }

    $session['data'][$field['key']] = trim($text);
    $session['invalid'] = 0;

    return advance_after_field($phone, $session);
}

/**
 * Move to the next field, or to review when the flow is complete or when an
 * edit asked to return to review.
 *
 * @param array<string, mixed> $session
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function advance_after_field(string $phone, array $session): array
{
    $role = (string) $session['role'];
    $fields = role_fields($role);

    if (($session['return_to_review'] ?? false) === true) {
        $session['return_to_review'] = false;

        return enter_review($phone, $session);
    }

    $next = (int) $session['field'] + 1;
    if (! isset($fields[$next])) {
        $session['field'] = $next;

        return enter_review($phone, $session);
    }

    $session['field'] = $next;
    save_session($phone, $session);

    return ['status' => 'accepted', 'reply' => $fields[$next]['q'], 'role' => $role, 'forward' => false];
}

/**
 * @param array<string, mixed> $session
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function enter_review(string $phone, array $session): array
{
    $role = (string) $session['role'];
    $session['state'] = 'REVIEW';
    $session['invalid'] = 0;
    save_session($phone, $session);

    return ['status' => 'accepted', 'reply' => review_summary($role, (array) $session['data']), 'role' => $role, 'forward' => false];
}

/**
 * Jump to a named field to edit it, returning to review after it is saved.
 *
 * @param array<string, mixed> $session
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function edit_field(string $phone, array $session, string $fieldName): array
{
    $role = (string) $session['role'];
    $fields = role_fields($role);
    $target = normalized_text($fieldName);
    $target = str_replace(' ', '_', $target);

    $foundIndex = null;
    foreach ($fields as $i => $field) {
        if ($field['key'] === $target) {
            $foundIndex = $i;
            break;
        }
    }

    if ($foundIndex === null) {
        return ['status' => 'accepted', 'reply' => 'I could not find that field. Reply REVIEW to see the summary, then EDIT <field> (for example EDIT email).', 'role' => $role, 'forward' => false];
    }

    $session['state'] = 'FIELDS';
    $session['field'] = $foundIndex;
    $session['return_to_review'] = true;
    $session['invalid'] = 0;
    save_session($phone, $session);

    return ['status' => 'accepted', 'reply' => $fields[$foundIndex]['q'], 'role' => $role, 'forward' => false];
}

/**
 * Record an invalid reply. After too many, hand the user to lead-intake/human.
 *
 * @param array<string, mixed> $session
 * @return array{status:string,reply:?string,role:string,forward:bool}
 */
function register_invalid(string $phone, array $session, string $message): array
{
    $role = (string) ($session['role'] ?? 'unknown');
    $session['invalid'] = (int) ($session['invalid'] ?? 0) + 1;

    if ($session['invalid'] >= max_invalid_attempts()) {
        clear_session($phone);

        // Too many invalid replies — let lead-intake / a human take over.
        return ['status' => 'forwarded', 'reply' => null, 'role' => $role, 'forward' => true];
    }

    save_session($phone, $session);

    return ['status' => 'accepted', 'reply' => $message, 'role' => $role, 'forward' => false];
}

/**
 * Persistent, race-safe idempotency claim for a wa_message_id.
 *
 * Returns true when the id is claimed for the first time (process it), or false
 * when it was already processed (duplicate). A filesystem store gives per-task
 * idempotency for retry storms; for cross-task dedup point
 * ONBOARDING_IDEMPOTENCY_DIR at a shared volume. An empty id cannot be
 * deduplicated, so it is allowed.
 */
function handoff_claim(string $messageId, int $ttlSeconds = 86400): bool
{
    if ($messageId === '') {
        return true;
    }

    $dir = env_value('ONBOARDING_IDEMPOTENCY_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nxtutors_onboarding_idemp');
    if (! is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $file = $dir . DIRECTORY_SEPARATOR . sha1($messageId) . '.json';

    if (is_file($file) && (time() - (int) @filemtime($file)) > $ttlSeconds) {
        @unlink($file);
    }

    $handle = @fopen($file, 'x');
    if ($handle === false) {
        return false; // already claimed → duplicate
    }

    fwrite($handle, json_encode(['wa_message_id' => $messageId, 'claimed_at' => time()], JSON_UNESCAPED_SLASHES));
    fclose($handle);

    return true;
}

/** @param array<string, mixed> $context */
function handoff_log(array $context): void
{
    error_log(json_encode(array_merge(['event' => 'lead_intake_handoff'], $context), JSON_UNESCAPED_SLASHES));
}

function verify_meta_signature(string $rawBody, string $signatureHeader): bool
{
    $secret = env_value('META_WHATSAPP_APP_SECRET', 'META_APP_SECRET');

    if ($secret === '') {
        // No app secret configured: only tolerate this outside production so a
        // direct Meta webhook is never accepted unverified in prod.
        return ! is_production();
    }

    if ($signatureHeader === '' || ! str_starts_with($signatureHeader, 'sha256=')) {
        return false;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

    return hash_equals($expected, $signatureHeader);
}

/** @return array<string, mixed> */
function check_db(): array
{
    $host = env_value('DB_HOST');
    $connection = env_value('DB_CONNECTION');
    if ($host === '' && $connection === '') {
        return ['configured' => false, 'ok' => null];
    }

    if (! class_exists('PDO')) {
        return ['configured' => true, 'ok' => false, 'error' => 'pdo_unavailable'];
    }

    $driver = $connection === 'pgsql' ? 'pgsql' : 'mysql';
    $port = env_value('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
    $database = env_value('DB_DATABASE', 'DB_NAME');

    try {
        $pdo = new PDO(
            sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $database),
            env_value('DB_USERNAME', 'DB_USER'),
            env_value('DB_PASSWORD'),
            [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $pdo->query('SELECT 1');

        return ['configured' => true, 'ok' => true];
    } catch (\Throwable $e) {
        return ['configured' => true, 'ok' => false];
    }
}

/** @return array<string, mixed> */
function check_whatsapp(): array
{
    $appSecret = env_value('META_WHATSAPP_APP_SECRET', 'META_APP_SECRET') !== '';
    $accessToken = env_value('META_WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN') !== '';
    $phoneNumberId = env_value('META_WHATSAPP_PHONE_NUMBER_ID', 'META_PHONE_NUMBER_ID') !== '';
    $verifyToken = env_value('META_WHATSAPP_VERIFY_TOKEN', 'WHATSAPP_VERIFY_TOKEN') !== '';

    return [
        'app_secret_configured' => $appSecret,
        'access_token_configured' => $accessToken,
        'phone_number_id_configured' => $phoneNumberId,
        'verify_token_configured' => $verifyToken,
        // Direct sending is optional for this agent; lead-intake sends replies.
        'direct_send_ready' => $accessToken && $phoneNumberId,
        'ok' => $appSecret || ($accessToken && $phoneNumberId),
    ];
}

/** @return array<string, mixed> */
function check_internal_handoff(): array
{
    $secretConfigured = env_value('ONBOARDING_AGENT_INTERNAL_SECRET') !== '';
    $routeEnabled = strtolower(env_value('ONBOARDING_HANDOFF_ENABLED') ?: 'true') !== 'false';

    return [
        'onboarding_agent_internal_secret_configured' => $secretConfigured,
        'handoff_route_enabled' => $routeEnabled,
        'ok' => $secretConfigured && $routeEnabled,
    ];
}

/* -------------------------------------------------------------------------- */
/* Tutor "Pro mode" web flow (payment + CV upload + AI profile)               */
/* -------------------------------------------------------------------------- */

require_once __DIR__ . '/pro.php';

if ($path === '/pro' || str_starts_with($path, '/pro/')) {
    pro_handle_request($path, $method);
}

/* -------------------------------------------------------------------------- */
/* Health endpoints                                                           */
/* -------------------------------------------------------------------------- */

if ($path === '/' || $path === '/health/live' || $path === '/health/Live') {
    json_response(['status' => 'ok', 'service' => 'nxtutors-whatsapp-onboarding']);
}

if ($path === '/health') {
    $handoff = check_internal_handoff();
    json_response([
        'status' => 'ok',
        'service' => 'nxtutors-whatsapp-onboarding',
        'app_env' => app_env(),
        'checks' => [
            'internal_handoff' => $handoff['ok'],
            'whatsapp' => check_whatsapp()['ok'],
        ],
    ]);
}

if ($path === '/health/db') {
    $db = check_db();
    $status = ($db['configured'] === true && $db['ok'] === false) ? 503 : 200;
    json_response(['status' => $status === 200 ? 'ok' : 'error', 'db' => $db], $status);
}

if ($path === '/health/whatsapp') {
    json_response(['status' => 'ok', 'whatsapp' => check_whatsapp()]);
}

if ($path === '/health/internal-handoff') {
    $handoff = check_internal_handoff();
    // Surface a clear signal in production when the secret is missing.
    $status = (is_production() && ! $handoff['onboarding_agent_internal_secret_configured']) ? 503 : 200;
    json_response([
        'status' => $status === 200 ? 'ok' : 'misconfigured',
        'internal_handoff' => $handoff,
    ], $status);
}

if ($path === '/health/ready' || $path === '/api/nx-whatsapp-onboarding/health') {
    json_response(['status' => 'ready', 'service' => 'nxtutors-whatsapp-onboarding', 'mode' => 'package']);
}

/* -------------------------------------------------------------------------- */
/* Meta webhook verification (GET)                                            */
/* -------------------------------------------------------------------------- */

if ($path === '/whatsapp/onboarding/webhook' && $method === 'GET') {
    $verifyToken = env_value('META_WHATSAPP_VERIFY_TOKEN', 'WHATSAPP_VERIFY_TOKEN');
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, (string) $token)) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo (string) $challenge;
        exit;
    }

    json_response(['error' => 'webhook verification failed'], 403);
}

/* -------------------------------------------------------------------------- */
/* Webhook POST: internal handoff OR genuine Meta webhook                      */
/* -------------------------------------------------------------------------- */

if (($path === '/whatsapp/onboarding/webhook' || $path === '/index.php') && $method === 'POST') {
    $maxBytes = (int) (env_value('WHATSAPP_ONBOARDING_MAX_WEBHOOK_BYTES') ?: 262144);
    $rawBody = file_get_contents('php://input');
    $rawBody = $rawBody === false ? '' : $rawBody;
    if (strlen($rawBody) > $maxBytes) {
        json_response(['status' => 'error', 'reason' => 'payload_too_large'], 413);
    }

    $payload = json_decode($rawBody !== '' ? $rawBody : '{}', true);
    $payload = is_array($payload) ? $payload : [];

    $providedSecret = request_header('X-NXTUTORS-INTERNAL-SECRET');
    $source = is_scalar($payload['source'] ?? null) ? (string) $payload['source'] : '';
    $isInternalHandoff = $providedSecret !== '' || $source === 'lead_intake_agent';

    if ($isInternalHandoff) {
        $correlationId = correlation_id();
        $configuredSecret = env_value('ONBOARDING_AGENT_INTERNAL_SECRET');

        // (Req 3) Server-side secret missing. Never silently accept a handoff.
        if ($configuredSecret === '') {
            handoff_log([
                'correlation_id' => $correlationId,
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => false,
                'reason' => 'server_secret_not_configured',
            ]);

            if (is_production()) {
                json_response([
                    'status' => 'error',
                    'reason' => 'server_internal_secret_not_configured',
                ], 503);
            }

            json_response(['status' => 'unauthorized', 'reason' => 'invalid_internal_secret'], 401);
        }

        // (Req 4) Wrong or missing client secret.
        if ($providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            handoff_log([
                'correlation_id' => $correlationId,
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => false,
            ]);

            json_response(['status' => 'unauthorized', 'reason' => 'invalid_internal_secret'], 401);
        }

        $messageText = normalize_text($payload);
        $waPhone = normalize_phone($payload);
        $waMessageId = normalize_message_id($payload);

        // (Req 10) Idempotency. A duplicate wa_message_id must not advance the
        // onboarding flow or cause lead-intake to send a duplicate reply.
        if (! handoff_claim($waMessageId)) {
            handoff_log([
                'correlation_id' => $correlationId,
                'wa_message_id' => $waMessageId,
                'wa_phone' => mask_phone($waPhone),
                'source' => $source !== '' ? $source : 'lead_intake_agent',
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => true,
                'reply_text_present' => false,
                'duplicate' => true,
            ]);

            json_response([
                'status' => 'duplicate',
                'mode' => 'lead_intake_handoff',
                'wa_message_id' => $waMessageId,
                'reply_text' => null,
            ]);
        }

        // Advance the stateful conversation for this phone.
        $result = $waPhone !== ''
            ? process_message($waPhone, $messageText)
            : ['status' => 'forwarded', 'reply' => null, 'role' => detect_role($messageText), 'forward' => true];

        $replyText = $result['reply'];
        $detectedRole = $result['role'];

        // (Req 11) One structured log line per handoff with all required fields.
        handoff_log([
            'correlation_id' => $correlationId,
            'wa_message_id' => $waMessageId,
            'wa_phone' => mask_phone($waPhone),
            'source' => $source !== '' ? $source : 'lead_intake_agent',
            'mode' => 'lead_intake_handoff',
            'internal_secret_valid' => true,
            'detected_role' => $detectedRole,
            'handled' => ! $result['forward'],
            'reply_text_present' => $replyText !== null && $replyText !== '',
            'duplicate' => false,
        ]);

        // Out-of-context message: onboarding does not own it. Tell lead-intake
        // to handle it (forward_to_lead_intake) and send no onboarding reply.
        if ($result['forward'] === true) {
            json_response([
                'status' => 'forwarded',
                'mode' => 'lead_intake_handoff',
                'wa_message_id' => $waMessageId,
                'wa_phone' => $waPhone,
                'detected_role' => $detectedRole,
                'handled' => false,
                'forward_to_lead_intake' => true,
                'reply_text' => null,
            ]);
        }

        // (Req 8) Do NOT send WhatsApp here. Return reply_text to lead-intake,
        // which sends exactly one reply to the user.
        json_response([
            'status' => 'accepted',
            'mode' => 'lead_intake_handoff',
            'wa_message_id' => $waMessageId,
            'wa_phone' => $waPhone,
            'detected_role' => $detectedRole,
            'handled' => true,
            'reply_text' => $replyText,
        ]);
    }

    // (Req 9) Not an internal handoff → treat as a genuine Meta webhook and
    // require a valid X-Hub-Signature-256 before doing anything with it.
    $signature = request_header('X-Hub-Signature-256');
    if (! verify_meta_signature($rawBody, $signature)) {
        json_response(['status' => 'forbidden', 'reason' => 'invalid_meta_signature'], 403);
    }

    json_response(['status' => 'received', 'mode' => 'meta_webhook']);
}

json_response(['status' => 'not_found'], 404);
