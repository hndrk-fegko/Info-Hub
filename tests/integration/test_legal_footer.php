<?php
/**
 * Test: Rechtstexte werden im Footer korrekt gerendert und sanitisiert.
 */

require_once __DIR__ . '/../../backend/core/LogService.php';
require_once __DIR__ . '/../../backend/core/StorageService.php';
require_once __DIR__ . '/../../backend/core/TileService.php';
require_once __DIR__ . '/../../backend/core/SecurityHelper.php';
require_once __DIR__ . '/../../backend/core/GeneratorService.php';

$generator = new GeneratorService();

$settings = [
    'site' => [
        'footerText' => "© 2026 Testgemeinde\nAlle Rechte vorbehalten"
    ],
    'legal' => [
        'enabled' => true,
        'displayStyle' => 'subtleButtons',
        'imprint' => [
            'mode' => 'link',
            'link' => 'https://example.org/impressum',
            'text' => ''
        ],
        'privacy' => [
            'mode' => 'text',
            'link' => '',
            'text' => '<p>Datenschutz</p><script>alert(1)</script><p><a href="https://example.org/privacy">Mehr lesen</a></p>'
        ]
    ]
];

$footerHtml = $generator->renderFooterMarkup($settings);
$modalHtml = $generator->renderLegalModalMarkup($settings);
$normalizedLegal = SecurityHelper::normalizeLegalSettings($settings['legal']);
$canvasJs = $generator->getCanvasJS();

$checks = [
    'Footer exists' => strpos($footerHtml, 'site-footer') !== false,
    'Footer text keeps line breaks' => strpos($footerHtml, '<br') !== false,
    'Impressum route rendered' => strpos($footerHtml, 'href="/impressum"') !== false,
    'Impressum target stored' => strpos($footerHtml, 'data-legal-target="https://example.org/impressum"') !== false,
    'Privacy route rendered' => strpos($footerHtml, 'href="/datenschutz"') !== false,
    'Privacy route handler rendered' => strpos($footerHtml, "handleLegalRouteClick(event, 'privacy'") !== false,
    'Modal exists for text entry' => strpos($modalHtml, 'legal-modal') !== false,
    'Privacy template exists' => strpos($modalHtml, 'legal-template-privacy') !== false,
    'Link entry does not create template' => strpos($modalHtml, 'legal-template-imprint') === false,
    'Script tag removed from legal text' => strpos($normalizedLegal['privacy']['text'], '<script') === false,
    'Allowed link preserved in legal text' => strpos($normalizedLegal['privacy']['text'], 'https://example.org/privacy') !== false,
    'Route sync JS exists' => strpos($canvasJs, 'syncLegalRouteFromLocation') !== false,
    'Impressum route path in JS exists' => strpos($canvasJs, '/impressum') !== false,
    'Datenschutz route path in JS exists' => strpos($canvasJs, '/datenschutz') !== false,
];

echo "=== LEGAL FOOTER RENDER TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    echo ($result ? '  ✅' : '  ❌') . " {$name}\n";
    if (!$result) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);