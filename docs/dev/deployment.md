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

## 📧 Mail-Konfiguration

Info-Hub versendet Mails für Login-Codes und Admin-Einladungen über PHP `mail()` / Sendmail.

### Absender-Adresse

Die Absender-Adresse wird automatisch aus der Domain ermittelt (`noreply@deine-domain.de`). Falls der Server für eine andere Domain senden soll (z.B. Subdomain auf fremdem Server), kann sie explizit gesetzt werden:

```php
// backend/config.php
define('MAIL_FROM_ADDRESS', 'noreply@deine-domain.de');
```

**Wichtig:** Die Domain muss zum sendenden Server passen (SPF-Record).

### DKIM-Signierung

Für bessere Zustellbarkeit (weniger Spam-Einstufung):
1. In Plesk: **Mail-Einstellungen → DKIM** aktivieren
2. DNS-Eintrag für DKIM-Key hinterlegen (Plesk zeigt den benötigten Record an)
3. Optional: SPF-Record im DNS prüfen

### Debug-Modus für Mail

Bei Mail-Problemen `DEBUG_MODE = true` in `config.php` setzen. Dann werden ausführliche Mail-Logs geschrieben (Empfänger, Header, Sendmail-Pfad, MTA-Ergebnis) in `/backend/logs/app.log`.

---

## 👥 Multi-Admin-Verwaltung

Info-Hub unterstützt mehrere Administratoren. Der erste Admin wird beim Setup festgelegt.

### Weitere Admins einladen

1. Im Editor → ⚙️ **Settings** → Abschnitt „Administratoren"
2. **[+ Admin einladen]** → Email-Adresse eingeben
3. Einladungslink wird per Mail versendet
4. Eingeladener Admin klickt den Link und meldet sich an → Account wird aktiviert

### Admins entfernen

- ✕-Button neben der Admin-Adresse
- Der letzte verbleibende Admin kann nicht gelöscht werden
- **Selbstlöschung:** Wer sich selbst entfernt, wird sofort abgemeldet und kann sich nicht mehr einloggen, bis ein anderer Admin erneut einlädt

### Sicherheit

- Einladungen sind **1 Stunde** gültig (konfigurierbar: `ADMIN_INVITE_EXPIRY` in `config.php`)
- Beide Admins sollten gleichzeitig am Rechner sein
- Abgelaufene Einladungen werden automatisch bereinigt
- Jeder Admin meldet sich per eigenem Email-Code an (keine geteilten Zugänge)

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

### Problem: Mails kommen nicht an (externer Empfänger)

**Symptom:** Mails an Adressen auf dem eigenen Server funktionieren, aber externe Empfänger (z.B. Outlook, Gmail) erhalten keine Mail.

**Ursache:** Das ist in vielen Hosting-Setups **Security by Design**. Der Mailserver ist standardmäßig nur für den Versand an eigene Domains konfiguriert — ohne Whitelist, ohne Relay. Das verhindert Spam-Missbrauch.

**Lösung (Plesk):**
1. Plesk → **Domains** → deine Domain → **Mail-Einstellungen**
2. Option aktivieren: *„Für eingehende E-Mails deaktiviert — auf dieser Domain können E-Mails nur gesendet werden, und zwar ausschließlich via Sendmail"*
3. **DKIM-Spamschutz** aktivieren für bessere Zustellbarkeit
4. DNS-Records prüfen (SPF, DKIM) — Plesk zeigt die benötigten Einträge an

**Alternative für Ehrenamtliche mit eigener Mail-Adresse:**
Wenn der externe Mailversand nicht aktiviert werden soll/kann, können Ehrenamtliche eine Weiterleitung auf der eigenen Domain einrichten (z.B. `vorname@deine-domain.de` → private Adresse). So bleibt der Mailversand server-intern.

### Problem: Mails landen im Spam

- **DKIM** in Plesk aktivieren und DNS-Key hinterlegen
- **SPF-Record** prüfen: `v=spf1 a mx ip4:SERVER-IP ~all`
- `MAIL_FROM_ADDRESS` in `config.php` auf eine Domain setzen, für die SPF/DKIM konfiguriert ist

### Problem: Admin-Einladung abgelaufen

Einladungen sind standardmäßig 1 Stunde gültig. Bei Ablauf einfach die alte Einladung löschen und eine neue versenden. Einstellbar via `ADMIN_INVITE_EXPIRY` in `config.php`.

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
