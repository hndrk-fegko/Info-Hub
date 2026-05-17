<?php
/**
 * Quick test: narrow layout generation
 */
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/core/StorageService.php';
require_once __DIR__ . '/../backend/core/TileService.php';
require_once __DIR__ . '/../backend/core/LogService.php';
require_once __DIR__ . '/../backend/core/GeneratorService.php';
require_once __DIR__ . '/../backend/tiles/_registry.php';

// Enable narrow layout temporarily
$ss = new StorageService('settings.json');
$s = $ss->read();
$origNarrow = $s['theme']['narrowLayout'] ?? false;
$origNarrowWidth = $s['theme']['narrowWidth'] ?? 960;
$s['theme']['narrowLayout'] = true;
$s['theme']['narrowWidth'] = 960;
$ss->write($s);

// Generate 
$g = new GeneratorService();
$r = $g->generate();
$html = file_get_contents(__DIR__ . '/../index.html');

$tests = [
    'NARROW_CSS' => strpos($html, 'Narrow Layout') !== false,
    'MAX_WIDTH' => strpos($html, 'max-width: 960px') !== false,
    'DARK_BG' => strpos($html, '#1a1a2e') !== false,
    'NARROW_BLUR_VAR' => strpos($html, '--narrow-background-blur:') !== false,
    'NARROW_SCALE_VAR' => strpos($html, '--narrow-background-scale:') !== false,
];

foreach ($tests as $name => $pass) {
    echo ($pass ? 'PASS' : 'FAIL') . ": $name\n";
}

// Restore: explicitly remove narrowLayout 
$s2 = $ss->read();
unset($s2['theme']['narrowLayout']);
$s2['theme']['narrowWidth'] = $origNarrowWidth;
$ss->write($s2);

// Re-generate with original settings
$g2 = new GeneratorService();
$g2->generate();

$html2 = file_get_contents(__DIR__ . '/../index.html');
$noNarrow = strpos($html2, 'Narrow Layout') === false;
echo ($noNarrow ? 'PASS' : 'FAIL') . ": NO_NARROW_WHEN_DISABLED\n";

// Summary
$allPass = array_reduce(array_values($tests), function($carry, $item) { return $carry && $item; }, true) && $noNarrow;
echo $allPass ? "\n🎉 ALL NARROW TESTS PASSED\n" : "\n❌ SOME TESTS FAILED\n";
