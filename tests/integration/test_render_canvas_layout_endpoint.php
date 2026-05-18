<?php
/**
 * Test: render_canvas_layout endpoint matches GeneratorService::renderCanvasSections().
 */

@session_start();
$_SESSION['authenticated'] = true;
$_SESSION['auth_time'] = time();
$_SESSION['csrf_token'] = 'test_csrf_token_123';
$_SESSION['auth_email'] = 'test@example.com';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/RenderContract.php';
require_once __DIR__ . '/../../backend/core/StorageService.php';
require_once __DIR__ . '/../../backend/core/TileService.php';
require_once __DIR__ . '/../../backend/core/GeneratorService.php';
require_once __DIR__ . '/../../backend/tiles/_registry.php';

$storage = new StorageService('tiles.json');
$originalTiles = $storage->read();
$originalGet = $_GET ?? [];
$originalPost = $_POST ?? [];
$originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
$originalHttpHost = $_SERVER['HTTP_HOST'] ?? null;
$originalServerName = $_SERVER['SERVER_NAME'] ?? null;

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
            'description' => 'Endpoint contract test',
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
            'quote' => 'API endpoint contract',
        ],
    ],
];

$checks = [];

try {
    if (!$storage->write($testTiles)) {
        throw new RuntimeException('Konnte Test-Tiles nicht schreiben');
    }

    $generator = new GeneratorService();
    $expectedSections = $generator->renderCanvasSections();

    $_GET = ['action' => 'render_canvas_layout'];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start();
    $oldLevel = error_reporting(E_ALL & ~E_WARNING);
    include __DIR__ . '/../../backend/api/endpoints.php';
    error_reporting($oldLevel);
    $output = ob_get_clean();
    restore_error_handler();

    $response = json_decode($output, true);
    $status = http_response_code();

    $checks = [
        'Endpoint returns valid JSON' => is_array($response),
        'Endpoint returns success true' => ($response['success'] ?? false) === true,
        'Endpoint returns contract version' => ($response['contractVersion'] ?? null) === RenderContract::CANVAS_SECTION_VERSION,
        'Endpoint returns sections array' => is_array($response['sections'] ?? null),
        'Endpoint returns HTTP 200' => $status === 200,
        'Endpoint section count matches generator' => count($response['sections'] ?? []) === count($expectedSections),
        'Endpoint sections equal generator output' => ($response['sections'] ?? []) == $expectedSections,
        'Endpoint section 1 exposes full contract keys' => empty(array_diff(RenderContract::canvasSectionKeys(), array_keys($response['sections'][0] ?? []))),
        'Endpoint section 1 marker id' => (($response['sections'][0]['markerTileId'] ?? null) === 'sec_intro'),
        'Endpoint section 2 background mode' => (($response['sections'][1]['backgroundMode'] ?? null) === 'image'),
        'Endpoint section 2 tile ids' => (($response['sections'][1]['tileIds'] ?? []) === ['tile_media']),
    ];
} finally {
    $storage->write($originalTiles);
    $_GET = $originalGet;
    $_POST = $originalPost;
    if ($originalRequestMethod === null) {
        unset($_SERVER['REQUEST_METHOD']);
    } else {
        $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
    }
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
    restore_error_handler();
}

echo "=== RENDER CANVAS LAYOUT ENDPOINT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    echo ($result ? '  ✅' : '  ❌') . " $name\n";
    if (!$result) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);