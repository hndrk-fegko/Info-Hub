<?php

class SystemApiActions implements ApiActionGroupInterface {

    public function register(ApiContext $context, ApiResponder $responder): array {
        return [
            'extend_session' => static function() use ($responder): void {
                $_SESSION['auth_time'] = time();
                $responder->success(['message' => 'Session verlängert']);
            },

            'check_permissions' => static function() use ($responder): void {
                require_once __DIR__ . '/../../core/SecurityHelper.php';
                $perms = SecurityHelper::checkMediaDirectoryPermissions();
                $responder->json([
                    'success' => $perms['writable'],
                    'permissions' => $perms,
                ]);
            },
        ];
    }
}