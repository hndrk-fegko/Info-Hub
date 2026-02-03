<?php
/**
 * Setup - Einmalige Erstkonfiguration
 * 
 * Wird nach erfolgreichem Setup automatisch gelöscht!
 * 
 * Konfiguriert:
 * - Admin Email (für Login)
 * - Seiten-Titel
 * - Header-Bild (optional)
 * - Erstellt benötigte Verzeichnisse
 * - Erstellt initiale JSON-Dateien
 */

// Zentrale Konfiguration laden (falls vorhanden)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Fallback für frische Installation
    define('DEBUG_MODE', false);
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Bereits konfiguriert?
$settingsFile = __DIR__ . '/data/settings.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!empty($settings['auth']['email'])) {
        // Setup bereits durchgeführt
        header('Location: login.php');
        exit;
    }
}

$errors = [];
$success = false;

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = trim($_POST['email'] ?? '');
    $title = trim($_POST['title'] ?? 'Info-Hub');
    $footerText = trim($_POST['footerText'] ?? '');
    
    // Validierung
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Gültige Email-Adresse erforderlich';
    }
    
    if (empty($title)) {
        $errors[] = 'Seiten-Titel erforderlich';
    }
    
    if (empty($errors)) {
        // Verzeichnisse erstellen mit Fehlerprüfung
        $dirs = [
            __DIR__ . '/data',
            __DIR__ . '/logs',
            __DIR__ . '/archive',
            __DIR__ . '/media/images',
            __DIR__ . '/media/downloads',
            __DIR__ . '/media/header'
        ];
        
        $permissionWarnings = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {  // 0777 statt 0755 für bessere Kompatibilität
                    $errors[] = "Verzeichnis konnte nicht erstellt werden: " . basename($dir);
                    continue;
                }
            }
            
            // Nach Erstellung prüfen ob schreibbar
            if (!is_writable($dir)) {
                $permissionWarnings[] = basename($dir) . " (chmod 777 erforderlich)";
            }
        }
        
        // Warnung anzeigen (aber Setup nicht blockieren)
        $permissionWarning = '';
        if (!empty($permissionWarnings)) {
            $permissionWarning = "⚠️ Schreibrechte-Problem erkannt. Bitte nach Setup ausführen:<br>" .
                       "<code style='background:#333;color:#0f0;padding:8px;display:block;margin-top:8px;border-radius:4px;'>" .
                       "chmod 777 backend/" . implode(" backend/", $permissionWarnings) .
                       "</code>";
        }
        
        // Header-Bild verarbeiten
        $headerImage = null;
        if (!empty($_FILES['headerImage']['tmp_name'])) {
            $uploadDir = __DIR__ . '/media/header/';
            $ext = strtolower(pathinfo($_FILES['headerImage']['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $filename = 'header_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['headerImage']['tmp_name'], $uploadDir . $filename)) {
                    $headerImage = '/backend/media/header/' . $filename;
                }
            }
        }
        
        // Settings erstellen
        $settings = [
            'site' => [
                'title' => $title,
                'headerImage' => $headerImage,
                'footerText' => $footerText ?: '© ' . date('Y')
            ],
            'theme' => [
                'backgroundColor' => '#f5f5f5',
                'accentColor' => '#667eea',
                'accentColor2' => '#48bb78',
                'accentColor3' => '#ed8936'
            ],
            'auth' => [
                'email' => $email
            ]
        ];
        
        // Settings speichern
        file_put_contents(
            $settingsFile,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        // Leere tiles.json erstellen
        file_put_contents(
            __DIR__ . '/data/tiles.json',
            json_encode([], JSON_PRETTY_PRINT)
        );
        
        // config.php erstellen (falls nicht vorhanden)
        $configFile = __DIR__ . '/config.php';
        if (!file_exists($configFile)) {
            $configExample = __DIR__ . '/config.example.php';
            if (file_exists($configExample)) {
                // Kopiere config.example.php als config.php
                copy($configExample, $configFile);
            } else {
                // Fallback: config.php manuell erstellen
                $configContent = <<<'CONFIG'
<?php
/**
 * ZENTRALE KONFIGURATION - Info-Hub
 * Automatisch erstellt durch Setup
 */

// DEBUG_MODE: false für Produktion, true für Entwicklung
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

define('SESSION_TIMEOUT', 3600);
define('SESSION_WARNING_BEFORE', 300);
define('LOGIN_CODE_EXPIRY', 900);
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_LOCKOUT_DURATION', 600);
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
define('MAX_DOWNLOAD_SIZE', 50 * 1024 * 1024);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOWNLOAD_EXTENSIONS', ['pdf', 'docx', 'xlsx', 'zip', 'pptx']);
CONFIG;
                file_put_contents($configFile, $configContent);
            }
        }
        
        // .htaccess in /backend/ NICHT überschreiben wenn bereits vorhanden
        // Die bestehende .htaccess ist besser konfiguriert
        $htaccessFile = __DIR__ . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            $htaccess = <<<'HTACCESS'
# Info-Hub Backend Security Rules

# Disable directory listing
Options -Indexes

# Prevent access to hidden files
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Protect data, logs, archive directories
<DirectoryMatch "(data|logs|archive|core|tiles|templates)">
    Require all denied
</DirectoryMatch>

# Block direct access to sensitive files
<FilesMatch "\.(json|log|bak)$">
    Require all denied
</FilesMatch>
HTACCESS;
            file_put_contents($htaccessFile, $htaccess);
        }
        
        $success = true;
        
        // Setup-Datei löschen (Sicherheit)
        // Auskommentiert für Development - in Production aktivieren!
        // unlink(__FILE__);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Info-Hub</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            padding: 40px;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .setup-header h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }
        
        .setup-header p {
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group label small {
            font-weight: normal;
            color: #999;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #11998e;
        }
        
        .form-group input[type="file"] {
            padding: 10px;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        
        .alert-warning {
            background: rgba(251, 146, 60, 0.1);
            color: #ea580c;
            border: 1px solid rgba(251, 146, 60, 0.3);
            border-left: 4px solid #fb923c;
        }
        
        .alert-warning code {
            display: block;
            margin-top: 8px;
            padding: 8px;
            background: #1a1a1a;
            color: #0f0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            overflow-x: auto;
        }
        
        .alert-error ul {
            margin: 10px 0 0 20px;
        }
        
        .success-message {
            text-align: center;
        }
        
        .success-message .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .success-message h2 {
            color: #11998e;
            margin-bottom: 15px;
        }
        
        .success-message p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .checklist {
            text-align: left;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .checklist h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .checklist ul {
            list-style: none;
        }
        
        .checklist li {
            padding: 8px 0;
            color: #11998e;
        }
        
        .checklist li::before {
            content: '✓ ';
        }
        
        .hint {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <?php if ($success): ?>
            <div class="success-message">
                <div class="icon">🎉</div>
                <h2>Setup erfolgreich!</h2>
                <p>Dein Info-Hub ist bereit zur Verwendung.</p>
                
                <?php if (!empty($permissionWarning)): ?>
                    <div class="alert alert-warning" style="margin: 20px 0; text-align: left;">
                        <?= $permissionWarning ?>
                    </div>
                <?php endif; ?>
                
                <div class="checklist">
                    <h3>Was wurde erstellt:</h3>
                    <ul>
                        <li>Admin-Konto mit Email-Login</li>
                        <li>Zentrale Konfiguration (config.php)</li>
                        <li>Verzeichnisstruktur für Uploads</li>
                        <li>Datenbank-Dateien (JSON)</li>
                    </ul>
                </div>
                
                <p class="hint" style="margin-bottom: 15px;">
                    💡 Tipp: Für Entwicklung DEBUG_MODE in <code>config.php</code> auf <code>true</code> setzen.
                </p>
                
                <a href="login.php" class="btn btn-primary">
                    Zum Login →
                </a>
            </div>
        <?php else: ?>
            <div class="setup-header">
                <h1>🚀 Info-Hub Setup</h1>
                <p>Konfiguriere dein Info-Hub in unter 2 Minuten</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Fehler:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="email">
                        Admin Email-Adresse *
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email"
                        placeholder="admin@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >
                    <p class="hint">Diese Email wird für den Login verwendet (Email-Code-Verfahren)</p>
                </div>
                
                <div class="form-group">
                    <label for="title">
                        Seiten-Titel *
                    </label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title"
                        placeholder="z.B. Biblischer Unterricht 2026"
                        value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="headerImage">
                        Header-Bild <small>(optional)</small>
                    </label>
                    <input 
                        type="file" 
                        id="headerImage" 
                        name="headerImage"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                    >
                    <p class="hint">Empfohlen: 1920x400px. Kann später geändert werden.</p>
                </div>
                
                <div class="form-group">
                    <label for="footerText">
                        Footer-Text <small>(optional)</small>
                    </label>
                    <input 
                        type="text" 
                        id="footerText" 
                        name="footerText"
                        placeholder="© 2026 Meine Organisation"
                        value="<?= htmlspecialchars($_POST['footerText'] ?? '') ?>"
                    >
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Setup abschließen ✨
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
