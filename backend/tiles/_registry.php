<?php
/**
 * TILE REGISTRY - Auto-Import System
 * 
 * NEUE TILE-TYPEN HINZUFÜGEN:
 * 1. Neue Datei erstellen: XyzTile.php
 * 2. Von TileBase erben
 * 3. Abstrakte Methoden implementieren:
 *    - getName(): Anzeigename
 *    - getDescription(): Beschreibung
 *    - getFields(): Aktive Felder definieren
 *    - validate($data): Input validieren
 *    - render($data): HTML-Output generieren
 * 4. Diese Datei lädt automatisch alle *Tile.php
 * 
 * KEINE manuelle Registrierung notwendig!
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
