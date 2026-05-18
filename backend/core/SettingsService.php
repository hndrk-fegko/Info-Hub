<?php
/**
 * SettingsService - fachliche Logik fuer globale Einstellungen.
 *
 * Kapselt Laden, Maskieren, Validieren und Persistieren der Settings,
 * damit API-Endpoints nur noch Transport und Response-Mapping uebernehmen.
 */

require_once __DIR__ . '/ConfigService.php';
require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/MediaPathHelper.php';
require_once __DIR__ . '/StorageService.php';

class SettingsService {

    private StorageService $storage;
    private ConfigService $configService;

    public function __construct(?StorageService $storage = null, ?ConfigService $configService = null) {
        $this->storage = $storage ?? new StorageService('settings.json');
        $this->configService = $configService ?? new ConfigService();
    }

    public function getSettingsForEditor(?string $fallbackHost = null, ?string $adminEmail = null): array {
        $settings = $this->storage->read();

        return $this->buildEditorResponseSettings($settings, $fallbackHost, $adminEmail);
    }

    public function saveSettings(array $newSettings, ?string $fallbackHost = null, ?string $adminEmail = null): array {
        $settings = $this->storage->read();
        $settings = $this->applySiteSettings($settings, $newSettings['site'] ?? null);
        $settings = $this->applyThemeSettings($settings, $newSettings['theme'] ?? null);

        $mailFromAddress = $this->resolveMailFromAddress($newSettings['system'] ?? null, $fallbackHost, $adminEmail);

        unset($settings['system']);

        if (!$this->storage->write($settings)) {
            throw new RuntimeException('Speichern fehlgeschlagen');
        }

        LogService::info('SettingsService', 'Settings saved');

        $responseSettings = $settings;
        $responseSettings['system']['mailFromAddress'] = $mailFromAddress;

        return $responseSettings;
    }

    private function buildEditorResponseSettings(array $settings, ?string $fallbackHost, ?string $adminEmail): array {
        $responseSettings = $settings;

        if (isset($responseSettings['auth']['email'])) {
            $responseSettings['auth']['emailMasked'] = $this->maskEmail((string) $responseSettings['auth']['email']);
        }

        if (isset($responseSettings['auth']['emails']) && is_array($responseSettings['auth']['emails'])) {
            $responseSettings['auth']['emailsMasked'] = array_map(function($email) {
                return $this->maskEmail((string) $email);
            }, $responseSettings['auth']['emails']);
        }

        unset($responseSettings['auth']['email'], $responseSettings['auth']['emails'], $responseSettings['auth']['invites'], $responseSettings['system']);
        $responseSettings['system']['mailFromAddress'] = $this->configService->getMailFromAddress($fallbackHost, $adminEmail);

        return $responseSettings;
    }

    private function applySiteSettings(array $settings, mixed $siteInput): array {
        if (!is_array($siteInput)) {
            return $settings;
        }

        $previousHeaderImage = $settings['site']['headerImage'] ?? null;
        $allowedSiteKeys = [
            'title',
            'pageTitle',
            'headerImage',
            'headerFocusPoint',
            'footerText',
            'headerImagePlaceholder',
            'headerImageWidth',
            'headerImageHeight',
        ];

        foreach ($allowedSiteKeys as $key) {
            if (!array_key_exists($key, $siteInput)) {
                continue;
            }

            if ($key === 'headerImage') {
                $settings['site'][$key] = MediaPathHelper::normalizeBackendMediaPath($siteInput[$key]);
                continue;
            }

            if ($key === 'headerImagePlaceholder') {
                $settings['site'][$key] = $this->normalizeHeaderPlaceholder($siteInput[$key]);
                continue;
            }

            if ($key === 'headerImageWidth' || $key === 'headerImageHeight') {
                $settings['site'][$key] = $this->normalizePositiveIntOrNull($siteInput[$key]);
                continue;
            }

            $settings['site'][$key] = $siteInput[$key];
        }

        if (array_key_exists('headerImage', $siteInput) && empty($siteInput['headerImage'])) {
            $settings['site']['headerImage'] = null;
            $settings['site']['headerImagePlaceholder'] = null;
            $settings['site']['headerImageWidth'] = null;
            $settings['site']['headerImageHeight'] = null;
        } elseif (($settings['site']['headerImage'] ?? null) !== $previousHeaderImage) {
            $settings['site']['headerImagePlaceholder'] = null;
            $settings['site']['headerImageWidth'] = null;
            $settings['site']['headerImageHeight'] = null;
        }

        return $settings;
    }

    private function applyThemeSettings(array $settings, mixed $themeInput): array {
        if (!is_array($themeInput)) {
            return $settings;
        }

        $colorKeys = [
            'backgroundColor',
            'accentColor',
            'accentColor2',
            'accentColor3',
            'narrowBackgroundColor',
            'narrowGradientColor1',
            'narrowGradientColor2',
            'narrowBackgroundOverlayColor',
        ];

        foreach ($colorKeys as $key) {
            if (!isset($themeInput[$key])) {
                continue;
            }

            $color = $themeInput[$key];
            if ($this->isValidHexColor($color)) {
                $settings['theme'][$key] = $color;
            }
        }

        $settings = $this->applyEnumThemeSetting($settings, $themeInput, 'narrowBackgroundMode', ['solid', 'gradient', 'image']);
        $settings = $this->applyEnumThemeSetting($settings, $themeInput, 'narrowBackgroundImageDisplay', ['cover', 'tile']);
        $settings = $this->applyEnumThemeSetting($settings, $themeInput, 'narrowBackgroundImageMotion', ['fixed', 'parallax']);

        $booleanKeys = [
            'narrowLayout',
            'narrowBackgroundOverlayEnabled',
            'narrowBackgroundOverlayColorEnabled',
            'narrowBackgroundOverlayBlurEnabled',
            'narrowContentShadow',
        ];

        foreach ($booleanKeys as $key) {
            if (isset($themeInput[$key])) {
                $settings['theme'][$key] = (bool) $themeInput[$key];
            }
        }

        $settings = $this->applyRangedIntThemeSetting($settings, $themeInput, 'narrowWidth', 600, 1400);
        $settings = $this->applyRangedIntThemeSetting($settings, $themeInput, 'narrowGradientAngle', 0, 360);
        $settings = $this->applyRangedIntThemeSetting($settings, $themeInput, 'narrowBackgroundOverlayOpacity', 0, 100);
        $settings = $this->applyRangedIntThemeSetting($settings, $themeInput, 'narrowBackgroundOverlayBlurStrength', 0, 100);

        if (array_key_exists('narrowBackgroundImage', $themeInput)) {
            $settings['theme']['narrowBackgroundImage'] = MediaPathHelper::normalizeBackendMediaPath($themeInput['narrowBackgroundImage']);
        }

        return $settings;
    }

    private function resolveMailFromAddress(mixed $systemInput, ?string $fallbackHost, ?string $adminEmail): string {
        if (is_array($systemInput) && isset($systemInput['mailFromAddress'])) {
            $mailFromAddress = strtolower(trim((string) $systemInput['mailFromAddress']));
            if ($mailFromAddress === '' || !filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Gültige Absender-Adresse erforderlich');
            }

            if (!$this->configService->updateMailFromAddress($mailFromAddress)) {
                throw new RuntimeException('MAIL_FROM_ADDRESS konnte nicht in config.php gespeichert werden');
            }

            return $mailFromAddress;
        }

        return $this->configService->getMailFromAddress($fallbackHost, $adminEmail);
    }

    private function applyEnumThemeSetting(array $settings, array $themeInput, string $key, array $allowedValues): array {
        if (!isset($themeInput[$key])) {
            return $settings;
        }

        $value = (string) $themeInput[$key];
        if (in_array($value, $allowedValues, true)) {
            $settings['theme'][$key] = $value;
        }

        return $settings;
    }

    private function applyRangedIntThemeSetting(array $settings, array $themeInput, string $key, int $min, int $max): array {
        if (!isset($themeInput[$key])) {
            return $settings;
        }

        $value = (int) $themeInput[$key];
        if ($value >= $min && $value <= $max) {
            $settings['theme'][$key] = $value;
        }

        return $settings;
    }

    private function maskEmail(string $email): string {
        return '***' . substr($email, -10);
    }

    private function isValidHexColor(mixed $value): bool {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    private function normalizeHeaderPlaceholder(mixed $value): ?string {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (!preg_match('#^data:image/(?:webp|jpeg);base64,[A-Za-z0-9+/=]+$#', $value) || strlen($value) > 100000) {
            return null;
        }

        return $value;
    }

    private function normalizePositiveIntOrNull(mixed $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }
}