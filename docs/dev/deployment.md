# Deployment & Wartung - Info-Hub

> Setup-Anleitung für Administratoren

## Systemanforderungen

- PHP 7.4 oder höher
- Webserver (Apache mit mod_rewrite oder nginx)
- `mail()` Funktion für Email-Versand
- Schreibrechte für:
  - `/backend/data/`
  - `/backend/media/`
  - `/backend/logs/`
  - `/backend/archive/`

---

## Installation (< 5 Minuten)

### 1. Dateien hochladen

```bash
scp infohub.zip user@host:/var/www/subdomain/
ssh user@host
cd /var/www/subdomain/
unzip infohub.zip
```

### 2. Berechtigungen setzen

```bash
chmod 755 backend/
chmod 644 backend/*.php
chmod 777 backend/data/
chmod 777 backend/media/
chmod 777 backend/logs/
chmod 777 backend/archive/
```

### 3. Setup ausführen

```
https://subdomain.example.com/backend/setup.php
```

Eingaben:
- Admin-Email-Adresse
- Seiten-Titel
- Optional: Header-Bild

Das Setup erstellt automatisch:
- `settings.json` mit Konfiguration
- `tiles.json` (leer)
- `.htaccess` mit Sicherheitsregeln
- Löscht `setup.php` selbst

### 4. Login testen

```
https://subdomain.example.com/backend/login.php
```

---

## Wartung

### Logs prüfen

```bash
tail -f /var/www/subdomain/backend/logs/app.log
```

### Backup erstellen

```bash
tar -czf backup_$(date +%Y%m%d).tar.gz backend/data/ backend/media/
```

### Restore

```bash
tar -xzf backup_20260112.tar.gz
```

---

## Sicherheit

### CSRF-Schutz

Alle POST-Requests im Editor erfordern einen CSRF-Token.
Der Token wird bei jedem Login neu generiert.

### Session-Sicherheit

- Session wird nach Login regeneriert (`session_regenerate_id`)
- Timeout nach 1 Stunde Inaktivität
- Automatische Verlängerung bei Aktivität

### .htaccess

Die `.htaccess` schützt:
- Direkten Zugriff auf `/backend/data/`
- Direkten Zugriff auf `.json`-Dateien
- Direkten Zugriff auf Logs
- Direkten Zugriff auf PHP-Dateien in `/core/` und `/tiles/`

### SecurityHelper-Warnungen

Das System warnt automatisch bei:
- **DEBUG_MODE aktiv** - Login-Code ohne Email (nur Dev!)
- **Kein HTTPS** - Verbindung unverschlüsselt
- **PHP-Fehler sichtbar** - display_errors aktiv

Warnungen erscheinen:
- Als Banner auf der Login-Seite
- Als Badge im Editor-Header
- In der Login-Email

### Zentrale Konfiguration

Alle Einstellungen werden in `/backend/config.php` verwaltet:

```php
// DEBUG_MODE systemweit ein/ausschalten
define('DEBUG_MODE', false);  // true für Development

// Session-Timeout, Upload-Limits, etc.
```

### Checkliste Production

- [ ] DEBUG_MODE = false in `backend/config.php`
- [ ] HTTPS aktivieren
- [ ] Regelmäßige Backups
- [ ] Log-Rotation prüfen (automatisch bei 5MB)

---

## Embedding

Die generierte Seite kann in andere Websites eingebettet werden:

```html
<iframe src="https://info.example.com/?embedded=true&style=clean" 
        width="100%" height="600"></iframe>
```

**URL-Parameter:**

| Parameter | Wirkung |
|-----------|---------|
| `?embedded=true` | Header & Footer ausblenden |
| `?style=clean` | Transparenter Hintergrund |
| `?style=minimalbox` | Alle Tiles weiß mit dunklem Text |

Parameter sind kombinierbar: `?embedded=true&style=clean,minimalbox`
