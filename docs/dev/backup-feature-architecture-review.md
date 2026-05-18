# Architektur-Review: Backup-/Restore-Feature

Stand: 2026-05-18
Review-Basis: Commit `2729d37` (`Add backup management and V2 restore workflow`)

## Scope

Geprueft wurden die neuen Komponenten fuer Paket-Backups, Restore-Modi, Quick-Restore und die Backup-Verwaltung:

- `backend/core/BackupService.php`
- `backend/api/endpoints.php`
- `backend/core/GeneratorService.php`
- `backend/backup.php`
- `backend/v2/editor.php`
- `assets/js/v2/canvas.js`
- `assets/js/v2/api-client.js`
- `tests/test_backup_service.php`

Referenz fuer die Bewertung:

- `docs/basics.txt`
- `docs/dev/architecture.md`
- `.github/copilot-instructions.md`

## Kurzfazit

Das Feature ist funktional stark und fuer den Benutzer bereits gut nutzbar. Architektonisch gibt es aber vier Punkte, die vor weiterer Ausbauarbeit bereinigt werden sollten, damit das System nicht zwischen alter und neuer Backup-Logik auseinanderlaeuft:

1. Publish-/Quick-Restore-Orchestrierung musste in einen klaren Service-Owner verschoben werden.
2. Quick-Restore-Sessionstate musste zentralisiert werden.
3. Die Backup-Verwaltung musste aus der monolithischen PHP-Datei in Assets zerlegt werden.
4. Validierungs- und Legacy-Mechanismen mussten entkoppelt und vereinheitlicht werden.

## Findings nach Architekturrelevanz

### 1. Publish- und Quick-Restore-Logik ist ueber API und Service verteilt

Status: Erledigt
Prioritaet: Hoch

Beobachtung:

- `backend/api/endpoints.php` orchestriert Snapshot, Generierung, Session-Update und Response-Mapping.
- `BackupService` enthaelt den eigentlichen fachlichen Kern, ist aber nicht der eindeutige Owner fuer den kompletten Publish-/Quick-Restore-Flow.

Warum relevant:

- Verletzt das Prinzip `API = duenner Wrapper`.
- Erhoeht das Risiko, dass neue Publish-Regeln spaeter an mehreren Stellen gepflegt werden.

Umgesetzter Fix:

- Publish-Workflow liegt jetzt in `BackupService::publishCurrentState()`.
- `endpoints.php` delegiert nur noch an den Service.

Abhaengigkeiten:

- Muss vor kleineren API-Bereinigungen passieren, sonst wird dieselbe Logik zweimal angefasst.

### 2. Quick-Restore-Sessionstate ist mehrfach verteilt

Status: Erledigt
Prioritaet: Hoch

Beobachtung:

- `$_SESSION['quick_restore_last_publish']` wird aktuell in `endpoints.php` und `backend/v2/editor.php` direkt gelesen, gesetzt und geloescht.

Warum relevant:

- Ein Session-Detail ist kein UI-Wissen, sondern Feature-State.
- Erhoeht das Risiko fuer inkonsistente Zustandsuebergaenge bei Restore, Delete und Publish.

Umgesetzter Fix:

- Einheitliche Getter-/Setter-/Clear-Methoden im `BackupService`.
- `backend/v2/editor.php` konsumiert nur noch vorbereitete Viewdaten.

Abhaengigkeiten:

- Haengt direkt an Finding 1 und wird im selben Umbau erledigt.

### 3. Backup-Verwaltung ist als Single-File-UI angelegt

Status: Erledigt
Prioritaet: Hoch

Beobachtung:

- `backend/backup.php` enthaelt Routing, Rendering, 700+ Zeilen CSS und das komplette Browser-Verhalten inline.

Warum relevant:

- Widerspricht der vorhandenen Asset-Struktur unter `assets/css` und `assets/js`.
- Erschwert Wartung, Review und Wiederverwendung.

Umgesetzter Fix:

- CSS liegt jetzt in `assets/css/backup.css`.
- JS liegt jetzt in `assets/js/backup.js`.
- `backend/backup.php` rendert nur noch Daten und Markup.

Abhaengigkeiten:

- Kann nach Finding 1/2 erfolgen, da es inhaltlich unabhaengig von der Publish-Orchestrierung ist.

### 4. Doppelte Validierungslogik fuer Media-Pfade

Status: Erledigt
Prioritaet: Mittel

Beobachtung:

- `backend/api/endpoints.php` und `backend/core/BackupService.php` validieren Media-Pfade mit eigener, leicht unterschiedlicher Logik.

Warum relevant:

- Risiko fuer schleichende Abweichungen.
- Klassischer Fall von gleicher Aufgabe in zwei Funktionen.

Umgesetzter Fix:

- Gemeinsame Helper-Klasse `MediaPathHelper` eingefuehrt.

Abhaengigkeiten:

- Kann nach Finding 1/2 oder parallel zu Finding 3 erfolgen.

### 5. Legacy-Backup-Pfad in `GeneratorService` ist veraltet

Status: Erledigt
Prioritaet: Mittel

Beobachtung:

- `GeneratorService::generate()` hat noch einen optionalen Legacy-Backup-Zweig, obwohl der neue Publish-Weg bereits auf Paket-Snapshots setzt.

Warum relevant:

- Unklare Ownership des Features.
- Neue Entwickler sehen zwei Backup-Mechanismen und wissen nicht, welcher der gueltige ist.

Umgesetzter Fix:

- Legacy-Backup-Erzeugung aus `GeneratorService::generate()` entfernt.
- Legacy-Backups bleiben nur als Lesekompatibilitaet im `BackupService`.

Abhaengigkeiten:

- Sollte zusammen mit Finding 1 umgesetzt werden, damit der neue Publish-Weg der einzige aktive Pfad ist.

### 6. Test- und Dokuabdeckung ist fuer Fehlerpfade noch duenn

Status: Teilweise erledigt
Prioritaet: Mittel

Beobachtung:

- `tests/test_backup_service.php` ist ein hilfreicher Smoke-Test.
- Es fehlen aber gezielte Checks fuer Sessionstate, Publish-Orchestrierung und UI-nahe Fehlerpfade.

Warum relevant:

- Nach den Strukturfixes sollte die neue Architektur mit klaren Regressionstests abgesichert werden.

Umgesetzter Fix:

- Smoke-Test deckt jetzt Publish-/Quick-Restore-Orchestrierung mit ab.
- Architektur-Dokumentation beschreibt den neuen Publish-/Restore-Flow.

Offen bleibt:

- Zusätzliche Fehlerpfad-Tests (z. B. defekte Medien, Restore-Abbruch mitten im Lauf).

## Abarbeitungsreihenfolge

1. Publish-/Quick-Restore-Orchestrierung in `BackupService` zentralisieren. ✅
2. Legacy-Backup-Erzeugung aus `GeneratorService` entfernen. ✅
3. Media-Pfad-Validierung vereinheitlichen. ✅
4. Backup-UI in separate CSS-/JS-Assets zerlegen. ✅
5. Tests und Doku fuer die neue Struktur nachziehen. ⏳ teilweise

## Bearbeitungsstatus

- [x] Finding 1 umgesetzt
- [x] Finding 2 umgesetzt
- [x] Finding 3 umgesetzt
- [x] Finding 4 umgesetzt
- [x] Finding 5 umgesetzt
- [ ] Finding 6 vollständig umgesetzt