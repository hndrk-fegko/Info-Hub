# WYSIWYG Editor – Historisches WIP-Dokument

> **Branch:** `feature/wysiwyg-editor`  
> **Basis:** Variante B (Integration) – paralleler Editor, gleiche Datenbasis  
> **Start:** 2026-03-26  
> **Status:** 📦 Archiviert als Arbeitsstand der fruehen V2-Portierung

> **Aktueller Stand 2026-05-18:**
> `backend/v2/editor.php` ist inzwischen der kanonische Editor und der Standardpfad nach dem Login.
> `backend/editor.php` bleibt nur noch als Legacy-/Wartungspfad im Code.
> Dieses Dokument dient daher nur noch als historische Entwicklungsnotiz und nicht mehr als aktueller Projektstatus.
> Der aktuelle Ist-Stand liegt in `docs/dev/feature-matrix.md`, die Architektur-Einordnung in `docs/dev/architecture.md`.

---

## Fortschrittsprotokoll

| Datum | Phase | Was wurde gemacht | Status |
|-------|-------|-------------------|--------|
| 2026-03-26 | Vorbereitung | Branch erstellt, DEBUG_MODE=true, WIP-Dokument angelegt | ✅ Done |
| | Phase 1 | | ⬜ Ausstehend |
| | Phase 2 | | ⬜ Ausstehend |
| | Phase 3 | | ⬜ Ausstehend |
| | Phase 4 | | ⬜ Ausstehend |
| | Phase 5 | | ⬜ Ausstehend |

---

## Phase 0: Vorbereitung ✅

### Aufgaben
- [x] Git-Branch `feature/wysiwyg-editor` von `main` erstellt
- [x] `DEBUG_MODE` auf `true` gesetzt (ermöglicht Login ohne E-Mail, Fehler sichtbar)
- [x] Dieses WIP-Dokument angelegt

### Validierung
- [x] `git branch` zeigt `feature/wysiwyg-editor` als aktiv
- [x] `config.php` → `DEBUG_MODE = true`

---

## Phase 1: Shared CSS Extraktion

> **Ziel:** Das inline-CSS aus `GeneratorService.php` (~500 Zeilen) in wiederverwendbare CSS-Dateien extrahieren, die sowohl der WYSIWYG-Editor als auch der Generator nutzen.

### Aufgaben
- [ ] `assets/css/shared/variables.css` – CSS Custom Properties (`:root`)
- [ ] `assets/css/shared/base.css` – Reset, Body, Typography
- [ ] `assets/css/shared/grid.css` – `.tile-grid`, Responsive Breakpoints (4→2→1)
- [ ] `assets/css/shared/tiles.css` – `.tile` Base, Sizes, Styles (flat/card), Color Schemes
- [ ] `assets/css/shared/header.css` – `.site-header`, Header-Image, Title
- [ ] `assets/css/shared/footer.css` – `.site-footer`
- [ ] `assets/css/shared/components.css` – Lightbox, Iframe-Modal, Download-Buttons
- [ ] `GeneratorService.php` refactorn: liest shared CSS-Dateien statt inline-Strings
- [ ] Tile-spezifisches CSS (`*Tile.css`) bleibt wo es ist (wird weiterhin von `collectTileCSS()` gesammelt)

### Erfolgskriterien
| # | Kriterium | Testmethode |
|---|-----------|-------------|
| 1.1 | Generierte `index.html` sieht **pixel-identisch** aus wie vorher | Visueller Diff: alte vs. neue `index.html` im Browser vergleichen |
| 1.2 | Kein CSS mehr inline in `GeneratorService.php` (nur noch `file_get_contents`) | Code-Review: grep nach ` .tile {` in GeneratorService |
| 1.3 | Shared CSS-Dateien existieren und sind syntaktisch korrekt | Jede CSS-Datei im Browser laden → keine Parse-Errors in DevTools |
| 1.4 | `?action=preview` funktioniert weiterhin | API-Call → HTML prüfen |

### Automatisierter Test (KI-prüfbar)
```bash
# Test 1: Generierung erfolgreich
curl -s "http://localhost:8000/backend/api/endpoints.php?action=preview" | head -20
# Erwartung: <!DOCTYPE html> mit <style> Block der CSS Custom Properties enthält

# Test 2: Shared CSS Dateien existieren
ls assets/css/shared/
# Erwartung: variables.css, base.css, grid.css, tiles.css, header.css, footer.css, components.css

# Test 3: Kein hardcodiertes Grid-CSS mehr im Generator
grep -c "\.tile-grid {" backend/core/GeneratorService.php
# Erwartung: 0 (kein direktes CSS mehr im PHP)
```

---

## Phase 2: WYSIWYG Canvas & Tile-Renderer (JS)

> **Ziel:** `backend/v2/editor.php` das Tiles im echten CSS-Grid rendert. JS-Renderer pro Tile-Typ.

### Aufgaben
- [ ] `backend/v2/editor.php` – Minimal-Shell (Auth, Config, CSS-Links, JS-Links)
- [ ] `assets/js/v2/tile-renderer.js` – JS-Render-Funktion pro Tile-Typ
- [ ] `assets/js/v2/canvas.js` – Grid-Container, rendert Tiles via tile-renderer
- [ ] `assets/js/v2/state.js` – Zentraler State Store (tiles, settings, selectedTile)
- [ ] `assets/js/v2/api-client.js` – API-Wrapper (übernimmt Logik aus editor-core.js)
- [ ] API erweitern: `get_tile_types` liefert auch `fieldMeta` für JS-Rendering
- [ ] Editor-Chrome: Top-Bar mit Publish, Preview-Toggle, Settings-Button
- [ ] Login-Flow: nach Auth Weiterleitung zu v2-Editor (konfigurierbar)

### Erfolgskriterien
| # | Kriterium | Testmethode |
|---|-----------|-------------|
| 2.1 | `backend/v2/editor.php` lädt und zeigt Tiles im Grid | Browser öffnen → Tiles im 4-Spalten-Grid sichtbar |
| 2.2 | Grid ist responsive (4→2→1 Spalten) | Browser-Fenster verkleinern → Grid passt sich an |
| 2.3 | Jeder bestehende Tile-Typ wird korrekt gerendert | Alle Tiles in `tiles.json` werden angezeigt (Infobox, Download, Bild, Link, Iframe, Countdown, Accordion, Contact, Quote, Separator) |
| 2.4 | Tile-Größe, Stil, Farbschema korrekt dargestellt | Visueller Vergleich mit `?action=preview` |
| 2.5 | Unsichtbare Tiles sind als "gedimmt" erkennbar | Tiles mit `visible:false` ausgegraut |
| 2.6 | State Store hält Tiles synchron mit Darstellung | Console: `EditorStore.tiles` zeigt aktuelle Daten |

### Automatisierter Test
```bash
# Test 1: v2 Editor lädt (Auth via Debug-Mode)
curl -s -b cookies.txt "http://localhost:8000/backend/v2/editor.php" | grep -c "tile-grid"
# Erwartung: >= 1

# Test 2: Tile-Renderer registriert
curl -s "http://localhost:8000/backend/api/endpoints.php?action=get_tile_types" | python -m json.tool
# Erwartung: JSON mit allen Tile-Typen inkl. fields und fieldMeta

# Test 3: JS-Module laden ohne Fehler
# → Browser Console darf keine Fehler zeigen (manuelle Prüfung)
```

---

## Phase 3: Drag & Drop + Toolbar

> **Ziel:** Tiles per Drag verschieben. Floating Toolbar für Größe/Stil/Farbe bei Selektion.

### Aufgaben
- [ ] `assets/js/v2/drag-drop.js` – SortableJS Integration (CDN oder lokal)
- [ ] `assets/js/v2/toolbar.js` – Floating Toolbar bei Tile-Selektion
- [ ] Toolbar-Optionen: Größe (S/M/L/Full), Stil (Flat/Card), Farbe (5 Schemata), Löschen, Duplizieren
- [ ] "+"-Insert-Buttons zwischen Tiles (Neue Kachel einfügen)
- [ ] Tile-Typ-Auswahl bei Neu: Compact Grid/Liste der verfügbaren Typen
- [ ] Positionen nach Drag automatisch neu berechnen (Array-Index × 10)
- [ ] API-Call `update_positions` nach jeder Drag-Operation

### Erfolgskriterien
| # | Kriterium | Testmethode |
|---|-----------|-------------|
| 3.1 | Tiles per Drag verschiebbar | Tile anfassen → ziehen → neue Position → loslassen |
| 3.2 | Positionen werden nach Drag gespeichert | Seite neu laden → Reihenfolge bleibt |
| 3.3 | Toolbar erscheint bei Tile-Klick | Auf Tile klicken → Toolbar sichtbar |
| 3.4 | Größe ändern funktioniert via Toolbar | Toolbar → Größe "Large" → Tile wird breiter |
| 3.5 | Stil/Farbe ändern funktioniert | Toolbar → Stil "Flat" → Card-Shadow verschwindet |
| 3.6 | Neues Tile einfügen über "+" Button | "+" klicken → Typ wählen → Tile erscheint im Grid |
| 3.7 | Löschen mit Undo-Möglichkeit | Löschen → Toast "Kachel gelöscht – Rückgängig" |

### Automatisierter Test
```bash
# Test 1: Tile speichern nach Drag (simuliert)
curl -X POST "http://localhost:8000/backend/api/endpoints.php" \
  -d "action=update_positions&csrf_token=TOKEN&positions=[{\"id\":\"tile_123\",\"position\":10}]"
# Erwartung: {"success": true}

# Test 2: SortableJS geladen
# → Browser Console: typeof Sortable !== 'undefined'
```

---

## Phase 4: Inline-Editing

> **Ziel:** Texte direkt in Tiles bearbeiten. Bilder per Klick austauschen.

### Aufgaben
- [ ] `assets/js/v2/inline-edit.js` – ContentEditable Manager
- [ ] Text-Felder (title, description) direkt editierbar bei Doppelklick  
- [ ] Auto-Save nach Blur (Fokus verlassen) oder nach 2s Idle
- [ ] Bild-Felder: Klick → FileBrowser Modal → Bild austauschen
- [ ] Download-Felder: Klick auf Dateiname → FileBrowser Modal
- [ ] URL-Felder: Klick → Inline-Input-Overlay
- [ ] Checkbox-Felder (lightbox, showTitle): Toggle in Toolbar
- [ ] Komplexe Tiles (Accordion): Fallback auf Modal-Editor
- [ ] Visual Feedback: Editierbare Bereiche bei Hover hervorgehoben

### Erfolgskriterien
| # | Kriterium | Testmethode |
|---|-----------|-------------|
| 4.1 | Titel inline änderbar | Doppelklick auf Titel → Text ändern → Wegklicken → Gespeichert |
| 4.2 | Beschreibung inline änderbar | Doppelklick auf Text → Bearbeiten → Auto-Save |
| 4.3 | Bild per Klick austauschbar | Auf Bild klicken → FileBrowser → Neues Bild → Sofort sichtbar |
| 4.4 | Änderungen werden persistiert | Browser neu laden → Änderungen bestehen |
| 4.5 | Undo funktioniert (Ctrl+Z) | Text ändern → Ctrl+Z → Alter Text wiederhergestellt |
| 4.6 | Editierbare Bereiche visuell hervorgehoben | Hover über Text → blaue Outline/Hintergrund |

### Automatisierter Test
```bash
# Test 1: Tile speichern via API
curl -X POST "http://localhost:8000/backend/api/endpoints.php" \
  -d "action=save_tile&csrf_token=TOKEN&tile={\"id\":\"tile_123\",\"type\":\"infobox\",\"data\":{\"title\":\"Test\"}}"
# Erwartung: {"success": true, "tile": {...}}

# Test 2: Tiles laden und Änderung prüfen
curl -s "http://localhost:8000/backend/api/endpoints.php?action=get_tiles" | python -m json.tool
# → tile mit geändertem Titel prüfen
```

---

## Phase 5: Settings, Header/Footer, Polish

> **Ziel:** Vollständiger Editor-Ersatz. Header/Footer editierbar. Settings-Sidebar.

### Aufgaben
- [ ] Header direkt editierbar: Titel-Text klicken, Bild-Upload via Klick
- [ ] Footer direkt editierbar: Text klicken → inline bearbeiten
- [ ] Settings-Sidebar (statt Modal): Theme-Farben, Seitentitel, Fokuspunkt
- [ ] Undo/Redo Stack (`assets/js/v2/history.js`)
- [ ] Keyboard Shortcuts: N (neu), Del (löschen), Ctrl+Z/Y (undo/redo), Ctrl+S (publish)
- [ ] Publish-Flow: Button → Bestätigung → Generierung → Erfolgs-Toast
- [ ] Preview-Mode Toggle: Edit-Overlays ausblenden → exakte Vorschau
- [ ] Responsive Editor: Toolbar/Sidebar passt sich an mobile Viewports an
- [ ] Session-Timer + auto-extend (aus Classic Editor portieren)
- [ ] Admin-Verwaltung (aus Classic Editor portieren)
- [ ] Editor-Wahl nach Login: "Classic" vs "Visual" (Preference speichern)

### Erfolgskriterien
| # | Kriterium | Testmethode |
|---|-----------|-------------|
| 5.1 | Header-Titel direkt änderbar | Klick auf Header-Titel → Tippen → Gespeichert |
| 5.2 | Header-Bild per Klick austauschbar | Klick auf Header → Upload → Neues Bild |
| 5.3 | Footer-Text inline editierbar | Klick auf Footer → Text ändern → Speichern |
| 5.4 | Theme-Farben ändern live sichtbar | Sidebar → Akzentfarbe ändern → Grid-Hintergrund ändert sich |
| 5.5 | Undo/Redo funktioniert global | Mehrere Änderungen → Ctrl+Z → Schrittweise rückgängig |
| 5.6 | Publish generiert korrekte `index.html` | Publish → index.html öffnen → identisch mit Preview |
| 5.7 | Preview-Mode zeigt exakte Endansicht | Toggle → Keine Edit-UI sichtbar → Visuell identisch |
| 5.8 | Session wird korrekt verwaltet | 50 Min Inaktivität → Warnung → Verlängern möglich |
| 5.9 | Mobile Editor benutzbar | 375px Viewport → Toolbar erreichbar, Tiles editierbar |

### Automatisierter Test
```bash
# Test 1: Publish funktioniert
curl -X POST "http://localhost:8000/backend/api/endpoints.php" \
  -d "action=generate&csrf_token=TOKEN"
# Erwartung: {"success": true, "message": "Seite erfolgreich generiert"}

# Test 2: index.html existiert und enthält Tiles
grep -c "tile-grid" index.html
# Erwartung: >= 1

# Test 3: Settings speichern
curl -X POST "http://localhost:8000/backend/api/endpoints.php" \
  -d "action=save_settings&csrf_token=TOKEN&settings={\"site\":{\"title\":\"Testseite\"}}"
# Erwartung: {"success": true}
```

---

## Globale Erfolgskriterien (Definition of Done)

Der WYSIWYG-Editor gilt als **fertig**, wenn alle folgenden Kriterien erfüllt sind:

| # | Kriterium | Messbar |
|---|-----------|---------|
| G1 | Alle 10 Tile-Typen werden im Grid korrekt dargestellt | Visueller Vergleich |
| G2 | Tiles können per Drag & Drop verschoben werden | Interaktionstest |
| G3 | Texte (Titel, Beschreibung) inline editierbar | Interaktionstest |
| G4 | Bilder per Klick austauschbar | Interaktionstest |
| G5 | Tile-Größe/Stil/Farbe via Toolbar änderbar | Interaktionstest |
| G6 | Neue Tiles können eingefügt werden | Interaktionstest |
| G7 | Publish generiert identische `index.html` wie Classic Editor | Diff-Test |
| G8 | Undo/Redo funktioniert (mind. 20 Schritte) | Interaktionstest |
| G9 | Responsive: 4→2→1 Spalten im Editor-Canvas | Viewport-Test |
| G10 | Classic Editor bleibt vollständig funktionsfähig | Regressions-Test |
| G11 | Gleiche Datenbasis: `tiles.json` + `settings.json` kompatibel | Zwischen Editoren wechseln |
| G12 | Keine JS-Fehler in Browser Console | Console-Check |

---

## Technische Notizen

### Branch-Strategie
- **`main`** – Produktiver Stand, Classic Editor
- **`feature/wysiwyg-editor`** – Entwicklung WYSIWYG 
- Merge nach `main` erst wenn G1-G12 erfüllt

### Debug-Mode
- `config.php` → `DEBUG_MODE = true` auf diesem Branch
- Login-Code wird ohne E-Mail angezeigt → automatisierte Tests möglich
- Fehler sichtbar → schnelleres Debugging

### Dateien die NICHT geändert werden dürfen (Regressions-Schutz)
- `backend/editor.php` – Classic Editor bleibt unverändert
- `assets/js/editor-*.js` – Classic Editor JS bleibt unverändert
- `assets/css/editor.css` – Classic Editor CSS bleibt unverändert
- `backend/data/tiles.json` – Datenformat kompatibel halten
- `backend/data/settings.json` – Datenformat kompatibel halten

### Dateien die geändert werden
- `backend/core/GeneratorService.php` – CSS-Extraktion
- `backend/api/endpoints.php` – Kleine Erweiterungen (fieldMeta)
- `backend/tiles/TileBase.php` – Optional: `getClientRenderData()`
- `backend/config.php` – DEBUG_MODE=true

### Neue Dateien
```
assets/css/shared/          ← Phase 1
  variables.css
  base.css
  grid.css
  tiles.css
  header.css
  footer.css
  components.css
backend/v2/                 ← Phase 2
  editor.php
assets/js/v2/               ← Phase 2-5
  api-client.js
  state.js
  tile-renderer.js
  canvas.js
  drag-drop.js              ← Phase 3
  toolbar.js                ← Phase 3
  inline-edit.js            ← Phase 4
  history.js                ← Phase 5
assets/css/editor-v2.css    ← Phase 2
```
