<?php
/**
 * Legacy editor entrypoint.
 *
 * The classic editor implementation has been retired.
 * Existing bookmarks are redirected to the canonical V2 editor.
 */

$bootstrapMode = 'page';
$bootstrapServices = [
    'AuthService',
];
$bootstrapMissingConfigRedirect = 'setup.php';
$bootstrap = require __DIR__ . '/bootstrap.php';

$auth = $bootstrap['container']->authService();
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

header('Location: v2/editor.php');
exit;
