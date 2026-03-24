<?php
/**
 * Newsletter subscribe endpoint.
 * Saves email to a CSV outside the web root.
 */

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$email = filter_input(INPUT_POST, 'EMAIL', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    header('Location: /?sub=invalid');
    exit;
}

// Rate limit: simple per-IP cooldown via temp files
$rateLimitDir = sys_get_temp_dir() . '/tsda_ratelimit';
if (!is_dir($rateLimitDir)) {
    mkdir($rateLimitDir, 0700, true);
}
$ipHash = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$lockFile = $rateLimitDir . '/' . $ipHash;
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 60) {
    http_response_code(429);
    header('Location: /?sub=wait');
    exit;
}
touch($lockFile);

// Save to CSV outside web root
$csvPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/subscribers.csv';
$isNew = !file_exists($csvPath);

$fp = fopen($csvPath, 'a');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        if ($isNew) {
            fputcsv($fp, ['timestamp', 'email', 'ip']);
        }
        fputcsv($fp, [
            date('Y-m-d H:i:s'),
            $email,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

header('Location: /?sub=ok');
exit;
