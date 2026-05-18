<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/SecurityHelper.php';
require_once dirname(__DIR__) . '/api_endpoint_test_helper.php';

function systemCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

seedAuthenticatedApiSession('admin@example.com');

$checks = [];

$previousAuthTime = time() - 900;
$_SESSION['auth_time'] = $previousAuthTime;

$extendSessionResponse = runApiRequest(
    [],
    [
        'action' => 'extend_session',
        'csrf_token' => 'test_csrf_token_123',
    ],
    [],
    'POST'
);

$checks['extend_session returns valid JSON'] = is_array($extendSessionResponse['json']);
$checks['extend_session returns success true'] = ($extendSessionResponse['json']['success'] ?? false) === true;
$checks['extend_session refreshes auth_time'] = (int) ($_SESSION['auth_time'] ?? 0) > $previousAuthTime;

$expectedPermissions = SecurityHelper::checkMediaDirectoryPermissions();
$permissionsResponse = runApiRequest(['action' => 'check_permissions'], [], [], 'GET');

$checks['check_permissions returns valid JSON'] = is_array($permissionsResponse['json']);
$checks['check_permissions success matches helper writable flag'] = ($permissionsResponse['json']['success'] ?? null) === $expectedPermissions['writable'];
$checks['check_permissions payload matches SecurityHelper'] = ($permissionsResponse['json']['permissions'] ?? null) == $expectedPermissions;

echo "=== SYSTEM ENDPOINT CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!systemCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);