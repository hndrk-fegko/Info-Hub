# WYSIWYG Editor Konzept – Info-Hub

> Analyse, Lessons Learned und drei Ausbau-Varianten  
> Stand: März 2026
>
> Historischer Konzeptstand vor dem Retirement des Classic-Editors. Verweise auf einen parallel gepflegten Classic-Client beschreiben die damalige Migrationsoption und nicht den aktuellen Laufzeitstand.

---

## 1. IST-Analyse des aktuellen Systems

### 1.1 Architektur-Übersicht

```
User (Backend)                              User (Frontend)
      │                                           │
      ▼                                           ▼
 ┌─────────┐    API      ┌──────────┐  generates  ┌───────────┐
 │ editor   │───────────▶│ endpoints │────────────▶│ index.html│
 │ (PHP+JS) │◀───────────│ (PHP)    │             │ (statisch)│
 └─────────┘   JSON      └──────────┘             └───────────┘
      │                        │
      ▼                        ▼
 ┌─────────┐            ┌───────────┐
 │ Modals   │            │ tiles.json│
 │ (Forms)  │            │settings   │
 └─────────┘            └───────────┘
```

### 1.2 Aktueller Editor-Flow

1. **Tile-Liste** – Listenansicht aller Kacheln mit Quick-Edit-Buttons (Position, Größe, Stil, Farbe, Sichtbarkeit)
2. **Modal-Editing** – Klick auf "Bearbeiten" öffnet ein Modal mit Formular-Feldern
3. **Preview** – Öffnet `endpoints.php?action=preview` in neuem Tab/Fenster
4. **Publish** – Ruft `endpoints.php?action=generate` auf → erzeugt statische `index.html`

### 1.3 Stärken des aktuellen Systems

| Stärke | Detail |
|--------|--------|
| **Modulares Tile-System** | Auto-Registry, TileBase, CSS/JS pro Tile-Typ |
| **Saubere Service-Architektur** | TileService, StorageService, GeneratorService getrennt |
| **File-based, kein DB** | Einfaches Deployment, JSON-basiert |
| **Security** | CSRF, Auth, Input-Validierung, CSP-Header |
| **Responsive Output** | 4→2→1 Column Grid, CSS Custom Properties |
| **Konfigurierbares Design-System** | 3 Akzentfarben, Flat/Card-Stil, Farbschemata |
| **Stabile API** | 15+ Endpoints, FormData + JSON unterstützt |

### 1.4 Schwächen / Gaps zum WYSIWYG

| Gap | Detail |
|-----|--------|
| **Listen-Editor ≠ visuelle Darstellung** | Kacheln werden als kompakte Listeneinträge angezeigt, nicht als Grid |
| **Kein Inline-Editing** | Jede Änderung erfordert Modal öffnen → Feld ändern → Speichern |
| **Preview ist losgelöst** | Separates Fenster, keine Live-Kopplung |
| **Keine Drag & Drop Sortierung** | Positionen nur numerisch anpassbar |
| **Kein visuelles Feedback** | Änderungen an Größe/Stil/Farbe sieht man erst in der Preview |
| **CSS doppelt** | GeneratorService enthält ~400 Zeilen inline CSS, editor.css ist komplett separat |
| **Tile-Rendering nur serverseitig (PHP)** | Für Live-Preview müsste JS die Tiles auch rendern können |

---

## 2. Lessons Learned

### 2.1 Was gut funktioniert (beibehalten!)

1. **TileBase + Registry-Pattern** – Neuen Tile-Typ in < 5 Minuten anlegen. Keine manuelle Registrierung. CSS/JS automatisch eingebunden. Dieses Pattern ist Gold wert.

2. **Service-Layer-Architektur** – StorageService als einziger File-I/O-Zugangspunkt. TileService für Business-Logic. GeneratorService für Output. Saubere Trennung.

3. **JSON als Datenbasis** – tiles.json und settings.json sind simpel, versionierbar, debuggbar. Kein DB-Overhead.

4. **Quick-Edit-Buttons im Editor** – Die Idee, häufige Änderungen (Position, Größe, Stil, Farbe) ohne Modal-Öffnung zu ermöglichen, ist UX-Gold. Muss in WYSIWYG übernommen werden.

5. **Atomares Schreiben** – `tmp + rename` Pattern im StorageService verhindert Datenverlust.

6. **Zeitsteuerung für Tiles** – `showFrom`/`showUntil` mit clientseitigem JS ist elegant.

### 2.2 Was man anders machen sollte

1. **CSS-Duplizierung vermeiden** – Das inline-CSS im GeneratorService ist eine exakte Kopie (quasi). Stattdessen: **Ein CSS-Modul** das sowohl der Editor-Preview als auch die generierte Seite verwenden.

2. **Tile-Rendering dual (PHP + JS)** – Aktuell rendert nur PHP die Tiles. Für WYSIWYG braucht man JS-seitige Renderfunktionen. Lösung: **Tile-Definitionsdateien, aus denen beide erzeugt werden**, oder **JS-only Rendering mit PHP für statischen Export**.

3. **Formular-Dialog-Flut reduzieren** – 5+ Modals (Tile, Settings, FileBrowser, InviteAdmin, KeyboardHelp) machen die UX schwer. WYSIWYG eliminiert das Tile-Modal fast komplett.

4. **Position als Zahl ist fragil** – Position 10, 20, 30... funktioniert, aber bei Drag & Drop braucht man Array-Index-basierte Sortierung.

5. **editor.js Modularisierung war richtig** – Aber die Module kommunizieren über globale Variablen (`tiles`, `settings`). Ein Event-Bus oder State-Store wäre robuster.

6. **Keine Undo-Funktion** – Bei WYSIWYG wo Änderungen direkt sichtbar sind, ist Undo/Redo essentiell.

### 2.3 Technische Schulden im aktuellen Code

- `editor.php` = 409 Zeilen mit viel inline HTML für Modals
- `GeneratorService.php` = 848 Zeilen mit ~500 Zeilen inline CSS
- `editor-modals.js` = 913 Zeilen, überladen
- Keine TypeScript-Typen, keine JS-Module (kein import/export)
- Emoji als Icons (🗑️) statt Icon-Library

---

## 3. WYSIWYG-Zielzustand (Vision)

### 3.1 Kernprinzip

> Der Editor zeigt **exakt das, was der Endnutzer sieht**: Das 4-Spalten-Grid, die Tiles in echtem Layout, Header, Footer – alles editierbar, alles live.

### 3.2 Interaktionsmodell

```
┌──────────────────────────────────────────────────────────────┐
│  ⚙️ Settings  │  👁 Preview-Mode  │  ✏️ Edit-Mode  │  🚀 Publish │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────────── HEADER ─────────────────────┐          │
│  │  [Titel direkt editieren]    📷 Bild ändern     │          │
│  └─────────────────────────────────────────────────┘          │
│                                                               │
│  ┌───────┐  ┌──────────────┐  ┌───────┐                     │
│  │Infobox│  │   Download   │  │ Bild  │  ← Echtes Grid      │
│  │ ────  │  │  ──────────  │  │       │                      │
│  │ Text  │  │  Datei.pdf   │  │ [img] │  ← Hover: Toolbar   │
│  │       │  │  [Download]  │  │       │    mit Edit/Resize   │
│  └───────┘  └──────────────┘  └───────┘                      │
│                                                               │
│  ─ ─ ─ ─ ─ ─ [➕ Kachel hier einfügen] ─ ─ ─ ─ ─ ─        │
│                                                               │
│  ┌──────────────────────────────────────┐                    │
│  │           Akkordeon (full)           │                    │
│  │  ▶ Punkt 1                          │  ← Drag-Handle     │
│  │  ▶ Punkt 2                          │    links            │
│  └──────────────────────────────────────┘                    │
│                                                               │
│  ┌─────────────── FOOTER ──────────────┐                     │
│  │   [Footer-Text direkt editieren]    │                     │
│  └─────────────────────────────────────┘                     │
└──────────────────────────────────────────────────────────────┘
```

### 3.3 Interaktions-Details

| Aktion | Aktuell | WYSIWYG |
|--------|---------|---------|
| **Text ändern** | Modal → Textarea | Direkt in der Kachel klicken & tippen |
| **Bild ändern** | Modal → File-Input | Auf Bild klicken → FileBrowser Overlay |
| **Größe ändern** | Quick-Edit Button | Drag am Rand ODER Toolbar-Button |
| **Position ändern** | Zahl eingeben | Drag & Drop der ganzen Kachel |
| **Stil ändern** | Quick-Edit Dropdown | Toolbar bei Hover/Selektion |
| **Farbe ändern** | Quick-Edit Dropdown | Toolbar bei Hover/Selektion |
| **Neue Kachel** | Button unten → Modal | "+" zwischen Kacheln → Typ wählen → inline editieren |
| **Löschen** | Button → confirm() | Toolbar → Mülleimer (mit Undo-Toast) |
| **Header/Footer** | Settings Modal | Direkt auf Header/Footer klicken |
| **Farben/Theme** | Settings Modal | Bleibt als Settings-Panel (Sidebar) |

### 3.4 Shared Design-System (Kern des WYSIWYG)

Das **exakt gleiche CSS** muss für Editor-Canvas und generierte Seite gelten:

```
shared/
  tile-grid.css        ← Grid, Sizes, Responsive Breakpoints
  tile-styles.css      ← card/flat, Farbschemata, Hover
  tile-types.css       ← Gesammelt aus *Tile.css Dateien
  header-footer.css    ← Header, Footer, Typography
  variables.css        ← CSS Custom Properties (zur Laufzeit gesetzt)
```

Der GeneratorService **inline-t** diese CSS-Dateien. Der Editor **lädt** sie als `<link>` oder `<style>`.

---

## 4. Drei Varianten im Vergleich

---

### 4.1 Variante A: Fork – Auf bestehendem Code aufsetzen

**Idee:** Repository forken. Neuen Editor entwickeln. Bestehender Code als Basis.

#### Architektur

```
info-hub/            (Fork)
├── index.html       # Generiert – gleich
├── backend/
│   ├── editor.php   # KOMPLETT NEU (WYSIWYG Canvas)
│   ├── api/
│   │   └── endpoints.php  # ERWEITERT (neue Actions)
│   ├── core/
│   │   ├── GeneratorService.php  # REFACTORED (nutzt shared CSS)
│   │   ├── TileService.php       # ~unverändert
│   │   └── ...
│   ├── tiles/       # ~unverändert (+ getEditorHtml() Methode)
│   └── data/        # ~unverändert
├── assets/
│   ├── css/
│   │   ├── shared/
│   │   │   ├── tile-grid.css
│   │   │   ├── tile-styles.css
│   │   │   └── variables.css
│   │   └── editor-chrome.css     # Editor-Rahmen (Toolbar, Sidebar)
│   └── js/
│       ├── wysiwyg/
│       │   ├── canvas.js         # Grid-Rendering, Tile-Platzierung
│       │   ├── inline-edit.js    # ContentEditable + Autosave
│       │   ├── drag-drop.js      # Sortable.js Integration
│       │   ├── toolbar.js        # Floating Toolbar pro Tile
│       │   ├── tile-renderer.js  # JS-seitige Tile-Darstellung
│       │   └── state.js          # Zentraler State + Undo/Redo
│       └── editor-core.js        # API-Helfer (aus bestehendem Code)
```

#### Umsetzungsschritte

1. **CSS extrahieren** – Inline-CSS aus GeneratorService in shared-Dateien auslagern (~2h)
2. **TileBase erweitern** – `getEditorTemplate()` Methode oder `getClientRenderData()` für JS (~3h)
3. **Canvas-Editor schreiben** – Neues `editor.php` das das Grid als Canvas rendert (~8h)
4. **JS Tile-Renderer** – Jeder Tile-Typ braucht eine JS-Render-Funktion (~6h)
5. **Drag & Drop** – SortableJS oder native DnD-API (~4h)
6. **Inline-Editing** – ContentEditable für Texte, Click-to-Upload für Bilder (~6h)
7. **Floating Toolbar** – Größe/Stil/Farbe/Löschen bei Selektion (~4h)
8. **Undo/Redo** – State-History Stack (~3h)
9. **GeneratorService refactorn** – Shared CSS nutzen statt inline (~2h)

#### Bewertung

| Kriterium | Score | Bemerkung |
|-----------|-------|-----------|
| **Aufwand** | ⭐⭐⭐ Mittel (~40h) | Backend+API wiederverwendbar, Frontend ~70% neu |
| **Risiko** | ⭐⭐ Gering-Mittel | Bestehende API stabil, neuer Editor isoliert |
| **Wartbarkeit** | ⭐⭐⭐ Gut | Fork kann divergieren, aber klare Basis |
| **Migration** | ⭐⭐⭐⭐ Einfach | Gleiche Datenbasis, sofort kompatibel |
| **Dual-Rendering** | ⭐⭐ Aufwändig | PHP render() + JS render() synchron halten |

#### Pro
- Schnellster Weg zum Ergebnis
- Bestehende API, Auth, Service-Layer 1:1 nutzbar
- Tile-Typen, Settings, Datenbasis bleiben gleich
- Alte Editor-Version als Fallback verfügbar

#### Contra
- Fork divergiert langfristig vom Original
- Dual-Rendering (PHP+JS) muss synchron gehalten werden
- Bestehende Code-Schulden werden mitgeschleppt (inline CSS im Generator)
- Keine echte Modularisierung (kein ES Modules, kein Build-System)

---

### 4.2 Variante B: Integration – Paralleler Editor im gleichen Projekt

**Idee:** WYSIWYG-Editor als zusätzliche Option unter `backend/v2/`. Gleiche Datenbasis, gleiche API. User wählt zwischen "Classic" und "Visual" Editor.

#### Architektur

```
info-hub/
├── index.html
├── backend/
│   ├── login.php              # Login bleibt – leitet zu gewähltem Editor
│   ├── editor.php             # Classic Editor (unverändert)
│   ├── v2/
│   │   ├── editor.php         # WYSIWYG Editor (neu)
│   │   └── assets/            # Optional: v2-spezifische Assets
│   ├── api/
│   │   └── endpoints.php      # Erweitert: neue Actions für WYSIWYG
│   ├── core/                  # Unverändert (evtl. kleine Erweiterungen)
│   ├── tiles/                 # Erweitert: getClientRenderData()
│   └── data/                  # Gleiche Datenbasis!
├── assets/
│   ├── css/
│   │   ├── shared/            # NEU: Geteiltes CSS
│   │   ├── editor.css         # Classic Editor CSS
│   │   └── editor-v2.css      # WYSIWYG Editor CSS
│   └── js/
│       ├── editor-*.js        # Classic Editor JS (unverändert)
│       └── v2/
│           ├── wysiwyg-app.js # WYSIWYG Hauptlogik
│           ├── canvas.js
│           ├── drag-drop.js
│           ├── inline-edit.js
│           ├── toolbar.js
│           └── state.js
```

#### Umsetzungsschritte

1. **Editor-Wahl in Login** – Nach Auth: "Classic Editor" oder "Visual Editor" (~1h)
2. **Shared CSS extrahieren** – Wie Variante A (~2h)
3. **v2/editor.php** – Neues WYSIWYG-Interface (~8h)
4. **JS-Module für v2** – Canvas, DnD, Inline-Edit, Toolbar (~18h)
5. **API erweitern** – `get_render_data` Action die Tile-Daten + Render-Hints für JS zurückgibt (~2h)
6. **TileBase erweitern** – `getClientRenderData(): array` für JS-Rendering (~3h)
7. **Settings-Sidebar statt Modal** – Für v2 eine Sidebar (~3h)
8. **User-Preference speichern** – Welchen Editor der User bevorzugt (~1h)

#### Bewertung

| Kriterium | Score | Bemerkung |
|-----------|-------|-----------|
| **Aufwand** | ⭐⭐⭐ Mittel (~40h) | Sehr ähnlich zu Variante A |
| **Risiko** | ⭐ Sehr gering | Classic Editor als Fallback, gleiche Datenbasis |
| **Wartbarkeit** | ⭐⭐⭐⭐ Sehr gut | Ein Repository, geteilte Services & API |
| **Migration** | ⭐⭐⭐⭐⭐ Null-Risiko | Beide Editoren parallel nutzbar |
| **Dual-Rendering** | ⭐⭐ Aufwändig | Wie Variante A |

#### Pro
- **Kein Breaking Change** – Classic Editor bleibt vollständig erhalten
- **Gleiche Datenbasis** – Wechsel zwischen Editoren jederzeit möglich
- **Schrittweiser Rollout** – v2 kann als "Beta" angeboten werden
- **Ein Repository** – Kein Fork-Drift
- **API-Erweiterungen kommen beiden zugute** – z.B. bessere Tile-Type-Metadaten

#### Contra
- Zwei Editor-Frontends pflegen (bis Classic abgelöst wird)
- `assets/` wird größer (zwei JS/CSS-Sets)
- Shared CSS muss von beiden Editoren korrekt genutzt werden
- Komplexere Routing-Logik nach Login

---

### 4.3 Variante C: Rewrite – Komplett neues Projekt

**Idee:** Lessons Learned nutzen, auf modernem Stack (z.B. Svelte/Vue/React SPA, oder Vanilla JS mit ES Modules + Build-Tool) komplett neu entwickeln.

#### Architektur-Vorschlag (Modern Stack)

```
info-hub-v2/
├── index.html                # Generiert – gleich wie bisher
├── frontend/                 # SPA Build-Output
│   └── editor/
│       ├── index.html
│       ├── app.js            # Bundled
│       └── app.css           # Bundled
├── src/                      # Source (wird compiled)
│   ├── components/
│   │   ├── Canvas.js         # WYSIWYG Grid-Canvas
│   │   ├── TileRenderer.js   # Rendert alle Tile-Typen
│   │   ├── Toolbar.js        # Floating Toolbar
│   │   ├── Sidebar.js        # Settings Panel
│   │   ├── DragDrop.js       # DnD Handler
│   │   └── InlineEdit.js     # ContentEditable Wrapper
│   ├── tiles/                # Tile-Definitionen (JS-First)
│   │   ├── registry.js
│   │   ├── InfoboxTile.js
│   │   ├── ImageTile.js
│   │   └── ...
│   ├── state/
│   │   ├── store.js          # Zentraler State
│   │   └── history.js        # Undo/Redo
│   ├── api/
│   │   └── client.js         # API-Client
│   ├── styles/
│   │   ├── shared/           # Tiles CSS
│   │   └── editor/           # Editor Chrome CSS
│   └── app.js                # Entry Point
├── backend/
│   ├── api/
│   │   └── endpoints.php     # API (vereinfacht, da SPA)
│   ├── core/
│   │   ├── GeneratorService.php  # Nutzt shared CSS + Tile-Defs
│   │   └── ...
│   ├── tiles/                # PHP-Tile-Klassen (nur für Generator)
│   └── data/
├── build/                    # Vite/esbuild Config
│   └── vite.config.js
└── package.json
```

#### Architektur-Vorschlag (Vanilla JS, kein Build)

```
info-hub-v2/
├── index.html
├── backend/
│   ├── editor.php            # SPA-Shell (minimal HTML)
│   ├── api/endpoints.php     # Erweiterte API
│   ├── core/                 # PHP Services (wie bisher)
│   ├── tiles/                # PHP Tiles + JSON-Metadaten
│   │   ├── TileBase.php
│   │   ├── InfoboxTile.php
│   │   └── tile-defs.json    # NEU: Maschinen-lesbare Tile-Definitionen
│   └── data/
├── assets/
│   ├── css/
│   │   ├── shared/           # Geteiltes Tile-CSS
│   │   └── editor/           # Editor-Rahmen
│   └── js/
│       ├── app.js            # Hauptmodul (type="module")
│       ├── components/       # Web Components oder Klassen
│       │   ├── WysiwygCanvas.js
│       │   ├── TileToolbar.js
│       │   ├── InlineEditor.js
│       │   └── DragHandler.js
│       ├── tiles/            # JS Tile Renderer
│       │   ├── tile-registry.js
│       │   ├── InfoboxTile.js
│       │   └── ...
│       ├── state/
│       │   ├── store.js
│       │   └── history.js
│       └── api/
│           └── client.js
```

#### Umsetzungsschritte

1. **Tile-Definition-Format definieren** – JSON Schema das PHP+JS Rendering steuert (~4h)
2. **Shared CSS als Dateien** – Alles aus GeneratorService extrahieren (~3h)
3. **JS State Management** – Store + History/Undo (~4h)
4. **JS Tile-Renderer** – Pro Tile-Typ eine render()-Funktion (~8h)
5. **WYSIWYG Canvas** – Grid, Drag&Drop, Insert-Points (~10h)
6. **Inline-Editing** – ContentEditable, Rich-Text für description-Felder (~8h)
7. **Floating Toolbar** – Kontextmenü pro Tile (~4h)
8. **Settings Sidebar** – Header, Footer, Theme (~4h)
9. **API Refactoring** – Schlankere Endpoint-Struktur (~3h)
10. **GeneratorService Rewrite** – PHP liest Tile-Defs + Shared CSS (~4h)
11. **Auth/Security** – Aus bestehendem Code portieren (~2h)
12. **Testing & QA** – (~8h)

#### Bewertung

| Kriterium | Score | Bemerkung |
|-----------|-------|-----------|
| **Aufwand** | ⭐ Hoch (~60-80h) | Alles neu, auch wenn Patterns übernommen werden |
| **Risiko** | ⭐⭐⭐ Mittel | Second System Syndrome möglich, aber Lessons Learned vorhanden |
| **Wartbarkeit** | ⭐⭐⭐⭐⭐ Exzellent | Saubere Architektur von Anfang an |
| **Migration** | ⭐⭐⭐ Mittel | tiles.json + settings.json kompatibel halten |
| **Dual-Rendering** | ⭐⭐⭐⭐ Gut | Tile-Definitions als Single Source of Truth |

#### Pro
- **Sauberste Architektur** – Keine technischen Schulden ab Tag 1
- **ES Modules** – Echte Importe, kein globaler Namespace
- **Single Source of Truth für Tiles** – JSON-Definition → PHP + JS Renderer generiert
- **Undo/Redo von Anfang an** – State Management als Fundament
- **Modern Tooling möglich** – TypeScript, Vite, etc. (optional)
- **Kein Dual-Rendering-Problem** – Tile-Defs steuern beides

#### Contra
- **Höchster Aufwand** – 60-80h statt 40h
- **Regressions-Risiko** – Alle Features müssen neu implementiert werden
- **Second System Syndrome** – Tendenz zur Überarchitektur
- **Build-Step** – Wenn SPA-Framework gewählt wird, braucht man Node.js
- **Bestehende Instanzen** – Migration nötig (wenn auch nur Datenformat)

---

## 5. Gesamtvergleich

| Kriterium | A: Fork | B: Integration | C: Rewrite |
|-----------|---------|----------------|------------|
| **Aufwand** | ~40h | ~40h | ~60-80h |
| **Risiko** | Mittel | Sehr gering | Mittel |
| **Time-to-MVP** | 2-3 Wochen | 2-3 Wochen | 4-6 Wochen |
| **Wartbarkeit** | Gut | Sehr gut | Exzellent |
| **Migrations-Aufwand** | Null | Null | Gering |
| **Fallback** | Git-Branch | Classic Editor | Altes Projekt |
| **Langfrist-Qualität** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Passt zum Projekt-Geist** | ✅ | ✅✅ | ⚠️ Overkill? |

---

## 6. Empfehlung

### Empfohlen: **Variante B (Integration)** mit **Upgrade-Pfad zu C**

#### Begründung

1. **Null-Risiko-Migration**: Classic Editor bleibt. Die bestehende produktive Instanz funktioniert weiterhin.

2. **Gleiche Datenbasis**: `tiles.json` und `settings.json` werden von beiden Editoren gelesen/geschrieben. Ein Wechsel zwischen Classic und WYSIWYG ist jederzeit möglich.

3. **Schrittweise Ablösung**: Der Classic Editor kann als Fallback für Edge-Cases bestehen bleiben (z.B. komplexe Akkordeon-Konfiguration), während Standard-Editing im WYSIWYG passiert.

4. **Passt zum Projekt-Geist**: Info-Hub wurde als "ultra-schlankes, file-based CMS" konzipiert. Variante B respektiert das: kein Build-Tool, kein Framework, Vanilla JS + PHP.

5. **Upgrade-Pfad**: Wenn der WYSIWYG-Editor stabil ist und der Classic Editor nicht mehr gebraucht wird, kann man `backend/v2/` zum neuen Standard machen und den alten entfernen. Das ist quasi der organische Weg zu Variante C.

#### Konkret: Phase 1 → Phase 2

**Phase 1 (MVP WYSIWYG, ~25h)**
- Shared CSS extrahieren
- `backend/v2/editor.php` mit Canvas-Grid
- JS Tile-Renderer (nutzt `getAvailableTypes()` API-Daten)
- Drag & Drop mit SortableJS
- Inline-Editing für Texte (ContentEditable)
- Floating Toolbar (Größe, Stil, Farbe, Löschen)
- "+" Buttons zwischen Tiles

**Phase 2 (Polish, ~15h)**
- Undo/Redo
- Inline Image-Upload (Drag auf Tile)
- Header/Footer direkt editierbar
- Settings-Sidebar statt Modal
- Mobile-optimierter WYSIWYG
- Classic Editor Deprecation-Banner

---

## 7. Technische Detailkonzepte (für Umsetzung)

### 7.1 Shared CSS Extraktion

```php
// GeneratorService.php – VORHER:
$html = "...<style>" . $this->getInlineCSS() . "</style>...";

// GeneratorService.php – NACHHER:
$sharedCSS = file_get_contents(__DIR__ . '/../../assets/css/shared/tile-grid.css');
$sharedCSS .= file_get_contents(__DIR__ . '/../../assets/css/shared/tile-styles.css');
$sharedCSS .= $this->collectTileCSS(); // Bereits vorhanden
$html = "...<style>:root{...}" . $sharedCSS . "</style>...";
```

### 7.2 JS Tile-Renderer Konzept

```javascript
// v2/tiles/tile-registry.js
const TileRenderers = {
    infobox: (data) => {
        const title = data.showTitle !== false ? `<h3>${esc(data.title)}</h3>` : '';
        const desc = data.description ? `<p>${nl2br(esc(data.description))}</p>` : '';
        return title + desc;
    },
    image: (data) => {
        const img = `<img src="${esc(data.image)}" alt="${esc(data.caption || '')}">`;
        const caption = data.caption ? `<p class="tile-caption">${esc(data.caption)}</p>` : '';
        return img + caption;
    },
    // ... pro Tile-Typ
};

// Canvas rendert Tile:
function renderTileInCanvas(tile) {
    const renderer = TileRenderers[tile.type];
    if (!renderer) return '<p>Unbekannter Typ</p>';
    
    const wrapper = document.createElement('div');
    wrapper.className = `tile tile-${tile.type} size-${tile.size} style-${tile.style} color-${tile.colorScheme}`;
    wrapper.innerHTML = renderer(tile.data);
    wrapper.dataset.tileId = tile.id;
    
    return wrapper;
}
```

### 7.3 Inline-Editing Konzept

```javascript
// Texte: ContentEditable
function enableInlineEdit(tileElement, tileId) {
    const editables = tileElement.querySelectorAll('h3, p');
    editables.forEach(el => {
        el.contentEditable = true;
        el.addEventListener('blur', () => {
            // Auto-Save: Textänderung an API senden
            const field = el.tagName === 'H3' ? 'title' : 'description';
            autoSaveTileField(tileId, field, el.textContent);
        });
    });
}

// Bilder: Click-to-Replace
function enableImageEdit(tileElement, tileId) {
    const imgs = tileElement.querySelectorAll('img');
    imgs.forEach(img => {
        img.style.cursor = 'pointer';
        img.addEventListener('click', () => openFileBrowser(tileId, 'image'));
    });
}
```

### 7.4 Drag & Drop Konzept

```javascript
// SortableJS Integration (3KB gzipped, kein Framework nötig)
import Sortable from 'sortablejs'; // oder CDN

const grid = document.querySelector('.tile-grid');
const sortable = new Sortable(grid, {
    handle: '.drag-handle',  // Nur am Handle ziehbar
    animation: 150,
    ghostClass: 'tile-ghost',
    onEnd: (evt) => {
        // Neue Reihenfolge an API senden
        const newOrder = [...grid.children].map((el, i) => ({
            id: el.dataset.tileId,
            position: (i + 1) * 10
        }));
        apiPost('update_positions', { positions: newOrder });
    }
});
```

### 7.5 State Management Konzept

```javascript
// v2/state/store.js
class EditorStore {
    #state = { tiles: [], settings: {}, selectedTileId: null };
    #history = [];
    #historyIndex = -1;
    #listeners = new Set();
    
    get tiles() { return this.#state.tiles; }
    get settings() { return this.#state.settings; }
    
    dispatch(action, payload) {
        // Snapshot vor Änderung (für Undo)
        this.#pushHistory();
        
        switch(action) {
            case 'SET_TILES': this.#state.tiles = payload; break;
            case 'UPDATE_TILE': /* ... */ break;
            case 'MOVE_TILE': /* ... */ break;
            case 'DELETE_TILE': /* ... */ break;
        }
        
        this.#notify();
    }
    
    undo() { /* historyIndex-- und state wiederherstellen */ }
    redo() { /* historyIndex++ und state wiederherstellen */ }
    
    subscribe(fn) { this.#listeners.add(fn); }
    #notify() { this.#listeners.forEach(fn => fn(this.#state)); }
    #pushHistory() { /* ... */ }
}
```

---

## 8. Einschränkungen & Design-Entscheidungen

### 8.1 Begrenztes Farb-/Stil-System (bewusst!)

Das aktuelle System bietet bewusst eine **begrenzte Auswahl**:
- **2 Tile-Stile**: Flat, Card
- **5 Farbschemata**: Default, Weiß, Akzent 1/2/3
- **4 Größen**: Small (1/4), Medium (1/2), Large (3/4), Full
- **3 Akzentfarben** global konfigurierbar

Diese Begrenzung ist eine **Feature, kein Bug** – sie erzwingt ein einheitliches Erscheinungsbild. Im WYSIWYG-Editor muss diese Begrenzung **beibehalten** werden:

- Keine freie Farbwahl pro Tile → weiterhin nur die 5 Schemata
- Keine freie Schriftgröße → weiterhin vom Tile-Typ bestimmt
- Keine freie Spacing-Werte → weiterhin CSS Grid mit festen Gaps

### 8.2 WYSIWYG ≠ Free-form Page Builder

Info-Hub ist ein **Tile-based CMS**, kein freier Page-Builder wie Wix oder Squarespace. Der WYSIWYG-Editor zeigt die Tiles im echten Grid, aber der User kann:
- ✅ Tiles verschieben, Größe ändern, Texte inline bearbeiten
- ❌ NICHT: freie Positionierung, Overlaps, Custom HTML
- ❌ NICHT: freie Schrift-/Farbwahl pro Element

Das hält die Komplexität beherrschbar und das Output konsistent.

### 8.3 PHP bleibt für statische Generierung

Auch mit JS-Rendering im Editor bleibt PHP für die statische HTML-Generierung zuständig. Das ist OK:
- Der Generator läuft nur bei "Publish" (nicht bei jedem Edit)
- PHP-Rendering ist der "Gold-Standard", JS-Rendering die "Preview"
- Kleine Abweichungen zwischen JS-Preview und PHP-Output sind akzeptabel

---

## 9. Zusammenfassung

| | Aufwand | Risiko | Empfehlung |
|---|---------|--------|------------|
| **A: Fork** | 40h | Mittel | ⚪ Möglich, aber Fork-Drift |
| **B: Integration** | 40h | Sehr gering | 🟢 **EMPFOHLEN** |
| **C: Rewrite** | 60-80h | Mittel | ⚪ Nur wenn langfristig geplant |

**Variante B** ist der pragmatische Weg: Gleiche Datenbasis, gleiche API, Classic Editor als Fallback, schrittweise Migration zum WYSIWYG. Das passt zum Projekt-Geist von Info-Hub: schnell, schlank, wartbar.
