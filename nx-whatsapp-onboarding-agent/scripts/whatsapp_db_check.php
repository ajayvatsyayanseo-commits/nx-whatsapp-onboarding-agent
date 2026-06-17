<?php

declare(strict_types=1);

/**
 * Standalone DB connectivity + register-mapping self-test for the WhatsApp
 * onboarding agent. Run this ON THE SERVER where the agent runs:
 *
 *   php nx-whatsapp-onboarding-agent/scripts/whatsapp_db_check.php
 *
 * It loads the same .env the agent uses, connects to the website DB via PDO,
 * introspects the `register` table, and prints exactly which columns a new
 * student signup would write — WITHOUT inserting anything (dry run).
 */

function out(string $line): void
{
    fwrite(STDOUT, $line . "\n");
}

/* ---- Load .env the same way public/index.php does (real env wins) -------- */
$candidates = [
    getenv('ONBOARDING_ENV_FILE') ?: '',
    __DIR__ . '/../.env',
    dirname(__DIR__, 2) . '/.env',
];
$envFile = '';
foreach ($candidates as $c) {
    if ($c !== '' && is_file($c)) { $envFile = $c; break; }
}
if ($envFile !== '') {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { continue; }
        $eq = strpos($line, '=');
        if ($eq === false) { continue; }
        $key = trim(substr($line, 0, $eq));
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) { continue; }
        if (getenv($key) !== false) { continue; }
        $val = trim(substr($line, $eq + 1));
        $len = strlen($val);
        if ($len >= 2 && (($val[0] === '"' && $val[$len - 1] === '"') || ($val[0] === "'" && $val[$len - 1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        putenv("$key=$val");
    }
    out("env file: $envFile");
} else {
    out("env file: (none found — relying on real environment)");
}

function e(string ...$names): string
{
    foreach ($names as $n) {
        $v = getenv($n);
        if ($v !== false && $v !== '') { return (string) $v; }
    }
    return '';
}

/* ---- Show the effective config (password masked) ------------------------- */
$conn = e('DB_CONNECTION') ?: 'mysql';
$host = e('DB_HOST');
$port = e('DB_PORT') ?: '3306';
$db   = e('DB_DATABASE', 'DB_NAME');
$user = e('DB_USERNAME', 'DB_USER');
$pass = e('DB_PASSWORD');
$table = preg_match('/^[A-Za-z0-9_]+$/', e('WHATSAPP_ONBOARDING_REGISTER_TABLE') ?: 'register') === 1
    ? (e('WHATSAPP_ONBOARDING_REGISTER_TABLE') ?: 'register') : 'register';

out("DB_CONNECTION=$conn  DB_HOST=$host  DB_PORT=$port  DB_DATABASE=$db  DB_USERNAME=$user  DB_PASSWORD=" . ($pass === '' ? '(empty)' : '***set***'));
out("WHATSAPP_CREATE_REAL_PROFILE=" . (e('WHATSAPP_CREATE_REAL_PROFILE') ?: '(unset)'));
out("register table=$table");
out(str_repeat('-', 60));

if ($host === '' && $conn !== 'sqlite') {
    out('RESULT: DB_HOST is empty — database not configured.');
    exit(1);
}

/* ---- Connect ------------------------------------------------------------- */
$driver = $conn === 'pgsql' ? 'pgsql' : ($conn === 'sqlite' ? 'sqlite' : 'mysql');
try {
    if ($driver === 'sqlite') {
        $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } else {
        $dsn = $driver === 'pgsql'
            ? sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db)
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    }
    $pdo->query('SELECT 1');
    out('CONNECT: OK');
} catch (\Throwable $ex) {
    out('CONNECT: FAILED — ' . $ex->getMessage());
    exit(1);
}

/* ---- Introspect register columns ----------------------------------------- */
$columns = [];
try {
    if ($driver === 'mysql') {
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[strtolower((string) $row['Field'])] = (string) $row['Field'];
        }
    } elseif ($driver === 'pgsql') {
        $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_name = ?');
        $stmt->execute([$table]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $columns[strtolower((string) $name)] = (string) $name;
        }
    } else {
        foreach ($pdo->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[strtolower((string) $row['name'])] = (string) $row['name'];
        }
    }
} catch (\Throwable $ex) {
    out("INTROSPECT: FAILED — " . $ex->getMessage());
    exit(1);
}
if ($columns === []) {
    out("INTROSPECT: no columns found — does table `$table` exist?");
    exit(1);
}
out('INTROSPECT: ' . count($columns) . " columns found in `$table`");

/* ---- Dry-run the candidate mapping for a sample student ------------------ */
$now = date('Y-m-d H:i:s');
$candidatesMap = [
    'user_id' => 'NXS-' . date('Y') . '-TEST01',
    'name' => 'Self Test',
    'email' => 'selftest@example.com',
    'phone' => '910000000000',
    'password' => password_hash('dummy', PASSWORD_BCRYPT),
    'user_type' => e('WHATSAPP_USER_TYPE_STUDENT') ?: 'student',
    'join_as' => 'student',
    'otp_status' => e('WHATSAPP_OTP_STATUS_VERIFIED') ?: 't',
    'status' => e('WHATSAPP_STUDENT_STATUS') ?: 't',
    'date' => $now,
    'dob' => '2004-04-03',
    'gender' => 'male',
    'class_type' => 'online',
    'for_class' => 'class 7 for maths',
    'budget' => '500',
    'city' => 'Dharuhera',
    'force_password_reset' => 1,
    'c_password' => '',
    'created_at' => $now,
    'updated_at' => $now,
];

$willWrite = [];
$skipped = [];
foreach ($candidatesMap as $k => $v) {
    if (isset($columns[$k])) { $willWrite[] = $columns[$k]; } else { $skipped[] = $k; }
}
out(str_repeat('-', 60));
out('Columns that WOULD be written for a student signup:');
out('  ' . implode(', ', $willWrite));
out('Candidate keys NOT present in the table (safely skipped):');
out('  ' . (empty($skipped) ? '(none)' : implode(', ', $skipped)));
out(str_repeat('-', 60));
out('RESULT: database is reachable and `' . $table . '` is writable-mappable.');
out('No row was inserted (dry run). Enable WHATSAPP_CREATE_REAL_PROFILE=true and');
out('complete a WhatsApp signup to create a real row.');
