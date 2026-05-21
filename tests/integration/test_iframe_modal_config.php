<?php
/**
 * Test: Iframe modal config contracts (rendered data attributes + CSS/JS precedence hooks).
 */

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/tiles/TileBase.php';
require_once __DIR__ . '/../../backend/tiles/IframeTile.php';

$tile = new IframeTile();

$defaultWithOverrideHtml = $tile->render([
    'title' => 'Formular',
    'showTitle' => true,
    'url' => 'https://example.com/form',
    'displayMode' => 'modal',
    'modalBackgroundMode' => 'default',
    'modalBackgroundColorOverrideEnabled' => true,
    'modalBackgroundColorOverride' => '#112233',
]);

$imageWithOverrideHtml = $tile->render([
    'title' => 'Formular',
    'showTitle' => true,
    'url' => 'https://example.com/form',
    'displayMode' => 'modal',
    'modalBackgroundMode' => 'image',
    'modalBackgroundImage' => '/backend/media/backgrounds/test.jpg',
    'modalBackgroundColorOverrideEnabled' => true,
    'modalBackgroundColorOverride' => '#445566',
    'modalBackgroundDisplay' => 'cover',
]);

$css = file_get_contents(__DIR__ . '/../../assets/css/shared/components.css');
$js = file_get_contents(__DIR__ . '/../../backend/tiles/IframeTile.js');

$checks = [
    'Default mode keeps bg-mode attribute' => strpos($defaultWithOverrideHtml, 'data-iframe-bg-mode="default"') !== false,
    'Default mode keeps manual override attribute' => strpos($defaultWithOverrideHtml, 'data-iframe-bg-color-override="#112233"') !== false,
    'Image mode keeps image attribute' => strpos($imageWithOverrideHtml, 'data-iframe-bg-image="/backend/media/backgrounds/test.jpg"') !== false,
    'Image mode keeps manual override attribute for fallback' => strpos($imageWithOverrideHtml, 'data-iframe-bg-color-override="#445566"') !== false,
    'Shared CSS keeps close button pinned right' => strpos($css, 'margin-left: auto;') !== false,
    'Shared CSS defines custom background priority class' => strpos($css, '.iframe-modal-content--bg-custom') !== false,
    'JS computes effective background mode' => strpos($js, 'const effectiveBackgroundMode = hasImageBackground') !== false,
    'JS applies image over override precedence' => strpos($js, "? 'image'") !== false,
    'JS applies override over non-image modes' => strpos($js, "? 'custom'") !== false,
];

echo "=== IFRAME MODAL CONFIG CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    echo ($result ? '  ✅' : '  ❌') . " $name\n";
    if (!$result) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);
