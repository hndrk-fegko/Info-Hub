# Info-Hub

**Ultra-schlankes, file-based CMS für schnelle Informationsseiten**

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![No Database](https://img.shields.io/badge/Database-None-blue)

## ✨ Features

- ⚡ **Setup in < 5 Minuten** - Kein kompliziertes Setup
- 🗃️ **Keine Datenbank** - Nur PHP + JSON
- 🔐 **Email-Code-Login** - Kein Passwort nötig
- 👥 **Multi-Admin** - Mehrere Admins per Einladung
- 🎨 **Visueller Tile-Editor** - Drag & Drop ready
- 📄 **Statische HTML-Generierung** - Schnell & SEO-freundlich
- 📱 **Responsive Design** - CSS Grid Layout
- 🛡️ **CSRF-Schutz** - Sichere API-Calls
- 🔒 **Security-Warnungen** - Debug-Mode & HTTPS-Checks

## 🧱 Tile-Typen

| Typ | Beschreibung | Felder |
|-----|--------------|--------|
| **Infobox** | Texte & Ankündigungen | title, showTitle, description |
| **Download** | Datei-Downloads | title, showTitle, description, file, buttonText |
| **Bild** | Fotos mit Lightbox/Link | title, showTitle, image, caption, lightbox, link |
| **Link** | Externe Verlinkungen | title, showTitle, description, url, linkText, external, showDomain |
| **Iframe** | Eingebettete Formulare | title, showTitle, url, description, displayMode, aspectRatio |
| **Countdown** | Countdown zu Datum | title, showTitle, description, targetDate, targetTime, countMode, expiredText, hideOnExpiry |
| **Kontakt** | Kontaktperson (Anti-Spam) | title, name, role, image, email, phone, showEmailButton, showPhoneButton |
| **Zitat** | Zitat oder Bibelvers | title, showTitle, quote, source, link |
| **Separator** | Optischer Trenner | height, showLine, lineWidth, lineStyle |
| **Akkordeon** | Auf-/zuklappbare Bereiche | title, sections (1-10), singleOpen, autoScroll, defaultOpen, fullRow |

### Visibility-Steuerung

Alle Tiles unterstützen:
- **Manuelles Verstecken** - Tile wird NICHT exportiert
- **Zeitsteuerung** - `showFrom` / `showUntil` für automatisches Ein-/Ausblenden

## 🎨 Design-Features

- **4 Akzentfarben** - Primär + 3 weitere
- **2 Tile-Styles** - Flat (transparent) oder Card (mit Schatten)
- **5 Farbschemata** - Default, White, Accent1-3
- **WCAG-Kontrast** - Automatische Textfarben-Anpassung
- **Sticky Footer** - Immer am unteren Rand
- **Rechtsrouten** - `/impressum` und `/datenschutz` öffnen Modal oder leiten weiter

## 📦 Embedding-Optionen

Seiten können mit URL-Parametern eingebettet werden:

```
?embedded=true          # Header & Footer ausblenden
?style=clean            # Transparenter Hintergrund
?style=minimalbox       # Alle Tiles weiß mit dunklem Text
?style=clean,minimalbox # Kombinierbar
```

## 🔧 Anforderungen

- PHP 7.4+ mit `mail()` Funktion
- Apache mit mod_rewrite (oder nginx)
- Schreibrechte für:
  - `/backend/data/`
  - `/backend/media/`
  - `/backend/logs/`
  - `/backend/archive/`

## 🚀 Installation

### 1. Repository klonen

```bash
git clone https://github.com/hdrk-fegko/Info-Hub.git
cd Info-Hub
```

### 2. Beispieldateien kopieren

```bash
cp backend/config.example.php backend/config.php
cp backend/data/settings.example.json backend/data/settings.json
cp backend/data/tiles.example.json backend/data/tiles.json
```

### 3. Berechtigungen setzen (Linux/Mac)

```bash
chmod 755 backend/
chmod 777 backend/data/ backend/media/ backend/logs/ backend/archive/
```

### 4. Setup aufrufen

```
https://deine-domain.de/backend/setup.php
```

### 5. Einloggen

```
https://deine-domain.de/backend/login.php
```

## 🏠 Shared Hosting / IONOS

- Die Root-`.htaccess` arbeitet jetzt relativ zum aktuellen Deploy-Ordner und leitet bei fehlender `index.html` robust zu `backend/setup.php` oder `backend/login.php`.
- `backend/.htaccess` schützt weiterhin interne Verzeichnisse, nutzt dafür aber keine `DirectoryMatch`-Direktive mehr, weil diese in `.htaccess` auf Shared Hosting häufig HTTP-500-Fehler verursacht.
- Falls ein Hoster `Options -Indexes` in `.htaccess` nicht erlaubt, kann der kommentierte Block in `backend/.htaccess` testweise auskommentiert werden.

## 📦 Deploy-ZIP bauen

Lokal:

```bash
chmod +x scripts/build-deploy-zip.sh
./scripts/build-deploy-zip.sh
```

- Ausgabe: `dist/info-hub-<branch>-<sha>.zip`
- Inhalt: nur deploy-relevante Dateien; `.github/`, `docs/`, `tests/`, `scripts/` und andere Dev-Artefakte bleiben draußen
- In GitHub Actions liegt das ZIP als Workflow-Artefakt im Lauf **Build deploy ZIP**

## 💻 Lokale Entwicklung

```bash
php -S localhost:8000
# Dann: http://localhost:8000/backend/setup.php
```

**Tipp:** `DEBUG_MODE` auf `true` setzen in `login.php` für Login-Codes ohne Mail-Server.

## 📁 Projektstruktur

```
Info-Hub/
├── index.html              # Generierte Seite
├── .htaccess               # Security & Redirects
│
├── backend/
│   ├── login.php           # Email-Code Login
│   ├── editor.php          # Visueller Editor
│   ├── setup.php           # Einmal-Setup
│   │
│   ├── api/
│   │   └── endpoints.php   # REST API
│   │
│   ├── core/               # Services
│   │   ├── AuthService.php
│   │   ├── TileService.php
│   │   ├── GeneratorService.php
│   │   ├── UploadService.php
│   │   ├── StorageService.php
│   │   ├── LogService.php
│   │   └── SecurityHelper.php
│   │
│   ├── tiles/              # Tile-Definitionen
│   │   ├── _registry.php   # Auto-Import
│   │   ├── TileBase.php    # Abstrakte Basis
│   │   ├── InfoboxTile.php
│   │   ├── DownloadTile.php
│   │   ├── ImageTile.php
│   │   ├── LinkTile.php
│   │   ├── IframeTile.php
│   │   ├── CountdownTile.php
│   │   └── ContactTile.php
│   │
│   ├── data/               # JSON-Speicher
│   ├── media/              # Uploads
│   ├── logs/               # Anwendungslogs
│   └── archive/            # Backups
│
├── assets/
│   ├── css/editor-v2.css
│   └── js/v2/
│
└── docs/                   # Dokumentation
```

## 🔐 Sicherheit

| Feature | Status |
|---------|--------|
| Email-Code-Auth | ✅ |
| Session-Timeout | ✅ 1 Stunde |
| Session-Regeneration | ✅ Nach Login |
| Rate-Limiting | ✅ 3 Versuche → 10 Min Sperre |
| CSRF-Token | ✅ Alle POST-Requests |
| Upload-Validierung | ✅ Extension + MIME |
| XSS-Schutz | ✅ htmlspecialchars() |
| Security-Warnungen | ✅ Debug-Mode & HTTPS |
| Multi-Admin | ✅ Einladungssystem mit Ablauf |

> Details: [Deployment & Wartung](docs/dev/deployment.md) (Multi-Admin, Mail-Konfiguration, Troubleshooting)

## 📚 Dokumentation

- [Benutzerhandbuch](docs/user/guide.md)
- [API-Referenz](docs/dev/api.md)
- [Architektur](docs/dev/architecture.md)
- [Deployment](docs/dev/deployment.md)
- [Roadmap](docs/ROADMAP.md)
- [Testing-Checkliste](docs/TESTING.md)

## 🤝 Contributing

Beiträge sind willkommen! Siehe [ROADMAP.md](docs/ROADMAP.md#-contributing) für Details.

1. Fork das Repository
2. Feature-Branch erstellen
3. Änderungen committen
4. Pull Request erstellen

## 📄 Lizenz

MIT License - siehe [LICENSE](LICENSE)

---

**Made with ❤️ für die Gemeindearbeit**
