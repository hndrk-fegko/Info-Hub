<?php
/**
 * Phase 3 Tests - V2 module graph + legacy cleanup
 */

$root = dirname(__DIR__, 2);

echo "=== PHASE 3 TESTS ===\n\n";

$pass = 0;
$fail = 0;

function check($name, $condition) {
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] $name\n";
        $pass++;
    } else {
        echo "  [FAIL] $name\n";
        $fail++;
    }
}

echo "--- V2 module files ---\n";

$jsFiles = [
    'assets/js/v2/boot.js',
    'assets/js/v2/toast.js',
    'assets/js/v2/media-picker.js',
    'assets/js/v2/edit-modal.js',
    'assets/js/v2/drag-drop.js',
    'assets/js/v2/insert.js',
    'assets/js/v2/canvas.js',
    'assets/js/v2/state.js',
    'assets/js/v2/api-client.js',
    'assets/js/v2/settings.js',
    'assets/js/v2/context-menu.js',
];

foreach ($jsFiles as $file) {
    check("File exists: $file", file_exists($root . '/' . $file));
}

$bridgeExpectations = [
    'assets/js/v2/state.js' => 'window.V2State',
    'assets/js/v2/api-client.js' => 'window.V2Api',
    'assets/js/v2/media-picker.js' => 'window.V2MediaPicker',
    'assets/js/v2/edit-modal.js' => 'window.V2EditModal',
    'assets/js/v2/drag-drop.js' => 'window.V2DragDrop',
    'assets/js/v2/insert.js' => 'window.V2Insert',
    'assets/js/v2/settings.js' => 'window.V2Settings',
    'assets/js/v2/context-menu.js' => 'window.V2ContextMenu',
    'assets/js/v2/canvas.js' => 'window.V2 =',
];

echo "\n--- No window bridges ---\n";
foreach ($bridgeExpectations as $file => $needle) {
    $content = file_get_contents($root . '/' . $file);
    check("$file avoids $needle", strpos($content, $needle) === false);
}
check('canvas.js avoids window.V2Canvas bridge', strpos(file_get_contents($root . '/assets/js/v2/canvas.js'), 'window.V2Canvas =') === false);

echo "\n--- Canvas + boot wiring ---\n";
$canvasContent = file_get_contents($root . '/assets/js/v2/canvas.js');
$bootContent = file_get_contents($root . '/assets/js/v2/boot.js');
$settingsContent = file_get_contents($root . '/assets/js/v2/settings.js');
$editorContent = file_get_contents($root . '/backend/v2/editor.php');

check('canvas lazy-loads interactive modules', strpos($canvasContent, 'loadInteractiveModules') !== false);
check('canvas lazy-loads drag-drop', strpos($canvasContent, "import('./drag-drop.js')") !== false);
check('canvas lazy-loads insert', strpos($canvasContent, "import('./insert.js')") !== false);
check('canvas lazy-loads edit-modal', strpos($canvasContent, "import('./edit-modal.js')") !== false);
check('canvas lazy-loads context-menu', strpos($canvasContent, "import('./context-menu.js')") !== false);
check('canvas binds declarative shell click actions', strpos($canvasContent, "closest('[data-v2-action]')") !== false);
check('canvas binds declarative shell change actions', strpos($canvasContent, "closest('[data-v2-change]')") !== false);
check('canvas default export remains intact', strpos($canvasContent, 'export default V2;') !== false);
check('settings modal uses delegated action attributes', strpos($settingsContent, 'data-v2-settings-action') !== false);
check('settings modal no longer emits inline V2Settings handlers', strpos($settingsContent, 'onclick="V2Settings') === false);
check('boot script keeps diagnostics local', strpos($bootContent, 'globalThis.V2ModuleBoot') === false);
check('boot imports drag-drop module', strpos($bootContent, "importCoreModule('drag-drop')") !== false);
check('boot imports canvas module', strpos($bootContent, "importCoreModule('canvas')") !== false);
check('boot resolves module URLs against document.baseURI', strpos($bootContent, 'document.baseURI') !== false);
check('editor shell no longer links to classic editor', strpos($editorContent, 'data-legacy-classic-link') === false && strpos($editorContent, '../editor.php') === false);
check('editor shell still uses module boot entry', strpos($editorContent, 'data-v2-entry="module-boot"') !== false && strpos($editorContent, 'type="module"') !== false);
check('editor shell remains inline-handler free', strpos($editorContent, 'onclick=') === false && strpos($editorContent, 'onchange=') === false);

echo "\n--- Legacy cleanup ---\n";
$legacyEditorContent = file_get_contents($root . '/backend/editor.php');
check('legacy editor entry redirects to v2', strpos($legacyEditorContent, "header('Location: v2/editor.php')") !== false);

$legacyFiles = [
    'assets/css/editor.css',
    'assets/js/editor-core.js',
    'assets/js/editor-init.js',
    'assets/js/editor-modals.js',
    'assets/js/editor-session.js',
    'assets/js/editor-settings.js',
    'assets/js/editor-tiles.js',
];

foreach ($legacyFiles as $file) {
    check("Legacy file removed: $file", !file_exists($root . '/' . $file));
}

echo "\n--- PHP syntax ---\n";
$phpExe = 'C:\\xampp\\php\\php.exe';
foreach (['backend/editor.php', 'backend/v2/editor.php', 'backend/backup.php'] as $file) {
    $output = shell_exec("\"$phpExe\" -l \"$root/$file\" 2>&1");
    check("PHP syntax OK: $file", strpos($output, 'No syntax errors') !== false);
}

echo "\n============================\n";
echo "PHASE 3 RESULTS: $pass passed, $fail failed\n";
echo "============================\n";

exit($fail > 0 ? 1 : 0);