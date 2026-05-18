<?php

class AdminApiActions implements ApiActionGroupInterface {

    public function register(ApiContext $context, ApiResponder $responder): array {
        return [
            'get_admins' => static function() use ($context, $responder): void {
                $responder->success($context->adminSnapshot());
            },

            'invite_admin' => static function() use ($context, $responder): void {
                $email = $_POST['email'] ?? '';
                $createdBy = $context->authEmail();
                $result = $context->auth()->createInvite($email, $createdBy);
                $responder->result($result + $context->adminSnapshot());
            },

            'remove_admin_email' => static function() use ($context, $responder): void {
                $email = $_POST['email'] ?? '';
                $currentEmail = $context->authEmail();
                $result = $context->auth()->removeAdminEmail($email);

                if (!empty($result['success']) && strtolower(trim($email)) === strtolower(trim($currentEmail))) {
                    LogService::info('AuthService', 'Admin self-deleted, destroying session', ['email' => $email]);
                    $result['self_deleted'] = true;
                    session_destroy();
                }

                $responder->result($result + $context->adminSnapshot());
            },

            'remove_admin_invite' => static function() use ($context, $responder): void {
                $email = $_POST['email'] ?? '';
                $result = $context->auth()->removeInvite($email);
                $responder->result($result + $context->adminSnapshot());
            },
        ];
    }
}