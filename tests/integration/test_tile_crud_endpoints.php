<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/StorageService.php';
require_once __DIR__ . '/../../backend/core/TileService.php';
require_once dirname(__DIR__) . '/api_endpoint_test_helper.php';

function crudCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

seedAuthenticatedApiSession('admin@example.com');

$storage = new StorageService('tiles.json');
$tileService = new TileService(null, $storage);
$originalTiles = $storage->read();

$baseTiles = [
    [
        'id' => 'tile_b',
        'type' => 'infobox',
        'position' => 20,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'data' => [
            'title' => 'Tile B',
            'showTitle' => true,
            'description' => 'Middle tile',
        ],
    ],
    [
        'id' => 'tile_a',
        'type' => 'infobox',
        'position' => 10,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'data' => [
            'title' => 'Tile A',
            'showTitle' => true,
            'description' => 'First tile',
        ],
    ],
    [
        'id' => 'tile_c',
        'type' => 'quote',
        'position' => 30,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'data' => [
            'title' => 'Tile C',
            'quote' => 'Third tile',
        ],
    ],
];

$newTile = [
    'id' => 'tile_contract_new',
    'type' => 'infobox',
    'position' => 40,
    'size' => 'medium',
    'style' => 'card',
    'colorScheme' => 'default',
    'data' => [
        'title' => 'Contract Tile',
        'showTitle' => true,
        'description' => 'Created through endpoint contract test',
    ],
];

$checks = [];

try {
    if (!$storage->write($baseTiles)) {
        throw new RuntimeException('Konnte CRUD-Testtiles nicht schreiben');
    }

    $getTilesResponse = runApiRequest(['action' => 'get_tiles'], [], [], 'GET');
    $expectedTiles = $tileService->getTiles();

    $checks['get_tiles returns valid JSON'] = is_array($getTilesResponse['json']);
    $checks['get_tiles returns success true'] = ($getTilesResponse['json']['success'] ?? false) === true;
    $checks['get_tiles matches TileService ordering'] = ($getTilesResponse['json']['tiles'] ?? null) == $expectedTiles;

    $getTileResponse = runApiRequest(['action' => 'get_tile', 'id' => 'tile_b'], [], [], 'GET');
    $checks['get_tile returns success true'] = ($getTileResponse['json']['success'] ?? false) === true;
    $checks['get_tile matches TileService item'] = ($getTileResponse['json']['tile'] ?? null) == $tileService->getTile('tile_b');

    $saveResponse = runApiRequest(
        [],
        [
            'action' => 'save_tile',
            'csrf_token' => 'test_csrf_token_123',
            'tile' => json_encode($newTile),
        ],
        [],
        'POST'
    );
    $savedTile = $tileService->getTile('tile_contract_new');
    $tilesAfterSave = $tileService->getTiles();

    $checks['save_tile returns success true'] = ($saveResponse['json']['success'] ?? false) === true;
    $checks['save_tile returns persisted tile payload'] = ($saveResponse['json']['tile'] ?? null) == $savedTile;
    $checks['save_tile returns synced tile list'] = ($saveResponse['json']['tiles'] ?? null) == $tilesAfterSave;

    $updateResponse = runApiRequest(
        [],
        [
            'action' => 'update_positions',
            'csrf_token' => 'test_csrf_token_123',
            'positions' => json_encode([
                ['id' => 'tile_contract_new', 'position' => 5],
                ['id' => 'tile_a', 'position' => 15],
                ['id' => 'tile_b', 'position' => 25],
                ['id' => 'tile_c', 'position' => 35],
            ]),
        ],
        [],
        'POST'
    );
    $orderedTileIds = array_column($tileService->getTiles(), 'id');

    $checks['update_positions returns success true'] = ($updateResponse['json']['success'] ?? false) === true;
    $checks['update_positions reorders tiles through TileService'] = $orderedTileIds === ['tile_contract_new', 'tile_a', 'tile_b', 'tile_c'];

    $deleteResponse = runApiRequest(
        [],
        [
            'action' => 'delete_tile',
            'csrf_token' => 'test_csrf_token_123',
            'id' => 'tile_contract_new',
        ],
        [],
        'POST'
    );

    $checks['delete_tile returns success true'] = ($deleteResponse['json']['success'] ?? false) === true;
    $checks['delete_tile removes tile from TileService'] = $tileService->getTile('tile_contract_new') === null;
    $checks['delete_tile restores original tile count'] = count($tileService->getTiles()) === count($baseTiles);
} finally {
    $storage->write($originalTiles);
}

echo "=== TILE CRUD ENDPOINT CONTRACT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!crudCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);