# Systemarchitektur - Info-Hub

> Technische Dokumentation für Entwickler und Administratoren

## Übersicht

Info-Hub ist ein file-based CMS ohne Datenbank. Die Architektur folgt dem Prinzip der strikten Trennung von Layout, Logic und Services.

Der kanonische Redaktionspfad ist `backend/v2/editor.php`. `backend/editor.php` bleibt vorerst als Legacy-Editor im Wartungsmodus erreichbar, wird aber nicht mehr aktiv weiterentwickelt.

## Schichtenarchitektur

`

   Frontend (v2/editor.php, editor.php legacy, index.html)    UI Layer

   API (endpoints.php)                  Dünne Wrapper + CSRF

   Services (TileService, Auth...)      Business Logic

   Storage (StorageService)             JSON File I/O

`

## Modulares Tile-System

Neue Tile-Typen werden durch einfaches Hinzufügen von Dateien in `/backend/tiles/` registriert:

```

Die Laufzeit-Registry fuer diese Tile-Typen wird zentral ueber `backend/core/TileRegistry.php` aufgebaut. Services wie `TileService` und `GeneratorService` greifen damit nicht mehr direkt auf einen globalen Registry-Zustand zu.
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
| TileRegistry | Discovery, Lookup und Instanziierung von Tile-Typen |
| GeneratorService | HTML-Generierung |
| BackupService | Paket-Backups, Export, Restore und Publish-/Quick-Restore-Orchestrierung |
| AuthService | Email-Code-Auth + Session |
| SettingsService | Laden, Maskieren, Validieren und Speichern globaler Settings |
| StorageService | JSON File-Operationen |
| UploadService | Datei-Upload & Validierung |
| LogService | Zentrales Logging |
| SecurityHelper | Debug/HTTPS-Warnungen |

## Globale Settings

Die `settings.json` enthaelt neben `site`, `theme` und `auth` auch einen `legal`-Block:

```json
"legal": {
   "enabled": false,
   "displayStyle": "subtleButtons",
   "imprint": { "mode": "off", "link": "", "text": "" },
   "privacy": { "mode": "off", "link": "", "text": "" }
}
```

- `GeneratorService` rendert daraus dezente Footer-Aktionen sowie optional ein Vollbild-Modal fuer eigene Rechtstexte.
- `SecurityHelper` normalisiert und sanitisiert Legal-Texte zentral, damit Link- und Text-Modus dieselben Regeln nutzen.
- `SettingsService` kapselt die Persistenz des `legal`-Blocks, damit beide Editoren und API-Endpunkte denselben fachlichen Pfad verwenden.

## Bootstrap und Composition Root

- `backend/bootstrap.php` ist der gemeinsame Einstieg fuer Backend-Entry-Points.
- Der Bootstrap laedt Config, Session und die benoetigten Service-Dateien.
- `backend/core/AppContainer.php` bildet die request-lokale Composition Root fuer Entry-Points wie `login.php`, `backup.php`, `editor.php`, `v2/editor.php`, `setup.php` und `api/endpoints.php`.
- Shared-Serviceinstanzen wie `AuthService`, `TileService`, `GeneratorService`, `SettingsService` und `BackupService` werden dort lazy pro Request bereitgestellt statt in jedem Entry-Point neu verdrahtet.

## V2 Render-Vertrag

- `backend/core/RenderContract.php` ist die zentrale Shape-Definition fuer den parallelen Render-Vertrag zwischen `GeneratorService`, `backend/v2/editor.php`, den Render-Endpoints und `assets/js/v2/canvas.js`.
- `RenderContract::CANVAS_SECTION_KEYS` und `RenderContract::RENDERED_TILE_KEYS` definieren die kanonischen Felder der V2-Section- und Tile-Payloads.
- Die Contract-Tests unter `tests/test_section_layout.php`, `tests/test_render_canvas_layout_endpoint.php` und `tests/test_render_all_tiles_html_endpoint.php` pruefen diese Definition direkt gegen die Runtime.

## Editor-Status

- `backend/v2/editor.php` ist der Standard-Editor nach dem Login und die Zielrichtung fuer weitere Editor-Features.
- `backend/editor.php` bleibt als Legacy-Pfad fuer bestehende Workflows erhalten.
- Classic-spezifische Stellen werden schrittweise mit `LEGACY_CLASSIC_EDITOR` markiert, damit der Pfad spaeter gezielt entfernt werden kann.

## Backup- und Publish-Flow

Das Backup-System folgt seit dem Paket-Backup-Umbau einem klaren Ablauf:

1. `endpoints.php` delegiert Publish und Restore an `BackupService`.
2. `BackupService::publishCurrentState()` erstellt vor jeder Veröffentlichung ein Paket-Backup.
3. Danach schreibt `GeneratorService::generate()` die neue `index.html`.
4. Der Quick-Restore-Status der Session wird ebenfalls im `BackupService` verwaltet.

Restore-Modi:

- `editor`: stellt `tiles.json`, `settings.json` und gepackte Medien wieder her.
- `site`: stellt die veröffentlichte `index.html` und gepackte Medien wieder her.
- `all`: kombiniert beide Wege.

Die Verwaltungsoberfläche in `backend/backup.php` rendert die Karten serverseitig und lädt ihr Styling und Verhalten aus `assets/css/backup.css` und `assets/js/backup.js`.

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
User  v2/editor.php (Standard) / editor.php (Legacy)  API (CSRF prüfen)  TileService  StorageService  tiles.json
                                
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
- [Feature-Matrix / Nachtraegliches Lastenheft](feature-matrix.md)
