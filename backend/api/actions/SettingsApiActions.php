<?php

class SettingsApiActions implements ApiActionGroupInterface {

    public function register(ApiContext $context, ApiResponder $responder): array {
        return [
            'get_settings' => static function() use ($context, $responder): void {
                $responseSettings = $context->settingsService()->getSettingsForEditor(
                    $context->requestHost(),
                    $context->authEmail()
                );

                $responder->success(['settings' => $responseSettings]);
            },

            'save_settings' => static function() use ($context, $responder): void {
                $newSettings = $context->readArrayPayload('settings', 'settings');
                $responseSettings = $context->settingsService()->saveSettings(
                    $newSettings,
                    $context->requestHost(),
                    $context->authEmail()
                );

                $responder->success(['settings' => $responseSettings]);
            },
        ];
    }
}