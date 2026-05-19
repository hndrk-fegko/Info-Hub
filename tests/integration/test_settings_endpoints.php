<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/StorageService.php';
require_once __DIR__ . '/../../backend/core/ConfigService.php';
require_once __DIR__ . '/../../backend/core/SettingsService.php';
require_once dirname(__DIR__) . '/api_endpoint_test_helper.php';

function settingsCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

seedAuthenticatedApiSession('admin@example.com');

$storage = new StorageService('settings.json');
$configService = new ConfigService(__DIR__ . '/../../backend/config.php');
$settingsService = new SettingsService(null, $configService);
$originalSettings = $storage->read();

$payload = [
    'site' => [
        'title' => 'Contract Title',
        'hideHeaderTitle' => true,
        'footerText' => "Zeile 1\nZeile 2",
        'headerImage' => '\\backend\\media\\header\\contract.jpg',
        'headerFocusPoint' => 'top center',
    ],
    'theme' => [
        'backgroundColor' => '#112233',
        'accentColor' => '#445566',
        'accentColor2' => '#778899',
        'narrowBackgroundMode' => 'image',
        'narrowBackgroundImage' => '\\backend\\media\\backgrounds\\contract.jpg',
        'narrowBackgroundOverlayEnabled' => true,
        'narrowBackgroundOverlayOpacity' => 42,
    ],
];

$checks = [];

try {
    if (!$storage->write($originalSettings)) {
        throw new RuntimeException('Konnte Ausgangs-Settings nicht schreiben');
    }

    $expectedSavedSettings = $settingsService->saveSettings($payload, 'localhost:8000', 'admin@example.com');
    $expectedStoredSettings = $storage->read();

    if (!$storage->write($originalSettings)) {
        throw new RuntimeException('Konnte Ausgangs-Settings fuer Endpoint-Test nicht wiederherstellen');
    }

    $saveResponse = runApiRequest(
        [],
        [
            'action' => 'save_settings',
            'csrf_token' => 'test_csrf_token_123',
            'settings' => json_encode($payload),
        ],
        [],
        'POST'
    );
    $storedAfterSave = $storage->read();

    $getResponse = runApiRequest(['action' => 'get_settings'], [], [], 'GET');
    $expectedEditorSettings = $settingsService->getSettingsForEditor('localhost:8000', 'admin@example.com');

    $checks['save_settings returns valid JSON'] = is_array($saveResponse['json']);
    $checks['save_settings returns success true'] = ($saveResponse['json']['success'] ?? false) === true;
    $checks['save_settings response matches SettingsService'] = ($saveResponse['json']['settings'] ?? null) == $expectedSavedSettings;
    $checks['save_settings persists normalized media paths'] = ($storedAfterSave['site']['headerImage'] ?? null) === '/backend/media/header/contract.jpg'
        && ($storedAfterSave['theme']['narrowBackgroundImage'] ?? null) === '/backend/media/backgrounds/contract.jpg';
    $checks['save_settings persists header title visibility flag'] = ($storedAfterSave['site']['hideHeaderTitle'] ?? null) === true;
    $checks['save_settings persists same data as SettingsService'] = $storedAfterSave == $expectedStoredSettings;
    $checks['get_settings returns valid JSON'] = is_array($getResponse['json']);
    $checks['get_settings returns success true'] = ($getResponse['json']['success'] ?? false) === true;
    $checks['get_settings response matches SettingsService'] = ($getResponse['json']['settings'] ?? null) == $expectedEditorSettings;
    $checks['get_settings masks admin emails'] = !isset($getResponse['json']['settings']['auth']['emails'])
        && isset($getResponse['json']['settings']['auth']['emailsMasked']);
} finally {
    $storage->write($originalSettings);
}

echo "=== SETTINGS ENDPOINT CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!settingsCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);