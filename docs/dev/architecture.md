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

Neue Tile-Typen werden durch einfaches Hinzufügen einer Datei in /backend/tiles/ registriert:

`
/backend/tiles/
 _registry.php     # Auto-Import
 TileBase.php      # Abstrakte Basis
 InfoboxTile.php
 DownloadTile.php
 ImageTile.php
 LinkTile.php
 IframeTile.php    # Für Formulare & Widgets
 [NeuerTile].php   # Einfach hinzufügen!
`

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
- enderSecurityBadge() - Badge für Editor-Header
- enderSecurityBanner() - Banner für Login-Seite
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
