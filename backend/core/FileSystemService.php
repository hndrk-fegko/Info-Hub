<?php
/**
 * FileSystemService - kapselt generische Datei- und Verzeichnisoperationen.
 *
 * JSON-Daten unter backend/data bleiben Aufgabe des StorageService.
 * Backup-/Snapshot-Dateien ausserhalb dieses Bereichs laufen ueber diesen Layer.
 */

class FileSystemService {

    public function exists(string $path): bool {
        return file_exists($path);
    }

    public function isDirectory(string $path): bool {
        return is_dir($path);
    }

    public function isFile(string $path): bool {
        return is_file($path);
    }

    public function isWritable(string $path): bool {
        return is_writable($path);
    }

    public function glob(string $pattern, int $flags = 0): array {
        $matches = glob($pattern, $flags) ?: [];

        return array_map(function(string $path): string {
            return str_replace('\\', '/', $path);
        }, $matches);
    }

    public function modifiedTime(string $path): int|false {
        return filemtime($path);
    }

    public function readFile(string $path): string|false {
        return file_get_contents($path);
    }

    public function writeFile(string $path, string $contents): void {
        $this->ensureDirectory(dirname($path));

        $temp = $path . '.tmp.' . getmypid();
        $bytes = file_put_contents($temp, $contents, LOCK_EX);
        if ($bytes === false) {
            $this->deleteFileIfExists($temp);
            throw new RuntimeException('Konnte Datei nicht schreiben: ' . basename($path));
        }

        if (!rename($temp, $path)) {
            $this->deleteFileIfExists($temp);
            throw new RuntimeException('Konnte Datei nicht finalisieren: ' . basename($path));
        }
    }

    public function copyFile(string $source, string $target): void {
        if (!$this->exists($source)) {
            throw new RuntimeException('Quelldatei fehlt: ' . basename($source));
        }

        $this->ensureDirectory(dirname($target));
        $temp = $target . '.tmp.' . getmypid();

        if (!copy($source, $temp)) {
            $this->deleteFileIfExists($temp);
            throw new RuntimeException('Konnte Datei nicht kopieren: ' . basename($source));
        }

        if (!rename($temp, $target)) {
            $this->deleteFileIfExists($temp);
            throw new RuntimeException('Konnte Datei nicht finalisieren: ' . basename($target));
        }
    }

    public function writeJsonFile(string $path, array $data): void {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('JSON konnte nicht serialisiert werden');
        }

        $this->writeFile($path, $json);
    }

    public function readJsonFile(string $path): array {
        if (!$this->exists($path)) {
            return [];
        }

        $content = $this->readFile($path);
        if (!is_string($content)) {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function ensureDirectory(string $path, int $permissions = 0755): void {
        if ($path === '' || $path === '.') {
            return;
        }

        if ($this->isDirectory($path)) {
            return;
        }

        if (!mkdir($path, $permissions, true) && !$this->isDirectory($path)) {
            throw new RuntimeException('Konnte Verzeichnis nicht erstellen: ' . basename($path));
        }
    }

    public function permissionOctal(string $path): string {
        if (!$this->exists($path)) {
            return '';
        }

        $permissions = @fileperms($path);
        if ($permissions === false) {
            return '';
        }

        return substr(sprintf('%o', $permissions), -4);
    }

    public function moveUploadedFile(string $sourceTmpPath, string $targetPath): void {
        $this->ensureDirectory(dirname($targetPath));

        if (!move_uploaded_file($sourceTmpPath, $targetPath)) {
            throw new RuntimeException('Konnte Upload nicht speichern: ' . basename($targetPath));
        }
    }

    public function fileSize(string $path): int {
        return $this->exists($path) ? (int) filesize($path) : 0;
    }

    public function getDirectorySize(string $directory): int {
        if (!$this->isDirectory($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += (int) $item->getSize();
            }
        }

        return $size;
    }

    public function deleteFileIfExists(string $path): bool {
        if (!$this->exists($path)) {
            return true;
        }

        return @unlink($path);
    }

    public function deleteDirectory(string $directory): void {
        if (!$this->isDirectory($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                $this->deleteFileIfExists($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}