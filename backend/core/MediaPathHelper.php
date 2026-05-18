<?php

class MediaPathHelper {

    /**
     * Normalisiert und validiert Projektpfade unter /backend/media.
     */
    public static function normalizeBackendMediaPath(mixed $value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));
        if ($value === '') {
            return null;
        }

        if (!preg_match('#^/backend/media/[A-Za-z0-9/_\-.]+$#', $value)) {
            return null;
        }

        return str_contains($value, '..') ? null : $value;
    }
}