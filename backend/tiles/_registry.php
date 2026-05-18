<?php
/**
 * TILE REGISTRY - Kompatibilitaetshuellen fuer aeltere Call Sites.
 *
 * Neue Services sollen direkt TileRegistry verwenden.
 */

require_once __DIR__ . '/../core/TileRegistry.php';

/**
 * Factory-Funktion zum Erstellen von Tile-Instanzen
 * 
 * @param string $type Tile-Typ (z.B. 'infobox')
 * @return TileBase|null Tile-Instanz oder null
 */
function createTile(string $type): ?TileBase {
    return TileRegistry::shared()->create($type);
}

/**
 * Gibt alle verfügbaren Tile-Typen zurück
 * 
 * @return array ['type' => 'ClassName', ...]
 */
function getTileTypes(): array {
    return TileRegistry::shared()->all();
}
