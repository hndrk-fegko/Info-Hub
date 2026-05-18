<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

echo "=== V2 BROWSER BOOT SMOKE TEST ===\n\n";

$server = null;
$baseUrl = '';

try {
    $baseUrl = detectOrStartServer($root, $server);
    waitForServer($baseUrl . '/tests/browser/v2_boot_smoke.php');

    $browserBinary = findHeadlessBrowserBinary();
    $targetUrl = $baseUrl . '/tests/browser/v2_boot_smoke.php?ts=' . rawurlencode((string) microtime(true));
    $html = dumpDomWithBrowser($browserBinary, $targetUrl);

    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML($html);

    $htmlEl = $dom->getElementsByTagName('html')->item(0);
    if (!$htmlEl instanceof DOMElement) {
        throw new RuntimeException('Headless-Browser lieferte kein HTML-Root zurück.');
    }

    $bootStatus = $htmlEl->getAttribute('data-v2-boot-status');
    $bootModules = $htmlEl->getAttribute('data-v2-boot-modules');
    $bootLegacyModules = $htmlEl->getAttribute('data-v2-boot-legacy-modules');
    $bootModuleUrls = $htmlEl->getAttribute('data-v2-boot-module-urls');
    $bootLegacyUrls = $htmlEl->getAttribute('data-v2-boot-legacy-urls');
    $bootError = $htmlEl->getAttribute('data-v2-boot-error');

    $tileGrid = $dom->getElementById('tileGrid');
    $sessionDisplay = $dom->getElementById('sessionTimeDisplay');
    $bootScriptFound = strpos($html, 'boot.js?v=') !== false;

    $checks = [
        'Headless browser binary found' => $browserBinary !== '',
        'Boot status is ready' => $bootStatus === 'ready',
        'Boot module list contains all V2 modules' => containsCsvValues($bootModules, ['state', 'api-client', 'media-picker', 'insert', 'drag-drop', 'edit-modal', 'settings', 'context-menu', 'canvas']),
        'Boot legacy list is empty' => trim($bootLegacyModules) === '',
        'Boot legacy urls are empty' => trim($bootLegacyUrls) === '',
        'Module URLs include cachebusters' => containsCacheBustedUrls($bootModuleUrls, ['state.js?v=', 'api-client.js?v=', 'media-picker.js?v=', 'insert.js?v=', 'drag-drop.js?v=', 'edit-modal.js?v=', 'settings.js?v=', 'context-menu.js?v=', 'canvas.js?v=']),
        'Boot error is empty' => $bootError === '',
        'HTML still contains module boot script' => $bootScriptFound,
        'Tile grid exists in browser DOM' => $tileGrid instanceof DOMElement,
        'Browser DOM contains rendered tile wrappers' => substr_count($html, 'v2-tile-wrapper') > 0,
        'Session timer was initialized by JS' => $sessionDisplay instanceof DOMElement && trim($sessionDisplay->textContent) !== '--',
    ];

    $allPass = true;
    foreach ($checks as $name => $passed) {
        echo ($passed ? '  ✅ ' : '  ❌ ') . $name . "\n";
        if (!$passed) {
            $allPass = false;
        }
    }

    if (!$allPass) {
        echo "\nBoot status: {$bootStatus}\n";
        echo "Boot modules: {$bootModules}\n";
        echo "Boot legacy modules: {$bootLegacyModules}\n";
        echo "Boot error: {$bootError}\n";
    }

    echo "\n" . ($allPass ? '🎉 ALL CHECKS PASSED!' : '⚠️ SOME CHECKS FAILED!') . "\n";
    exit($allPass ? 0 : 1);
} finally {
    stopPhpServer($server);
}

function findAvailablePort(int $start, int $end): int {
    for ($port = $start; $port <= $end; $port++) {
        $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $error);
        if ($socket !== false) {
            fclose($socket);
            return $port;
        }
    }

    throw new RuntimeException('Kein freier Port für den Browser-Smoke-Test gefunden.');
}

function startPhpServer(string $root, int $port) {
    $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['file', $nullDevice, 'a'],
        2 => ['file', $nullDevice, 'a'],
    ];

    $command = escapeshellarg(PHP_BINARY)
        . ' -S 127.0.0.1:' . $port
        . ' -t ' . escapeshellarg($root);

    $process = proc_open($command, $descriptorSpec, $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('PHP Built-in Server konnte nicht gestartet werden.');
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    return $process;
}

function stopPhpServer($process): void {
    if (is_resource($process)) {
        @proc_terminate($process);
    }
}

function detectOrStartServer(string $root, &$server): string {
    foreach (['http://127.0.0.1:8000', 'http://localhost:8000'] as $baseUrl) {
        if (isServerReachable($baseUrl . '/tests/browser/v2_boot_smoke.php')) {
            return $baseUrl;
        }
    }

    $port = findAvailablePort(8011, 8025);
    $server = startPhpServer($root, $port);
    return "http://127.0.0.1:{$port}";
}

function isServerReachable(string $url): bool {
    $context = stream_context_create([
        'http' => [
            'timeout' => 1,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    return is_string($body) && $body !== '';
}

function waitForServer(string $url): void {
    $deadline = microtime(true) + 10;
    while (microtime(true) < $deadline) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 1,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (is_string($body) && $body !== '') {
            return;
        }
        usleep(100000);
    }

    throw new RuntimeException('Lokaler PHP-Server hat nicht rechtzeitig geantwortet.');
}

function findHeadlessBrowserBinary(): string {
    $candidates = [];
    if ($custom = getenv('INFO_HUB_BROWSER_BIN')) {
        $candidates[] = $custom;
    }

    $candidates = array_merge($candidates, [
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    ]);

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Kein headless Browser gefunden. Setze INFO_HUB_BROWSER_BIN auf Edge oder Chrome.');
}

function dumpDomWithBrowser(string $browserBinary, string $url): string {
    $command = escapeshellarg($browserBinary)
        . ' --headless --disable-gpu --virtual-time-budget=4000 --dump-dom '
        . escapeshellarg($url)
        . ' 2>&1';

    $output = shell_exec($command);
    if (!is_string($output) || trim($output) === '') {
        throw new RuntimeException('Headless-Browser lieferte keinen DOM-Output zurück.');
    }

    $doctypePos = stripos($output, '<!DOCTYPE html');
    $htmlPos = stripos($output, '<html');
    $startPos = $doctypePos !== false ? $doctypePos : $htmlPos;
    if ($startPos !== false && $startPos > 0) {
        $output = substr($output, $startPos);
    }

    return $output;
}

function containsCsvValues(string $csv, array $expectedValues): bool {
    $values = array_filter(array_map('trim', explode(',', $csv)), static function(string $value): bool {
        return $value !== '';
    });

    foreach ($expectedValues as $expectedValue) {
        if (!in_array($expectedValue, $values, true)) {
            return false;
        }
    }

    return true;
}

function containsCacheBustedUrls(string $joinedUrls, array $needles): bool {
    foreach ($needles as $needle) {
        if (strpos($joinedUrls, $needle) === false) {
            return false;
        }
    }

    return true;
}

function containsAnyCsvValues(string $csv, array $valuesToCheck): bool {
    $values = array_filter(array_map('trim', explode(',', $csv)), static function(string $value): bool {
        return $value !== '';
    });

    foreach ($valuesToCheck as $valueToCheck) {
        if (in_array($valueToCheck, $values, true)) {
            return true;
        }
    }

    return false;
}