<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$_SESSION['authenticated'] = true;
$_SESSION['auth_time'] = time();
$_SESSION['csrf_token'] = 'browser_smoke_csrf_token';
$_SESSION['auth_email'] = 'browser-smoke@example.com';

ob_start();
$oldLevel = error_reporting(E_ALL & ~E_WARNING);
include dirname(__DIR__, 2) . '/backend/v2/editor.php';
error_reporting($oldLevel);
$html = (string) ob_get_clean();

$baseTag = '<base href="/backend/v2/">';
if (strpos($html, $baseTag) === false) {
    $html = preg_replace('/<head>/', "<head>\n    {$baseTag}", $html, 1) ?? $html;
}

header('Content-Type: text/html; charset=UTF-8');
echo $html;