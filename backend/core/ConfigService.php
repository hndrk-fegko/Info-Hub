<?php

class ConfigService {
    private string $configFile;

    public function __construct(?string $configFile = null) {
        $this->configFile = $configFile ?: __DIR__ . '/../config.php';
    }

    public function getMailFromAddress(?string $fallbackHost = null, ?string $adminEmail = null): string {
        $configured = defined('MAIL_FROM_ADDRESS') ? trim((string) constant('MAIL_FROM_ADDRESS')) : '';
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return strtolower($configured);
        }

        return self::deriveSuggestedMailFromAddress($fallbackHost, $adminEmail);
    }

    public function updateMailFromAddress(string $mailFromAddress): bool {
        $mailFromAddress = strtolower(trim($mailFromAddress));
        if ($mailFromAddress === '' || !filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $configContent = @file_get_contents($this->configFile);
        if ($configContent === false) {
            return false;
        }

        $mailFromDefinition = "define('MAIL_FROM_ADDRESS', " . var_export($mailFromAddress, true) . ");";
        $pattern = "/define\(\s*'MAIL_FROM_ADDRESS'\s*,\s*.*?\);/";

        if (preg_match($pattern, $configContent)) {
            $updatedContent = preg_replace($pattern, $mailFromDefinition, $configContent, 1);
            if ($updatedContent === null) {
                return false;
            }
            $configContent = $updatedContent;
        } else {
            $configContent = rtrim($configContent) . "\n\n" . $mailFromDefinition . "\n";
        }

        return file_put_contents($this->configFile, $configContent) !== false;
    }

    public static function deriveSuggestedMailFromAddress(?string $host, ?string $adminEmail = null): string {
        $baseDomain = self::deriveBaseMailDomain((string) $host);

        if ($baseDomain === '' && !empty($adminEmail)) {
            $normalizedAdminEmail = strtolower(trim($adminEmail));
            if (filter_var($normalizedAdminEmail, FILTER_VALIDATE_EMAIL)) {
                $emailDomain = substr(strrchr($normalizedAdminEmail, '@') ?: '', 1);
                $baseDomain = self::deriveBaseMailDomain($emailDomain ?: '');
            }
        }

        if ($baseDomain === '') {
            return 'noreply@example.com';
        }

        return 'noreply@' . $baseDomain;
    }

    public static function deriveBaseMailDomain(string $host): string {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host);
        $host = trim($host, '.');

        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return '';
        }

        $parts = array_values(array_filter(explode('.', $host)));
        if (count($parts) <= 2) {
            return $host;
        }

        $compoundSuffixPrefixes = ['ac', 'co', 'com', 'edu', 'gov', 'net', 'org'];
        $tld = $parts[count($parts) - 1];
        $secondLevel = $parts[count($parts) - 2];

        if (strlen($tld) === 2 && in_array($secondLevel, $compoundSuffixPrefixes, true) && count($parts) >= 3) {
            return implode('.', array_slice($parts, -3));
        }

        return implode('.', array_slice($parts, -2));
    }
}