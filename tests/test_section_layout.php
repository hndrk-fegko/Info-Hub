<?php
/**
 * Test: section markers create grouped page sections in preview and canvas layout.
 */

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/core/LogService.php';
require_once __DIR__ . '/../backend/core/StorageService.php';
require_once __DIR__ . '/../backend/core/TileService.php';
require_once __DIR__ . '/../backend/core/GeneratorService.php';
require_once __DIR__ . '/../backend/tiles/_registry.php';

$storage = new StorageService('tiles.json');
$originalTiles = $storage->read();

$testTiles = [
    [
        'id' => 'sec_intro',
        'type' => 'section',
        'position' => 10,
        'size' => 'full',
        'style' => 'flat',
        'colorScheme' => 'default',
        'data' => [
            'title' => 'Intro',
            'backgroundMode' => 'accent1',
            'backgroundAttachment' => 'content',
            'backgroundDisplay' => 'cover',
            'overlayEnabled' => false,
            'overlayColor' => '#000000',
            'overlayOpacity' => 35,
        ],
    ],
    [
        'id' => 'tile_intro',
        'type' => 'infobox',
        'position' => 20,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'data' => [
            'title' => 'Willkommen',
            'showTitle' => true,
            'description' => 'Testabschnitt A',
        ],
    ],
    [
        'id' => 'sec_media',
        'type' => 'section',
        'position' => 30,
        'size' => 'full',
        'style' => 'flat',
        'colorScheme' => 'default',
        'data' => [
            'title' => 'Media',
            'backgroundMode' => 'image',
            'backgroundImage' => '/backend/media/header/test.jpg',
            'backgroundAttachment' => 'content',
            'backgroundDisplay' => 'cover',
            'overlayEnabled' => true,
            'overlayColor' => '#112233',
            'overlayOpacity' => 40,
        ],
    ],
    [
        'id' => 'tile_media',
        'type' => 'quote',
        'position' => 40,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'data' => [
            'title' => 'Zitat',
            'quote' => 'Section smoke test',
        ],
    ],
];

$checks = [];

try {
    if (!$storage->write($testTiles)) {
        throw new RuntimeException('Konnte Test-Tiles nicht schreiben');
    }

    $generator = new GeneratorService();
    $preview = $generator->preview();
    $sections = $generator->renderCanvasSections();

    $checks = [
        'Preview has page sections' => strpos($preview, 'page-sections') !== false,
        'Preview has intro section wrapper' => strpos($preview, 'data-section-id="section_sec_intro"') !== false,
        'Preview has media section wrapper' => strpos($preview, 'data-section-id="section_sec_media"') !== false,
        'Preview keeps marker id attribute' => strpos($preview, 'data-section-marker-id="sec_intro"') !== false,
        'Preview uses accent background var' => strpos($preview, '--section-background-color:var(--accent-color);') !== false,
        'Preview does not render section as normal tile' => strpos($preview, 'tile tile-section') === false,
        'Canvas returns two sections' => count($sections) === 2,
        'Canvas section 1 marker id' => ($sections[0]['markerTileId'] ?? null) === 'sec_intro',
        'Canvas section 1 contains intro tile' => in_array('tile_intro', $sections[0]['tileIds'] ?? [], true),
        'Canvas section 2 marker id' => ($sections[1]['markerTileId'] ?? null) === 'sec_media',
        'Canvas section 2 keeps overlay flag' => ($sections[1]['overlayEnabled'] ?? false) === true,
    ];
} finally {
    $storage->write($originalTiles);
}

echo "=== SECTION LAYOUT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    echo ($result ? '  ✅' : '  ❌') . " $name\n";
    if (!$result) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);