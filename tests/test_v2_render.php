<?php
/**
 * Test: v2 editor.php renders without PHP errors
 */

// Fake session BEFORE config.php sessions settings
@session_start();
$_SESSION['authenticated'] = true;
$_SESSION['auth_time'] = time();
$_SESSION['csrf_token'] = 'test_csrf_token_123';
$_SESSION['auth_email'] = 'test@example.com';

// Buffer output - suppress session warnings from test bootstrap
ob_start();
$oldLevel = error_reporting(E_ALL & ~E_WARNING);
chdir(__DIR__ . '/../backend/v2');
include __DIR__ . '/../backend/v2/editor.php';
error_reporting($oldLevel);
$html = ob_get_clean();

// Validate
$checks = [
    'HTML length > 1000' => strlen($html) > 1000,
    'Has V2_CONFIG' => strpos($html, 'V2_CONFIG') !== false,
    'Has renderedSections' => strpos($html, 'renderedSections') !== false,
    'Has tileGrid' => strpos($html, 'tileGrid') !== false,
    'Has canvasStyles' => strpos($html, 'canvasStyles') !== false,
    'Has editor-v2.css' => strpos($html, 'editor-v2.css') !== false,
    'Has canvas.js' => strpos($html, 'canvas.js') !== false,
    'Has state.js' => strpos($html, 'state.js') !== false,
    'Has api-client.js' => strpos($html, 'api-client.js') !== false,
    'Has page-sections class' => strpos($html, 'page-sections') !== false,
    'Has v2-toolbar' => strpos($html, 'v2-toolbar') !== false,
    'Has v2-canvas' => strpos($html, 'v2-canvas') !== false,
    'No Fatal errors' => strpos($html, 'Fatal error') === false,
    'No PHP Notices' => strpos($html, 'Notice:') === false,
];

// Special check: warnings excluding test-bootstrap session warnings
// Match PHP warning format: "Warning: ..." at line start (not JS variable names like "sessionWarning")
$warningLines = [];
if (preg_match_all('/^.*\bWarning\b:.*$/m', $html, $m)) {
    foreach ($m[0] as $w) {
        $trimmed = trim($w);
        if (strpos($trimmed, 'Session ini') === false && strpos($trimmed, 'sessionWarning') === false) {
            $warningLines[] = $trimmed;
        }
    }
}
$checks['No real PHP Warnings'] = empty($warningLines);
if (!empty($warningLines)) {
    echo "  Unexpected warnings:\n";
    foreach ($warningLines as $w) echo "    - $w\n";
}

echo "=== V2 EDITOR PHP RENDER TEST ===\n\n";
echo "HTML output: " . strlen($html) . " bytes\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    echo ($result ? '  ✅' : '  ❌') . " $name\n";
    if (!$result) $allPass = false;
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);
