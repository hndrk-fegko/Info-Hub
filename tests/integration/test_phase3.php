<?php
/**
 * Phase 3 Tests - Drag & Drop + Insert + CSS
 * 
 * Prüft:
 * - JS-Dateien vorhanden und syntaktisch korrekt (keine PHP-Fehler, Grundstruktur)
 * - CSS-Klassen für DnD und Insert in editor-v2.css
 * - editor.php Module-Liste enthält drag-drop und insert
 * - canvas.js init ruft DragDrop und Insert auf
 * - Kein window.V2 regression break
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

// ---- 1. JS Files exist ----
echo "--- JS Files ---\n";

$jsFiles = [
    'assets/js/v2/boot.js',
    'assets/js/v2/media-picker.js',
    'assets/js/v2/edit-modal.js',
    'assets/js/v2/drag-drop.js',
    'assets/js/v2/insert.js',
    'assets/js/v2/canvas.js',
    'assets/js/v2/state.js',
    'assets/js/v2/api-client.js',
];

foreach ($jsFiles as $f) {
    check("File exists: $f", file_exists($root . '/' . $f));
}

// ---- 2. drag-drop.js structure ----
echo "\n--- drag-drop.js ---\n";

$ddContent = file_get_contents($root . '/assets/js/v2/drag-drop.js');
check("DragDrop IIFE wrapper", strpos($ddContent, 'window.V2DragDrop') !== false);
check("DragDrop init function", strpos($ddContent, 'function init()') !== false);
check("DragDrop imports state module", strpos($ddContent, "import V2State from './state.js';") !== false);
check("DragDrop imports api-client module", strpos($ddContent, "import V2Api from './api-client.js';") !== false);
check("DragDrop uses HTML5 dragstart", strpos($ddContent, 'dragstart') !== false);
check("DragDrop uses dragover", strpos($ddContent, 'dragover') !== false);
check("DragDrop uses drop event", strpos($ddContent, "'drop'") !== false || strpos($ddContent, '"drop"') !== false);
check("DragDrop has reorderToIndex", strpos($ddContent, 'reorderToIndex') !== false);
check("DragDrop calls V2Api.updatePositions", strpos($ddContent, 'V2Api.updatePositions') !== false);
check("DragDrop has v2-dragging class", strpos($ddContent, 'v2-dragging') !== false);
check("DragDrop uses insert gaps as drop zones", strpos($ddContent, 'V2Insert.findNearestGap') !== false);
check("DragDrop setEnabled export", strpos($ddContent, 'setEnabled') !== false);
check("DragDrop returns init", preg_match('/return\s*\{[^}]*init/', $ddContent) === 1);
check("DragDrop event delegation (no per-tile listeners)", strpos($ddContent, '_boundGrid') !== false);
check("DragDrop closestWrapper for delegation", strpos($ddContent, 'closestWrapper') !== false);
check("DragDrop hides insert popup during drag", strpos($ddContent, 'V2Insert.hideTypePopup') !== false);
check("DragDrop sets drop mode on insert", strpos($ddContent, 'V2Insert.setDropMode') !== false);
check("DragDrop auto-scroll on viewport edge", strpos($ddContent, 'handleAutoScroll') !== false);
check("DragDrop SCROLL_ZONE constant", strpos($ddContent, 'SCROLL_ZONE') !== false);
check("DragDrop stopAutoScroll on dragend", strpos($ddContent, 'stopAutoScroll') !== false);
check("DragDrop exports default module", strpos($ddContent, 'export default V2DragDrop;') !== false);

// ---- 3. insert.js structure ----
echo "\n--- insert.js ---\n";

$insContent = file_get_contents($root . '/assets/js/v2/insert.js');
check("Insert IIFE wrapper", strpos($insContent, 'window.V2Insert') !== false);
check("Insert init function", strpos($insContent, 'function init()') !== false);
check("Insert imports state module", strpos($insContent, "import V2State from './state.js';") !== false);
check("Insert imports api-client module", strpos($insContent, "import V2Api from './api-client.js';") !== false);
check("Insert createTypePopup", strpos($insContent, 'createTypePopup') !== false);
check("Insert showTypePopup (exported)", strpos($insContent, 'showTypePopup') !== false);
check("Insert insertTileAtPosition", strpos($insContent, 'insertTileAtPosition') !== false);
check("Insert has type icons", strpos($insContent, "typeIcons") !== false);
check("Insert floating indicator (not grid-child)", strpos($insContent, 'v2-insert-indicator') !== false);
check("Insert NO grid-column in code (no layout break)", preg_match("/grid-column(?!.*zerstörten)/", $insContent) === 0);
check("Insert NO injectInsertButtons (old approach)", strpos($insContent, 'injectInsertButtons') === false);
check("Insert computeGaps for h+v position", strpos($insContent, 'computeGaps') !== false);
check("Insert mousemove tracking", strpos($insContent, 'mousemove') !== false);
check("Insert v2-type-popup class", strpos($insContent, 'v2-type-popup') !== false);
check("Insert position calc (midpoint)", strpos($insContent, 'Math.round') !== false);
check("Insert calls V2Api.saveTile", strpos($insContent, 'V2Api.saveTile') !== false);
check("Insert type defaults (separator)", strpos($insContent, "separator") !== false && strpos($insContent, 'showLine') !== false);
check("Insert returns showTypePopup", preg_match('/return\s*\{[^}]*showTypePopup/', $insContent) === 1);
check("Insert invalidateCache export", strpos($insContent, 'invalidateCache') !== false);
check("Insert setDropMode export", strpos($insContent, 'setDropMode') !== false);
check("Insert findNearestGap export", strpos($insContent, 'findNearestGap') !== false);
check("Insert getGaps export", strpos($insContent, 'getGaps') !== false);
check("Insert showIndicator export", preg_match('/return\s*\{[^}]*showIndicator/', $insContent) === 1);
check("Insert hideIndicator export", preg_match('/return\s*\{[^}]*hideIndicator/', $insContent) === 1);
check("Insert exports default module", strpos($insContent, 'export default V2Insert;') !== false);

// ---- 4. CSS classes ----
echo "\n--- CSS for DnD + Insert ---\n";

$cssContent = file_get_contents($root . '/assets/css/editor-v2.css');

$requiredCSS = [
    '.v2-dragging' => 'Drag state opacity',
    '.v2-insert-drop-mode' => 'Drop mode for insert indicator',
    '.v2-insert-indicator' => 'Floating insert indicator',
    '.v2-insert-line' => 'Insert indicator line',
    '.v2-insert-trigger' => 'Insert trigger button',
    '.v2-type-popup' => 'Type popup container',
    '.v2-type-popup-header' => 'Type popup header',
    '.v2-type-popup-grid' => 'Type popup grid',
    '.v2-type-option' => 'Type option button',
    '.v2-type-icon' => 'Type option icon',
    '.v2-type-name' => 'Type option name',
];

foreach ($requiredCSS as $class => $desc) {
    check("CSS: $class ($desc)", strpos($cssContent, $class) !== false);
}

// Verify NO grid-breaking insert-btn in CSS 
check("CSS: NO .v2-insert-btn (old grid child)", strpos($cssContent, '.v2-insert-btn') === false);
check("CSS: insert-indicator is position:absolute", 
    preg_match('/\.v2-insert-indicator\s*\{[^}]*position:\s*absolute/', $cssContent) === 1);

// ---- 5. editor.php integration ----
echo "\n--- editor.php integration ---\n";

$editorContent = file_get_contents($root . '/backend/v2/editor.php');
check("editor.php includes drag-drop module", strpos($editorContent, "'drag-drop'") !== false || strpos($editorContent, '"drag-drop"') !== false);
check("editor.php includes insert module", strpos($editorContent, "'insert'") !== false || strpos($editorContent, '"insert"') !== false);
check("editor.php module order (state first)", preg_match("/'state'.*'api-client'.*'canvas'.*'drag-drop'.*'insert'/s", $editorContent) === 1);
check("editor.php exposes v2ScriptUrls config", strpos($editorContent, 'v2ScriptUrls') !== false);
check("editor.php uses module boot entry", strpos($editorContent, 'data-v2-entry="module-boot"') !== false && strpos($editorContent, 'type="module"') !== false);
check("editor.php has no inline V2 shell handlers", strpos($editorContent, 'onclick=') === false && strpos($editorContent, 'onchange=') === false);
check("editor.php uses declarative V2 action hooks", strpos($editorContent, 'data-v2-action=') !== false && strpos($editorContent, 'data-v2-change=') !== false);

// ---- 6. canvas.js integration ----
echo "\n--- canvas.js integration ---\n";

$canvasContent = file_get_contents($root . '/assets/js/v2/canvas.js');
check("canvas.js V2.init calls V2DragDrop.init()", strpos($canvasContent, 'V2DragDrop.init()') !== false);
check("canvas.js V2.init calls V2Insert.init()", strpos($canvasContent, 'V2Insert.init()') !== false);
check("canvas.js checks V2DragDrop existence", strpos($canvasContent, "typeof V2DragDrop !== 'undefined'") !== false);
check("canvas.js checks V2Insert existence", strpos($canvasContent, "typeof V2Insert !== 'undefined'") !== false);
check("canvas.js supports late auto-init", strpos($canvasContent, 'autoInitV2WhenReady') !== false && strpos($canvasContent, "document.readyState === 'loading'") !== false);
check("canvas.js imports state module", strpos($canvasContent, "import V2State from './state.js';") !== false);
check("canvas.js imports api-client module", strpos($canvasContent, "import V2Api from './api-client.js';") !== false);
check("canvas.js delegates V2 shell click actions", strpos($canvasContent, 'handleShellActionClick') !== false && strpos($canvasContent, "closest('[data-v2-action]')") !== false);
check("canvas.js delegates V2 shell change actions", strpos($canvasContent, 'handleShellActionChange') !== false && strpos($canvasContent, "closest('[data-v2-change]')") !== false);
check("canvas.js empty state no longer uses inline onclick", strpos($canvasContent, 'onclick="V2.addTile()"') === false && strpos($canvasContent, 'data-v2-action="addTile"') !== false);

// ---- 7. addTile uses popup ----
echo "\n--- addTile popup integration ---\n";
check("addTile uses V2Insert.showTypePopup()", strpos($canvasContent, 'V2Insert.showTypePopup') !== false);
check("addTile has prompt fallback", strpos($canvasContent, "prompt('Tile-Typ wählen") !== false);
check("addTile NO direct popup DOM manipulation", strpos($canvasContent, "popup.style.display = 'block'") === false);

// ---- 8. No regressions in existing modules ----
echo "\n--- No regressions ---\n";
check("V2 main module still exists", strpos($canvasContent, 'window.V2 =') !== false);
check("V2Canvas still exists", strpos($canvasContent, 'window.V2Canvas =') !== false);
check("V2.toast still exported", strpos($canvasContent, 'toast') !== false);
check("V2.publish still exported", strpos($canvasContent, 'publish') !== false);
check("V2Canvas.reloadAll still exported", strpos($canvasContent, 'reloadAll') !== false);
check("V2Canvas.refreshTile still exported", strpos($canvasContent, 'refreshTile') !== false);
check("canvas.js exports default module entry", strpos($canvasContent, 'export default V2;') !== false);

// Check state.js hasn't been broken
$stateContent = file_get_contents($root . '/assets/js/v2/state.js');
check("State module intact", strpos($stateContent, 'window.V2State') !== false);
check("State events intact", strpos($stateContent, 'state:tiles-changed') !== false);
check("State exports default for module boot", strpos($stateContent, 'export default V2State;') !== false);

// Check api-client.js hasn't been broken
$apiContent = file_get_contents($root . '/assets/js/v2/api-client.js');
check("API client intact", strpos($apiContent, 'window.V2Api') !== false);
check("API updatePositions intact", strpos($apiContent, 'updatePositions') !== false);
check("API client exports default for module boot", strpos($apiContent, 'export default V2Api;') !== false);

// Check boot.js wiring
$bootContent = file_get_contents($root . '/assets/js/v2/boot.js');
check("Boot script imports state module dynamically", strpos($bootContent, "importCoreModule('state')") !== false);
check("Boot script imports api-client module dynamically", strpos($bootContent, "importCoreModule('api-client')") !== false);
check("Boot script imports media-picker module dynamically", strpos($bootContent, "importCoreModule('media-picker')") !== false);
check("Boot script imports insert module dynamically", strpos($bootContent, "importCoreModule('insert')") !== false);
check("Boot script imports drag-drop module dynamically", strpos($bootContent, "importCoreModule('drag-drop')") !== false);
check("Boot script imports edit-modal module dynamically", strpos($bootContent, "importCoreModule('edit-modal')") !== false);
check("Boot script imports settings module dynamically", strpos($bootContent, "importCoreModule('settings')") !== false);
check("Boot script imports context-menu module dynamically", strpos($bootContent, "importCoreModule('context-menu')") !== false);
check("Boot script imports canvas module dynamically", strpos($bootContent, "importCoreModule('canvas')") !== false);
check("Boot script no longer depends on legacy module config", strpos($bootContent, 'v2LegacyModules') === false);
check("Boot script resolves page-relative URLs", strpos($bootContent, 'document.baseURI') !== false);

// ---- 9. API endpoint for update_positions ----
echo "\n--- API endpoints ---\n";
$endpointsContent = file_get_contents($root . '/backend/api/endpoints.php');
check("API has update_positions endpoint", strpos($endpointsContent, 'update_positions') !== false);

// ---- 10. Edit Modal ----
echo "\n--- Edit Modal ---\n";
$mediaPickerPath = $root . '/assets/js/v2/media-picker.js';
check("File exists: assets/js/v2/media-picker.js", file_exists($mediaPickerPath));
$editModalPath = $root . '/assets/js/v2/edit-modal.js';
check("File exists: assets/js/v2/edit-modal.js", file_exists($editModalPath));
$settingsPath = $root . '/assets/js/v2/settings.js';
check("File exists: assets/js/v2/settings.js", file_exists($settingsPath));
$contextMenuPath = $root . '/assets/js/v2/context-menu.js';
check("File exists: assets/js/v2/context-menu.js", file_exists($contextMenuPath));

$classicModalPath = $root . '/assets/js/editor-modals.js';
check("File exists: assets/js/editor-modals.js", file_exists($classicModalPath));

$mediaPickerContent = file_get_contents($mediaPickerPath);
$editModalContent = file_get_contents($editModalPath);
$settingsContent = file_get_contents($settingsPath);
$contextMenuContent = file_get_contents($contextMenuPath);
$classicModalContent = file_get_contents($classicModalPath);
check("MediaPicker imports api-client module", strpos($mediaPickerContent, "import V2Api from './api-client.js';") !== false);
check("MediaPicker exports default module", strpos($mediaPickerContent, 'export default V2MediaPicker;') !== false);
check("MediaPicker keeps window bridge", strpos($mediaPickerContent, 'window.V2MediaPicker = V2MediaPicker;') !== false);
check("EditModal IIFE wrapper", strpos($editModalContent, 'window.V2EditModal') !== false);
check("EditModal init function", strpos($editModalContent, 'function init()') !== false);
check("EditModal open function", strpos($editModalContent, 'function open(') !== false);
check("EditModal close function", strpos($editModalContent, 'function close()') !== false);
check("EditModal imports state module", strpos($editModalContent, "import V2State from './state.js';") !== false);
check("EditModal imports api-client module", strpos($editModalContent, "import V2Api from './api-client.js';") !== false);
check("EditModal imports media-picker module", strpos($editModalContent, "import V2MediaPicker from './media-picker.js';") !== false);
check("EditModal builds fields from fieldMeta", strpos($editModalContent, 'fieldMeta') !== false);
check("EditModal handles checkbox type", strpos($editModalContent, '_buildCheckbox') !== false);
check("EditModal handles textarea type", strpos($editModalContent, '_buildTextarea') !== false);
check("EditModal handles select type", strpos($editModalContent, '_buildSelect') !== false);
check("EditModal handles image upload", strpos($editModalContent, '_buildImageUpload') !== false);
check("EditModal handles file upload", strpos($editModalContent, '_buildFileUpload') !== false);
check("EditModal calls V2Api.saveTile", strpos($editModalContent, 'V2Api.saveTile') !== false);
check("EditModal supports section groups (accordion)", strpos($editModalContent, '_buildSectionFields') !== false);
check("EditModal has overlay close on backdrop click", strpos($editModalContent, 'e.target === _overlay') !== false);
check("EditModal exports default module", strpos($editModalContent, 'export default V2EditModal;') !== false);
check("EditModal keeps window bridge", strpos($editModalContent, 'window.V2EditModal = V2EditModal;') !== false);
check("Classic modal reads typeInfo.fieldMeta", strpos($classicModalContent, 'typeInfo?.fieldMeta?.[fieldName]') !== false);
check("Settings imports state module", strpos($settingsContent, "import V2State from './state.js';") !== false);
check("Settings imports api-client module", strpos($settingsContent, "import V2Api from './api-client.js';") !== false);
check("Settings imports media-picker module", strpos($settingsContent, "import V2MediaPicker from './media-picker.js';") !== false);
check("Settings exports default module", strpos($settingsContent, 'export default V2Settings;') !== false);
check("Settings keeps window bridge", strpos($settingsContent, 'window.V2Settings = V2Settings;') !== false);
check("ContextMenu imports state module", strpos($contextMenuContent, "import V2State from './state.js';") !== false);
check("ContextMenu imports api-client module", strpos($contextMenuContent, "import V2Api from './api-client.js';") !== false);
check("ContextMenu exports default module", strpos($contextMenuContent, 'export default V2ContextMenu;') !== false);
check("ContextMenu keeps window bridge", strpos($contextMenuContent, 'window.V2ContextMenu = V2ContextMenu;') !== false);

// ---- 11. Type-aware Toolbar ----
echo "\n--- Type-aware Toolbar ---\n";
check("Toolbar has layout group attributes", strpos($editorContent, 'data-tb-group="layout"') !== false);
check("Toolbar has color group attributes", strpos($editorContent, 'data-tb-group="color"') !== false);
check("canvas.js has _noLayoutTypes", strpos($canvasContent, '_noLayoutTypes') !== false);
check("canvas.js has _noColorTypes", strpos($canvasContent, '_noColorTypes') !== false);
check("canvas.js separator in noColor list", strpos($canvasContent, "const _noColorTypes = ['separator']") !== false);
check("canvas.js toggles layout group", strpos($canvasContent, '[data-tb-group="layout"]') !== false);
check("canvas.js toggles color group", strpos($canvasContent, '[data-tb-group="color"]') !== false);

// ---- 12. API uploadDownload ----
echo "\n--- API uploadDownload ---\n";
check("API client has uploadDownload", strpos($apiContent, 'uploadDownload') !== false);
check("API client exports uploadDownload", strpos($apiContent, 'uploadDownload') !== false);

// ---- 13. canvas.js editSelectedTile uses modal ----
echo "\n--- Edit integration ---\n";
check("editSelectedTile calls V2EditModal.open", strpos($canvasContent, 'V2EditModal.open') !== false);
check("editSelectedTile has prompt fallback", strpos($canvasContent, 'prompt(') !== false);
check("V2.init calls V2EditModal.init", strpos($canvasContent, 'V2EditModal.init()') !== false);
check("editor.php includes edit-modal module", strpos($editorContent, "'edit-modal'") !== false);
check("Document click ignores modal overlay", strpos($canvasContent, 'v2-modal-overlay') !== false);

// ---- 14. CSS for Edit Modal ----
echo "\n--- CSS for Edit Modal ---\n";
check("CSS: .v2-modal-overlay", strpos($cssContent, '.v2-modal-overlay') !== false);
check("CSS: .v2-modal", strpos($cssContent, '.v2-modal {') !== false || strpos($cssContent, '.v2-modal{') !== false);
check("CSS: .v2-modal-header", strpos($cssContent, '.v2-modal-header') !== false);
check("CSS: .v2-modal-body", strpos($cssContent, '.v2-modal-body') !== false);
check("CSS: .v2-modal-footer", strpos($cssContent, '.v2-modal-footer') !== false);
check("CSS: .v2-field-input", strpos($cssContent, '.v2-field-input') !== false);
check("CSS: .v2-field-checkbox", strpos($cssContent, '.v2-field-checkbox') !== false);
check("CSS: .v2-upload-preview", strpos($cssContent, '.v2-upload-preview') !== false);
check("CSS: .v2-modal-fieldset", strpos($cssContent, '.v2-modal-fieldset') !== false);
check("CSS: .v2-modal-section (accordion groups)", strpos($cssContent, '.v2-modal-section') !== false);

// ---- 15. Toolbar repositioning fix ----
echo "\n--- Toolbar position fix ---\n";
check("swapPosition deselects before re-select", strpos($canvasContent, 'deselectAll()') !== false && strpos($canvasContent, 'requestAnimationFrame') !== false);

echo "\n--- PHP Syntax Check ---\n";
$phpExe = 'C:\\xampp\\php\\php.exe';
$phpFiles = [
    'backend/v2/editor.php',
    'backend/api/endpoints.php',
    'backend/core/GeneratorService.php',
    'backend/core/TileService.php',
];

foreach ($phpFiles as $f) {
    $output = shell_exec("\"$phpExe\" -l \"$root/$f\" 2>&1");
    check("PHP syntax OK: $f", strpos($output, 'No syntax errors') !== false);
}

// ---- Summary ----
echo "\n============================\n";
echo "PHASE 3 RESULTS: $pass passed, $fail failed\n";
echo "============================\n";

exit($fail > 0 ? 1 : 0);
