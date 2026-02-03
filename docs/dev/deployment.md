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

## Troubleshooting

### Problem: Header-Bild Upload funktioniert nicht

**Symptom:** "Keine Schreibrechte im Upload-Verzeichnis" oder "Datei konnte nicht gespeichert werden"

**Ursache:** Der Webserver hat keine Schreibrechte auf `/backend/media/`

**Lösung:**

1. **Im Editor**: Ein Warnbanner wird angezeigt mit der Lösungsanleitung
2. **über SSH/Terminal**:

```bash
# Option A: Maximale Rechte (einfach, aber weniger sicher)
chmod 777 backend/media/images
chmod 777 backend/media/downloads
chmod 777 backend/media/header
chmod 777 backend/data
chmod 777 backend/logs
chmod 777 backend/archive

# Option B: Korrekte Eigentümerschaft (sicherer)
# Ersetze 'www-data' mit dem aktuellen Webserver-User (apache, www-data, nginx, etc.)
chown -R www-data:www-data backend/
chmod 755 backend/media/images
chmod 755 backend/media/downloads
chmod 755 backend/media/header
chmod 755 backend/data
```

3. **Den aktuellen Webserver-User finden**:
```bash
ps aux | grep -E 'apache|nginx|www-data' | head -1
```

### Problem: Logs zeigen "Permission denied"

Das System versucht in `/backend/logs/app.log` zu schreiben, hat aber keine Rechte.

```bash
chmod 777 backend/logs
```

### Problem: Berechtigungen nach mkdir() nicht korrekt

Das System setzt beim Erstellen neuer Ordner standardmäßig `0777` (maximale Rechte).
Falls das nicht funktioniert, kann es an der `umask`-Einstellung liegen.

```bash
# In der Shell prüfen
umask
# Falls zu restriktiv (z.B. 0077), manuell vor PHP ausführen anpassen
# ODER: Direct chmod nach mkdir nutzen
```

### Problem: Setup schlägt fehl bei Verzeichnis-Erstellung

**Fehler:** "Verzeichnis konnte nicht erstellt werden"

1. Prüfe ob der Parent-Ordner `/backend/` existiert und schreibbar ist
2. Manuell erstellen und Rechte setzen:

```bash
mkdir -p backend/{data,logs,archive,media/{images,downloads,header}}
chmod 777 backend/data backend/logs backend/archive
chmod 777 backend/media/images backend/media/downloads backend/media/header
```

3. Setup erneut ausführen

### Problem: Email-Code wird nicht versendet

Prüfe ob `mail()` auf dem Server funktioniert:

```bash
# Im Editor: Logs prüfen
tail -f backend/logs/app.log | grep AuthService

# Oder Test-Script:
php -r "mail('test@example.com', 'Test', 'Test Mail'); echo 'Mail sent';"
```

Falls das nicht funktioniert:
- Kontaktiere den Hosting-Provider
- Oder nutze einen externen SMTP-Service (erfordert Änderung in `AuthService.php`)

---

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
