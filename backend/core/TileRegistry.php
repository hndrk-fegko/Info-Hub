<?php
/**
 * TileRegistry - registriert alle Tile-Typen explizit als Abhaengigkeit.
 *
 * Laedt Tile-Klassen aus backend/tiles und stellt Lookup-/Factory-Methoden
 * fuer Services bereit, ohne dass diese auf globale Registry-Zustaende
 * zugreifen muessen.
 */

require_once __DIR__ . '/../tiles/TileBase.php';

class TileRegistry {

    private static ?self $shared = null;

    private string $tilesDirectory;

    /** @var array<string, class-string<TileBase>> */
    private array $types;

    public function __construct(?string $tilesDirectory = null) {
        $this->tilesDirectory = str_replace('\\', '/', $tilesDirectory ?? (__DIR__ . '/../tiles'));
        $this->types = $this->discoverTypes();
    }

    public static function shared(): self {
        if (self::$shared === null) {
            self::$shared = new self();
        }

        return self::$shared;
    }

    /**
     * @return array<string, class-string<TileBase>>
     */
    public function all(): array {
        return $this->types;
    }

    public function has(string $type): bool {
        return isset($this->types[$type]);
    }

    /**
     * @return class-string<TileBase>|null
     */
    public function getClassName(string $type): ?string {
        return $this->types[$type] ?? null;
    }

    public function create(string $type): ?TileBase {
        $className = $this->getClassName($type);
        if ($className === null) {
            return null;
        }

        return new $className();
    }

    /**
     * @return array<string, class-string<TileBase>>
     */
    private function discoverTypes(): array {
        $types = [];

        foreach (glob($this->tilesDirectory . '/*Tile.php') ?: [] as $tileFile) {
            $className = basename($tileFile, '.php');
            if ($className === 'TileBase') {
                continue;
            }

            require_once $tileFile;

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || !$reflection->isSubclassOf(TileBase::class)) {
                continue;
            }

            $type = strtolower(str_replace('Tile', '', $className));
            $types[$type] = $className;
        }

        ksort($types);

        return $types;
    }
}