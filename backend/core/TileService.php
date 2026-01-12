<?php
/**
 * TileService - Business Logic für Tile-Verwaltung
 * 
 * CRUD-Operationen für Tiles.
 * Nutzt StorageService für Persistenz und Tile-Registry für Validierung.
 */

require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/StorageService.php';
require_once __DIR__ . '/../tiles/_registry.php';

class TileService {
    
    private StorageService $storage;
    
    public function __construct() {
        $this->storage = new StorageService('tiles.json');
    }
    
    /**
     * Gibt alle Tiles zurück (sortiert nach Position)
     * 
     * @return array Liste aller Tiles
     */
    public function getTiles(): array {
        $tiles = $this->storage->read();
        
        // Nach Position sortieren
        usort($tiles, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        
        return $tiles;
    }
    
    /**
     * Gibt eine einzelne Tile zurück
     * 
     * @param string $id Tile-ID
     * @return array|null Tile oder null wenn nicht gefunden
     */
    public function getTile(string $id): ?array {
        $tiles = $this->storage->read();
        
        foreach ($tiles as $tile) {
            if ($tile['id'] === $id) {
                return $tile;
            }
        }
        
        return null;
    }
    
    /**
     * Speichert eine Tile (neu oder Update)
     * 
     * @param array $tileData Tile-Daten mit 'type', 'position', 'size', 'style', 'data'
     * @return array ['success' => bool, 'tile' => array] oder ['errors' => array]
     */
    public function saveTile(array $tileData): array {
        global $TILE_TYPES;
        
        LogService::info('TileService', 'Saving tile', ['id' => $tileData['id'] ?? 'new']);
        
        // 1. Pflichtfelder prüfen
        if (empty($tileData['type'])) {
            return ['success' => false, 'errors' => ['Tile-Typ erforderlich']];
        }
        
        // 2. Tile-Typ validieren
        $type = $tileData['type'];
        if (!isset($TILE_TYPES[$type])) {
            return ['success' => false, 'errors' => ["Unbekannter Tile-Typ: $type"]];
        }
        
        // 3. Tile-spezifische Validierung
        $tileClass = $TILE_TYPES[$type];
        $tileInstance = new $tileClass();
        $errors = $tileInstance->validate($tileData['data'] ?? []);
        
        if (!empty($errors)) {
            LogService::warning('TileService', 'Validation failed', ['errors' => $errors]);
            return ['success' => false, 'errors' => $errors];
        }
        
        // 4. ID generieren oder beibehalten
        $isNew = empty($tileData['id']);
        $tileData['id'] = $tileData['id'] ?? 'tile_' . time() . '_' . bin2hex(random_bytes(4));
        
        // 5. Defaults setzen
        $tileData['position'] = (int)($tileData['position'] ?? 10);
        $tileData['size'] = $tileData['size'] ?? 'medium';
        $tileData['style'] = $tileData['style'] ?? 'card';
        $tileData['updated'] = date('c');
        
        if ($isNew) {
            $tileData['created'] = date('c');
        }
        
        // 6. Speichern
        $tiles = $this->storage->read();
        
        if ($isNew) {
            $tiles[] = $tileData;
        } else {
            // Existierende Tile aktualisieren
            $found = false;
            foreach ($tiles as $i => $existing) {
                if ($existing['id'] === $tileData['id']) {
                    $tileData['created'] = $existing['created'] ?? date('c');
                    $tiles[$i] = $tileData;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $tiles[] = $tileData;
            }
        }
        
        // 7. Sortieren nach Position
        usort($tiles, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        
        // 8. Speichern
        if (!$this->storage->write($tiles)) {
            LogService::error('TileService', 'Failed to save tiles');
            return ['success' => false, 'errors' => ['Speichern fehlgeschlagen']];
        }
        
        LogService::success('TileService', 'Tile saved', ['id' => $tileData['id'], 'new' => $isNew]);
        
        return ['success' => true, 'tile' => $tileData];
    }
    
    /**
     * Löscht eine Tile
     * 
     * @param string $id Tile-ID
     * @return array ['success' => bool]
     */
    public function deleteTile(string $id): array {
        LogService::info('TileService', 'Deleting tile', ['id' => $id]);
        
        $tiles = $this->storage->read();
        $originalCount = count($tiles);
        
        $tiles = array_filter($tiles, fn($tile) => $tile['id'] !== $id);
        $tiles = array_values($tiles);  // Reindex
        
        if (count($tiles) === $originalCount) {
            return ['success' => false, 'error' => 'Tile nicht gefunden'];
        }
        
        if (!$this->storage->write($tiles)) {
            LogService::error('TileService', 'Failed to delete tile', ['id' => $id]);
            return ['success' => false, 'error' => 'Löschen fehlgeschlagen'];
        }
        
        LogService::success('TileService', 'Tile deleted', ['id' => $id]);
        
        return ['success' => true];
    }
    
    /**
     * Aktualisiert die Positionen mehrerer Tiles (für Drag & Drop)
     * 
     * @param array $positions Array von ['id' => string, 'position' => int]
     * @return array ['success' => bool]
     */
    public function updatePositions(array $positions): array {
        $tiles = $this->storage->read();
        
        foreach ($positions as $update) {
            foreach ($tiles as &$tile) {
                if ($tile['id'] === $update['id']) {
                    $tile['position'] = (int)$update['position'];
                    $tile['updated'] = date('c');
                    break;
                }
            }
        }
        
        // Sortieren
        usort($tiles, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        
        if (!$this->storage->write($tiles)) {
            return ['success' => false, 'error' => 'Speichern fehlgeschlagen'];
        }
        
        LogService::info('TileService', 'Positions updated', ['count' => count($positions)]);
        
        return ['success' => true];
    }
    
    /**
     * Erstellt ein Backup aller Tiles
     */
    public function backup(): string|false {
        return $this->storage->backup();
    }
    
    /**
     * Gibt verfügbare Tile-Typen zurück
     * 
     * @return array ['type' => ['name' => string, 'fields' => array]]
     */
    public function getAvailableTypes(): array {
        global $TILE_TYPES;
        
        $types = [];
        
        foreach ($TILE_TYPES as $type => $class) {
            $instance = new $class();
            $types[$type] = [
                'name' => $instance->getName(),
                'fields' => $instance->getFields(),
                'description' => $instance->getDescription()
            ];
        }
        
        return $types;
    }
}
