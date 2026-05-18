<?php

class UploadApiActions implements ApiActionGroupInterface {

    public function register(ApiContext $context, ApiResponder $responder): array {
        return [
            'upload_image' => static function() use ($context, $responder): void {
                if (empty($_FILES['file'])) {
                    throw new InvalidArgumentException('Keine Datei hochgeladen');
                }

                $responder->result($context->uploadService()->uploadImage($_FILES['file']));
            },

            'upload_download' => static function() use ($context, $responder): void {
                if (empty($_FILES['file'])) {
                    throw new InvalidArgumentException('Keine Datei hochgeladen');
                }

                $responder->result($context->uploadService()->uploadDownload($_FILES['file']));
            },

            'upload_header' => static function() use ($context, $responder): void {
                if (empty($_FILES['file'])) {
                    throw new InvalidArgumentException('Keine Datei hochgeladen');
                }

                $result = $context->uploadService()->uploadHeader(
                    $_FILES['file'],
                    $_POST['headerPlaceholder'] ?? null,
                    $_POST['headerImageWidth'] ?? null,
                    $_POST['headerImageHeight'] ?? null
                );

                if (!empty($result['success'])) {
                    $context->settingsService()->saveSettings(
                        [
                            'site' => [
                                'headerImage' => $result['path'] ?? null,
                                'headerImagePlaceholder' => $result['placeholder'] ?? null,
                                'headerImageWidth' => isset($result['width']) ? (int) $result['width'] : null,
                                'headerImageHeight' => isset($result['height']) ? (int) $result['height'] : null,
                            ],
                        ],
                        $context->requestHost(),
                        $context->authEmail()
                    );
                }

                $responder->result($result);
            },

            'upload_background' => static function() use ($context, $responder): void {
                if (empty($_FILES['file'])) {
                    throw new InvalidArgumentException('Keine Datei hochgeladen');
                }

                $result = $context->uploadService()->uploadBackground($_FILES['file']);
                if (!empty($result['success'])) {
                    $context->settingsService()->saveSettings(
                        ['theme' => ['narrowBackgroundImage' => $result['path'] ?? null]],
                        $context->requestHost(),
                        $context->authEmail()
                    );
                }

                $responder->result($result);
            },

            'delete_file' => static function() use ($context, $responder): void {
                $success = $context->uploadService()->deleteFile(
                    $_POST['type'] ?? '',
                    $_POST['filename'] ?? '',
                    $_POST['path'] ?? null
                );

                $responder->json(['success' => $success]);
            },

            'list_files' => static function() use ($context, $responder): void {
                $files = $context->uploadService()->listFiles($_GET['type'] ?? 'images');
                $responder->success(['files' => $files]);
            },
        ];
    }
}