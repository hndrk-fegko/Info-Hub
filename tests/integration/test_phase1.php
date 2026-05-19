<?php
/**
 * Phase 1 Test: Prüft ob GeneratorService mit Shared CSS korrekt arbeitet
 */
chdir(__DIR__ . '/../../');

require_once __DIR__ . '/../../backend/core/GeneratorService.php';

$g = new GeneratorService();
$html = $g->preview();

$tests = [
    'HTML_LENGTH' => strlen($html) > 1000,
    'HAS_DOCTYPE' => strpos($html, '<!DOCTYPE html>') !== false,
    'HAS_GRID' => strpos($html, 'tile-grid') !== false,
    'HAS_ROOT_VARS' => strpos($html, '--bg-color') !== false,
    'HAS_TEXT_COLOR_VAR' => strpos($html, '--text-color') !== false,
    'HAS_GRID_CSS' => strpos($html, 'grid-template-columns') !== false,
    'HAS_TILE_BASE_CSS' => strpos($html, '.tile.style-card') !== false,
    'HAS_HEADER_CSS' => strpos($html, '.site-header') !== false,
    'HAS_FOOTER_CSS' => strpos($html, '.site-footer') !== false,
    'HAS_TILE_SPECIFIC_CSS' => strpos($html, 'AccordionTile') !== false,
    'CSS_FILES_LOADED' => strpos($html, '/* variables.css */') !== false,
    'HAS_LIGHTBOX' => strpos($html, 'lightbox') !== false,
    'HAS_PULL_REFRESH_MARKUP' => strpos($html, 'pullRefreshIndicator') !== false,
    'HAS_PULL_REFRESH_INIT' => strpos($html, 'initPullToRefresh') !== false,
    'HAS_RESPONSIVE' => strpos($html, '@media (max-width: 600px)') !== false,
    'NO_DUPLICATE_RESET' => substr_count($html, 'box-sizing: border-box') <= 2,
];

$allPassed = true;
foreach ($tests as $name => $result) {
    $status = $result ? 'PASS' : 'FAIL';
    if (!$result) $allPassed = false;
    echo "$status: $name\n";
}

echo "\n" . ($allPassed ? 'ALL TESTS PASSED' : 'SOME TESTS FAILED') . "\n";
echo "HTML length: " . strlen($html) . " bytes\n";
