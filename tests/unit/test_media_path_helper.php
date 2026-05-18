<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/core/MediaPathHelper.php';

function mediaPathCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

$checks = [
    'Valid backend media path stays unchanged' => MediaPathHelper::normalizeBackendMediaPath('/backend/media/images/example.jpg') === '/backend/media/images/example.jpg',
    'Backslashes are normalized to project media path' => MediaPathHelper::normalizeBackendMediaPath('\\backend\\media\\downloads\\handout.pdf') === '/backend/media/downloads/handout.pdf',
    'Nested backend media paths stay valid' => MediaPathHelper::normalizeBackendMediaPath('/backend/media/header/events/summer-2026.webp') === '/backend/media/header/events/summer-2026.webp',
    'Empty strings are rejected' => MediaPathHelper::normalizeBackendMediaPath('   ') === null,
    'Non-string values are rejected' => MediaPathHelper::normalizeBackendMediaPath(['not-a-path']) === null,
    'Paths outside backend media root are rejected' => MediaPathHelper::normalizeBackendMediaPath('/assets/images/example.jpg') === null,
    'Traversal segments are rejected' => MediaPathHelper::normalizeBackendMediaPath('/backend/media/images/../secret.txt') === null,
];

echo "=== MEDIA PATH HELPER UNIT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!mediaPathCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);