<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/core/RenderContract.php';

function renderContractCheck(string $name, bool $condition): bool {
    echo ($condition ? '  ✅ ' : '  ❌ ') . $name . "\n";
    return $condition;
}

$section = RenderContract::canvasSection([
    'id' => 'section_intro',
    'html' => '<section>Intro</section>',
    'markerTileId' => 'section_marker',
    'markerTitle' => 'Intro',
    'backgroundMode' => 'accent1',
    'backgroundAttachment' => 'content',
    'backgroundDisplay' => 'cover',
    'overlayEnabled' => true,
    'overlayOpacity' => 35,
    'visible' => true,
    'tileIds' => ['tile_a', 'tile_b'],
    'isImplicit' => false,
    'ignoredExtraKey' => 'ignored',
]);

$renderedTile = RenderContract::renderedTile([
    'id' => 'tile_a',
    'type' => 'infobox',
    'html' => '<article>Tile</article>',
    'size' => 'medium',
    'style' => 'card',
    'colorScheme' => 'default',
    'position' => 10,
    'visible' => true,
    'ignoredExtraKey' => 'ignored',
]);

$missingSectionMessage = null;
try {
    RenderContract::canvasSection([
        'id' => 'broken_section',
        'html' => '<section>Broken</section>',
    ]);
} catch (InvalidArgumentException $exception) {
    $missingSectionMessage = $exception->getMessage();
}

$checks = [
    'Canvas section keys are normalized in canonical order' => array_keys($section) === RenderContract::canvasSectionKeys(),
    'Canvas section drops extra keys' => !array_key_exists('ignoredExtraKey', $section),
    'Rendered tile keys are normalized in canonical order' => array_keys($renderedTile) === RenderContract::renderedTileKeys(),
    'Rendered tile drops extra keys' => !array_key_exists('ignoredExtraKey', $renderedTile),
    'Missing section keys raise InvalidArgumentException' => is_string($missingSectionMessage),
    'Missing section keys message names the contract' => is_string($missingSectionMessage) && strpos($missingSectionMessage, 'canvas section') !== false,
    'Missing section keys message lists missing keys' => is_string($missingSectionMessage) && strpos($missingSectionMessage, 'markerTileId') !== false,
];

echo "=== RENDER CONTRACT UNIT TEST ===\n\n";

$allPass = true;
foreach ($checks as $name => $result) {
    if (!renderContractCheck($name, $result)) {
        $allPass = false;
    }
}

echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
exit($allPass ? 0 : 1);