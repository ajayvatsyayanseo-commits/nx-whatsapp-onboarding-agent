<?php

declare(strict_types=1);

/**
 * NXtutors Tutor "Pro Mode" — paid, AI-written SEO profile.
 *
 * This file is required by public/index.php and serves the hosted web flow that
 * WhatsApp links to (payment + CV upload + AI generation + account creation).
 * It reuses helpers already defined in index.php: env_value(), load_session(),
 * save_session(), create_register_profile(), mask_email(), role_checklist(),
 * login_url(), dashboard_url(), capture_lead().
 *
 * WhatsApp chat cannot host a payment UI or a file-upload form, so this entire
 * sub-flow runs on a web page behind a one-time, unguessable token. Payment is
 * verified through Razorpay (order + signature, with a webhook backup) before
 * the upload/AI/account steps unlock.
 *
 * No Composer libraries are used (the runtime has none) — only curl, hash_hmac,
 * $_FILES and PDO (via index.php's create_register_profile()).
 */

/* -------------------------------------------------------------------------- */
/* Config                                                                     */
/* -------------------------------------------------------------------------- */

function pro_mode_enabled(): bool
{
    return strtolower(env_value('PRO_MODE_ENABLED') ?: 'false') === 'true';
}

function pro_price_inr(): int
{
    return max(1, (int) (env_value('PRO_MODE_PRICE_INR') ?: 10));
}

function pro_base_url(): string
{
    $base = env_value('PRO_MODE_BASE_URL');
    if ($base !== '') {
        return rtrim($base, '/');
    }

    $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host;
}

function pro_fake_payment(): bool
{
    return ! is_production() && strtolower(env_value('PRO_MODE_FAKE_PAYMENT') ?: 'false') === 'true';
}

function pro_fake_ai(): bool
{
    return strtolower(env_value('PRO_MODE_FAKE_AI') ?: 'false') === 'true';
}

function pro_cv_max_kb(): int
{
    return max(64, (int) (env_value('PRO_CV_MAX_KB') ?: 5120));
}

/* -------------------------------------------------------------------------- */
/* Token + CV stores (file-backed, mirror of the session store)               */
/* -------------------------------------------------------------------------- */

function pro_store_dir(): string
{
    $dir = env_value('PRO_DIR');
    if ($dir === '') {
        $base = env_value('ONBOARDING_IDEMPOTENCY_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nxtutors_onboarding');
        $dir = $base . DIRECTORY_SEPARATOR . 'pro';
    }
    if (! is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir;
}

function pro_cv_dir(): string
{
    $dir = env_value('PRO_CV_DIR');
    if ($dir === '') {
        $base = env_value('ONBOARDING_IDEMPOTENCY_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nxtutors_onboarding');
        $dir = $base . DIRECTORY_SEPARATOR . 'cv';
    }
    if (! is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir;
}

function pro_ttl_seconds(): int
{
    return 24 * 60 * 60;
}

function pro_path(string $token): string
{
    return pro_store_dir() . DIRECTORY_SEPARATOR . 'pro_' . $token . '.json';
}

function pro_valid_token(string $token): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $token) === 1;
}

function pro_create_token(string $phone): string
{
    $token = bin2hex(random_bytes(16));
    save_pro($token, [
        'token' => $token,
        'wa_phone' => $phone,
        'role' => 'tutor',
        'status' => 'created',
        'order_id' => '',
        'payment_id' => '',
        'email' => '',
        'cv_path' => '',
        'created_at' => time(),
    ]);

    return $token;
}

/** @return array<string, mixed>|null */
function load_pro(string $token): ?array
{
    if (! pro_valid_token($token)) {
        return null;
    }

    $file = pro_path($token);
    if (! is_file($file)) {
        return null;
    }

    if ((time() - (int) @filemtime($file)) > pro_ttl_seconds()) {
        @unlink($file);

        return null;
    }

    $decoded = json_decode((string) @file_get_contents($file), true);

    return is_array($decoded) ? $decoded : null;
}

/** @param array<string, mixed> $record */
function save_pro(string $token, array $record): void
{
    @file_put_contents(pro_path($token), json_encode($record, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/** Maintain an order_id -> token index so the webhook can resolve a payment. */
function pro_index_order(string $orderId, string $token): void
{
    if ($orderId === '') {
        return;
    }
    @file_put_contents(pro_store_dir() . DIRECTORY_SEPARATOR . 'order_' . sha1($orderId) . '.txt', $token, LOCK_EX);
}

function pro_token_for_order(string $orderId): string
{
    $file = pro_store_dir() . DIRECTORY_SEPARATOR . 'order_' . sha1($orderId) . '.txt';

    return is_file($file) ? trim((string) @file_get_contents($file)) : '';
}

/* -------------------------------------------------------------------------- */
/* HTTP helper (curl)                                                         */
/* -------------------------------------------------------------------------- */

/**
 * @param array<string, mixed> $options curl-friendly options: headers, json, body, multipart, timeout, auth
 * @return array{0:int,1:string} [status, body]
 */
function pro_http(string $method, string $url, array $options = []): array
{
    if (! function_exists('curl_init')) {
        return [0, ''];
    }

    $ch = curl_init($url);
    $headers = $options['headers'] ?? [];

    if (isset($options['json'])) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json'], JSON_UNESCAPED_SLASHES));
    } elseif (isset($options['multipart'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $options['multipart']);
    } elseif (isset($options['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
    }

    if (isset($options['auth'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $options['auth']);
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? 30),
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, $body === false ? '' : (string) $body];
}

/* -------------------------------------------------------------------------- */
/* Razorpay                                                                   */
/* -------------------------------------------------------------------------- */

/** @return array{0:string,1:string} [key_id, key_secret] */
function razorpay_keys(): array
{
    return [env_value('RAZORPAY_KEY_ID'), env_value('RAZORPAY_KEY_SECRET')];
}

/** Create a Razorpay order; returns the order id or '' on failure. */
function razorpay_create_order(int $amountPaise, string $receipt): string
{
    [$keyId, $keySecret] = razorpay_keys();
    if ($keyId === '' || $keySecret === '') {
        return '';
    }

    [$status, $body] = pro_http('POST', 'https://api.razorpay.com/v1/orders', [
        'auth' => $keyId . ':' . $keySecret,
        'json' => [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => $receipt,
            'payment_capture' => 1,
        ],
        'timeout' => 20,
    ]);

    if ($status < 200 || $status >= 300) {
        error_log(json_encode(['event' => 'razorpay_order_failed', 'status' => $status], JSON_UNESCAPED_SLASHES));

        return '';
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? (string) ($decoded['id'] ?? '') : '';
}

function razorpay_verify_payment_signature(string $orderId, string $paymentId, string $signature): bool
{
    [, $keySecret] = razorpay_keys();
    if ($keySecret === '' || $orderId === '' || $paymentId === '' || $signature === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);

    return hash_equals($expected, $signature);
}

function razorpay_verify_webhook(string $rawBody, string $signatureHeader): bool
{
    $secret = env_value('RAZORPAY_WEBHOOK_SECRET');
    if ($secret === '' || $signatureHeader === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $rawBody, $secret);

    return hash_equals($expected, $signatureHeader);
}

/* -------------------------------------------------------------------------- */
/* CV upload                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * @param array<string, mixed> $file a single $_FILES entry
 * @return array{ok:bool,path?:string,mime?:string,error?:string}
 */
function pro_store_cv(array $file): array
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Please attach your CV file.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > pro_cv_max_kb() * 1024) {
        return ['ok' => false, 'error' => 'CV must be a non-empty file under ' . pro_cv_max_kb() . ' KB.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || ! is_readable($tmp)) {
        return ['ok' => false, 'error' => 'Upload failed, please try again.'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($finfo, $tmp);
        finfo_close($finfo);
    }
    if ($mime === '') {
        $mime = (string) ($file['type'] ?? '');
    }

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
    ];
    if (! isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'CV must be a PDF, JPG or PNG file.'];
    }

    $dest = pro_cv_dir() . DIRECTORY_SEPARATOR . 'cv_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];

    // move_uploaded_file in real requests; rename as a fallback for tests.
    if (! @move_uploaded_file($tmp, $dest) && ! @rename($tmp, $dest) && ! @copy($tmp, $dest)) {
        return ['ok' => false, 'error' => 'Could not store the uploaded CV.'];
    }

    return ['ok' => true, 'path' => $dest, 'mime' => $mime];
}

/* -------------------------------------------------------------------------- */
/* OpenAI profile generation                                                  */
/* -------------------------------------------------------------------------- */

function pro_openai_prompt(string $name, string $subject, string $email): string
{
    return "You are an expert SEO copywriter for an Indian tutoring marketplace (NXtutors). "
        . "Using the attached CV/resume and the details below, produce ONE JSON object only — no prose, no markdown.\n\n"
        . "Tutor name: {$name}\nPrimary subject: {$subject}\nEmail: {$email}\n\n"
        . "Return exactly this JSON shape:\n"
        . '{"education":"<concise highest qualification>","experience":"<years, e.g. 5 years>",'
        . '"profile_title":"<short SEO title under 90 chars>","profile_desc":"<1-2 sentence summary>",'
        . '"pro_desc":"<a detailed, human, SEO-rich professional tutor profile of AT LEAST 2000 words, '
        . 'written in first person, covering teaching philosophy, subjects, experience, achievements, '
        . 'methodology, results, and a call to action>","email":"<email found in CV or empty>",'
        . '"phone":"<phone found in CV or empty>"}'."\n\n"
        . 'Only output the JSON object.';
}

/**
 * @return array<string, string>|null structured fields, or null on failure
 */
function pro_generate_profile(string $cvPath, string $cvMime, string $name, string $subject, string $email): ?array
{
    if (pro_fake_ai()) {
        $body = trim(str_repeat(
            "I am {$name}, a passionate {$subject} educator dedicated to helping every student "
            . "build deep understanding and exam confidence. Over years of teaching I have refined a "
            . "methodology that blends fundamentals, practice, and personalised feedback. ",
            40
        ));

        return [
            'education' => 'M.Sc / B.Ed (from CV)',
            'experience' => '5 years',
            'profile_title' => 'Expert ' . $subject . ' Tutor — Concept-First, Results-Driven',
            'profile_desc' => 'Experienced ' . $subject . ' tutor focused on concept clarity and measurable results.',
            'pro_desc' => $body,
            'email' => $email,
            'phone' => '',
        ];
    }

    $apiKey = env_value('OPENAI_API_KEY');
    if ($apiKey === '') {
        return null;
    }
    $model = env_value('OPENAI_MODEL') ?: 'gpt-4o';

    // Build the user content. PDFs go through the Files API; images are inlined.
    $userContent = [['type' => 'input_text', 'text' => pro_openai_prompt($name, $subject, $email)]];

    if ($cvMime === 'application/pdf') {
        $fileId = pro_openai_upload_file($cvPath, $apiKey);
        if ($fileId === '') {
            return null;
        }
        $userContent[] = ['type' => 'input_file', 'file_id' => $fileId];
    } else {
        $data = @file_get_contents($cvPath);
        if ($data === false) {
            return null;
        }
        $userContent[] = ['type' => 'input_image', 'image_url' => 'data:' . $cvMime . ';base64,' . base64_encode($data)];
    }

    $payload = [
        'model' => $model,
        'input' => [
            ['role' => 'system', 'content' => 'You write long, accurate, SEO-rich tutor profiles and return strict JSON only.'],
            ['role' => 'user', 'content' => $userContent],
        ],
        'text' => ['format' => ['type' => 'json_object']],
        'max_output_tokens' => 6000,
    ];

    for ($attempt = 0; $attempt < 2; $attempt++) {
        [$status, $body] = pro_http('POST', 'https://api.openai.com/v1/responses', [
            'headers' => ['Authorization: Bearer ' . $apiKey],
            'json' => $payload,
            'timeout' => 90,
        ]);

        if ($status >= 200 && $status < 300) {
            $parsed = pro_openai_extract_json($body);
            if ($parsed !== null && trim((string) ($parsed['pro_desc'] ?? '')) !== '') {
                return $parsed;
            }
        } else {
            error_log(json_encode(['event' => 'openai_failed', 'status' => $status], JSON_UNESCAPED_SLASHES));
        }
    }

    return null;
}

function pro_openai_upload_file(string $path, string $apiKey): string
{
    if (! function_exists('curl_init') || ! class_exists('CURLFile')) {
        return '';
    }

    $ch = curl_init('https://api.openai.com/v1/files');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => ['purpose' => 'user_data', 'file' => new CURLFile($path, 'application/pdf', 'cv.pdf')],
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300 || $body === false) {
        return '';
    }
    $decoded = json_decode((string) $body, true);

    return is_array($decoded) ? (string) ($decoded['id'] ?? '') : '';
}

/** Extract the JSON object returned by the Responses API. */
function pro_openai_extract_json(string $body): ?array
{
    $decoded = json_decode($body, true);
    if (! is_array($decoded)) {
        return null;
    }

    $text = '';
    if (isset($decoded['output_text']) && is_string($decoded['output_text'])) {
        $text = $decoded['output_text'];
    } else {
        foreach ($decoded['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $chunk) {
                if (in_array($chunk['type'] ?? '', ['output_text', 'text'], true) && isset($chunk['text'])) {
                    $text .= (string) $chunk['text'];
                }
            }
        }
    }

    $text = trim($text);
    if ($text === '') {
        return null;
    }

    $fields = json_decode($text, true);
    if (! is_array($fields)) {
        // Tolerate a stray code fence or surrounding text.
        if (preg_match('/\{.*\}/s', $text, $m) === 1) {
            $fields = json_decode($m[0], true);
        }
    }

    return is_array($fields) ? $fields : null;
}

/* -------------------------------------------------------------------------- */
/* HTML rendering                                                             */
/* -------------------------------------------------------------------------- */

function pro_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pro_layout(string $title, string $inner): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . pro_e($title) . '</title><style>'
        . 'body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b141a;color:#e9edef;margin:0;padding:24px;}'
        . '.card{max-width:560px;margin:24px auto;background:#111b21;border:1px solid #233138;border-radius:14px;padding:24px;}'
        . 'h1{font-size:20px;margin:0 0 8px;} h2{font-size:16px;color:#8696a0;font-weight:500;margin:0 0 20px;}'
        . 'label{display:block;margin:14px 0 6px;font-size:14px;color:#aebac1;}'
        . 'input[type=text],input[type=email],input[type=file]{width:100%;box-sizing:border-box;padding:12px;border-radius:8px;border:1px solid #2a3942;background:#202c33;color:#e9edef;font-size:15px;}'
        . 'button,.btn{display:inline-block;background:#00a884;color:#0b141a;border:0;border-radius:8px;padding:13px 20px;font-size:16px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:18px;}'
        . '.muted{color:#8696a0;font-size:13px;} .cred{background:#202c33;border-radius:8px;padding:14px;margin:10px 0;font-size:15px;word-break:break-all;}'
        . '.err{background:#3a2326;border:1px solid #5a2f33;color:#f3c0c0;border-radius:8px;padding:12px;margin:10px 0;}'
        . 'pre{white-space:pre-wrap;}</style></head><body><div class="card">' . $inner . '</div></body></html>';
}

function pro_html(string $title, string $inner, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo pro_layout($title, $inner);
    exit;
}

function pro_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function pro_page_error(string $message, int $status = 400): never
{
    pro_html('NXtutors', '<h1>NXtutors Pro</h1><div class="err">' . pro_e($message) . '</div>'
        . '<p class="muted">If you need help, reply HUMAN on WhatsApp.</p>', $status);
}

/* -------------------------------------------------------------------------- */
/* Pages                                                                      */
/* -------------------------------------------------------------------------- */

function pro_page_landing(string $token, array $record): never
{
    if (($record['status'] ?? '') === 'completed') {
        pro_html('NXtutors Pro', '<h1>All done ✅</h1><h2>Your Pro tutor profile is already created.</h2>'
            . '<a class="btn" href="' . pro_e(login_url()) . '">Go to login</a>');
    }

    if (($record['status'] ?? '') === 'paid') {
        header('Location: ' . pro_base_url() . '/pro/' . $token . '/form');
        exit;
    }

    $price = pro_price_inr();

    if (pro_fake_payment()) {
        pro_html('NXtutors Pro — Payment', '<h1>NXtutors Pro Profile</h1>'
            . '<h2>Our AI writes a full, SEO-rich tutor profile from your CV.</h2>'
            . '<p>One-time fee: <strong>₹' . $price . '</strong></p>'
            . '<form method="post" action="' . pro_e(pro_base_url() . '/pro/' . $token . '/simulate-paid') . '">'
            . '<button type="submit">Simulate paid (dev)</button></form>'
            . '<p class="muted">Developer mode — no real payment is taken.</p>');
    }

    [$keyId] = razorpay_keys();
    if ($keyId === '') {
        pro_page_error('Online payment is not configured yet. Please try again later.', 503);
    }

    // Create (or reuse) the Razorpay order for this token.
    $orderId = (string) ($record['order_id'] ?? '');
    if ($orderId === '') {
        $orderId = razorpay_create_order($price * 100, 'pro_' . substr($token, 0, 12));
        if ($orderId === '') {
            pro_page_error('Could not start the payment. Please try again in a moment.', 502);
        }
        $record['order_id'] = $orderId;
        save_pro($token, $record);
        pro_index_order($orderId, $token);
    }

    $verifyUrl = pro_base_url() . '/pro/' . $token . '/verify';
    $formUrl = pro_base_url() . '/pro/' . $token . '/form';

    $inner = '<h1>NXtutors Pro Profile</h1>'
        . '<h2>Our AI writes a full, SEO-rich tutor profile from your CV.</h2>'
        . '<p>One-time fee: <strong>₹' . $price . '</strong></p>'
        . '<button id="pay">Pay ₹' . $price . ' now</button>'
        . '<p class="muted">Secure payment via Razorpay (UPI / cards / netbanking).</p>'
        . '<script src="https://checkout.razorpay.com/v1/checkout.js"></script><script>'
        . 'document.getElementById("pay").onclick=function(){var rzp=new Razorpay({'
        . 'key:' . json_encode($keyId) . ',order_id:' . json_encode($orderId) . ','
        . 'amount:' . ($price * 100) . ',currency:"INR",name:"NXtutors Pro Profile",'
        . 'description:"AI-written tutor profile",'
        . 'handler:function(r){fetch(' . json_encode($verifyUrl) . ',{method:"POST",headers:{"Content-Type":"application/json"},'
        . 'body:JSON.stringify(r)}).then(function(x){return x.json();}).then(function(j){'
        . 'if(j.ok){window.location=' . json_encode($formUrl) . ';}else{alert("Payment verification failed. If money was deducted, contact support.");}});}'
        . '});rzp.open();};</script>';

    pro_html('NXtutors Pro — Payment', $inner);
}

function pro_page_form(string $token, array $record): never
{
    if (($record['status'] ?? '') !== 'paid') {
        pro_page_error('Please complete the ₹' . pro_price_inr() . ' payment first.', 402);
    }

    $action = pro_base_url() . '/pro/' . $token . '/submit';
    $inner = '<h1>Payment received ✅</h1><h2>Upload your CV and tell us a little about you.</h2>'
        . '<form method="post" action="' . pro_e($action) . '" enctype="multipart/form-data">'
        . '<label>Your CV / Resume (PDF, JPG or PNG)</label>'
        . '<input type="file" name="cv" accept=".pdf,.jpg,.jpeg,.png" required>'
        . '<label>Full name</label><input type="text" name="name" required maxlength="255">'
        . '<label>Main subject you teach</label><input type="text" name="subject" required maxlength="255">'
        . '<label>Email (for your login)</label><input type="email" name="email" required maxlength="255">'
        . '<button type="submit">Generate my profile</button>'
        . '<p class="muted">This can take up to a minute while our AI writes your profile.</p></form>';

    pro_html('NXtutors Pro — Upload', $inner);
}

function pro_page_success(string $role, string $email, string $tempPassword): never
{
    $inner = '<h1>Your Pro profile is ready ✅</h1>'
        . '<h2>Your tutor account is created and pending document review.</h2>'
        . '<div class="cred"><strong>Login page:</strong><br>' . pro_e(login_url()) . '</div>'
        . '<div class="cred"><strong>Login email:</strong><br>' . pro_e($email) . '</div>'
        . '<div class="cred"><strong>Temporary password:</strong><br>' . pro_e($tempPassword) . '</div>'
        . '<div class="cred"><strong>Dashboard:</strong><br>' . pro_e(dashboard_url($role)) . '</div>'
        . '<p class="muted">Please change your password after your first login.</p>'
        . '<a class="btn" href="' . pro_e(login_url()) . '">Go to login</a>'
        . '<pre class="muted">' . pro_e(role_checklist($role)) . '</pre>';

    pro_html('NXtutors Pro — Done', $inner);
}

/* -------------------------------------------------------------------------- */
/* Submit handler: store CV -> AI -> create account                           */
/* -------------------------------------------------------------------------- */

function pro_handle_submit(string $token, array $record): never
{
    $phone = (string) ($record['wa_phone'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if (strlen($name) < 2 || strlen($name) > 255) {
        pro_page_error('Please enter your full name.');
    }
    if (strlen($subject) < 2 || strlen($subject) > 255) {
        pro_page_error('Please enter the subject you teach.');
    }
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        pro_page_error('Please enter a valid email address.');
    }

    $stored = pro_store_cv($_FILES['cv'] ?? []);
    if (! $stored['ok']) {
        pro_page_error((string) $stored['error']);
    }

    $record['status'] = 'submitted';
    $record['email'] = $email;
    $record['cv_path'] = (string) $stored['path'];
    save_pro($token, $record);

    $ai = pro_generate_profile((string) $stored['path'], (string) $stored['mime'], $name, $subject, $email);
    if ($ai === null) {
        // AI failed — still record the paid lead so it is never lost.
        capture_lead('tutor', $phone, ['name' => $name, 'email' => $email, 'for_class' => $subject, 'pro_mode' => 'ai_failed', 'cv_path' => (string) $stored['path']]);
        pro_page_error('We received your payment and CV but could not generate the profile right now. Our team will finish it and contact you on WhatsApp.', 200);
    }

    $data = [
        'name' => $name,
        'email' => $email,
        'wa_phone' => $phone,
        'role' => 'tutor',
        'for_class' => $subject,
        'class_type' => 'online',
        'education' => trim((string) ($ai['education'] ?? '')),
        'experience' => trim((string) ($ai['experience'] ?? '')),
        'profile' => trim((string) ($ai['profile_title'] ?? ('Expert ' . $subject . ' Tutor'))),
        'profile_desc' => trim((string) ($ai['profile_desc'] ?? '')),
        'pro_desc' => trim((string) ($ai['pro_desc'] ?? '')),
    ];

    // Always keep a file record of the completed (paid) signup.
    capture_lead('tutor', $phone, array_merge($data, ['pro_mode' => 'ai', 'cv_path' => (string) $stored['path']]));

    if (! real_profile_enabled()) {
        $record['status'] = 'completed';
        save_pro($token, $record);
        pro_complete_chat_session($phone);
        pro_html('NXtutors Pro — Done', '<h1>All set ✅</h1>'
            . '<h2>Your Pro profile is ready and saved.</h2>'
            . '<p>Our team will activate your tutor account and send your login on WhatsApp shortly.</p>');
    }

    $result = create_register_profile('tutor', $phone, $data);

    if (($result['status'] ?? '') === 'created') {
        $record['status'] = 'completed';
        $record['user_id'] = (string) ($result['user_id'] ?? '');
        save_pro($token, $record);
        pro_complete_chat_session($phone);
        pro_maybe_send_whatsapp($phone, (string) $result['email'], (string) $result['temp_password']);
        pro_page_success('tutor', mask_email((string) $result['email']), (string) $result['temp_password']);
    }

    if (($result['status'] ?? '') === 'duplicate') {
        $record['status'] = 'completed';
        save_pro($token, $record);
        pro_complete_chat_session($phone);
        pro_html('NXtutors Pro', '<h1>Account already exists</h1>'
            . '<h2>An NXtutors account already exists with this email or number.</h2>'
            . '<a class="btn" href="' . pro_e(login_url()) . '">Go to login</a>');
    }

    // create error → keep paid lead, ask team follow-up.
    pro_page_error('We received your payment and details but hit a problem creating the account. Our team will finish it and contact you on WhatsApp.', 200);
}

/** Mark the user's WhatsApp chat session as completed so it does not loop. */
function pro_complete_chat_session(string $phone): void
{
    if ($phone === '') {
        return;
    }
    $session = load_session($phone);
    if ($session !== null) {
        $session['state'] = 'COMPLETED';
        save_session($phone, $session);
    }
}

/** Best-effort WhatsApp confirmation via Graph API (within the 24h window). */
function pro_maybe_send_whatsapp(string $phone, string $email, string $tempPassword): void
{
    if (strtolower(env_value('PRO_WHATSAPP_CONFIRM') ?: 'true') === 'false') {
        return;
    }
    $token = env_value('META_WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN');
    $phoneId = env_value('META_WHATSAPP_PHONE_NUMBER_ID', 'META_PHONE_NUMBER_ID');
    if ($token === '' || $phoneId === '' || $phone === '') {
        return;
    }

    $body = "✅ Your NXtutors Pro tutor account is ready.\nLogin: " . login_url()
        . "\nEmail: " . mask_email($email) . "\nTemporary password: " . $tempPassword
        . "\n\nPlease change your password after first login.";

    $version = env_value('META_WHATSAPP_API_VERSION') ?: 'v20.0';
    $base = rtrim(env_value('META_GRAPH_BASE_URL') ?: 'https://graph.facebook.com', '/');

    pro_http('POST', $base . '/' . $version . '/' . $phoneId . '/messages', [
        'headers' => ['Authorization: Bearer ' . $token],
        'json' => [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ],
        'timeout' => 10,
    ]);
}

/* -------------------------------------------------------------------------- */
/* Webhook (Razorpay)                                                         */
/* -------------------------------------------------------------------------- */

function pro_handle_webhook(): never
{
    $raw = file_get_contents('php://input');
    $raw = $raw === false ? '' : $raw;
    $sig = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

    if (! razorpay_verify_webhook($raw, $sig)) {
        pro_json(['status' => 'invalid_signature'], 400);
    }

    $payload = json_decode($raw, true);
    $orderId = (string) ($payload['payload']['payment']['entity']['order_id'] ?? '');
    $paymentId = (string) ($payload['payload']['payment']['entity']['id'] ?? '');

    if ($orderId !== '') {
        $token = pro_token_for_order($orderId);
        $record = $token !== '' ? load_pro($token) : null;
        if ($record !== null && ($record['status'] ?? '') === 'created') {
            $record['status'] = 'paid';
            $record['payment_id'] = $paymentId;
            save_pro($token, $record);
        }
    }

    pro_json(['status' => 'ok']);
}

/* -------------------------------------------------------------------------- */
/* Router                                                                     */
/* -------------------------------------------------------------------------- */

function pro_handle_request(string $path, string $method): void
{
    if (! pro_mode_enabled()) {
        pro_page_error('Pro mode is currently unavailable.', 404);
    }

    $parts = explode('/', trim($path, '/')); // ['pro', token|webhook, action?]
    $seg1 = $parts[1] ?? '';
    $action = $parts[2] ?? '';

    if ($seg1 === 'webhook') {
        if ($method !== 'POST') {
            pro_json(['status' => 'method_not_allowed'], 405);
        }
        pro_handle_webhook();
    }

    $token = $seg1;
    $record = load_pro($token);
    if ($record === null) {
        pro_page_error('This link is invalid or has expired. Please start again on WhatsApp.', 404);
    }

    if ($action === '' && $method === 'GET') {
        pro_page_landing($token, $record);
    }

    if ($action === 'verify' && $method === 'POST') {
        $input = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($input) ? $input : $_POST;
        $ok = razorpay_verify_payment_signature(
            (string) ($input['razorpay_order_id'] ?? ''),
            (string) ($input['razorpay_payment_id'] ?? ''),
            (string) ($input['razorpay_signature'] ?? '')
        );
        if (! $ok) {
            pro_json(['ok' => false], 400);
        }
        $record['status'] = 'paid';
        $record['payment_id'] = (string) ($input['razorpay_payment_id'] ?? '');
        save_pro($token, $record);
        pro_json(['ok' => true, 'redirect' => pro_base_url() . '/pro/' . $token . '/form']);
    }

    if ($action === 'simulate-paid' && $method === 'POST') {
        if (! pro_fake_payment()) {
            pro_page_error('Not available.', 404);
        }
        $record['status'] = 'paid';
        save_pro($token, $record);
        header('Location: ' . pro_base_url() . '/pro/' . $token . '/form');
        exit;
    }

    if ($action === 'form' && $method === 'GET') {
        pro_page_form($token, $record);
    }

    if ($action === 'submit' && $method === 'POST') {
        if (($record['status'] ?? '') !== 'paid' && ($record['status'] ?? '') !== 'submitted') {
            pro_page_error('Please complete the payment first.', 402);
        }
        pro_handle_submit($token, $record);
    }

    pro_page_error('Page not found.', 404);
}
