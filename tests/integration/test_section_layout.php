<?php
/**
 * Test: render contract for grouped page sections in preview, canvas layout and V2 bootstrap.
 */

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/RenderContract.php';
require_once __DIR__ . '/../../backend/core/StorageService.php';
require_once __DIR__ . '/../../backend/core/TileService.php';
require_once __DIR__ . '/../../backend/core/GeneratorService.php';
require_once __DIR__ . '/../../backend/tiles/_registry.php';

@session_start();
$_SESSION['authenticated'] = true;
$_SESSION['auth_time'] = time();
$_SESSION['csrf_token'] = 'test_csrf_token_123';
$_SESSION['auth_email'] = 'test@example.com';

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
    $requiredSectionKeys = RenderContract::canvasSectionKeys();

    ob_start();
    $oldLevel = error_reporting(E_ALL & ~E_WARNING);
    $originalCwd = getcwd();
    chdir(__DIR__ . '/../../backend/v2');
    include __DIR__ . '/../../backend/v2/editor.php';
    if ($originalCwd !== false) {
        chdir($originalCwd);
    }
    error_reporting($oldLevel);
    $editorHtml = ob_get_clean();
    restore_error_handler();

    $embeddedSections = null;
    if (preg_match('/renderedSections:\s*(\[.*?\])\s*,\s*\/\/ Tile type metadata/s', $editorHtml, $matches) === 1) {
        $decodedSections = json_decode($matches[1], true);
        if (is_array($decodedSections)) {
            $embeddedSections = $decodedSections;
        }
    }

    $checks = [
        'Preview has page sections' => strpos($preview, 'page-sections') !== false,
        'Preview has intro section wrapper' => strpos($preview, 'data-section-id="section_sec_intro"') !== false,
        'Preview has media section wrapper' => strpos($preview, 'data-section-id="section_sec_media"') !== false,
        'Preview keeps marker id attribute' => strpos($preview, 'data-section-marker-id="sec_intro"') !== false,
        'Preview uses accent background var' => strpos($preview, '--section-background-color:var(--accent-color);') !== false,
        'Preview does not render section as normal tile' => strpos($preview, 'tile tile-section') === false,
        'Canvas returns two sections' => count($sections) === 2,
        'Canvas section 1 exposes full contract keys' => empty(array_diff($requiredSectionKeys, array_keys($sections[0] ?? []))),
        'Canvas section 2 exposes full contract keys' => empty(array_diff($requiredSectionKeys, array_keys($sections[1] ?? []))),
        'Editor exposes contract version' => strpos($editorHtml, 'renderContractVersion:') !== false,
        'Canvas section 1 marker id' => ($sections[0]['markerTileId'] ?? null) === 'sec_intro',
        'Canvas section 1 marker title' => ($sections[0]['markerTitle'] ?? null) === 'Intro',
        'Canvas section 1 background mode' => ($sections[0]['backgroundMode'] ?? null) === 'accent1',
        'Canvas section 1 background attachment' => ($sections[0]['backgroundAttachment'] ?? null) === 'content',
        'Canvas section 1 background display' => ($sections[0]['backgroundDisplay'] ?? null) === 'cover',
        'Canvas section 1 overlay opacity' => ($sections[0]['overlayOpacity'] ?? null) === 35,
        'Canvas section 1 visible flag defaults true' => ($sections[0]['visible'] ?? null) === true,
        'Canvas section 1 is explicit section' => ($sections[0]['isImplicit'] ?? null) === false,
        'Canvas section 1 contains intro tile' => in_array('tile_intro', $sections[0]['tileIds'] ?? [], true),
        'Canvas section 1 html contains tile grid' => strpos($sections[0]['html'] ?? '', 'tile-grid') !== false,
        'Canvas section 1 html keeps section id' => strpos($sections[0]['html'] ?? '', 'data-section-id="section_sec_intro"') !== false,
        'Canvas section 2 marker id' => ($sections[1]['markerTileId'] ?? null) === 'sec_media',
        'Canvas section 2 marker title' => ($sections[1]['markerTitle'] ?? null) === 'Media',
        'Canvas section 2 background mode' => ($sections[1]['backgroundMode'] ?? null) === 'image',
        'Canvas section 2 tile ids stay ordered' => ($sections[1]['tileIds'] ?? []) === ['tile_media'],
        'Canvas section 2 keeps overlay flag' => ($sections[1]['overlayEnabled'] ?? false) === true,
        'Canvas section 2 overlay opacity' => ($sections[1]['overlayOpacity'] ?? null) === 40,
        'Canvas section 2 html keeps marker id' => strpos($sections[1]['html'] ?? '', 'data-section-marker-id="sec_media"') !== false,
        'Editor embeds rendered sections config' => strpos($editorHtml, 'renderedSections:') !== false,
        'Editor embedded sections JSON is parseable' => is_array($embeddedSections),
        'Editor embedded sections equal generator output' => $embeddedSections == $sections,
        'Editor keeps page sections mount point' => strpos($editorHtml, 'class="page-sections" id="tileGrid"') !== false,
    ];
} finally {
    $storage->write($originalTiles);
}

echo "=== SECTION RENDER CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    echo ($result ? '  ✅' : '  ❌') . " $name\n";
    if (!$result) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);