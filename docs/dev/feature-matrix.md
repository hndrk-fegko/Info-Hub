# Feature-Matrix / Nachtraegliches Lastenheft

> Kanonische Ist-Dokumentation des aktuell implementierten Funktionsumfangs.

## Zweck

Dieses Dokument beschreibt den tatsaechlich vorhandenen Funktionsumfang von Info-Hub.

Es ergaenzt die bestehende Doku mit klarer Rollenverteilung:

- `docs/vision.md`: Produktvision, Zielbild, grobere Anforderungen
- `docs/ROADMAP.md`: Zukunft, Ideen, Priorisierung
- `docs/dev/WYSIWYG-KONZEPT.md`: Migrations- und Architekturentscheidungen fuer v2
- `docs/dev/WYSIWYG-WIP.md`: historisches Arbeitsdokument der V2-Portierung
- `docs/dev/feature-matrix.md`: kanonischer Ist-Stand aller Features und Editor-Paritaet

## Produktgrenzen

Info-Hub ist ein tile-basiertes, file-basiertes CMS mit gemeinsamer Datenbasis fuer:

- oeffentliche statische Ausgabe ueber `index.html`
- WYSIWYG Editor unter `backend/v2/editor.php` als kanonischer Redaktionspfad
- Classic Editor unter `backend/editor.php` als Legacy-/Wartungspfad

## Editor-Status

- `backend/v2/editor.php` ist die Zielrichtung und der standardmaessige Editor nach dem Login.
- `backend/editor.php` bleibt vorerst als Legacy-Pfad im Code, wird aber nicht mehr aktiv weiterentwickelt.
- Classic-spezifische Fundstellen koennen schrittweise ueber den Marker `LEGACY_CLASSIC_EDITOR` auffindbar gemacht und spaeter entfernt werden.

Nicht Ziel dieses Produkts:

- freies Page-Builder-Layout ohne Rasterlogik
- Mehrbenutzer-Rollenmodell mit fein granularen Rechten
- datenbankgestuetztes CMS

## Funktionsmatrix

Legende:

- `Ja`: vorhanden und nutzbar
- `Teilweise`: vorhanden, aber mit Luecken oder eingeschraenkter UX
- `Nein`: aktuell nicht vorhanden

### 1. System- und Plattform-Features

| Bereich | Feature | Oeffentliche Seite | Classic | V2 | Status | Referenz |
|---|---|---:|---:|---:|---|---|
| Betrieb | File-based CMS ohne DB | Ja | Ja | Ja | Ja | `backend/data/*.json` |
| Betrieb | Statische HTML-Generierung | Ja | Ja | Ja | Ja | `GeneratorService`, `generate` |
| Betrieb | Gleiche Datenbasis fuer beide Editoren | - | Ja | Ja | Ja | `tiles.json`, `settings.json` |
| Sicherheit | Email-Code-Login | - | Ja | Ja | Ja | `AuthService` |
| Sicherheit | CSRF-Schutz fuer schreibende Requests | - | Ja | Ja | Ja | `backend/api/endpoints.php` |
| Sicherheit | Session-Timeout / Warnung / Verlängerung | - | Ja | Ja | Ja | Classic + V2 Timer |
| Sicherheit | Security-Warnungen (Debug/HTTPS) | - | Ja | Teilweise | Teilweise | Classic ausgebaut, V2 reduziert |
| Medien | Bild-Upload | - | Ja | Ja | Ja | `upload_image` |
| Medien | Download-Upload | - | Ja | Ja | Ja | `upload_download` |
| Medien | Header-Upload | - | Ja | Ja | Ja | `upload_header` |
| Ausgabe | Preview | Ja | Ja | Ja | Ja | `preview` |
| Ausgabe | Publish | Ja | Ja | Ja | Ja | `generate` |

### 2. Editor-Paritaet

Hinweis:

- V2 ist der kanonische Editor fuer neue Arbeit.
- Classic wird nur noch gewartet, damit bestehende Workflows voruebergehend weiter verfuegbar bleiben.

| Feature | Classic | V2 | Status | Hinweise |
|---|---:|---:|---|---|
| Tiles anzeigen / laden | Ja | Ja | Ja | V2 serverseitig gerendert |
| Tile anlegen | Ja | Ja | Ja | V2 ueber Insert-Popup |
| Tile bearbeiten | Ja | Ja | Ja | V2 modalgetrieben aus `fieldMeta` |
| Tile loeschen | Ja | Ja | Ja | |
| Tile duplizieren | Ja | Ja | Ja | |
| Reihenfolge aendern | Ja | Ja | Ja | Classic ueber Position, V2 ueber DnD/Move |
| Groesse aendern | Ja | Ja | Ja | |
| Stil aendern | Ja | Ja | Ja | |
| Farbschema aendern | Ja | Ja | Ja | |
| Settings bearbeiten | Ja | Ja | Ja | V2 als eigenes Settings-Modul |
| Admin-Verwaltung | Ja | Ja | Ja | V2 in Settings integriert |
| Header/Footer editieren | Ja | Ja | Ja | V2 ueber Settings, nicht inline |
| File-Browser fuer Medien | Ja | Ja | Ja | UX unterschiedlich |
| Quick-Edit fuer Darstellung | Ja | Ja | Ja | Classic Kontextmenues, V2 Toolbar |
| Sichtbarkeit manuell umschalten | Ja | Ja | Ja | V2 ueber Rechtsklickmenue |
| Zeitsteuerung `showFrom/showUntil` bearbeiten | Ja | Ja | Ja | V2 ueber Rechtsklickmenue |
| Zeitsteuerungs-Status im Editor sichtbar | Ja | Ja | Ja | V2 zeigt Status direkt im Canvas |
| Kontextmenues / Rechtsklick-Aktionen | Ja | Ja | Ja | V2 fokussiert auf tile-bezogene Schnellaktionen |
| Undo/Redo | Nein | Nein | Nein | geplant, aber noch offen |
| Vollstaendige Inline-Bearbeitung | Nein | Teilweise | Teilweise | nur Teilbereiche / Modal-Fallback |

### 3. Tile-Typen

| Tile-Typ | Frontend | Classic Edit | V2 Edit | CSS | JS | Bemerkung |
|---|---:|---:|---:|---:|---:|---|
| Infobox | Ja | Ja | Ja | optional | Nein | Text-/Hinweis-Kachel mit umschaltbarer Textausrichtung |
| Download | Ja | Ja | Ja | Nein | Nein | Datei-Download |
| Image | Ja | Ja | Ja | Ja | Ja | Lightbox moeglich |
| Link | Ja | Ja | Ja | Ja | Nein | Externer Link |
| Iframe | Ja | Ja | Ja | Ja | Ja | Inline/Modal |
| Countdown | Ja | Ja | Ja | Ja | Ja | Ablauf-/Countdown-Logik |
| Contact | Ja | Ja | Ja | Ja | Ja | Anti-Spam / Reveal |
| Quote | Ja | Ja | Ja | Ja | Nein | Zitat/Bibelvers |
| Accordion | Ja | Ja | Ja | Ja | Ja | Gruppierte Inhalte |
| Separator | Ja | Ja | Ja | Ja | Nein | Layout-/Trennelement |

### 4. Tile-Datenmodell und Erweiterbarkeit

| Feature | Status | Hinweise |
|---|---|---|
| Tile-Klassen pro Typ | Ja | `backend/tiles/*Tile.php` |
| Tile-spezifisches CSS | Ja | automatisch ueber `TileBase::getCSS()` |
| Tile-spezifisches JS | Ja | automatisch ueber `TileBase::getJS()` |
| `getFieldMeta()` pro Tile | Ja | vorhanden in den Tile-Klassen |
| Dynamic Forms aus `fieldMeta` | Teilweise | voll in V2, Classic noch hartcodiert |
| Wrapper-Klassen pro Tile | Ja | z.B. `tile-full-row` beim Akkordeon |

### 5. Bekannte Paritaetsluecken V1 ↔ V2

Classic befindet sich bereits im Wartungsmodus. Diese Punkte markieren die wichtigsten Unterschiede, die bei Regressionstests, Bugfixes und spaeterer Entfernung des Legacy-Pfads beachtet werden muessen:

1. Classic und V2 nutzen unterschiedliche Interaktionsmodelle; Paritaet muss weiterhin pro Workflow geprueft werden, nicht nur pro Datenfeld.
2. Classic bleibt bei einigen Verwaltungs- und Randfall-Workflows noch direkter, waehrend V2 weiter konsolidiert wird.
3. Dynamic Forms aus `fieldMeta` sind in V2 bereits das staerkere Modell; der Classic Editor bleibt in diesem Punkt Legacy-naher.

## Dokumentationsregel

Wenn ein Feature neu hinzukommt oder zwischen Classic und V2 portiert wird, wird zuerst dieses Dokument aktualisiert.

Nur wenn sich dadurch Architektur, API oder Zielbild aendern, werden zusaetzlich die entsprechenden Spezialdokumente angepasst.