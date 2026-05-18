<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/StorageService.php';
require_once __DIR__ . '/../../backend/core/AuthService.php';
require_once dirname(__DIR__) . '/api_endpoint_test_helper.php';

function adminCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

function resetAdminApiSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    foreach (['authenticated', 'auth_time', 'csrf_token', 'auth_email'] as $key) {
        unset($_SESSION[$key]);
    }
}

$storage = new StorageService('settings.json');
$auth = new AuthService($storage);
$originalSettings = $storage->read();

$checks = [];

try {
    $now = time();
    $testSettings = $originalSettings;
    $testSettings['auth']['emails'] = ['admin@example.com', 'helper@example.com'];
    $testSettings['auth']['invites'] = [
        [
            'email' => 'pending@example.com',
            'createdAt' => $now,
            'expiresAt' => $now + 3600,
            'createdBy' => 'admin@example.com',
        ],
    ];
    unset($testSettings['auth']['email']);

    if (!$storage->write($testSettings)) {
        throw new RuntimeException('Konnte Admin-Testsettings nicht schreiben');
    }

    seedAuthenticatedApiSession('admin@example.com');
    $getAdminsResponse = runApiRequest(['action' => 'get_admins'], [], [], 'GET');

    $checks['get_admins returns valid JSON'] = is_array($getAdminsResponse['json']);
    $checks['get_admins returns success true'] = ($getAdminsResponse['json']['success'] ?? false) === true;
    $checks['get_admins matches AuthService snapshot'] = ($getAdminsResponse['json']['emails'] ?? null) == $auth->getAdminEmails()
        && ($getAdminsResponse['json']['invites'] ?? null) == $auth->getPendingInvites();

    seedAuthenticatedApiSession('admin@example.com');
    $invalidInviteResponse = runApiRequest(
        [],
        [
            'action' => 'invite_admin',
            'csrf_token' => 'test_csrf_token_123',
            'email' => 'ungueltig',
        ],
        [],
        'POST'
    );

    $checks['invite_admin invalid email returns 400'] = $invalidInviteResponse['status'] === 400;
    $checks['invite_admin invalid email keeps snapshot'] = ($invalidInviteResponse['json']['emails'] ?? null) == $auth->getAdminEmails()
        && ($invalidInviteResponse['json']['invites'] ?? null) == $auth->getPendingInvites();

    seedAuthenticatedApiSession('admin@example.com');
    $removeInviteResponse = runApiRequest(
        [],
        [
            'action' => 'remove_admin_invite',
            'csrf_token' => 'test_csrf_token_123',
            'email' => 'pending@example.com',
        ],
        [],
        'POST'
    );

    $checks['remove_admin_invite returns success true'] = ($removeInviteResponse['json']['success'] ?? false) === true;
    $checks['remove_admin_invite removes invite from storage and response'] = ($removeInviteResponse['json']['invites'] ?? []) === []
        && $auth->getPendingInvites() === [];

    seedAuthenticatedApiSession('admin@example.com');
    $removeSelfResponse = runApiRequest(
        [],
        [
            'action' => 'remove_admin_email',
            'csrf_token' => 'test_csrf_token_123',
            'email' => 'admin@example.com',
        ],
        [],
        'POST'
    );

    $checks['remove_admin_email self-delete returns success true'] = ($removeSelfResponse['json']['success'] ?? false) === true;
    $checks['remove_admin_email self-delete marks self_deleted'] = ($removeSelfResponse['json']['self_deleted'] ?? false) === true;
    $checks['remove_admin_email self-delete keeps remaining admin in snapshot'] = ($removeSelfResponse['json']['emails'] ?? []) === ['helper@example.com']
        && $auth->getAdminEmails() === ['helper@example.com'];
} finally {
    $storage->write($originalSettings);
    resetAdminApiSession();
}

echo "=== ADMIN ENDPOINT CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!adminCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);