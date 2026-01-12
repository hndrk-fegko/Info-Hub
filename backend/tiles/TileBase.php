<?php
/**
 * TileBase - Abstrakte Basis-Klasse für alle Tile-Typen
 * 
 * NEUE TILE-TYPEN:
 * 1. Neue Datei erstellen: XyzTile.php
 * 2. Von TileBase erben
 * 3. Abstrakte Methoden implementieren
 * 4. Fertig! Auto-Import durch _registry.php
 */

abstract class TileBase {
    
    /**
     * Gibt den Anzeigenamen des Tile-Typs zurück
     */
    abstract public function getName(): string;
    
    /**
     * Gibt die Beschreibung des Tile-Typs zurück
     */
    abstract public function getDescription(): string;
    
    /**
     * Definiert welche Felder dieser Tile-Typ verwendet
     * 
     * @return array Liste der Feldnamen
     */
    abstract public function getFields(): array;
    
    /**
     * Validiert die Tile-Daten
     * 
     * @param array $data Die zu validierenden Daten
     * @return array Leeres Array wenn OK, sonst Fehlermeldungen
     */
    abstract public function validate(array $data): array;
    
    /**
     * Rendert die Tile als HTML
     * 
     * @param array $data Tile-Daten
     * @return string HTML-Output
     */
    abstract public function render(array $data): string;
    
    /**
     * Hilfsmethode: HTML escapen
     */
    protected function esc(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Hilfsmethode: URL validieren
     */
    protected function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Hilfsmethode: Relativen Pfad validieren
     */
    protected function isValidPath(string $path): bool {
        // Erlaubt relative Pfade beginnend mit /
        return preg_match('/^\/[a-zA-Z0-9_\-\/\.]+$/', $path) === 1;
    }
    
    /**
     * Hilfsmethode: Dateiendung prüfen
     */
    protected function hasAllowedExtension(string $path, array $allowedExts): bool {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $allowedExts);
    }
    
    /**
     * Gibt Feld-Metadaten für den Editor zurück
     * Kann von Subklassen überschrieben werden
     * 
     * @return array ['fieldName' => ['type' => 'text|textarea|file|url', 'label' => string, 'required' => bool]]
     */
    public function getFieldMeta(): array {
        return [];
    }
}
