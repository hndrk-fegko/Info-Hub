# Info-Hub: Modulares Informationssystem
> Ein self-hosted, file-based Content Management System für schnelle Informationsseiten

## 🎯 Vision & Problemstellung

### Das Problem
In der Gemeindearbeit und Projektorganisation entstehen immer wieder temporäre oder thematische Informationsbedarfe:
- **Jahrgänge des Biblischen Unterrichts**: Anmeldung, Freizeiten, Aktionen, Downloads
- **Technologie-Dokumentation**: Nextcloud, ChurchTools, App, Website - Zugänge, Zwecke, Links
- **Event-Seiten**: Veranstaltungsinfos, Anmeldungen, Materialien
- **Onboarding-Seiten**: Neue Mitarbeiter, Teaminfos

**Aktuelles Problem**: Für jede neue Infoseite braucht man entweder ein vollwertiges CMS (Overhead) oder baut HTML-Seiten manuell (nicht wartbar). 

### Die Lösung
Ein **ultra-schlankes, file-based CMS** das:
- In **< 5 Minuten** auf einer Subdomain deployt ist
- **Keine Datenbank** benötigt (nur PHP + Webspace)
- Über einen **visuellen Editor** pflegbar ist
- **Statische HTML** generiert (schnell, SEO-freundlich, sicher)
- Mit einem **kryptischen Backend-URL** geschützt ist

---

## 🏗️ Architektur

### Dateistruktur
```
/infohub/
├── index.html                    # Generierte statische Seite (öffentlich)
├── .htaccess                     # Sicherheit: Schützt /backend/
├── backend/
│   ├── editor-a8f3n2k9.php      # Editor-Interface (kryptischer Name)
│   ├── api.php                   # Backend-API für CRUD-Operationen
│   ├── generator.php             # HTML-Generator
│   ├── data/
│   │   └── tiles.json           # Kachel-Daten (single source of truth)
│   ├── media/
│   │   ├── images/              # Hochgeladene Bilder
│   │   ├── downloads/           # Download-Dateien
│   │   └── icons/               # Tile-Icons
│   └── archive/
│       ├── 2026-01-09_14-30.html
│       └── 2026-01-08_09-15.html
├── assets/
│   ├── css/
│   │   └── style.css            # Frontend-Styling
│   └── js/
│       └── lightbox.js          # Lightbox-Funktionalität
```

### .htaccess Konfiguration
```apache
# Schütze /backend/ komplett
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^backend/(?!editor-a8f3n2k9\.php$) - [F,L]
</IfModule>

# Verbiete direkten Zugriff auf .json Dateien
<FilesMatch "\.(json)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

## 🎨 UX-Konzept: Der Editor

### Design-Philosophie
**"WordPress Block Editor trifft Notion"**
- Drag & Drop für intuitives Arbeiten
- Live-Preview während der Bearbeitung
- Inline-Editing wo möglich
- Mobile-friendly Editor (Responsive)

### Editor-Interface

#### 1. Hauptansicht (Tile-Grid)
```
┌─────────────────────────────────────────────────────────┐
│  Info-Hub Editor                    [Preview] [Publish] │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌─────┐ ┌──────────┐ ┌─────┐ ┌─────┐                 │
│  │ 1x1 │ │   1x2    │ │ 1x1 │ │ 1x1 │  ← Desktop: 4 Spalten│
│  └─────┘ └──────────┘ └─────┘ └─────┘                 │
│  [+] Neue Kachel hinzufügen                            │
│                                                          │
│  ┌──────────────────────┐                              │
│  │        2x2           │                              │
│  │                      │                              │
│  └──────────────────────┘                              │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**Interaktionen**:
- **Hover**: Zeigt Bearbeiten/Löschen-Icons
- **Drag Handle**: 6-Punkt-Icon zum Verschieben
- **Click**: Öffnet Inline-Editor (wenn möglich) oder Modal
- **Double-Click**: Toggle zwischen Bearbeitungs- und Preview-Modus

#### 2. Tile-Bearbeitungsmodal
```
┌─────────────────────────────────────────────────────────┐
│  Kachel bearbeiten                            [✕ Schließen] │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Typ: [Download ▼] [Infobox] [Bildbox] [Video] [Event] │
│       ↑ Tab-Navigation                                  │
│                                                          │
│  ┌─ ALLE Felder (persistent) ────────────────────────┐ │
│  │ 📁 Datei/Bild:  [Auswählen] [Hochladen]           │ │
│  │ 📝 Titel:       [________________]  ← aktiv       │ │
│  │ 📄 Text:        [________________]  ← ausgegraut  │ │
│  │ 🔗 Link:        [________________]  ← ausgegraut  │ │
│  │ 📅 Datum:       [________________]  ← ausgegraut  │ │
│  │ 📏 Größe:       [1x1 ▼] [1x2] [1x4] [2x2] [2x4]  │ │
│  └───────────────────────────────────────────────────┘ │
│                                                          │
│  💡 Hinweis: Ausgegraute Felder werden für "Download"  │
│     nicht verwendet, bleiben aber gespeichert           │
│                                                          │
│  [🗑️ Nicht benötigte Felder leeren]                    │
│                                                          │
│  [Abbrechen]                        [Speichern]         │
└─────────────────────────────────────────────────────────┘
```

**UX-Features**:
- **Feldvalidierung**: Echtzeit-Feedback bei Pflichtfeldern
- **Bild-Upload**: Drag & Drop mit Preview
- **Icon-Picker**: Für Download-Tiles (FontAwesome oder Custom)
- **Live-Preview**: Rechte Sidebar zeigt Tile-Vorschau
- **Smart Defaults**: Vorschläge basierend auf Dateiendung (PDF → Download-Icon)

---

## 📦 Tile-Typen & Datenmodell

### Universelles Tile-Objekt
```json
{
  "id": "tile_1704810000",
  "type": "download|infobox|image|video|event|link|countdown",
  "position": 0,
  "size": "1x1|1x2|1x4|2x2|2x4",
  "data": {
    "title": "Beispiel-Titel",
    "text": "Mehrzeiliger Text mit Markdown-Support",
    "file": "/backend/media/downloads/dokument.pdf",
    "image": "/backend/media/images/bild.jpg",
    "icon": "fa-file-pdf",
    "link": "https://example.com",
    "linkText": "Mehr erfahren",
    "date": "2026-06-15",
    "dateEnd": "2026-06-20",
    "lightbox": true,
    "color": "#3498db",
    "backgroundColor": "#ecf0f1"
  },
  "metadata": {
    "created": "2026-01-09T14:30:00Z",
    "modified": "2026-01-09T15:45:00Z",
    "author": "admin"
  }
}
```

### 1. Download-Tile
**Anwendungsfall**: PDFs, Formulare, Vorlagen
```
┌─────────────┐
│  📄 PDF     │
│             │
│  Anmeldung  │
│  Freizeit   │
│  2026.pdf   │
└─────────────┘
```
**Aktive Felder**: `file`, `icon`, `title`  
**Optionale Felder**: `text` (Beschreibung), `backgroundColor`

### 2. Infobox-Tile
**Anwendungsfall**: Textinformationen, Hinweise, Ankündigungen
```
┌───────────────────────┐
│ 📢 Wichtiger Hinweis  │
│                       │
│ Die Anmeldung für    │
│ die Sommerfreizeit   │
│ läuft bis 31. März.  │
│                       │
│ [Jetzt anmelden →]   │
└───────────────────────┘
```
**Aktive Felder**: `title`, `text`, `link`, `linkText`, `icon`  
**Optionale Felder**: `color`, `backgroundColor`

### 3. Bildbox-Tile
**Anwendungsfall**: Galerien, Team-Fotos, Impressionen
```
┌───────────────────────┐
│                       │
│   [Schönes Bild]     │
│                       │
│ Gemeindefreizeit 2025│
│                       │
└───────────────────────┘
```
**Aktive Felder**: `image`, `title`, `lightbox`  
**Optionale Felder**: `text`, `link`

### 4. Video-Tile
**Anwendungsfall**: YouTube-Embeds, Vimeo, selbst gehostete Videos
```
┌───────────────────────┐
│   ▶️ [Video-Thumb]    │
│                       │
│ Jahresrückblick 2025 │
└───────────────────────┘
```
**Aktive Felder**: `link` (YouTube-URL), `title`, `image` (Custom Thumbnail)  
**Besonderheit**: Auto-Embed von YouTube/Vimeo-Links

### 5. Event-Tile
**Anwendungsfall**: Veranstaltungen mit Datum
```
┌───────────────────────┐
│ 📅 15. Juni 2026      │
│                       │
│ Gemeindefest          │
│                       │
│ Anmeldung bis 1. Juni│
│ [Details →]          │
└───────────────────────┘
```
**Aktive Felder**: `date`, `dateEnd`, `title`, `text`, `link`  
**Besonderheit**: Farbliches Highlighting bei näher rückenden Events

### 6. Link-Tile (NEU)
**Anwendungsfall**: Externe Ressourcen, Tools
```
┌─────────────┐
│  🔗 Tools   │
│             │
│ ChurchTools │
│ Login       │
└─────────────┘
```
**Aktive Felder**: `link`, `title`, `icon`  
**Besonderheit**: Extern-Icon automatisch

### 7. Countdown-Tile (NEU)
**Anwendungsfall**: Anmeldeschluss, Event-Start
```
┌───────────────────────┐
│ ⏰ Noch 45 Tage       │
│                       │
│ Bis zur Anmeldung    │
│ Sommerfreizeit        │
└───────────────────────┘
```
**Aktive Felder**: `date`, `title`, `text`  
**Besonderheit**: JavaScript-Countdown

---

## 🎛️ Advanced Features

### 1. Flexible Attribut-Verwaltung
**Problem**: User ändert Tile-Typ von "Infobox" zu "Download" - Text geht verloren?  
**Lösung**: 
- Alle Felder bleiben im JSON erhalten
- Beim Typ-Wechsel werden Felder nur ausgegraut, nicht gelöscht
- Button "Nicht benötigte Felder leeren" für Aufräum-Aktion

**UX-Flow**:
```
1. User erstellt Infobox mit Titel + Text
2. User ändert Typ zu "Download"
3. System graut Text-Feld aus (bleibt gespeichert)
4. User lädt Datei hoch
5. Optional: User klickt "Felder leeren" → Text wird gelöscht
```

### 2. Drag & Drop Reordering
**Technologie**: SortableJS oder native HTML5 Drag & Drop  
**Verhalten**:
- Grid passt sich automatisch an (CSS Grid)
- Ghost-Element zeigt Zielposition
- Touch-Support für Mobile

### 3. Live-Preview
**Zwei Modi**:
1. **Inline-Preview**: Tile wird im Editor wie im Frontend dargestellt
2. **Vollbild-Preview**: Öffnet generierte HTML in neuem Tab

**Technologie**: AJAX-Call an `generator.php?mode=preview`

### 4. Versionierung
**Automatische Archive**:
- Bei jedem "Publish" wird alte `index.html` archiviert
- Format: `YYYY-MM-DD_HH-MM.html`
- Restore-Funktion im Editor

---

## 🔐 Sicherheitskonzept

### 1. Backend-Schutz
**Methode**: Security by Obscurity + .htaccess
- Editor-URL: `backend/editor-a8f3n2k9.php` (8-stelliger Random-String)
- `.htaccess` blockiert alle anderen Backend-Dateien
- Kein Login-System (→ weniger Angriffsfläche)
Bewerte die Sicherheit: 2FA? Login.php versendet einen temporären Code per Email an hinterlegte Adresse? (Setup Prozess nötig um Mail festzulegen oder Settings Dialog im Editor. Auch sinnvoll für Headline, Footer, Hintergrund etc.)

**Deployment**:
```php
// config.php - wird beim Setup generiert
define('SECRET_TOKEN', bin2hex(random_bytes(16)));
```

### 2. Upload-Validierung
```php
// Erlaubte Dateitypen
$allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$allowed_downloads = ['pdf', 'docx', 'xlsx', 'zip'];

// Maximale Dateigrößen
$max_image_size = 5 * 1024 * 1024; // 5MB
$max_download_size = 50 * 1024 * 1024; // 50MB

// Dateinamen sanitizen
$safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
```

### 3. XSS-Schutz
```php
// Alle User-Inputs werden escaped
function sanitize_tile_data($data) {
    return [
        'title' => htmlspecialchars($data['title'] ?? '', ENT_QUOTES),
        'text' => strip_tags($data['text'], '<p><br><strong><em><ul><li>'),
        'link' => filter_var($data['link'], FILTER_VALIDATE_URL),
        // ...
    ];
}
```

### 4. CSRF-Schutz
```php
// Session-basierte CSRF-Tokens
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

---

## 📱 Responsive Design

### Grid-System
```css
/* Desktop: 4 Spalten */
.tile-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

/* Tablet: 2 Spalten */
@media (max-width: 768px) {
    .tile-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    /* 1x1 Tiles bleiben 1x1 */
    /* 1x2 Tiles werden zu 2x1 (volle Breite) */
    /* 2x2 Tiles werden zu 2x2 */
}

/* Mobile: 1 Spalte */
@media (max-width: 480px) {
    .tile-grid {
        grid-template-columns: 1fr;
    }
}
```

### Tile-Größen
| Desktop (4 Spalten) | Tablet (2 Spalten) | Mobile (1 Spalte) |
|---------------------|-------------------|-------------------|
| 1x1 → 1 Zelle       | 1x1 → 1 Zelle     | Volle Breite      |
| 1x2 → 2 Zellen      | 1x2 → 2 Zellen    | Volle Breite      |
| 1x4 → 4 Zellen      | 1x4 → 2 Zellen    | Volle Breite      |
| 2x2 → 2x2 Block     | 2x2 → 2x2 Block   | Volle Breite      |
| 2x4 → 2x4 Block     | 2x4 → 2x2 Block   | Volle Breite      |

---

## 🚀 Deployment-Workflow

### Initiales Setup (< 5 Minuten)
```bash
# 1. ZIP-Datei hochladen
scp infohub.zip user@host:/var/www/subdomain/

# 2. Entpacken
cd /var/www/subdomain/
unzip infohub.zip

# 3. Permissions setzen
chmod 755 backend/
chmod 644 backend/*.php
chmod 777 backend/data/
chmod 777 backend/media/
chmod 777 backend/archive/

# 4. Setup-Script aufrufen (einmalig)
https://subdomain.example.com/backend/setup.php
→ Generiert zufälligen Editor-URL
→ Erstellt .htaccess
→ Löscht sich selbst
```

### Täglicher Workflow
1. **Bearbeiten**: `https://subdomain.example.com/backend/editor-a8f3n2k9.php`
2. **Preview**: Button im Editor
3. **Publish**: Button im Editor → generiert neue `index.html`
4. **Fertig**: Frontend ist sofort aktualisiert

---

## 💻 Technologie-Stack (detailliert)

### Backend
- **PHP 7.4+** (keine Datenbank!)
  - `api.php`: REST-ähnliche Endpoints (GET/POST/DELETE)
  - `generator.php`: Template-Engine (ähnlich Twig, aber simpler)
  - `upload.php`: File-Upload-Handler
- **JSON**: Datenspeicherung (single file `tiles.json`)

### Frontend (Editor)
- **Vanilla JavaScript** (kein Framework-Overhead)
- **SortableJS**: Drag & Drop
- **Choices.js**: Schöne Dropdowns
- **SimpleLightbox**: Bild-Lightbox
- **FontAwesome**: Icons

### Frontend (Öffentliche Seite)
- **Statisches HTML**: Generiert, keine Server-Requests
- **CSS Grid**: Layout
- **Minimal JS**: Nur für Lightbox + Countdown

### Entwicklungs-Tools
- **Browser-DevTools**: Kein Build-Prozess nötig
- **PHP Built-in Server**: Lokales Testen
```bash
php -S localhost:8000
```

---

## 📊 Entwicklungsplan

### Phase 1: Core (1 Woche)
- [ ] Dateistruktur aufsetzen
- [ ] JSON-Datenmodell implementieren
- [ ] CRUD-API für Tiles (PHP)
- [ ] HTML-Generator (grundlegend)
- [ ] .htaccess + Setup-Script

### Phase 2: Editor-UI (1,5 Wochen) ⚠️ VERALTET - siehe vereinfachter Plan unten
- [ ] Grid-Layout mit Drag & Drop
- [ ] Tile-Bearbeitungsmodal
- [ ] Typ-Switcher mit Feld-Persistenz
- [ ] File-Upload (Bilder + Downloads)
- [ ] Live-Preview

### Phase 3: Tile-Typen (1 Woche)
- [ ] Download-Tile (mit Icon-Picker)
- [ ] Infobox-Tile (Markdown-Support?)
- [ ] Bildbox-Tile (Lightbox)
- [ ] Video-Tile (YouTube-Embed)
- [ ] Event-Tile (Datumsformatierung)
- [ ] Link-Tile
- [ ] Countdown-Tile

### Phase 4: Frontend-Template (0,5 Wochen)
- [ ] Responsive CSS Grid
- [ ] Tile-Templates für alle Typen
- [ ] Lightbox-Integration
- [ ] Performance-Optimierung

### Phase 5: Polish (1 Woche)
- [ ] Versionierung/Archivierung
- [ ] Error-Handling
- [ ] Sicherheitstests
- [ ] Dokumentation (README.md)
- [ ] Demo-Daten

**Gesamt: ~5 Wochen** (1 Person, Teilzeit ~15h/Woche)

> ⚠️ **WICHTIG**: Dieser Plan ist für die "volle" Version.  
> Siehe unten für den **vereinfachten MVP-Plan** mit nur 2 Wochen Aufwand!

---

## 🎯 Schwierigkeitsgrad-Analyse

| Komponente                | Schwierigkeit | Begründung |
|---------------------------|---------------|------------|
| JSON-Datenmodell          | ⭐ Einfach    | Single-File, keine Relations |
| CRUD-API                  | ⭐⭐ Mittel   | Standard-PHP, keine komplexen Queries |
| HTML-Generator            | ⭐⭐ Mittel   | Template-String-Replacement |
| Drag & Drop               | ⭐⭐⭐ Komplex | SortableJS hilft, aber Grid-Update tricky → **SKIP im MVP!** |
| Responsive Grid           | ⭐⭐ Mittel   | CSS Grid ist mächtig, aber Tile-Sizes komplex → **auto-fit vereinfacht!** |
| File-Upload               | ⭐⭐ Mittel   | Standard, aber Validierung wichtig |
| Feld-Persistenz-Feature   | ⭐⭐⭐ Komplex | UI muss klar zeigen, was aktiv/inaktiv ist → **Vereinfachbar!** |
| Versionierung             | ⭐ Einfach    | Nur File-Copy mit Timestamp |
| Email-Code-Login          | ⭐⭐ Mittel   | mail() + Session-Handling |
| Manuelle Sortierung       | ⭐ Einfach    | Number-Input + usort() |

**Gesamt: Mittel** (durch Vereinfachungen)  
Mit Simplifications: **Machbar in 2-3 Wochen statt 5 Wochen!**

---

## ⚡ Komplexitätsreduktion: MVP-Fokus

### Grundsatz
**Use-Case**: Einmaliges Erstellen + seltene Updates (alle ~3 Wochen)  
**Ziel**: Intuitiv bedienbar in 10 Sekunden statt 1 Sekunde  
**Strategie**: "Good enough" statt "perfekt poliert"

### 🔻 Vereinfachungen mit großem Impact

#### 1. Sortierung: Manuelle Positionsnummer statt Drag & Drop
**Komplexitätsreduktion**: ⭐⭐⭐ MASSIV

**Vorher** (Drag & Drop):
- SortableJS-Library einbinden (~15KB)
- Touch-Events implementieren
- Grid-Reflow nach Drag berechnen
- Ghost-Element-Styling
- Konfliktauflösung bei unterschiedlichen Tile-Größen
- **Entwicklungsaufwand**: ~3-4 Tage

**Nachher** (Manuelle Sortierung):
```
┌─────────────────────────────────────┐
│  Position: [3____] ↑↓               │ ← Einfaches Zahlenfeld
│  (Tipp: Schritte von 10 lassen     │
│   Platz für Einfügungen)            │
└─────────────────────────────────────┘
```
- Einfaches Number-Input-Feld
- PHP sortiert Array nach `position`-Wert
- **Entwicklungsaufwand**: ~2 Stunden
- **Ersparnis**: ~90% Entwicklungszeit!

**UX bleibt intuitiv**:
- User gibt Position 10, 20, 30 ein
- Später neue Tile dazwischen? → Position 15
- Automatisches Re-Numbering optional (Button "Positionen aufräumen" → 10, 20, 30, 40...)

**Code-Beispiel**:
```php
// Tiles sortieren - eine Zeile!
usort($tiles, fn($a, $b) => $a['position'] <=> $b['position']);
```

**Empfehlung**: ✅ **Ja, machen!** Für deinen Use-Case ist Drag & Drop Overkill.

---

#### 2. Inline-Editing abspecken
**Komplexitätsreduktion**: ⭐⭐ MITTEL

**Vorher**: 
- Click auf Tile → Inline-Editor mit contenteditable
- Double-Click → Modal
- Komplexe State-Verwaltung

**Nachher**:
- **Immer Modal** für Bearbeitung
- Kein contenteditable (fehleranfällig)
- Einfacher: Click → Modal öffnet sich

**Vorteil**:
- Weniger JavaScript-Logic
- Konsistentes Editing-Erlebnis
- Einfacher zu testen

**Nachteil**:
- Ein Klick mehr (aber nur 0,5 Sekunden Unterschied)

**Empfehlung**: ✅ **Ja!** Modal ist wartbarer.

---

#### 3. Live-Preview weglassen (zunächst)
**Komplexitätsreduktion**: ⭐⭐ MITTEL

**Vorher**:
- AJAX-Call an `generator.php?mode=preview`
- Preview in Sidebar oder neuem Tab
- Synchronisation zwischen Editor und Preview

**Nachher**:
- Nur ein **"Preview"-Button** → öffnet generierte HTML in neuem Tab
- Kein Live-Sync nötig

**Workflow**:
1. Tile bearbeiten
2. "Speichern" → Änderung in JSON
3. "Preview" klicken → Generiert index.html temporär
4. Zufrieden? → "Publish"

**Empfehlung**: ✅ **Erstmal ohne Live-Preview starten**, später hinzufügen wenn gewünscht.

---

#### 4. Icon-Picker vereinfachen
**Komplexitätsreduktion**: ⭐ GERING

**Vorher**: 
- FontAwesome-Icon-Picker mit Suchfunktion
- Modal mit 1000+ Icons

**Nachher**:
- **Dropdown mit 20 vorgefertigten Icons**:
  - PDF, Word, Excel, ZIP, Image, Video
  - Info, Warning, Calendar, Link, Download
  - Mail, Phone, Map, etc.
- Textfeld für fortgeschrittene User (z.B. `fa-custom-icon`)

**Empfehlung**: ✅ **Ja!** Weniger Auswahl = schnellere Entscheidung.

---

#### 5. Feld-Persistenz vereinfachen
**Komplexitätsreduktion**: ⭐⭐ MITTEL

**Vorher**:
- Alle Felder bleiben gespeichert
- Ausgrauen nicht genutzter Felder
- Button "Felder leeren"
- Komplexe UI-Logik

**Nachher**:
- **Einfach alle Felder immer speichern** (bleibt)
- **ABER**: Keine Ausgrau-Logik
- Beim Typ-Wechsel: Warnung "Vorherige Daten bleiben erhalten"
- Kein "Felder leeren"-Button (User kann Felder manuell leeren)

**Empfehlung**: ⚠️ **Optional** - das Feature ist cool, aber nicht kritisch.

---

#### 6. Tile-Typen reduzieren (MVP)
**Komplexitätsreduktion**: ⭐⭐⭐ MASSIV

**MVP-Auswahl** (4 statt 7 Typen):
1. ✅ **Infobox** (universell: Text, Titel, Link, Icon)
2. ✅ **Download** (Datei + Icon)
3. ✅ **Bildbox** (Bild + Lightbox)
4. ✅ **Link-Tile** (externe Ressourcen)

**Später hinzufügen**:
5. 🔜 Video-Tile
6. 🔜 Event-Tile
7. 🔜 Countdown-Tile

**Begründung**:
- Mit **Infobox** kann man 80% der Use-Cases abdecken
- Event-Tile = Infobox mit Icon 📅
- Countdown kann als JavaScript später ergänzt werden

**Empfehlung**: ✅ **Start mit 4 Typen**, Rest iterativ.
Ja- aber mit Template Kommentaren im Code, damit man neue Typen leicht hinzufügen kann. (ggf. Ornder und auto include?)

---

#### 7. Responsive Grid-Logik vereinfachen
**Komplexitätsreduktion**: ⭐⭐ MITTEL

**Vorher**: 
- Komplexe Umbrüche (1x4 → 2x2 auf Tablet)
- Grid-Template-Areas für präzise Positionierung

**Nachher**:
```css
/* Super-simple Grid */
.tile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.tile-1x2 { grid-column: span 2; }
.tile-2x2 { grid-column: span 2; grid-row: span 2; }
```
- Browser entscheidet automatisch Umbrüche
- Keine manuellen Breakpoints pro Tile-Größe

**Empfehlung**: ✅ **Modern CSS macht das alleine!**

---

#### 8. Versionierung vereinfachen
**Komplexitätsreduktion**: ⭐ GERING

**Vorher**:
- Automatisches Archiv bei jedem Publish
- Restore-Funktion im Editor

**Nachher**:
- Archiv nur **auf Knopfdruck** (optional)
- Button "Aktuelle Version archivieren"
- Keine Restore-Funktion (User kopiert manuell aus /archive/)

**Empfehlung**: ✅ **Optional-Archiv** statt automatisch.

---

#### 9. Authentifizierung: Email-Code statt Login
**Komplexitätsreduktion**: ⭐⭐ MITTEL (aber Sicherheitsgewinn!)

**Deine Idee** (aus Kommentar):
> Login.php versendet temporären Code per Email

**Implementierung**:
```php
// login.php
1. User ruft /backend/login.php auf
2. Gibt Email-Adresse ein
3. System generiert 6-stelligen Code, speichert in Session
4. Sendet Code per mail() an Email
5. User gibt Code ein → Session aktiviert
6. Redirect zu editor.php
```

**Setup**:
- Während Setup: Email-Adresse hinterlegen (in config.php)
- Code gültig für 15 Minuten
- Nach 3 falschen Versuchen: 10 Minuten Sperre

**Vorteile**:
- Kein Passwort-Management
- Kein 2FA-System (Email IST der zweite Faktor)
- Sicherer als nur kryptischer URL

**Nachteil**:
- Benötigt `mail()`-Funktion auf Server
- Verzögerung durch Email-Versand (~10-30 Sekunden)

**Empfehlung**: ✅ **Geniale einfache Lösung!** Besser als 2FA-Overhead.

---

### 📉 Komplexitätsreduktion: Zusammenfassung

| Feature | Aufwand vorher | Aufwand nachher | Ersparnis | Empfehlung |
|---------|----------------|-----------------|-----------|------------|
| Drag & Drop → Manuelle Sortierung | 3-4 Tage | 2 Stunden | 90% | ✅ MACHEN |
| Inline-Editing → Nur Modal | 2 Tage | 4 Stunden | 75% | ✅ MACHEN |
| Live-Preview → On-Demand | 1,5 Tage | 3 Stunden | 80% | ✅ MVP ohne |
| Icon-Picker → Dropdown | 1 Tag | 2 Stunden | 75% | ✅ MACHEN |
| 7 Tile-Typen → 4 Typen | 1 Woche | 3 Tage | 60% | ✅ MVP mit 4 |
| Auto-Grid → CSS auto-fit | 1 Tag | 2 Stunden | 75% | ✅ MACHEN |
| Feld-Persistenz vereinfachen | 2 Tage | 4 Stunden | 75% | ⚠️ Optional |
| Auto-Archiv → Optional | 4 Stunden | 1 Stunde | 75% | ✅ MACHEN |

**Gesamtersparnis**: ~2-3 Wochen Entwicklungszeit!  
**Neuer Aufwand**: ~2-3 Wochen statt 5 Wochen

---

### 🎯 Vereinfachtes Editor-Mockup

```
┌─────────────────────────────────────────────────────────┐
│  Info-Hub Editor        [⚙️ Settings] [Preview] [Publish]│
├─────────────────────────────────────────────────────────┤
│                                                          │
│  [+ Neue Kachel]                                        │
│                                                          │
│  ┌────────────────┐  ┌────────────────┐                │
│  │ Infobox        │  │ Download       │                │
│  │ Pos: 10        │  │ Pos: 20        │                │
│  │                │  │                │                │
│  │ "Willkommen"   │  │ 📄 Anmeldung   │                │
│  │                │  │                │                │
│  │ [✏️ Bearbeiten] │  │ [✏️ Bearbeiten] │                │
│  │ [🗑️ Löschen]    │  │ [🗑️ Löschen]    │                │
│  └────────────────┘  └────────────────┘                │
│                                                          │
│  ┌──────────────────────────────────┐                  │
│  │ Bildbox - Pos: 30                │                  │
│  │                                  │                  │
│  │  [Gemeindefoto.jpg]             │                  │
│  │                                  │                  │
│  │  [✏️ Bearbeiten] [🗑️ Löschen]     │                  │
│  └──────────────────────────────────┘                  │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**Änderungen**:
- Keine Drag-Handles
- Position als **Zahl sichtbar**
- Einfache Buttons (Bearbeiten/Löschen)
- Grid-Layout bleibt visuell

---

### 🛠️ Weitere Vereinfachungen

#### A) Setup-Prozess ultrasimpel
```php
// setup.php - läuft nur einmal
1. Email-Adresse eingeben
2. Seiten-Titel + Footer-Text (optional)
3. [Setup abschließen] → Generiert:
   - config.php mit Email + Random-String
   - .htaccess
   - Leeres tiles.json
   - Löscht setup.php selbst
```

#### B) Settings-Dialog statt config.php editieren
```
⚙️ Settings:
├─ Seiten-Titel: [___________]
├─ Footer-Text:  [___________]
├─ Email:        [___________] (für Login-Codes)
├─ Hintergrundbild: [Upload] (optional)
└─ [Speichern]
```

#### C) Kein separates `api.php`
- Alle Backend-Logik in `editor.php`
- Weniger Dateien = einfacher zu verstehen
- AJAX-Calls an `editor.php?action=save_tile`

#### D) Generator = Simple Template
```php
// Kein Twig, nur PHP-Template
function generate_tile($tile) {
    switch ($tile['type']) {
        case 'infobox':
            return "<div class='tile infobox'>
                      <h3>{$tile['title']}</h3>
                      <p>{$tile['text']}</p>
                    </div>";
        // ...
    }
}
```

---

### 💡 Zusätzliche Ideen zur Vereinfachung

#### 1. Kontakt-Tile mit Crawler-Schutz
**Deine Anforderung**: "Kontakt aufnehmen. gegen crawler schützen"

**Simple Lösung**:
```html
<!-- Email wird erst bei Click generiert -->
<div class="tile contact" data-user="name" data-domain="gemeinde.de">
    <h3>Kontakt</h3>
    <button onclick="showEmail(this)">Email anzeigen</button>
</div>

<script>
function showEmail(btn) {
    const user = btn.parentElement.dataset.user;
    const domain = btn.parentElement.dataset.domain;
    btn.outerHTML = `<a href="mailto:${user}@${domain}">${user}@${domain}</a>`;
}
</script>
```
- Kein mailto: im HTML-Source
- Crawler sehen nur Button
- Ein Click mehr, aber sicher

#### 2. Template-Vorlagen statt leerer Seite
```
Setup-Prozess:
"Wähle eine Vorlage:"
[ ] Leer starten
[✓] Biblischer Unterricht (5 vorgefertigte Tiles)
[ ] Tech-Dokumentation (4 Tiles mit Links)
[ ] Event-Seite (3 Tiles: Info, Anmeldung, Datum)
```

#### 3. Import/Export für schnelles Duplizieren
```
Export:
[Download tiles.json] → Kann auf anderer Subdomain importiert werden

Import:
[Upload tiles.json] → Überschreibt aktuelle Tiles (Warnung!)
```

---

### ⏱️ Überarbeiteter Entwicklungsplan (Simplified)

#### Phase 1: Minimal Setup (3 Tage)
- [ ] Dateistruktur
- [ ] setup.php (Email + Config generieren)
- [ ] JSON-Struktur
- [ ] .htaccess

#### Phase 2: Editor Core (5 Tage)
- [ ] editor.php mit Tile-Liste (Position-Nummern)
- [ ] Modal für Tile-Bearbeitung
- [ ] 4 Tile-Typen (Infobox, Download, Bild, Link)
- [ ] File-Upload

#### Phase 3: Generator (2 Tage)
- [ ] Template-System (simple PHP)
- [ ] Responsive CSS Grid
- [ ] Lightbox

#### Phase 4: Auth + Settings (2 Tage)
- [ ] Email-Code-Login
- [ ] Settings-Dialog

#### Phase 5: Polish (2 Tage)
- [ ] Error-Handling
- [ ] Demo-Daten
- [ ] README

**Gesamt: 2 Wochen** (statt 5 Wochen!)  
Bei Teilzeit (15h/Woche) = **4 Wochen statt 10 Wochen**

---

## 🤔 Offene Diskussionspunkte

### 1. Authentifizierung
**Aktuell**: Security by Obscurity (zufälliger URL)  
**Alternativen**:
- [ ] HTTP Basic Auth (.htpasswd)
- [ ] Simples Password-Feld (Session-basiert)
- [ ] IP-Whitelist

**Frage**: Reicht der kryptische URL oder brauchen wir zusätzlichen Schutz? (siehe Idee im Dokument)

### 2. Multi-User-Fähigkeit
**Aktuell**: Single-User (eine Person bearbeitet) --> reicht. ansonsten credentials sharing. zeitgleiches bearbeiten ist zwar theoretisch möglich, aber in unserem use-case sehr unwahrscheinlich.  
**Erweiterung**:
- [ ] Concurrent-Editing-Lock (wer bearbeitet gerade?)
- [ ] User-Management (verschiedene Rollen?)

**Frage**: Wird gleichzeitig bearbeitet oder ist das ein Edge-Case?

### 3. Markdown-Support in Textfeldern
**Pro**: Formatierung ohne WYSIWYG-Editor  
**Contra**: Mehr Komplexität, Library nötig (Parsedown)

**Frage**: Brauchen wir formatierten Text oder reicht Plain-Text + HTML-Tags? Plain Text reicht. 

### 4. Mehrsprachigkeit
**Aktuell**: Eine Sprache pro Deployment  
**Erweiterung**: Lang-Switcher im Frontend

**Frage**: Sind mehrsprachige Infoseiten ein Use-Case? nein

### 5. Theme-System
**Aktuell**: Ein CSS-File  
**Erweiterung**: Wechselbare Themes (Light/Dark, Farb-Schemata)

**Frage**: Wichtig oder später hinzufügen? Darkmode wäre nice-to-have. --> optional bei langeweile

### 6. Zusätzliche Tile-Typen?
**Vorschläge**:
- **Kontakt-Tile**: Name, Email, Telefon, Foto
- **Map-Tile**: Google Maps Embed
- **Form-Tile**: Einfaches Kontaktformular
- **Accordion-Tile**: FAQ-Style

**Frage**: Welche Use-Cases sind am wichtigsten? Kontakt aufnehmen. gegen crawler schützen (mailto ist zu unsicher).

### 7. Analytics/Tracking
**Frage**: Sollte das System Zugriffe tracken (z.B. Download-Counter)?

### 8. API für externe Integration
**Use-Case**: ChurchTools könnte Events automatisch als Tiles hinzufügen  
**Frage**: Brauchen wir eine API oder ist manuelles Pflegen okay?

---

## 🏆 Erfolgsmetriken

### Must-Have (MVP)
- ✅ Deployment in < 5 Minuten möglich
- ✅ Neue Tile in < 30 Sekunden erstellt
- ✅ Responsive auf allen Geräten
- ✅ Keine Datenbank erforderlich
- ✅ Sicher gegen Standard-Angriffe

### Nice-to-Have (v2.0)
- 🎯 Multi-User-Editing
- 🎯 Theme-System
- 🎯 Markdown-Editor
- 🎯 Import/Export-Funktion
- 🎯 Dark-Mode

---

## 📝 Nächste Schritte

1. **Entscheidung**: Diskussionspunkte klären
2. **Prototyp**: Minimales Setup (Phase 1) bauen
3. **UX-Test**: Editor mit Dummy-Daten testen
4. **Iteration**: Basierend auf Feedback verbessern
5. **Dokumentation**: Setup-Guide + Video-Tutorial

---

## 💡 Inspiration & Ähnliche Tools
- **Notion**: Flexible Blöcke, Inline-Editing
- **WordPress Gutenberg**: Block-basiertes Editing
- **Carrd**: Single-Page-Builder (aber SaaS)
- **TiddlyWiki**: Self-contained HTML-File (Inspiration für Versionierung)

---

**Status**: 🟡 Konzeptphase  
**Maintainer**: Zu definieren  
**Lizenz**: Open Source (MIT?) oder Internal Tool?