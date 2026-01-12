# API-Referenz - Info-Hub

> Dokumentation aller Backend-Endpoints

## Übersicht

Alle API-Calls gehen an /backend/api/endpoints.php mit dem Parameter ction.

## Authentifizierung

Alle Endpoints (außer Login) erfordern:
1. **Aktive Session** - Gültiger Login via Email-Code
2. **CSRF-Token** - Bei allen POST/DELETE Requests

### CSRF-Token

Der Token wird bei Login generiert und muss bei allen modifizierenden Requests mitgesendet werden:

**Option A: JSON Body**
`json
{
  "csrf_token": "abc123...",
  "tile": { ... }
}
`

**Option B: HTTP Header**
`
X-CSRF-TOKEN: abc123...
`

---

## Tile-Endpoints

### GET ?action=get_tiles

Gibt alle Tiles zurück, sortiert nach Position.

### POST ?action=save_tile

Speichert eine neue oder bestehende Tile.

### POST ?action=duplicate_tile

Dupliziert eine bestehende Tile.

### POST ?action=delete_tile

Löscht eine Tile.

---

## Tile-Typen & Felder

| Typ | Pflichtfelder | Optionale Felder |
|-----|---------------|------------------|
| **Infobox** | title | showTitle, description |
| **Download** | title | showTitle, description, file, buttonText |
| **Bild** | title | showTitle, image, caption, lightbox, link |
| **Link** | title, url | showTitle, description, linkText, external |
| **Iframe** | title, url | showTitle, description, displayMode, aspectRatio |

---

## Settings-Endpoints

### GET ?action=get_settings

Gibt die globalen Einstellungen zurück.

### POST ?action=save_settings

Speichert die Einstellungen (mit CSRF-Token).

---

## Upload-Endpoints

### POST ?action=upload_image

Upload eines Bildes (max 5MB, jpg/png/gif/webp).

### POST ?action=upload_file

Upload einer Datei (max 50MB, pdf/docx/xlsx/zip).

---

## Session-Endpoints

### POST ?action=extend_session

Verlängert die aktive Session um 1 Stunde.

---

## Publish-Endpoint

### POST ?action=publish

Generiert die statische index.html aus den aktuellen Tiles.

---

## Auth-Endpoints

### POST /backend/login.php

Sendet Login-Code per Email.

### POST /backend/login.php?verify

Verifiziert den Code, regeneriert Session, erstellt CSRF-Token.

---

## Fehler-Responses

| HTTP Status | Bedeutung |
|-------------|-----------|
| 401 | Nicht authentifiziert |
| 403 | CSRF-Token ungültig |
| 400 | Ungültige Anfrage |
| 500 | Server-Fehler |
