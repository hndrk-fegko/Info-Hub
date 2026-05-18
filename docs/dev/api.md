# API-Referenz - Info-Hub

> Dokumentation der aktuellen Backend-Endpoints unter `backend/api/endpoints.php`.

## Übersicht

Alle API-Requests laufen ueber `backend/api/endpoints.php` und den Parameter `action`.

Der kanonische Editor ist `backend/v2/editor.php`; Classic bleibt nur als Legacy-/Wartungspfad bestehen.

## Authentifizierung und CSRF

Alle Actions in `endpoints.php` erfordern eine gueltige Session.

Schreibende Requests (`POST` ausser reine GET-Actions) brauchen zusaetzlich ein gueltiges CSRF-Token.

Moegliche Uebergabeformen:

```json
{
  "csrf_token": "abc123...",
  "tile": { "type": "infobox" }
}
```

oder als Header:

```text
X-CSRF-TOKEN: abc123...
```

Typische Fehlercodes:

| HTTP Status | Bedeutung |
|-------------|-----------|
| 401 | Nicht authentifiziert |
| 403 | Ungueltiges CSRF-Token |
| 400 | Ungueltige Anfrage / Validierungsfehler |
| 404 | Ressource nicht gefunden |
| 500 | Interner Fehler |

## Tile- und Editor-Actions

### GET `?action=get_tiles`

Liefert alle Tiles sortiert nach Position.

### GET `?action=get_tile&id=...`

Liefert genau eine Tile.

### POST `?action=save_tile`

Speichert eine neue oder bestehende Tile. Erwartet `tile` als JSON-Objekt oder JSON-String.

### POST `?action=delete_tile`

Loescht eine Tile per `id`.

### POST `?action=update_positions`

Aktualisiert mehrere Positionen per `positions`-Array.

### GET `?action=get_tile_types`

Liefert die verfuegbaren Tile-Typen sowie `typesWithMeta` fuer V2-Formulare.

### POST `?action=render_tile_html`

Rendert eine einzelne Tile serverseitig fuer die Live-Vorschau.

### GET `?action=render_all_tiles_html`

Liefert servergerenderte HTML-Fragmente aller Tiles fuer den V2-Canvas.
Die Antwort enthaelt zusaetzlich `contractVersion` gemaess `RenderContract::RENDERED_TILE_VERSION`.

### GET `?action=render_canvas_layout`

Liefert die Abschnittsstruktur (`sections`) fuer den V2-Canvas.
Die Antwort enthaelt zusaetzlich `contractVersion` gemaess `RenderContract::CANVAS_SECTION_VERSION`.

### GET `?action=get_canvas_css`

Liefert Shared- und Tile-CSS als `text/css`.

### GET `?action=get_canvas_js`

Liefert tile-spezifisches JavaScript als `application/javascript`.

## Settings- und Admin-Actions

### GET `?action=get_settings`

Liefert die globalen Editor-Settings in maskierter Form.

### POST `?action=save_settings`

Speichert Site- und Theme-Settings.

### GET `?action=get_admins`

Liefert Admin-Emails und offene Einladungen.

### POST `?action=invite_admin`

Erstellt und versendet eine Admin-Einladung.

### POST `?action=remove_admin_email`

Entfernt eine Admin-Email. Self-Delete invalidiert die Session sofort.

### POST `?action=remove_admin_invite`

Entfernt eine offene Einladung.

## Upload- und Medien-Actions

### POST `?action=upload_image`

Upload eines Bildes fuer Tile-Medien.

### POST `?action=upload_download`

Upload einer Download-Datei.

### POST `?action=upload_header`

Upload eines Header-Bilds inklusive Placeholder-/Dimensions-Metadaten.

### POST `?action=upload_background`

Upload eines Hintergrundbilds fuer den Narrow-/Section-Kontext.

### POST `?action=delete_file`

Loescht eine Medien-Datei per `type` und `filename` oder alternativ per `path`.

### GET `?action=list_files&type=images|downloads|header|backgrounds`

Listet gespeicherte Medien-Dateien eines Typs.

## Backup-Actions

### GET `?action=list_backups`

Liefert alle sichtbaren Paket- und Legacy-Backups.

### GET `?action=view_backup_html&id=...`

Liefert die HTML-Vorschau eines Backups.

### GET `?action=view_backup_asset&id=...&path=...`

Liefert eine Asset-Datei aus einem Backup.

### GET `?action=export_backup&id=...`

Erstellt und streamt ein ZIP-Archiv des Backups.

### POST `?action=restore_backup`

Stellt ein Backup per `mode=editor|site|all` wieder her.

### POST `?action=quick_restore_last_publish`

Nimmt die letzte Veroeffentlichung der aktuellen Session zurueck.

### POST `?action=delete_backup`

Loescht ein Backup.

## Generator- und Diagnose-Actions

### POST `?action=generate`

Veroeffentlicht den aktuellen Stand und erzeugt `index.html` ueber den Paket-Backup-/Publish-Workflow.

### GET `?action=preview`

Liefert die aktuelle Vorschau direkt als HTML.

### POST `?action=extend_session`

Setzt die Session-Zeit zurueck.

### POST `?action=check_permissions`

Liefert eine Diagnose der Schreibrechte fuer Medien-Verzeichnisse.

## Login-Flow

Der Login laeuft nicht ueber `endpoints.php`, sondern ueber `backend/login.php`:

1. `POST /backend/login.php` mit `step=email` versendet einen Login-Code.
2. `POST /backend/login.php` mit `step=code` verifiziert den Code.
3. Bei Erfolg erfolgt der Redirect nach `backend/v2/editor.php`.
