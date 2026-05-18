<?php

class ApiContext {

    private AppContainer $container;
    private AuthService $auth;
    private SettingsService $settingsService;

    private ?array $requestJson = null;
    private ?TileService $tileService = null;
    private ?GeneratorService $generatorService = null;
    private ?BackupService $backupService = null;
    private ?UploadService $uploadService = null;

    public function __construct(AppContainer $container, AuthService $auth, SettingsService $settingsService) {
        $this->container = $container;
        $this->auth = $auth;
        $this->settingsService = $settingsService;
    }

    public function auth(): AuthService {
        return $this->auth;
    }

    public function settingsService(): SettingsService {
        return $this->settingsService;
    }

    public function tileService(): TileService {
        if (!$this->tileService instanceof TileService) {
            $this->tileService = $this->container->tileService();
        }

        return $this->tileService;
    }

    public function generatorService(): GeneratorService {
        if (!$this->generatorService instanceof GeneratorService) {
            $this->generatorService = $this->container->generatorService();
        }

        return $this->generatorService;
    }

    public function backupService(): BackupService {
        if (!$this->backupService instanceof BackupService) {
            $this->backupService = $this->container->backupService();
        }

        return $this->backupService;
    }

    public function uploadService(): UploadService {
        if (!$this->uploadService instanceof UploadService) {
            $this->uploadService = $this->container->uploadService();
        }

        return $this->uploadService;
    }

    public function readJsonRequest(): array {
        if (is_array($this->requestJson)) {
            return $this->requestJson;
        }

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            $this->requestJson = [];
            return $this->requestJson;
        }

        $decoded = json_decode($rawBody, true);
        $this->requestJson = is_array($decoded) ? $decoded : [];

        return $this->requestJson;
    }

    public function readArrayPayload(string $postKey, string $bodyKey, array $default = []): array {
        $postValue = json_decode($_POST[$postKey] ?? 'null', true);
        if (is_array($postValue) && $postValue !== []) {
            return $postValue;
        }

        $requestData = $this->readJsonRequest();
        $bodyValue = $requestData[$bodyKey] ?? $default;
        return is_array($bodyValue) ? $bodyValue : $default;
    }

    public function requireStringParam(array $values, string $message): string {
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        throw new InvalidArgumentException($message);
    }

    public function adminSnapshot(): array {
        return [
            'emails' => $this->auth->getAdminEmails(),
            'invites' => $this->auth->getPendingInvites(),
        ];
    }

    public function requestHost(): string {
        return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    }

    public function authEmail(): string {
        return $_SESSION['auth_email'] ?? '';
    }
}