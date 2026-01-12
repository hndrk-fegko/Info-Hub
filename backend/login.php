<?php
/**
 * Login - Email-Code-Authentifizierung
 * 
 * Flow:
 * 1. Email eingeben → Code wird versendet
 * 2. Code eingeben → Session aktiviert
 * 3. Redirect zu editor.php
 */

// Zentrale Konfiguration laden
require_once __DIR__ . '/config.php';

session_start();

require_once __DIR__ . '/core/AuthService.php';
require_once __DIR__ . '/core/SecurityHelper.php';

$auth = new AuthService();
$securityStatus = SecurityHelper::getSecurityStatus();

// Logout-Request
if (isset($_GET['logout'])) {
    $auth->logout();
}

// Bereits eingeloggt?
if ($auth->isAuthenticated()) {
    header('Location: editor.php');
    exit;
}

$error = '';
$success = '';
$step = $_POST['step'] ?? 'email';

// Session abgelaufen?
if (isset($_GET['expired'])) {
    $error = 'Deine Session ist aus Sicherheitsgründen abgelaufen. Bitte erneut anmelden.';
}

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($step === 'email') {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Bitte gültige Email-Adresse eingeben';
        } else {
            $result = $auth->sendCode($email);
            if ($result['success']) {
                $success = $result['message'];
                $step = 'code';
            } else {
                $error = $result['message'];
            }
        }
    }
    
    elseif ($step === 'code') {
        $code = trim($_POST['code'] ?? '');
        
        if (empty($code)) {
            $error = 'Bitte Code eingeben';
            $step = 'code';
        } else {
            $result = $auth->verifyCode($code);
            if ($result['success']) {
                header('Location: editor.php');
                exit;
            } else {
                $error = $result['message'];
                $step = 'code';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Info-Hub</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.95rem;
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
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input[type="text"].code-input {
            text-align: center;
            font-size: 24px;
            letter-spacing: 8px;
            font-family: monospace;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: #e8e8e8;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .alert-error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #060;
            border: 1px solid #cfc;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .step.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .step.inactive {
            background: #e0e0e0;
            color: #999;
        }
        
        .step-connector {
            width: 30px;
            height: 2px;
            background: #e0e0e0;
            align-self: center;
        }
        
        .hint {
            text-align: center;
            color: #999;
            font-size: 0.85rem;
            margin-top: 20px;
        }
        
        /* Security Banner */
        .security-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #fff3cd;
            border-bottom: 2px solid #ffc107;
            padding: 10px 50px 10px 20px;
            z-index: 1000;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-100%); }
            to { transform: translateY(0); }
        }
        
        .security-banner .banner-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .security-banner .banner-item {
            padding: 5px 0;
            font-size: 0.9rem;
        }
        
        .security-banner .banner-error {
            color: #dc3545;
        }
        
        .security-banner .banner-warning {
            color: #856404;
        }
        
        .security-banner .banner-dismiss {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #856404;
            line-height: 1;
        }
        
        .security-banner .banner-dismiss:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <?= SecurityHelper::renderSecurityBanner() ?>
    
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Info-Hub Login</h1>
            <p>Melde dich mit deiner Email an</p>
        </div>
        
        <div class="step-indicator">
            <div class="step <?= $step === 'email' ? 'active' : 'inactive' ?>">1</div>
            <div class="step-connector"></div>
            <div class="step <?= $step === 'code' ? 'active' : 'inactive' ?>">2</div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($step === 'email'): ?>
            <form method="POST">
                <input type="hidden" name="step" value="email">
                
                <div class="form-group">
                    <label for="email">Email-Adresse</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="admin@example.com"
                        autofocus
                        required
                    >
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Code senden 📧
                </button>
            </form>
            
            <p class="hint">
                Du erhältst einen 6-stelligen Login-Code per Email.
            </p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="step" value="code">
                
                <div class="form-group">
                    <label for="code">Login-Code</label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code"
                        class="code-input"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        placeholder="000000"
                        autofocus
                        required
                        autocomplete="one-time-code"
                    >
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Einloggen 🚀
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="location.href='login.php'">
                    ← Zurück
                </button>
            </form>
            
            <p class="hint">
                Code gültig für 15 Minuten. Prüfe auch deinen Spam-Ordner.
            </p>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-focus und nur Zahlen im Code-Input erlauben
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }
    </script>
</body>
</html>
