<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/UploadService.php';

function uploadCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

$service = new UploadService();
$tempFile = tempnam(sys_get_temp_dir(), 'ihub_upload_');
$fixtureName = 'contract_fixture_' . bin2hex(random_bytes(4)) . '.txt';
$fixturePath = __DIR__ . '/../../backend/media/images/' . $fixtureName;
$checks = [];

try {
    file_put_contents($tempFile, 'not-an-image');
    file_put_contents($fixturePath, 'fixture');

    $uploadError = $service->uploadImage([
        'name' => 'missing.png',
        'tmp_name' => $tempFile,
        'size' => 10,
        'error' => UPLOAD_ERR_NO_FILE,
    ]);
    $checks['Image upload rejects upload errors'] = ($uploadError['success'] ?? true) === false
        && ($uploadError['error'] ?? '') === 'Upload fehlgeschlagen';

    $tooLarge = $service->uploadDownload([
        'name' => 'large.pdf',
        'tmp_name' => $tempFile,
        'size' => (50 * 1024 * 1024) + 1,
        'error' => UPLOAD_ERR_OK,
    ]);
    $checks['Download upload rejects oversized files'] = ($tooLarge['success'] ?? true) === false
        && strpos((string)($tooLarge['error'] ?? ''), 'Datei zu groß') !== false;

    $invalidExtension = $service->uploadImage([
        'name' => 'invalid.txt',
        'tmp_name' => $tempFile,
        'size' => filesize($tempFile),
        'error' => UPLOAD_ERR_OK,
    ]);
    $checks['Image upload rejects invalid extension'] = ($invalidExtension['success'] ?? true) === false
        && ($invalidExtension['error'] ?? '') === 'Dateityp nicht erlaubt';

    $invalidMime = $service->uploadImage([
        'name' => 'fake-image.png',
        'tmp_name' => $tempFile,
        'size' => filesize($tempFile),
        'error' => UPLOAD_ERR_OK,
    ]);
    $checks['Image upload rejects invalid mime types'] = ($invalidMime['success'] ?? true) === false
        && ($invalidMime['error'] ?? '') === 'Ungültiger Bildtyp';

    $listedFiles = $service->listFiles('images');
    $listedFixture = array_values(array_filter($listedFiles, static function(array $file) use ($fixtureName): bool {
        return ($file['filename'] ?? '') === $fixtureName;
    }));

    $checks['listFiles exposes media fixtures with backend path'] = isset($listedFixture[0]['path'])
        && $listedFixture[0]['path'] === '/backend/media/images/' . $fixtureName;

    $deleted = $service->deleteFile('', '', '/backend/media/images/' . $fixtureName);
    $checks['deleteFile resolves media path and removes file'] = $deleted === true && !file_exists($fixturePath);
} finally {
    if (is_string($tempFile) && file_exists($tempFile)) {
        @unlink($tempFile);
    }
    if (file_exists($fixturePath)) {
        @unlink($fixturePath);
    }
}

echo "=== UPLOAD SERVICE CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!uploadCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);