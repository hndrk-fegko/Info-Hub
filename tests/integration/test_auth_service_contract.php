<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/StorageService.php';
require_once __DIR__ . '/../../backend/core/AuthService.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function authCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

function resetAuthSessionState(): void {
    foreach ([
        'authenticated',
        'auth_time',
        'csrf_token',
        'auth_code',
        'auth_code_expires',
        'auth_attempts',
        'auth_lockout',
        'auth_email',
        'auth_pending_email',
        'debug_code_display',
    ] as $key) {
        unset($_SESSION[$key]);
    }
}

$settingsStorage = new StorageService('settings.json');
$originalSettings = $settingsStorage->read();
$originalHttpHost = $_SERVER['HTTP_HOST'] ?? null;
$originalServerName = $_SERVER['SERVER_NAME'] ?? null;

$checks = [];

try {
    $testSettings = $originalSettings;
    $testSettings['auth']['emails'] = ['admin@example.com'];
    $testSettings['auth']['invites'] = [];
    unset($testSettings['auth']['email']);

    if (!$settingsStorage->write($testSettings)) {
        throw new RuntimeException('Konnte Auth-Testsettings nicht schreiben');
    }

    $_SERVER['HTTP_HOST'] = 'localhost:8000';
    $_SERVER['SERVER_NAME'] = 'localhost';

    resetAuthSessionState();
    $auth = new AuthService();

    $sendUnknown = $auth->sendCode('intruder@example.com');
    $checks['Unauthorized email gets generic success response'] = ($sendUnknown['success'] ?? false) === true;
    $checks['Unauthorized email keeps generic message'] = ($sendUnknown['message'] ?? '') === 'Falls die Email korrekt ist, wurde ein Code versendet';
    $checks['Unauthorized email does not create auth code'] = !isset($_SESSION['auth_code']);

    resetAuthSessionState();
    $noCode = $auth->verifyCode('123456');
    $checks['verifyCode without pending code fails'] = ($noCode['success'] ?? true) === false;
    $checks['verifyCode without pending code explains missing request'] = ($noCode['message'] ?? '') === 'Kein Code angefordert';

    resetAuthSessionState();
    $_SESSION['auth_code'] = '654321';
    $_SESSION['auth_code_expires'] = time() + 300;
    $_SESSION['auth_attempts'] = 0;
    $_SESSION['auth_email'] = 'admin@example.com';

    $wrongAttempt = $auth->verifyCode('000000');
    $checks['Wrong code attempt fails'] = ($wrongAttempt['success'] ?? true) === false;
    $checks['Wrong code increments attempt counter'] = (int)($_SESSION['auth_attempts'] ?? 0) === 1;

    $auth->verifyCode('111111');
    $auth->verifyCode('222222');
    $checks['Third wrong code activates lockout'] = isset($_SESSION['auth_lockout']) && (int)$_SESSION['auth_lockout'] > time();

    $lockedOut = $auth->verifyCode('654321');
    $checks['Locked-out account stays blocked even with correct code'] = ($lockedOut['success'] ?? true) === false;
    $checks['Locked-out message mentions rate limit'] = strpos((string)($lockedOut['message'] ?? ''), 'Zu viele Versuche') !== false;

    resetAuthSessionState();
    $_SESSION['auth_code'] = '123123';
    $_SESSION['auth_code_expires'] = time() + 300;
    $_SESSION['auth_attempts'] = 0;
    $_SESSION['auth_email'] = 'admin@example.com';

    $success = $auth->verifyCode('123123');
    $checks['Correct code authenticates session'] = ($success['success'] ?? false) === true;
    $checks['Correct code sets authenticated flag'] = ($_SESSION['authenticated'] ?? false) === true;
    $checks['Correct code creates csrf token'] = is_string($_SESSION['csrf_token'] ?? null) && strlen($_SESSION['csrf_token']) === 64;
    $checks['Correct code clears pending auth code'] = !isset($_SESSION['auth_code']) && !isset($_SESSION['auth_code_expires']);

    $_SESSION['authenticated'] = true;
    $_SESSION['auth_time'] = time() - $auth->getSessionTimeout() - 5;
    $checks['Expired session is rejected by isAuthenticated'] = $auth->isAuthenticated() === false;
} finally {
    $settingsStorage->write($originalSettings);
    resetAuthSessionState();

    if ($originalHttpHost === null) {
        unset($_SERVER['HTTP_HOST']);
    } else {
        $_SERVER['HTTP_HOST'] = $originalHttpHost;
    }

    if ($originalServerName === null) {
        unset($_SERVER['SERVER_NAME']);
    } else {
        $_SERVER['SERVER_NAME'] = $originalServerName;
    }
}

echo "=== AUTH SERVICE CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!authCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);