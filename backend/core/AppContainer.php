<?php

/**
 * AppContainer - zentrale Composition Root fuer Backend-Entry-Points.
 *
 * Liefert lazy initialisierte Shared-Serviceinstanzen pro Request,
 * damit Entry-Points keine Objekte mehr dezentral zusammensetzen muessen.
 */

class AppContainer {

    private string $backendRoot;
    private string $configPath;

    /** @var array<string, object> */
    private array $services = [];

    /** @var array<string, StorageService> */
    private array $storage = [];

    public function __construct(string $backendRoot, string $configPath) {
        $this->backendRoot = str_replace('\\', '/', $backendRoot);
        $this->configPath = str_replace('\\', '/', $configPath);
    }

    public function backendRoot(): string {
        return $this->backendRoot;
    }

    public function configPath(): string {
        return $this->configPath;
    }

    public function authService(): AuthService {
        return $this->service('auth', static function(): AuthService {
            return new AuthService();
        });
    }

    public function backupService(): BackupService {
        return $this->service('backup', static function(): BackupService {
            return new BackupService();
        });
    }

    public function configService(): ConfigService {
        return $this->service('config', function(): ConfigService {
            return new ConfigService($this->configPath);
        });
    }

    public function generatorService(): GeneratorService {
        return $this->service('generator', function(): GeneratorService {
            return new GeneratorService($this->tileRegistry(), $this->tileService());
        });
    }

    public function settingsService(): SettingsService {
        return $this->service('settings', function(): SettingsService {
            return new SettingsService($this->storage('settings.json'), $this->configService());
        });
    }

    public function tileRegistry(): TileRegistry {
        return $this->service('tileRegistry', function(): TileRegistry {
            return new TileRegistry($this->backendRoot . '/tiles');
        });
    }

    public function tileService(): TileService {
        return $this->service('tile', function(): TileService {
            return new TileService($this->tileRegistry());
        });
    }

    public function uploadService(): UploadService {
        return $this->service('upload', static function(): UploadService {
            return new UploadService();
        });
    }

    public function storage(string $fileName): StorageService {
        if (!isset($this->storage[$fileName])) {
            $this->storage[$fileName] = new StorageService($fileName);
        }

        return $this->storage[$fileName];
    }

    /**
     * @template T of object
     * @param callable():T $factory
     * @return T
     */
    private function service(string $key, callable $factory) {
        if (!isset($this->services[$key])) {
            $this->services[$key] = $factory();
        }

        return $this->services[$key];
    }
}