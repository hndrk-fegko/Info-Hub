<?php
/**
 * TILE REGISTRY - Auto-Import System
 * 
 * NEUE TILE-TYPEN HINZUFÜGEN:
 * Siehe ausführliche Anleitung in TileBase.php
 * 
 * Kurzfassung:
 * 1. XyzTile.php erstellen (von TileBase erben)
 * 2. Optional: XyzTile.css und XyzTile.js für Styling/Funktionalität
 * 3. Fertig! Auto-Import, keine manuelle Registrierung nötig.
 */

// Basis-Klasse zuerst laden
require_once __DIR__ . '/TileBase.php';

// Auto-load alle Tile-Typen (außer TileBase.php)
foreach (glob(__DIR__ . '/*Tile.php') as $tileFile) {
    $filename = basename($tileFile);
    
    // TileBase.php überspringen
    if ($filename === 'TileBase.php') {
        continue;
    }
    
    require_once $tileFile;
}

// Tile-Type Registry erstellen
$TILE_TYPES = [];

foreach (get_declared_classes() as $class) {
    if (is_subclass_of($class, 'TileBase') && !((new ReflectionClass($class))->isAbstract())) {
        // Typ-Name aus Klassennamen ableiten: InfoboxTile -> infobox
        $type = strtolower(str_replace('Tile', '', $class));
        $TILE_TYPES[$type] = $class;
    }
}

/**
 * Factory-Funktion zum Erstellen von Tile-Instanzen
 * 
 * @param string $type Tile-Typ (z.B. 'infobox')
 * @return TileBase|null Tile-Instanz oder null
 */
function createTile(string $type): ?TileBase {
    global $TILE_TYPES;
    
    if (!isset($TILE_TYPES[$type])) {
        return null;
    }
    
    $class = $TILE_TYPES[$type];
    return new $class();
}

/**
 * Gibt alle verfügbaren Tile-Typen zurück
 * 
 * @return array ['type' => 'ClassName', ...]
 */
function getTileTypes(): array {
    global $TILE_TYPES;
    return $TILE_TYPES;
}
