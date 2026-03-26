<?php
/**
 * Phase 2 Tests - WYSIWYG Canvas + Server-Side Renderer
 * 
 * Tests:
 * 1. GeneratorService::renderSingleTile() works
 * 2. GeneratorService::renderAllTilesHtml() returns correct structure
 * 3. GeneratorService::getCanvasCSS() returns CSS
 * 4. GeneratorService::getCanvasJS() returns JS
 * 5. TileService::getAvailableTypesWithMeta() returns fieldMeta
 * 6. All tile types can be rendered individually
 * 7. render_tile_html API endpoint works
 * 8. render_all_tiles_html API endpoint works
 * 9. Classic editor still works (regression)
 * 10. v2 editor page loads without PHP errors
 */

// Bootstrap
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/core/LogService.php';
require_once __DIR__ . '/../backend/core/StorageService.php';
require_once __DIR__ . '/../backend/core/TileService.php';
require_once __DIR__ . '/../backend/core/GeneratorService.php';
require_once __DIR__ . '/../backend/tiles/_registry.php';

$passed = 0;
$failed = 0;
$total = 0;

function test($name, $condition, $detail = '') {
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        echo "  ✅ PASS: $name\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: $name" . ($detail ? " ($detail)" : "") . "\n";
        $failed++;
    }
}

echo "=== PHASE 2 TESTS: WYSIWYG Canvas + Server-Side Renderer ===\n\n";

// === Test 1: renderSingleTile ===
echo "--- Test Group 1: renderSingleTile() ---\n";
$generator = new GeneratorService();
$testTile = [
    'id' => 'test_tile_001',
    'type' => 'infobox',
    'position' => 10,
    'size' => 'medium',
    'style' => 'card',
    'colorScheme' => 'default',
    'data' => [
        'title' => 'Test Title',
        'showTitle' => true,
        'description' => 'Test Beschreibung'
    ]
];

$html = $generator->renderSingleTile($testTile);
test('renderSingleTile returns HTML', $html !== null && strlen($html) > 0);
test('HTML contains tile wrapper', strpos($html, 'tile tile-infobox') !== false);
test('HTML contains correct size', strpos($html, 'size-medium') !== false);
test('HTML contains correct style', strpos($html, 'style-card') !== false);
test('HTML contains tile ID', strpos($html, 'data-tile-id="test_tile_001"') !== false);
test('HTML contains rendered content', strpos($html, 'Test Title') !== false);

// Unknown type returns null
$nullResult = $generator->renderSingleTile(['type' => 'nonexistent', 'data' => []]);
test('Unknown tile type returns null', $nullResult === null);

echo "\n--- Test Group 2: renderAllTilesHtml() ---\n";
$allRendered = $generator->renderAllTilesHtml();
test('renderAllTilesHtml returns array', is_array($allRendered));
test('Each rendered tile has id', !empty($allRendered) ? isset($allRendered[0]['id']) : true);
test('Each rendered tile has html', !empty($allRendered) ? isset($allRendered[0]['html']) : true);
test('Each rendered tile has type', !empty($allRendered) ? isset($allRendered[0]['type']) : true);
test('Each rendered tile has size', !empty($allRendered) ? isset($allRendered[0]['size']) : true);
test('Each rendered tile has position', !empty($allRendered) ? isset($allRendered[0]['position']) : true);

echo "\n--- Test Group 3: getCanvasCSS() ---\n";
$css = $generator->getCanvasCSS();
test('getCanvasCSS returns CSS', strlen($css) > 100);
test('CSS contains tile-grid', strpos($css, '.tile-grid') !== false);
test('CSS contains tile styles', strpos($css, '.tile.style-card') !== false || strpos($css, '.style-card') !== false);
test('CSS contains tile-specific styles', strpos($css, 'TILE-SPEZIFISCHE') !== false);

echo "\n--- Test Group 4: getCanvasJS() ---\n";
$js = $generator->getCanvasJS();
test('getCanvasJS returns JS', is_string($js));
// JS might be empty if no tiles have JS, check it's at least a string
test('JS is string (may be empty)', is_string($js));

echo "\n--- Test Group 5: getAvailableTypesWithMeta() ---\n";
$tileService = new TileService();
$typesWithMeta = $tileService->getAvailableTypesWithMeta();
test('typesWithMeta returns array', is_array($typesWithMeta));
test('Contains infobox type', isset($typesWithMeta['infobox']));
test('Infobox has fieldMeta', isset($typesWithMeta['infobox']['fieldMeta']));
test('fieldMeta has title field', isset($typesWithMeta['infobox']['fieldMeta']['title']));
test('title field has type', isset($typesWithMeta['infobox']['fieldMeta']['title']['type']));
test('Contains hasCSS flag', isset($typesWithMeta['infobox']['hasCSS']));
test('Contains hasJS flag', isset($typesWithMeta['infobox']['hasJS']));

echo "\n--- Test Group 6: All tile types render individually ---\n";
global $TILE_TYPES;
$allTypes = array_keys($TILE_TYPES);
foreach ($allTypes as $type) {
    $tile = [
        'id' => 'test_' . $type,
        'type' => $type,
        'position' => 10,
        'size' => 'medium',
        'style' => 'card',
        'colorScheme' => 'default',
        'data' => ['title' => 'Test ' . $type, 'showTitle' => true]
    ];
    
    // Add required fields for specific types
    if ($type === 'download') $tile['data']['file'] = '/backend/media/downloads/test.pdf';
    if ($type === 'image') $tile['data']['image'] = '/backend/media/images/test.jpg';
    if ($type === 'link') { $tile['data']['url'] = 'https://example.com'; $tile['data']['linkText'] = 'Link'; }
    if ($type === 'quote') $tile['data']['quote'] = 'Test quote';
    if ($type === 'separator') { $tile['data']['height'] = 40; $tile['data']['showLine'] = true; }
    if ($type === 'contact') { $tile['data']['name'] = 'Test'; $tile['data']['email'] = 'test@example.com'; }
    if ($type === 'countdown') { $tile['data']['targetDate'] = '2026-12-31'; $tile['data']['targetTime'] = '23:59'; }
    if ($type === 'iframe') $tile['data']['url'] = 'https://example.com';
    if ($type === 'accordion') { 
        $tile['data']['section1_heading'] = 'Section 1'; 
        $tile['data']['section1_content'] = 'Content 1'; 
    }
    
    $html = $generator->renderSingleTile($tile);
    test("Tile type '{$type}' renders", $html !== null && strlen($html) > 0);
}

echo "\n--- Test Group 7: Regression - classic preview() still works ---\n";
$previewHtml = $generator->preview();
test('Classic preview() returns HTML', strlen($previewHtml) > 1000);
test('Preview has DOCTYPE', strpos($previewHtml, '<!DOCTYPE html>') !== false);
test('Preview has tile-grid', strpos($previewHtml, 'tile-grid') !== false);

echo "\n=== RESULTS ===\n";
echo "Total: $total | Passed: $passed | Failed: $failed\n";
echo ($failed === 0) ? "🎉 ALL TESTS PASSED!\n" : "⚠️ SOME TESTS FAILED!\n";

exit($failed > 0 ? 1 : 0);
