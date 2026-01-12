# COPILOT INSTRUCTIONS - INFO-HUB

**Projekt:** Info-Hub - Modulares Informationssystem  
**Version:** 1.0 MVP  
**Tech-Stack:** PHP 7.4+, Vanilla JavaScript, CSS Grid  
**Architektur:** File-based CMS ohne Datenbank  

---

## 🎯 PROJEKT-ÜBERSICHT

### Vision
Ein ultra-schlankes, file-based CMS für schnelle Informationsseiten in der Gemeindearbeit.
- Deployment in < 5 Minuten auf beliebiger Subdomain
- Keine Datenbank (nur PHP + JSON)
- Visueller Editor für Content-Verwaltung
- Generiert statische HTML-Seiten

### Erfolgskriterien
- ✅ Setup in < 5 Minuten komplett
- ✅ Neue Tile in < 30 Sekunden erstellt
- ✅ Responsive auf allen Geräten
- ✅ Sicher gegen Standard-Angriffe
- ✅ Wartbar ohne PHP-Kenntnisse

### Dokumentation
- **Single Source of Truth**: `/docs/vision.md`
- **Development Guidelines**: `/docs/basics.txt`
- **Template Reference**: `/docs/copilot_instructions_template.md`

### Dokumentationsstruktur
```
/docs/
├── dev/                      # Entwickler + Admin Dokumentation
│   ├── architecture.md       # Systemarchitektur
│   ├── api.md               # API-Referenz
│   └── deployment.md        # Setup & Wartung
└── user/                     # Benutzer-Dokumentation
    └── guide.md             # Bedienungsanleitung
```

---

## 🏗️ ARCHITEKTUR-PRINZIPIEN

### 1. STRIKTE MODULARITÄT

#### Tile-Typen als separate Module
**KRITISCH**: Jeder Tile-Typ ist eine eigenständige Datei!

```
/backend/tiles/
├── _registry.php          # Auto-Import + Dokumentation
├── TileBase.php          # Abstrakte Basis-Klasse
├── InfoboxTile.php       # Einzelner Tile-Typ
├── DownloadTile.php      # Einzelner Tile-Typ
├── ImageTile.php         # Einzelner Tile-Typ
└── LinkTile.php          # Einzelner Tile-Typ
```

**`_registry.php` Struktur:**
```php
<?php
/**
 * TILE REGISTRY - Auto-Import System
 * 
 * NEUE TILE-TYPEN HINZUFÜGEN:
 * 1. Neue Datei erstellen: XyzTile.php
 * 2. Von TileBase erben
 * 3. Methoden implementieren:
 *    - render(): HTML-Output generieren
 *    - validate($data): Input validieren
 *    - getFields(): Aktive Felder definieren
 * 4. Diese Datei lädt automatisch alle *Tile.php
 * 
 * KEINE manuelle Registrierung notwendig!
 */

// Auto-load alle Tile-Typen
foreach (glob(__DIR__ . '/*Tile.php') as $tileFile) {
    require_once $tileFile;
}

// Tile-Type Registry erstellen
$TILE_TYPES = [];
foreach (get_declared_classes() as $class) {
    if (is_subclass_of($class, 'TileBase')) {
        $type = strtolower(str_replace('Tile', '', $class));
        $TILE_TYPES[$type] = $class;
    }
}
?>
```

**Beispiel: Neuer Tile-Typ hinzufügen**
```php
<?php
// backend/tiles/ContactTile.php

class ContactTile extends TileBase {
    
    public function getFields(): array {
        return ['title', 'name', 'email', 'phone', 'image'];
    }
    
    public function validate(array $data): array {
        $errors = [];
        if (empty($data['name'])) $errors[] = 'Name erforderlich';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültige Email';
        }
        return $errors;
    }
    
    public function render(array $data): string {
        $safeEmail = htmlspecialchars($data['email']);
        $emailParts = explode('@', $safeEmail);
        
        return <<<HTML
        <div class="tile contact-tile size-{$data['size']}">
            <img src="{$data['image']}" alt="{$data['name']}">
            <h3>{$data['name']}</h3>
            <button onclick="showEmail('{$emailParts[0]}', '{$emailParts[1]}')">
                Email anzeigen
            </button>
        </div>
        HTML;
    }
}
?>
```

**REGEL**: Niemals Tile-Logik in die Hauptdateien schreiben! Immer separate Tile-Klassen.

---

### 2. STRIKTE TRENNUNG: LAYOUT / LOGIC / SERVICES

```
/backend/
├── core/
│   ├── TileService.php       # Business Logic (CRUD)
│   ├── GeneratorService.php  # HTML-Generierung
│   ├── AuthService.php       # Email-Code Auth
│   ├── StorageService.php    # JSON File I/O
│   └── LogService.php        # Zentrales Logging
├── tiles/
│   └── [siehe oben]          # Tile-Definitionen
├── templates/
│   ├── page.template.php     # Layout Template
│   ├── tile.template.php     # Tile Wrapper
│   └── partials/
│       ├── header.php        # Header mit Titel/Bild
│       └── footer.php        # Footer
└── api/
    └── endpoints.php         # API-Handler (nutzt Services)
```

**Workflow-Beispiel: Tile speichern**
```php
// ❌ FALSCH - Logic in Endpoint
// backend/api/endpoints.php
if ($_POST['action'] === 'save_tile') {
    $tile = $_POST['tile'];
    $json = file_get_contents('data/tiles.json');
    $tiles = json_decode($json, true);
    $tiles[] = $tile;
    file_put_contents('data/tiles.json', json_encode($tiles));
}

// ✅ RICHTIG - Service-Aufruf
// backend/api/endpoints.php
if ($_POST['action'] === 'save_tile') {
    $tileService = new TileService();
    $result = $tileService->saveTile($_POST['tile']);
    echo json_encode($result);
}

// backend/core/TileService.php
class TileService {
    private $storage;
    
    public function __construct() {
        $this->storage = new StorageService('tiles.json');
    }
    
    public function saveTile(array $tileData): array {
        // 1. Validierung
        $tile = TileFactory::create($tileData['type']);
        $errors = $tile->validate($tileData);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // 2. Speichern
        $tiles = $this->storage->read();
        $tileData['id'] = $tileData['id'] ?? 'tile_' . time();
        $tiles[] = $tileData;
        
        // 3. Sortieren nach Position
        usort($tiles, fn($a, $b) => $a['position'] <=> $b['position']);
        
        $this->storage->write($tiles);
        
        return ['success' => true, 'tile' => $tileData];
    }
}
```

**REGEL**: 
- **API-Endpoints** = Dünne Wrapper, nur Input/Output
- **Services** = Business Logic, keine direkte I/O
- **Storage** = Einzige Stelle für File-Operationen
- **Templates** = Nur HTML, keine Logik (außer Schleifen/Conditionals)

---

### 3. KONFIGURIERBARE GLOBALE SETTINGS

**Settings-Struktur (vereinfacht):**
```php
// backend/data/settings.json
{
  "site": {
    "title": "Biblischer Unterricht 2026",
    "headerImage": "/backend/media/header/hero.jpg",  // null = kein Header
    "footerText": "© 2026 Freie evangelische Gemeinde"
  },
  "theme": {
    "backgroundColor": "#f5f5f5",
    "primaryColor": "#ff6b6b"
  },
  "auth": {
    "email": "admin@example.com"
  }
}
```

**Template-Integration (vereinfacht):**
```php
// backend/templates/page.template.php
<?php
$settings = json_decode(file_get_contents(__DIR__ . '/../data/settings.json'), true);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($settings['site']['title']) ?></title>
    <style>
        :root {
            --bg-color: <?= $settings['theme']['backgroundColor'] ?>;
            --primary-color: <?= $settings['theme']['primaryColor'] ?>;
        }
        body { background-color: var(--bg-color); }
        
        /* Tile-Styles: Individuell pro Tile */
        .tile.style-flat { background: transparent; }
        .tile.style-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php if ($settings['site']['headerImage']): ?>
        <header class="site-header">
            <img src="<?= $settings['site']['headerImage'] ?>" alt="">
            <h1><?= htmlspecialchars($settings['site']['title']) ?></h1>
        </header>
    <?php endif; ?>
    
    <main class="tile-grid">
        <?= $tilesHtml ?>
    </main>
    
    <footer><?= $settings['site']['footerText'] ?></footer>
</body>
</html>
```

---

## 🔐 SICHERHEIT

### 1. Email-Code-Authentifizierung

**Flow:**
```
1. User → backend/login.php
2. Email-Adresse eingeben
3. System generiert 6-stelligen Code
4. Code per mail() versenden
5. User gibt Code ein
6. Session aktiviert (1 Stunde gültig)
7. Redirect zu editor.php
```

**Implementierung:**
```php
// backend/core/AuthService.php
class AuthService {
    
    public function sendCode(string $email): bool {
        $settings = $this->getSettings();
        
        if ($email !== $settings['auth']['email']) {
            return false;  // Nur hinterlegte Email
        }
        
        // Code generieren
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // In Session speichern
        $_SESSION['auth_code'] = $code;
        $_SESSION['auth_code_expires'] = time() + $settings['auth']['codeExpiry'];
        $_SESSION['auth_attempts'] = 0;
        
        // Email versenden
        $subject = 'Info-Hub Login-Code';
        $message = "Dein Login-Code: $code\n\nGültig für 15 Minuten.";
        return mail($email, $subject, $message);
    }
    
    public function verifyCode(string $inputCode): bool {
        // Rate Limiting
        if (($_SESSION['auth_attempts'] ?? 0) >= 3) {
            if (time() < ($_SESSION['auth_lockout'] ?? 0)) {
                return false;  // Noch gesperrt
            }
        }
        
        // Code prüfen
        if (!isset($_SESSION['auth_code']) || 
            time() > $_SESSION['auth_code_expires']) {
            return false;  // Abgelaufen
        }
        
        if ($inputCode !== $_SESSION['auth_code']) {
            $_SESSION['auth_attempts']++;
            if ($_SESSION['auth_attempts'] >= 3) {
                $_SESSION['auth_lockout'] = time() + 600;  // 10 Min Sperre
            }
            return false;
        }
        
        // Erfolg
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();
        unset($_SESSION['auth_code'], $_SESSION['auth_code_expires']);
        
        return true;
    }
    
    public function isAuthenticated(): bool {
        if (!isset($_SESSION['authenticated'])) {
            return false;
        }
        
        // Session nach 1 Stunde ablaufen lassen
        if (time() - $_SESSION['auth_time'] > 3600) {
            session_destroy();
            return false;
        }
        
        return true;
    }
}
```

### 2. Upload-Validierung

```php
// backend/core/UploadService.php
class UploadService {
    
    private const ALLOWED_IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_DOWNLOADS = ['pdf', 'docx', 'xlsx', 'zip'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;  // 5MB
    private const MAX_DOWNLOAD_SIZE = 50 * 1024 * 1024;  // 50MB
    
    public function uploadImage($file): array {
        return $this->upload($file, 'images', self::ALLOWED_IMAGES, self::MAX_IMAGE_SIZE);
    }
    
    public function uploadDownload($file): array {
        return $this->upload($file, 'downloads', self::ALLOWED_DOWNLOADS, self::MAX_DOWNLOAD_SIZE);
    }
    
    private function upload($file, string $type, array $allowedExts, int $maxSize): array {
        // 1. Validierung
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload fehlgeschlagen'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Datei zu groß'];
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            return ['success' => false, 'error' => 'Dateityp nicht erlaubt'];
        }
        
        // 2. Sicherer Dateiname
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $basename . '_' . time() . '.' . $ext;
        
        // 3. Speichern
        $targetDir = __DIR__ . "/../media/$type/";
        $targetPath = $targetDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Speichern fehlgeschlagen'];
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => "/backend/media/$type/$filename"
        ];
    }
}
```

---

## 📦 DATEISTRUKTUR (Vollständig)

```
/infohub/
├── index.html                    # ⚠️ Generiert - nicht manuell bearbeiten!
├── .htaccess                     # Security
│
├── backend/
│   ├── setup.php                # Einmaliges Setup (löscht sich selbst)
│   ├── login.php                # Email-Code Login
│   ├── editor.php               # Haupteditor (Protected)
│   │
│   ├── core/                    # Business Logic
│   │   ├── TileService.php
│   │   ├── GeneratorService.php
│   │   ├── AuthService.php
│   │   ├── UploadService.php
│   │   ├── StorageService.php
│   │   └── LogService.php      # Zentrales Logging
│   │
│   ├── tiles/                   # Tile-Typen (modular!)
│   │   ├── _registry.php       # Auto-Import
│   │   ├── TileBase.php        # Abstrakte Basis
│   │   ├── InfoboxTile.php
│   │   ├── DownloadTile.php
│   │   ├── ImageTile.php
│   │   └── LinkTile.php
│   │
│   ├── templates/               # HTML-Templates
│   │   ├── page.template.php
│   │   ├── tile.template.php
│   │   └── partials/
│   │       ├── header.php
│   │       └── footer.php
│   │
│   ├── api/
│   │   └── endpoints.php       # API-Handler
│   │
│   ├── data/                    # JSON-Storage
│   │   ├── tiles.json
│   │   └── settings.json
│   │
│   ├── logs/                    # Log-Dateien
│   │   └── app.log             # Zentrales Anwendungslog
│   │
│   ├── media/
│   │   ├── images/
│   │   ├── downloads/
│   │   └── header/             # Header-Bilder
│   │
│   └── archive/
│       └── [Versionen...]
│
├── assets/                      # Frontend-Assets
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── editor.js           # Editor-UI
│       └── frontend.js         # Lightbox, Email-Reveal
│
└── docs/
    ├── dev/                     # Entwickler + Admin
    │   ├── architecture.md
    │   ├── api.md
    │   └── deployment.md
    └── user/                    # Benutzer
        └── guide.md
```

---

## 🎨 FRONTEND-STRUKTUR (Editor)

### Editor-Layout (Vereinfacht)

```html
<!-- backend/editor.php -->
<div class="editor">
    <header class="editor-header">
        <h1>Info-Hub Editor</h1>
        <div class="actions">
            <button id="settingsBtn">⚙️ Settings</button>
            <button id="previewBtn">👁️ Preview</button>
            <button id="publishBtn" class="primary">🚀 Publish</button>
        </div>
    </header>
    
    <main class="editor-main">
        <div class="tiles-list" id="tilesList">
            <!-- Tiles werden per JS gerendert -->
        </div>
        
        <button id="addTileBtn" class="add-tile-btn">
            + Neue Kachel hinzufügen
        </button>
    </main>
</div>

<!-- Modal für Tile-Bearbeitung -->
<div id="tileModal" class="modal">
    <div class="modal-content">
        <h2>Kachel bearbeiten</h2>
        <form id="tileForm">
            <label>Typ</label>
            <select name="type">...</select>
            
            <label>Position</label>
            <input type="number" name="position" step="10">
            
            <label>Größe</label>
            <select name="size">...</select>
            
            <label>Style</label>  <!-- ← INDIVIDUELL! -->
            <select name="style">
                <option value="flat">Flat (transparent)</option>
                <option value="card">Card (mit Rahmen/Schatten)</option>
            </select>
            
            <!-- Weitere Felder je nach Typ -->
        </form>
    </div>
</div>

<!-- ✅ Settings Modal (Global) -->
<div id="settingsModal" class="modal">
    <div class="modal-content">
        <h2>⚙️ Seiten-Einstellungen</h2>
        <form id="settingsForm">
            <label>Seiten-Titel</label>
            <input type="text" name="title" placeholder="Biblischer Unterricht 2026">
            
            <label>Header-Bild</label>
            <input type="file" name="headerImage" accept="image/*">
            <button type="button" onclick="removeHeaderImage()">Kein Header</button>
            
            <label>Hintergrundfarbe</label>
            <input type="color" name="backgroundColor" value="#f5f5f5">
            
            <label>Akzentfarbe</label>
            <input type="color" name="primaryColor" value="#ff6b6b">
            
            <label>Footer-Text</label>
            <input type="text" name="footerText" placeholder="© 2026 ...">
            
            <label>Admin-Email (für Login)</label>
            <input type="email" name="email" readonly>
            <small>Änderbar nur via setup.php</small>
            
            <div class="modal-actions">
                <button type="button" onclick="closeSettingsModal()">Abbrechen</button>
                <button type="submit" class="primary">Speichern</button>
            </div>
        </form>
    </div>
</div>
```

### JavaScript-Architektur (Modular!)

```javascript
// assets/js/editor.js

// ❌ FALSCH - Alles in einer Datei
document.getElementById('addTileBtn').addEventListener('click', () => {
    // 500 Zeilen Code hier...
});

// ✅ RICHTIG - Module
class TileManager {
    constructor(apiUrl) {
        this.apiUrl = apiUrl;
        this.tiles = [];
    }
    
    async loadTiles() {
        const res = await fetch(`${this.apiUrl}?action=get_tiles`);
        this.tiles = await res.json();
        this.render();
    }
    
    render() {
        const container = document.getElementById('tilesList');
        container.innerHTML = this.tiles
            .map(tile => this.renderTileCard(tile))
            .join('');
    }
    
    renderTileCard(tile) {
        return `
            <div class="tile-card" data-id="${tile.id}">
                <span class="tile-position">Pos: ${tile.position}</span>
                <h3>${tile.data.title || 'Ohne Titel'}</h3>
                <div class="tile-actions">
                    <button onclick="tileManager.edit('${tile.id}')">✏️</button>
                    <button onclick="tileManager.delete('${tile.id}')">🗑️</button>
                </div>
            </div>
        `;
    }
    
    async saveTile(tileData) {
        const res = await fetch(this.apiUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'save_tile', tile: tileData})
        });
        return res.json();
    }
}

class ModalManager {
    open(tileId = null) {
        // Modal-Logik
    }
    
    close() {
        // Modal schließen
    }
}

// Initialisierung
const tileManager = new TileManager('/backend/api/endpoints.php');
const modalManager = new ModalManager();

tileManager.loadTiles();
```

---

## 🧪 TESTING & VALIDATION

### Unit-Tests (Optional für MVP, empfohlen für v2.0)

```php
// tests/unit/TileServiceTest.php
use PHPUnit\Framework\TestCase;

class TileServiceTest extends TestCase {
    
    public function testSaveTileValidation() {
        $service = new TileService();
        
        $result = $service->saveTile([
            'type' => 'infobox',
            'data' => ['title' => '']  // Fehlt: Pflichtfeld
        ]);
        
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }
    
    public function testTileSorting() {
        $service = new TileService();
        
        $service->saveTile(['position' => 30, 'data' => []]);
        $service->saveTile(['position' => 10, 'data' => []]);
        $service->saveTile(['position' => 20, 'data' => []]);
        
        $tiles = $service->getTiles();
        $this->assertEquals(10, $tiles[0]['position']);
        $this->assertEquals(20, $tiles[1]['position']);
        $this->assertEquals(30, $tiles[2]['position']);
    }
}
```

---

## 📝 NAMING CONVENTIONS (Projekt-spezifisch)

| Bereich | Konvention | Beispiel |
|---------|-----------|----------|
| **PHP Klassen** | PascalCase + Service-Suffix | `TileService`, `AuthService` |
| **PHP Dateien** | PascalCase.php | `TileService.php` |
| **JSON Dateien** | lowercase.json | `tiles.json`, `settings.json` |
| **CSS Klassen** | kebab-case | `.tile-card`, `.editor-header` |
| **JavaScript Funktionen** | camelCase | `saveTile()`, `renderModal()` |
| **JavaScript Klassen** | PascalCase | `TileManager`, `ModalManager` |
| **API Actions** | snake_case | `save_tile`, `get_tiles` |
| **Tile-Felder** | camelCase (JSON) | `title`, `linkText`, `backgroundColor` |
| **Session Keys** | snake_case | `auth_code`, `auth_time` |

---

## 🚀 DEPLOYMENT CHECKLISTE

### Setup-Prozess (< 5 Minuten)

1. **ZIP hochladen & entpacken**
   ```bash
   scp infohub.zip user@host:/var/www/subdomain/
   ssh user@host
   cd /var/www/subdomain/
   unzip infohub.zip
   ```

2. **Permissions setzen**
   ```bash
   chmod 755 backend/
   chmod 644 backend/*.php
   chmod 777 backend/data/
   chmod 777 backend/media/
   chmod 777 backend/archive/
   ```

3. **Setup aufrufen**
   ```
   https://subdomain.example.com/backend/setup.php
   
   Eingaben:
   - Email-Adresse für Login
   - Seiten-Titel
   - Header-Bild (optional)
   
   → Generiert:
     - settings.json mit Email
     - Leeres tiles.json
     - .htaccess mit Security
     - Löscht setup.php selbst
   ```

4. **Login testen**
   ```
   https://subdomain.example.com/backend/login.php
   → Email-Code erhalten
   → Code eingeben
   → Redirect zu editor.php
   ```

### Production-Ready Checklist

- [ ] `setup.php` wurde gelöscht (automatisch)
- [ ] `.htaccess` schützt `/backend/*` (außer login.php, editor.php)
- [ ] Email-Versand funktioniert (`mail()` konfiguriert)
- [ ] `backend/data/` ist schreibbar
- [ ] `backend/media/` ist schreibbar
- [ ] PHP 7.4+ verfügbar
- [ ] Error-Reporting in Production: OFF

---

## ⚙️ DEVELOPMENT WORKFLOW

### Lokales Setup

```bash
# 1. Repository clonen
git clone <repo-url>
cd infohub

# 2. PHP Built-in Server starten
php -S localhost:8000

# 3. Browser öffnen
http://localhost:8000/backend/setup.php
```

### Neuen Tile-Typ hinzufügen

```bash
# 1. Neue Datei erstellen
touch backend/tiles/ContactTile.php

# 2. Von TileBase erben (siehe Template oben)
# 3. Methoden implementieren
# 4. Fertig! Auto-Import durch _registry.php

# Kein Neustart, keine Konfiguration nötig!
```

### Debugging & Logging

**Zentrales Logging über `LogService`:**

```php
// backend/core/LogService.php
class LogService {
    
    private const LOG_FILE = __DIR__ . '/../logs/app.log';
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB, dann rotieren
    
    /**
     * Log-Level: DEBUG, INFO, WARNING, ERROR, SUCCESS, FAILURE
     */
    public static function log(
        string $level, 
        string $module, 
        string $message, 
        array $context = []
    ): void {
        $entry = [
            'timestamp' => date('c'),
            'level'     => strtoupper($level),
            'module'    => $module,
            'message'   => $message,
            'context'   => $context,
            'file'      => debug_backtrace()[0]['file'] ?? '',
            'line'      => debug_backtrace()[0]['line'] ?? 0
        ];
        
        self::rotateIfNeeded();
        
        $logLine = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents(self::LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    // Convenience Methods
    public static function info(string $module, string $msg, array $ctx = []): void {
        self::log('INFO', $module, $msg, $ctx);
    }
    
    public static function error(string $module, string $msg, array $ctx = []): void {
        self::log('ERROR', $module, $msg, $ctx);
    }
    
    public static function debug(string $module, string $msg, array $ctx = []): void {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::log('DEBUG', $module, $msg, $ctx);
        }
    }
    
    private static function rotateIfNeeded(): void {
        if (file_exists(self::LOG_FILE) && filesize(self::LOG_FILE) > self::MAX_SIZE) {
            rename(self::LOG_FILE, self::LOG_FILE . '.' . date('Y-m-d_H-i-s'));
        }
    }
}
```

**Verwendung in Services:**

```php
// In TileService.php
public function saveTile(array $tileData): array {
    LogService::info('TileService', 'Saving tile', ['id' => $tileData['id'] ?? 'new']);
    
    try {
        // ... Logik ...
        LogService::info('TileService', 'Tile saved successfully', ['id' => $tile['id']]);
        return ['success' => true, 'tile' => $tile];
    } catch (Exception $e) {
        LogService::error('TileService', 'Failed to save tile', [
            'error' => $e->getMessage(),
            'data'  => $tileData
        ]);
        return ['success' => false, 'error' => 'Speichern fehlgeschlagen'];
    }
}
```

**Log-Datei Struktur:**
```
/backend/logs/
├── app.log              # Aktuelles Log
└── app.log.2026-01-12   # Rotierte Logs
```

**REGEL**: Alle Services loggen über `LogService` - kein direktes `error_log()` oder `file_put_contents()` für Logs!

---

## 🎯 MVP-UMFANG (Phase 1)

### Must-Have Features

- ✅ Email-Code-Authentifizierung
- ✅ 4 Tile-Typen (Infobox, Download, Bild, Link)
- ✅ Manuelle Sortierung (Position-Feld)
- ✅ Modal-basierte Bearbeitung
- ✅ File-Upload (Bilder + Downloads)
- ✅ **Settings-Modal** (Titel, Header-Bild, Farben, Footer)
- ✅ HTML-Generierung (statisch)
- ✅ Responsive CSS Grid
- ✅ **Style pro Tile** (flat/card) - für flexible Designs
- ✅ Header mit konfigurierbarem Bild (oder ohne)

### Nice-to-Have (v2.0)

- 🔜 Drag & Drop Sortierung
- 🔜 Live-Preview
- 🔜 3 weitere Tile-Typen (Video, Event, Countdown)
- 🔜 Dark-Mode
- 🔜 Versionierung mit Restore-Funktion
- 🔜 Import/Export
- 🔜 Analytics/Download-Counter

---

## 💡 BEST PRACTICES FÜR KI-AGENTEN

### Do's ✅

1. **Modularität wahren**
   - Neue Tile-Typen = neue Datei in `/tiles/`
   - Neue Service = neue Datei in `/core/`
   - Keine God-Classes

2. **Services nutzen**
   - Niemals direktes `file_get_contents()` im Endpoint
   - Immer über `StorageService` arbeiten

3. **Dokumentation im Code**
   ```php
   /**
    * Speichert eine Tile und sortiert automatisch.
    * 
    * @param array $tileData Tile-Daten mit 'type', 'position', 'data'
    * @return array ['success' => bool, 'tile' => array] oder ['errors' => array]
    */
   public function saveTile(array $tileData): array
   ```

4. **Error-Handling**
   ```php
   try {
       $result = $service->operation();
       return ['success' => true, 'data' => $result];
   } catch (Exception $e) {
       error_log('[Module] Error: ' . $e->getMessage());
       return ['success' => false, 'error' => 'Operation fehlgeschlagen'];
   }
   ```

5. **Input-Validierung**
   ```php
   // Immer validieren vor Verarbeitung
   if (!isset($data['type']) || !in_array($data['type'], $TILE_TYPES)) {
       return ['success' => false, 'error' => 'Ungültiger Typ'];
   }
   ```

### Don'ts ❌

1. **Keine Tile-Logik in editor.php**
   - Tile-Rendering gehört in Tile-Klassen
   - editor.php ist nur UI

2. **Keine direkten DB-Zugriffe** (falls später erweitert)
   - Immer über Services

3. **Keine hartcodierten Werte**
   ```php
   // ❌ FALSCH
   $maxSize = 5242880;
   
   // ✅ RICHTIG
   const MAX_SIZE = 5 * 1024 * 1024;
   ```

4. **Keine Security-Shortcuts**
   ```php
   // ❌ FALSCH
   $file = $_GET['file'];
   include($file);
   
   // ✅ RICHTIG
   $allowedFiles = ['header.php', 'footer.php'];
   if (in_array($_GET['file'], $allowedFiles)) {
       include(__DIR__ . '/partials/' . basename($_GET['file']));
   }
   ```

---

## 📚 WICHTIGE REFERENZEN

### Dokumentation
- `/docs/vision.md` - Projektvision & Use-Cases
- `/docs/basics.txt` - Allgemeine Development Guidelines
- `/docs/copilot_instructions_template.md` - Best Practices Template

### Code-Templates
- `/backend/tiles/TileBase.php` - Basis-Klasse für neue Tiles
- `/backend/templates/page.template.php` - Seiten-Layout

### Beispiele
- `/backend/tiles/InfoboxTile.php` - Einfachster Tile-Typ
- `/backend/core/TileService.php` - Service-Pattern

---

## 🔄 VERSION HISTORY

### v1.0 MVP (aktuell)
- Basis-Funktionalität
- 4 Tile-Typen
- Email-Auth
- Settings-System
- Header mit Bild (YouTube-Style)
- Flat/Card-Stil

### Roadmap
- v1.1: Weitere Tile-Typen (Video, Event, Countdown)
- v1.2: Drag & Drop Sortierung
- v2.0: Versionierung & Dark-Mode

---

**Ende der Instructions. Viel Erfolg beim Coding! 🚀**
