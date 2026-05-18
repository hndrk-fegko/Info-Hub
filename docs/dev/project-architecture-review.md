# Architektur-Review: Gesamtprojekt

Stand: 2026-05-18
Review-Basis: Workspace-Stand nach Commit `5af4ac1` (`Refactor backup architecture and extract backup UI assets`)

## Scope

Geprueft wurden die tragenden Architektur- und Wartungspunkte des Gesamtprojekts:

- Backend-Entry-Points und Service-Bootstrap
- Tile-Registry und Tile-Metadatenmodell
- API-Schicht unter `backend/api/endpoints.php`
- Classic Editor unter `backend/editor.php` plus `assets/js/editor-*.js`
- V2 Editor unter `backend/v2/editor.php` plus `assets/js/v2/*.js`
- Test- und Dokumentationsstand

Referenz fuer die Bewertung:

- `docs/basics.txt`
- `docs/dev/architecture.md`
- `docs/dev/feature-matrix.md`
- `.github/copilot-instructions.md`

Hinweis:

- Backup-/Restore-spezifische Findings stehen separat in `docs/dev/backup-feature-architecture-review.md` und werden hier nur beruehrt, wenn sie systemischen Charakter haben.

## Kurzfazit

Info-Hub hat eine gute fachliche Grundstruktur: file-based Storage, klare Tile-Module, nutzbare Service-Klassen und inzwischen auch eine solide Backup-Orchestrierung. Die groessten Risiken liegen aktuell nicht in Einzelbugs, sondern in drei systemischen Themen:

1. zentrale Infrastruktur wird noch ueber Globals und manuelle Bootstrap-Reihenfolgen getragen,
2. Classic und V2 werden parallel als vollwertige Editoren gepflegt,
3. mehrere kanonische Dokumente bilden den Ist-Zustand nicht mehr korrekt ab.

Wenn diese drei Ebenen nicht zuerst geordnet werden, steigt die Wahrscheinlichkeit, dass neue Features mehrfach, inkonsistent oder an der falschen Schicht implementiert werden.

## Findings nach Architekturrelevanz

### 1. Die Tile-Registry ist als globaler Prozesszustand statt als klare Abhaengigkeit modelliert

Status: Erledigt
Prioritaet: Hoch

Beobachtung:

- `backend/tiles/_registry.php` baut `$TILE_TYPES` global auf und exponiert zusaetzlich globale Factory-Helfer.
- `backend/core/TileService.php` und `backend/core/GeneratorService.php` greifen mehrfach per `global $TILE_TYPES` darauf zu.

Warum relevant:

- Services haben damit versteckte Laufzeitabhaengigkeiten statt expliziter Konstruktor- oder Methodenabhaengigkeiten.
- Das erschwert Refactoring, Testbarkeit und spaetere Erweiterungen wie alternative Registry-Strategien.
- Das Muster steht quer zu den eigenen Schichtenprinzipien in `docs/basics.txt`.

Empfohlene Richtung:

- Eine echte `TileRegistry`-Klasse oder einen kleinen Registry-Service einfuehren.
- `TileService` und `GeneratorService` sollen die Registry explizit erhalten statt auf globale Variablen zuzugreifen.

Umgesetzt:

- `backend/core/TileRegistry.php` eingefuehrt.
- `TileService` und `GeneratorService` nutzen jetzt eine Registry-Abhaengigkeit statt `global $TILE_TYPES`.
- `backend/tiles/_registry.php` bleibt nur noch als Kompatibilitaetshuellen fuer aeltere Call Sites bestehen.

Abhaengigkeiten:

- Sinnvoll nach Einfuehrung eines zentralen Bootstraps, damit die Registry dort einmalig aufgebaut und verteilt wird.

### 2. Service- und Config-Bootstrap ist ueber mehrere Entry-Points fragmentiert und reihenfolgenabhaengig

Status: Erledigt
Prioritaet: Hoch

Beobachtung:

- `backend/api/endpoints.php`, `backend/editor.php`, `backend/v2/editor.php`, `backend/login.php` und `backend/backup.php` laden ihre Services jeweils manuell per `require_once`.
- `backend/core/AuthService.php` dokumentiert selbst, dass `config.php` vorher geladen sein muss.
- `backend/core/ConfigService.php` existiert, ist aber kein zentraler Initialisierungspunkt fuer den restlichen Stack.

Warum relevant:

- Die Initialisierung basiert auf Konvention statt auf einer klaren Composition Root.
- Neue Services oder Konfigurationswerte muessen an mehreren Stellen nachgezogen werden.
- Verfuegbarkeit und Lade-Reihenfolge sind schwer auditierbar.

Empfohlene Richtung:

- Ein zentrales `backend/bootstrap.php` oder einen kleinen Service-Container einfuehren.
- Konfiguration, Session, Registry und Service-Erzeugung dort vereinheitlichen.
- Entry-Points sollen nur noch bootstrapen und danach ihre eigentliche Aufgabe ausfuehren.

Umgesetzt:

- `backend/bootstrap.php` als gemeinsamer Bootstrap eingefuehrt.
- `backend/editor.php`, `backend/v2/editor.php`, `backend/login.php`, `backend/backup.php`, `backend/setup.php` und `backend/api/endpoints.php` nutzen jetzt denselben Config-/Session-/Service-Start.
- `backend/core/AppContainer.php` bildet jetzt die request-lokale Composition Root fuer Entry-Points und liefert Shared-Serviceinstanzen zentral aus dem Bootstrap.
- Die verbliebene manuelle Service-Instanziierung in `editor.php`, `v2/editor.php`, `login.php`, `backup.php`, `setup.php` und `api/endpoints.php` wurde auf den Bootstrap-Container umgestellt.

Abhaengigkeiten:

- Sollte vor groesseren API- oder Editor-Refactorings passieren, weil fast alle Schichten darauf aufbauen.

### 3. Das Projekt betreibt zwei vollwertige Editor-Linien ohne klare Zielarchitektur

Status: Erledigt
Prioritaet: Hoch

Beobachtung:

- `docs/dev/feature-matrix.md` beschreibt sowohl Classic (`backend/editor.php`) als auch V2 (`backend/v2/editor.php`) als aktive Editoren mit gemeinsamer Datenbasis.
- Gleichzeitig steht `docs/dev/WYSIWYG-WIP.md` noch auf `Phase 0 abgeschlossen`, obwohl V2 produktiv genutzt und ueber `backend/login.php` als Zieleditor verlinkt wird.
- Die Codebasis spaltet sich dadurch in zwei UI-Stacks auf: `assets/js/editor-*.js` und `assets/js/v2/*.js`.

Warum relevant:

- Jeder neue Editor-Workflow verdoppelt Implementierungs-, Test- und Review-Aufwand.
- Fehlerbehebungen in einem Editor muessen aktiv gegen den anderen gespiegelt werden.
- Es bleibt unklar, ob Classic ein Legacy-Pfad oder ein dauerhaft gleichwertiger Client sein soll.

Empfohlene Richtung:

- Auf Architektur-Ebene festlegen, welcher Editor der kanonische Zukunftspfad ist.
- Den anderen Editor entweder klar in Wartungsmodus setzen oder eine bewusst geteilte Editor-API und gemeinsame Contracts definieren.

Umgesetzt:

- V2 ist jetzt als Zielrichtung festgelegt.
- `backend/login.php` bleibt auf V2 als Standardpfad.
- `backend/editor.php` ist im Code und in der UI als Legacy-/Wartungspfad markiert.
- Der Classic-Aufruf zeigt einen Warn-/Confirm-Dialog; Abbruch fuehrt zurueck nach V2.
- `docs/dev/feature-matrix.md`, `docs/dev/architecture.md` und `docs/dev/WYSIWYG-WIP.md` beschreiben den Editor-Status jetzt konsistent als `V2 = kanonisch`, `Classic = Legacy/Wartung`.

Abhaengigkeiten:

- Diese Entscheidung sollte vor weiterer groesserer V2- oder Classic-Ausbauarbeit fallen, weil sie viele Folgeentscheidungen beeinflusst.

### 4. Die API-Schicht ist weiterhin ein monolithischer Router mit fachlicher Validierungslogik im Transport-Layer

Status: Erledigt
Prioritaet: Hoch

Beobachtung:

- `backend/api/endpoints.php` besitzt einen zentralen Switch fuer viele Actions.
- Besonders `save_settings` enthaelt umfangreiche Feld-, Bereichs- und Typpruefungen direkt im Endpoint.
- Die Datei uebernimmt damit neben Routing und Response-Mapping auch fachliche Sanitizing- und Persistenzvorbereitung.

Warum relevant:

- Das widerspricht dem eigenen Prinzip `API = duenner Wrapper`.
- Die Schicht ist schwer isoliert testbar und aendert sich bei fast jedem Feature mit.
- Fehlerbehandlung und Response-Semantik bleiben dadurch leicht inkonsistent.

Empfohlene Richtung:

- Settings-Validierung und aehnliche fachliche Regeln in dedizierte Services oder Value-Mapper verschieben.
- Ein kleines gemeinsames API-Response-Muster fuer Erfolg, Validation Errors und Exceptions einfuehren.

Umgesetzt:

- `backend/core/SettingsService.php` uebernimmt jetzt den fachlichen Pfad fuer `get_settings` und `save_settings`.
- `backend/api/endpoints.php` delegiert diese beiden Actions nur noch an den Service und mappt das JSON-Response.
- Die Settings-Persistenz aus `upload_header` und `upload_background` laeuft jetzt ebenfalls ueber `SettingsService`, statt direkt `settings.json` im Endpoint zu veraendern.
- Die Request-Normalisierung fuer `list_files` und `delete_file` liegt jetzt in `UploadService`, statt Typ- und Pfad-Parsing im Router zu halten.
- Die Header-Metadaten-Normalisierung fuer `upload_header` liegt jetzt in `UploadService`; der Endpoint reicht nur noch Upload + Requestdaten weiter.
- Der Admin-API-Block teilt sich jetzt einen kleinen Snapshot-Helper fuer `emails` und `invites`, statt dieselbe Response mehrfach zusammenzubauen.
- Der Tile-/WYSIWYG-Block nutzt jetzt gemeinsame Request-Parser fuer JSON- und Formular-Payloads, statt dieselben `json_decode`-Pfade mehrfach im Router zu wiederholen.
- Fuer die bereits bereinigten Router-Slices existiert jetzt ein kleines lokales JSON-Response-Muster (`respondJson` / `respondSuccess` / `respondResult`) als erster Schritt zu konsistenterem API-Transportcode.
- Der verbleibende zentrale Switch ist aktuell weitgehend auf Routing, Parameteraufnahme, Statuscodes und Response-Mapping reduziert; die zuvor im Endpoint liegende fachliche Validierungs- und Persistenzlogik wurde in Services oder lokale Transport-Helfer verschoben.

Abhaengigkeiten:

- Baut sinnvoll auf Finding 2 auf, weil ein sauberer Bootstrap die API-Zerlegung vereinfacht.

### 5. V2 haengt an einem impliziten Render-Vertrag zwischen PHP und JavaScript, der nur als Kommentar abgesichert ist

Status: Erledigt
Prioritaet: Mittel

Beobachtung:

- `backend/v2/editor.php` markiert explizit einen `PARALLEL RENDER CONTRACT` fuer `GeneratorService::renderCanvasSections()` und `assets/js/v2/canvas.js`.
- `tests/test_v2_render.php` prueft aktuell nur Smoke-Signale der Seite, aber nicht die Struktur dieses Vertrags.

Warum relevant:

- Schon kleine Aenderungen an Wrappern oder Datenattributen koennen den Editor still brechen.
- Der Vertrag existiert real, ist aber weder formal modelliert noch gezielt regressionsgetestet.

Empfohlene Richtung:

- Entweder ein gemeinsames serverseitiges Datenmodell fuer Sections definieren oder einen echten Kontrakttest auf HTML-/DOM-Struktur einfuehren.
- Der Kommentar in `backend/v2/editor.php` sollte dann nur noch auf diesen Test oder diese Spezifikation verweisen.

Umgesetzt:

- `tests/test_section_layout.php` prueft jetzt nicht mehr nur grobe Section-Gruppierung, sondern den konkreten Render-Vertrag von `GeneratorService::renderCanvasSections()`.
- Der Test deckt jetzt die fuer `backend/v2/editor.php` und `assets/js/v2/canvas.js` relevanten Felder (`markerTileId`, `markerTitle`, `backgroundMode`, `tileIds`, `visible`, `isImplicit`, HTML-Wrapper) explizit ab.
- Zusaetzlich wird validiert, dass `backend/v2/editor.php` die gerenderten Sections in `window.V2_CONFIG.renderedSections` einbettet.
- `tests/test_render_canvas_layout_endpoint.php` prueft zusaetzlich den API-Vertrag von `render_canvas_layout` und vergleicht die Endpunkt-Antwort direkt mit `GeneratorService::renderCanvasSections()`.
- `tests/test_render_all_tiles_html_endpoint.php` prueft den parallelen API-Vertrag von `render_all_tiles_html` gegen `GeneratorService::renderAllTilesHtml()`.
- `backend/core/RenderContract.php` definiert jetzt zentral die kanonischen Keys und Contract-Versionen fuer Sections und gerenderte Tiles.
- `GeneratorService::renderCanvasSections()` und `GeneratorService::renderAllTilesHtml()` bauen ihre Rueckgaben jetzt explizit ueber `RenderContract` statt ueber frei modellierte Arrays.
- `backend/v2/editor.php` und die Render-Endpoints exponieren die Contract-Version sichtbar (`renderContractVersion` bzw. `contractVersion`).

Abhaengigkeiten:

- Sollte nach der Editor-Entscheidung aus Finding 3 angegangen werden.

### 6. Die Teststrategie ist flach, smoke-test-lastig und nicht an den eigenen Projektregeln ausgerichtet

Status: Teilweise erledigt
Prioritaet: Mittel

Beobachtung:

- Unter `tests/` liegen aktuell flache PHP-Skripte wie `test_phase1.php`, `test_phase2.php`, `test_v2_render.php` und `test_backup_service.php`.
- `docs/basics.txt` fordert dagegen eine klar strukturierte Test-Suite mit `unit`, `integration` und `e2e`.
- Viele systemrelevante Contracts werden nur indirekt oder gar nicht abgesichert.

Warum relevant:

- Der aktuelle Stil ist fuer schnelle Smoke-Checks brauchbar, skaliert aber schlecht fuer systematische Regressionen.
- Architektur-Refactorings bleiben dadurch teurer und riskanter als noetig.

Empfohlene Richtung:

- Bestehende Smoke-Tests behalten, aber in eine klarere Testhierarchie ueberfuehren.
- Zuerst Kontrakttests fuer API, Tile-Registry und V2-Render-Pfade aufbauen.

Umgesetzt:

- `tests/run.php` fuehrt bestehende PHP-Tests jetzt zentral aus und unterstuetzt `--list`, `--suite`, `--test` und `--format=json` fuer menschen- und maschinenlesbare Aufrufe.
- `tests/manifest.php` gruppiert die bisher flachen Tests logisch ueber Suite-Tags wie `contracts`, `render`, `api`, `editor`, `backup` und `smoke`.
- `scripts/test.ps1` und `scripts/test.sh` bieten schlanke Wrapper fuer Windows- und Shell-Aufrufe.
- Die neuen Kontrakttests fuer Render-Pfade staerken die Suite bereits in einem systemkritischen Bereich.
- Zusaetzliche Kontrakttests decken jetzt AuthService, UploadService, `get_settings`/`save_settings` sowie die Tile-CRUD-Endpunkte gegen ihre jeweiligen Fachservices ab.
- Die bisher roten Legacy-Sammeltests `test_phase2.php` und `test_phase3.php` laufen wieder gruen gegen den aktuellen Registry- und Toolbar-Stand.
- Die ausfuehrbaren PHP-Tests liegen jetzt physisch unter `tests/integration/` statt weiter flach im Root von `tests/`.
- Unter `tests/unit/` laufen jetzt erste echte isolierte Unit-Tests fuer `MediaPathHelper` und `RenderContract` ohne Storage-, Session- oder HTTP-Kontext.
- `tests/run.php --suite=e2e` listet jetzt zentral registrierte manuelle Browser-/E2E-Szenarien aus `tests/e2e/*.md`, statt diese nur implizit in `docs/TESTING.md` zu belassen.

Noch offen:

- Die `unit`-Suite deckt bislang nur erste Helper-/Contract-Logik ab; weitere isolierte Fachlogik ist noch nicht in aehnlicher Breite abgesichert.
- Browser-Interaktionen, responsive Verhalten, Setup/.htaccess und produktionsnahe End-to-End-Szenarien sind weiterhin nur manuell oder indirekt abgesichert; der neue E2E-Runner ist aktuell eine zentrale Checklisten-Laufbahn, aber noch keine Browser-Automation.

Abhaengigkeiten:

- Kann parallel zu Finding 4 und 5 starten, sobald deren Zielstruktur feststeht.

### 7. Mehrere Kern-Dokumente sind gegenueber dem aktuellen Laufzeitsystem sichtbar veraltet

Status: Erledigt
Prioritaet: Mittel

Beobachtung:

- `docs/dev/api.md` dokumentiert `upload_file` und `publish`, waehrend `backend/api/endpoints.php` `upload_download` und `generate` verwendet.
- `docs/TESTING.md` erwartet weiterhin den Redirect zu `editor.php`, waehrend `backend/login.php` auf `v2/editor.php` leitet.
- `docs/dev/WYSIWYG-WIP.md` beschreibt den V2-Editor weiterhin als fruehe Portierung, obwohl `docs/dev/feature-matrix.md` ihn bereits als gleichwertigen Editor fuehrt.
- `docs/dev/architecture.md` spricht im Datenfluss weiter hauptsaechlich ueber `editor.php`.

Warum relevant:

- Onboarding, Review und spaetere Refactorings basieren damit auf uneinheitlichen Wahrheiten.
- Besonders problematisch ist das, weil `feature-matrix.md` gleichzeitig als kanonischer Ist-Stand definiert ist.

Empfohlene Richtung:

- Einen klaren Dokumentations-Owner pro Themenfeld festlegen.
- `feature-matrix.md`, `api.md`, `architecture.md` und `TESTING.md` auf denselben Ist-Zustand ziehen.
- `WYSIWYG-WIP.md` entweder archivieren oder in ein bewusst historisches Dokument umwidmen.

Umgesetzt:

- `docs/dev/api.md` dokumentiert jetzt die aktuellen Action-Namen wie `upload_download`, `generate`, die Render-/Canvas-Endpoints sowie Backup- und Admin-Actions.
- `docs/dev/architecture.md` beschreibt jetzt auch den gemeinsamen Bootstrap plus `AppContainer` als Composition Root.
- `docs/TESTING.md` und `docs/dev/feature-matrix.md` spiegeln bereits den V2-Redirect und den Legacy-Status von Classic wider.
- `docs/dev/WYSIWYG-WIP.md` ist explizit als historisches Dokument markiert und verweist auf die kanonischen Ist-Dokumente.

Abhaengigkeiten:

- Sollte direkt nach der strategischen Editor-Entscheidung passieren, damit die Doku nicht erneut halb korrigiert werden muss.

### 8. Tile-Felddefinitionen sind doppelt modelliert und koennen zwischen Classic und V2 auseinanderlaufen

Status: Teilweise erledigt
Prioritaet: Mittel

Beobachtung:

- Tile-Klassen wie `backend/tiles/InfoboxTile.php` definieren Felder in `getFields()` und noch einmal in `getFieldMeta()`.
- V2 nutzt `fieldMeta` fuer dynamische Formulare, waehrend der Classic Editor weiterhin staerker hartcodiert arbeitet.

Warum relevant:

- Ein Feld kann leicht in einer der beiden Definitionen fehlen oder anders beschrieben sein.
- Gerade bei einer parallelen Editor-Strategie verstaerkt das das Risiko fuer schleichende Paritaetsluecken.

Empfohlene Richtung:

- Ein einziges Feldschema definieren, aus dem sowohl reine Feldlisten als auch Editor-Metadaten abgeleitet werden.
- Danach Classic und V2 soweit moeglich an dieselbe Metadatenquelle anbinden.

Umgesetzt:

- `TileBase::getFields()` leitet Feldlisten jetzt standardmaessig aus `getFieldMeta()` ab, statt jede Tile-Klasse zu einer zweiten parallelen Feldliste zu zwingen.
- Redundante `getFields()`-Overrides wurden aus den Tile-Klassen entfernt; die Feldlisten kommen dort jetzt zentral aus derselben Metadatenquelle.
- `TileService::getAvailableTypes()` und `TileService::getAvailableTypesWithMeta()` leiten `fields` jetzt primaer aus `fieldMeta` ab, statt zwei unabhaengige Feldlisten weiterzureichen.
- `backend/editor.php` versorgt den Classic Editor jetzt ebenfalls mit `getAvailableTypesWithMeta()`, nicht mehr nur mit einer reduzierten Feldliste.
- `assets/js/editor-modals.js` liest Feldtyp, Label, Defaults und Optionen jetzt primaer aus `typeInfo.fieldMeta` und nutzt die alte JS-Feldtabelle nur noch als Legacy-Fallback.
- `tests/integration/test_phase2.php` und `tests/integration/test_phase3.php` pruefen den gemeinsamen `fieldMeta`-Pfad jetzt explizit fuer TileService, Classic-Konfiguration und Modal-Assets.

Noch offen:

- Classic enthaelt fuer Legacy-Sonderfaelle weiterhin spezielle Render-Pfade und Fallback-Configs; die Metadatenbasis ist vereinheitlicht, aber noch nicht restlos exklusiv.

Abhaengigkeiten:

- Sollte nach der Editor-Entscheidung aus Finding 3 erfolgen, damit klar ist, wie viel gemeinsame Editor-Metadatenbasis noch gebraucht wird.

## Abarbeitungsreihenfolge

1. Editor-Zielarchitektur festlegen: Classic vs. V2 vs. bewusst duales Modell.
2. Zentrales Bootstrap fuer Config, Session, Registry und Services einfuehren.
3. Globale Tile-Registry in explizite Abhaengigkeiten ueberfuehren.
4. API-Datei in duenne Routing-/Response-Schicht plus Fachservices zerlegen.
5. V2-Render-Vertrag und Kern-API-Pfade mit belastbaren Kontrakttests absichern.
6. Teststruktur an den dokumentierten Projektstandard angleichen.
7. Kern-Dokumente auf denselben Ist-Zustand bringen.
8. Tile-Feldschema vereinheitlichen.

## Bearbeitungsstatus

- [x] Finding 1 bearbeitet
- [x] Finding 2 bearbeitet
- [x] Finding 3 bearbeitet
- [x] Finding 4 bearbeitet
- [x] Finding 5 bearbeitet
- [~] Finding 6 teilweise bearbeitet
- [x] Finding 7 bearbeitet
- [~] Finding 8 teilweise bearbeitet
