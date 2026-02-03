# Systemarchitektur - Info-Hub

> Technische Dokumentation für Entwickler und Administratoren

## Übersicht

Info-Hub ist ein file-based CMS ohne Datenbank. Die Architektur folgt dem Prinzip der strikten Trennung von Layout, Logic und Services.

## Schichtenarchitektur

`

   Frontend (editor.php, index.html)    UI Layer

   API (endpoints.php)                  Dünne Wrapper + CSRF

   Services (TileService, Auth...)      Business Logic

   Storage (StorageService)             JSON File I/O

`

## Modulares Tile-System

Neue Tile-Typen werden durch einfaches Hinzufügen von Dateien in `/backend/tiles/` registriert:

```
/backend/tiles/
├── _registry.php      # Auto-Import
├── TileBase.php       # Abstrakte Basis (siehe Anleitung dort!)
├── XyzTile.php        # Tile-Logik (Pflicht)
├── XyzTile.css        # Tile-spezifisches CSS (Optional)
└── XyzTile.js         # Tile-spezifisches JavaScript (Optional)
```

### Neuen Tile-Typ erstellen

1. **XyzTile.php** erstellen - von TileBase erben, abstrakte Methoden implementieren
2. **XyzTile.css** erstellen (optional) - wird automatisch in `<style>` eingebunden
3. **XyzTile.js** erstellen (optional) - wird automatisch in `<script>` eingebunden
4. Falls JS eine Init-Funktion braucht: `getInitFunction()` überschreiben

Siehe ausführliche Dokumentation in `TileBase.php`.

### Vorhandene Tile-Typen

| Tile | CSS | JS | Beschreibung |
|------|-----|----|--------------|
| InfoboxTile | - | - | Text mit Titel |
| DownloadTile | - | - | Datei-Download |
| ImageTile | ✓ | ✓ | Bild mit Lightbox |
| LinkTile | - | - | Externer Link |
| IframeTile | ✓ | ✓ | Eingebettete Formulare |
| CountdownTile | ✓ | ✓ | Countdown zu Datum |
| ContactTile | ✓ | ✓ | Kontakt mit Anti-Spam |
| QuoteTile | ✓ | - | Zitat/Bibelvers |
| AccordionTile | ✓ | ✓ | Auf-/zuklappbare Bereiche |
| QuoteTile | ✓ | - | Zitat/Bibelvers |

## Services

| Service | Verantwortung |
|---------|---------------|
| TileService | CRUD-Operationen für Tiles |
| GeneratorService | HTML-Generierung |
| AuthService | Email-Code-Auth + Session |
| StorageService | JSON File-Operationen |
| UploadService | Datei-Upload & Validierung |
| LogService | Zentrales Logging |
| SecurityHelper | Debug/HTTPS-Warnungen |

## Sicherheitsarchitektur

`

   CSRF-Token (alle POST-Requests)   

   Session-Regeneration (nach Login) 

   SecurityHelper (Warnings)         

   Rate-Limiting (3 Versuche)        

`

### SecurityHelper

Zentrale Klasse für Sicherheitsprüfungen:

- isDebugMode() - Prüft ob DEBUG_MODE aktiv
- isHttps() - Prüft SSL-Verbindung
- isLocalhost() - Prüft lokale Entwicklung
- getSecurityStatus() - Sammelt alle Warnungen
- 
enderSecurityBadge() - Badge für Editor-Header
- 
enderSecurityBanner() - Banner für Login-Seite
- getEmailSecurityInfo() - Text für Login-Emails

## Datenfluss

`
User  editor.php  API (CSRF prüfen)  TileService  StorageService  tiles.json
                                
                         LogService (protokolliert)
`

## Embedding-System

Die generierte index.html unterstützt URL-Parameter:

| Parameter | Wirkung |
|-----------|---------|
| ?embedded=true | Header & Footer ausblenden |
| ?style=clean | Transparenter Hintergrund |
| ?style=minimalbox | Alle Tiles weiß |

## Weitere Dokumentation

- [API-Referenz](api.md)
- [Deployment & Setup](deployment.md)
