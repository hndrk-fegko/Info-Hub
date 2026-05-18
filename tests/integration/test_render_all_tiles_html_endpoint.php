<?php
/**
 * Test: render_all_tiles_html endpoint matches GeneratorService::renderAllTilesHtml().
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
        'id' => 'tile_intro',
        'type' => 'infobox',
        'position' => 10,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'visible' => true,
        'data' => [
            'title' => 'Tile Intro',
            'showTitle' => true,
            'description' => 'Endpoint tile render contract',
        ],
    ],
    [
        'id' => 'tile_quote',
        'type' => 'quote',
        'position' => 20,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'visible' => false,
        'data' => [
            'title' => 'Zitat',
            'quote' => 'Render all tiles endpoint contract',
        ],
    ],
];

$checks = [];

try {
    if (!$storage->write($testTiles)) {
        throw new RuntimeException('Konnte Test-Tiles nicht schreiben');
    }

    $generator = new GeneratorService();
    $expectedTiles = $generator->renderAllTilesHtml();

    $_GET = ['action' => 'render_all_tiles_html'];
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
        'Endpoint returns contract version' => ($response['contractVersion'] ?? null) === RenderContract::RENDERED_TILE_VERSION,
        'Endpoint returns tiles array' => is_array($response['tiles'] ?? null),
        'Endpoint returns HTTP 200' => $status === 200,
        'Endpoint tile count matches generator' => count($response['tiles'] ?? []) === count($expectedTiles),
        'Endpoint tiles equal generator output' => ($response['tiles'] ?? []) == $expectedTiles,
        'Endpoint tile 1 exposes full contract keys' => empty(array_diff(RenderContract::renderedTileKeys(), array_keys($response['tiles'][0] ?? []))),
        'Endpoint tile 1 id' => (($response['tiles'][0]['id'] ?? null) === 'tile_intro'),
        'Endpoint tile 1 html contains wrapper' => strpos($response['tiles'][0]['html'] ?? '', 'data-tile-id="tile_intro"') !== false,
        'Endpoint tile 2 visible flag preserved' => (($response['tiles'][1]['visible'] ?? null) === false),
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

echo "=== RENDER ALL TILES HTML ENDPOINT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    echo ($result ? '  ✅' : '  ❌') . " $name\n";
    if (!$result) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);