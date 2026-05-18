<?php

final class RenderContract {
    public const CANVAS_SECTION_VERSION = 1;
    public const RENDERED_TILE_VERSION = 1;

    public const CANVAS_SECTION_KEYS = [
        'id',
        'html',
        'markerTileId',
        'markerTitle',
        'backgroundMode',
        'backgroundAttachment',
        'backgroundDisplay',
        'overlayEnabled',
        'overlayOpacity',
        'visible',
        'tileIds',
        'isImplicit',
    ];

    public const RENDERED_TILE_KEYS = [
        'id',
        'type',
        'html',
        'size',
        'style',
        'colorScheme',
        'position',
        'visible',
    ];

    public static function canvasSection(array $data): array {
        return self::normalize($data, self::CANVAS_SECTION_KEYS, 'canvas section');
    }

    public static function renderedTile(array $data): array {
        return self::normalize($data, self::RENDERED_TILE_KEYS, 'rendered tile');
    }

    public static function canvasSectionKeys(): array {
        return self::CANVAS_SECTION_KEYS;
    }

    public static function renderedTileKeys(): array {
        return self::RENDERED_TILE_KEYS;
    }

    private static function normalize(array $data, array $keys, string $shapeName): array {
        $missingKeys = array_values(array_diff($keys, array_keys($data)));
        if ($missingKeys !== []) {
            throw new InvalidArgumentException(
                sprintf('Missing %s contract keys: %s', $shapeName, implode(', ', $missingKeys))
            );
        }

        $normalized = [];
        foreach ($keys as $key) {
            $normalized[$key] = $data[$key];
        }

        return $normalized;
    }
}