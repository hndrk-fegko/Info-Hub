<?php

declare(strict_types=1);

$manifest = require __DIR__ . '/manifest.php';

main($argv, $manifest);

function main(array $argv, array $manifest): void {
    $options = parseOptions($argv);

    if (!empty($options['help'])) {
        printHelp();
        exit(0);
    }

    $tests = normalizeManifest($manifest);
    $selectedTests = filterTests($tests, $options['suites'], $options['tests']);

    if (!empty($options['list'])) {
        outputList($tests, $options['format']);
        exit(0);
    }

    if ($selectedTests === []) {
        outputNoMatch($options['format'], $options['suites'], $options['tests']);
        exit(2);
    }

    $results = runTests($selectedTests, $options['format'] === 'text');
    outputResults($results, $options['format']);
    exit($results['summary']['failed'] > 0 ? 1 : 0);
}

function parseOptions(array $argv): array {
    $options = [
        'list' => false,
        'help' => false,
        'format' => 'text',
        'suites' => [],
        'tests' => [],
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--list') {
            $options['list'] = true;
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if (strpos($arg, '--format=') === 0) {
            $format = strtolower(substr($arg, 9));
            $options['format'] = in_array($format, ['text', 'json'], true) ? $format : 'text';
            continue;
        }

        if (strpos($arg, '--suite=') === 0) {
            $options['suites'] = array_merge($options['suites'], splitCsv(substr($arg, 8)));
            continue;
        }

        if (strpos($arg, '--test=') === 0) {
            $options['tests'] = array_merge($options['tests'], splitCsv(substr($arg, 7)));
            continue;
        }
    }

    $options['suites'] = array_values(array_unique(array_map('strtolower', $options['suites'])));
    $options['tests'] = array_values(array_unique(array_map('strtolower', $options['tests'])));

    return $options;
}

function splitCsv(string $value): array {
    return array_values(array_filter(array_map('trim', explode(',', $value)), static function(string $item): bool {
        return $item !== '';
    }));
}

function normalizeManifest(array $manifest): array {
    $tests = [];

    foreach ($manifest as $entry) {
        $type = strtolower((string) ($entry['type'] ?? 'php'));
        if (!in_array($type, ['php', 'manual'], true)) {
            $type = 'php';
        }

        $tests[] = [
            'id' => (string) $entry['id'],
            'idLower' => strtolower((string) $entry['id']),
            'type' => $type,
            'path' => (string) $entry['path'],
            'label' => (string) ($entry['label'] ?? $entry['id']),
            'description' => (string) ($entry['description'] ?? ''),
            'suites' => array_values(array_unique(array_map('strtolower', $entry['suites'] ?? []))),
        ];
    }

    return $tests;
}

function filterTests(array $tests, array $suiteFilters, array $testFilters): array {
    return array_values(array_filter($tests, static function(array $test) use ($suiteFilters, $testFilters): bool {
        if ($suiteFilters !== []) {
            $matchedSuite = false;
            foreach ($suiteFilters as $suite) {
                if (in_array($suite, $test['suites'], true)) {
                    $matchedSuite = true;
                    break;
                }
            }
            if (!$matchedSuite) {
                return false;
            }
        }

        if ($testFilters !== []) {
            foreach ($testFilters as $testFilter) {
                if ($test['idLower'] === $testFilter || strpos($test['idLower'], $testFilter) !== false) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }));
}

function runTests(array $selectedTests, bool $showProgress = true): array {
    $results = [];
    $suiteCounts = [];
    $startedAt = microtime(true);
    $totalTests = count($selectedTests);
    $currentIndex = 0;

    foreach ($selectedTests as $test) {
        $currentIndex++;

        foreach ($test['suites'] as $suite) {
            if (!isset($suiteCounts[$suite])) {
                $suiteCounts[$suite] = 0;
            }
            $suiteCounts[$suite]++;
        }

        if ($showProgress) {
            echo sprintf(
                "[RUN ] %d/%d %s (%s)%s",
                $currentIndex,
                $totalTests,
                $test['id'],
                implode(', ', $test['suites']),
                PHP_EOL
            );
        }

        if ($test['type'] === 'manual') {
            $instructions = '';
            if (is_file($test['path'])) {
                $instructions = trim((string) file_get_contents($test['path']));
            }

            $results[] = [
                'id' => $test['id'],
                'label' => $test['label'],
                'description' => $test['description'],
                'type' => $test['type'],
                'path' => relativePath($test['path']),
                'suites' => $test['suites'],
                'status' => 'manual',
                'exitCode' => 0,
                'durationMs' => 0,
                'output' => $instructions,
            ];

            if ($showProgress) {
                echo sprintf("[MAN ] %s%s", $test['id'], PHP_EOL);
            }

            continue;
        }

        $testStartedAt = microtime(true);
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test['path']);
        $outputLines = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $outputLines, $exitCode);
        $durationMs = (int) round((microtime(true) - $testStartedAt) * 1000);
        $status = $exitCode === 0 ? 'passed' : 'failed';

        $results[] = [
            'id' => $test['id'],
            'label' => $test['label'],
            'description' => $test['description'],
            'type' => $test['type'],
            'path' => relativePath($test['path']),
            'suites' => $test['suites'],
            'status' => $status,
            'exitCode' => $exitCode,
            'durationMs' => $durationMs,
            'output' => implode(PHP_EOL, $outputLines),
        ];

        if ($showProgress) {
            echo sprintf(
                "[%s] %s (%dms)%s",
                $status === 'passed' ? 'PASS' : 'FAIL',
                $test['id'],
                $durationMs,
                PHP_EOL
            );
        }
    }

    $totalDurationMs = (int) round((microtime(true) - $startedAt) * 1000);
    $passed = count(array_filter($results, static function(array $result): bool {
        return $result['status'] === 'passed';
    }));
    $manual = count(array_filter($results, static function(array $result): bool {
        return $result['status'] === 'manual';
    }));
    $failed = count(array_filter($results, static function(array $result): bool {
        return $result['status'] === 'failed';
    }));

    ksort($suiteCounts);

    return [
        'summary' => [
            'total' => count($results),
            'passed' => $passed,
            'failed' => $failed,
            'manual' => $manual,
            'durationMs' => $totalDurationMs,
            'suiteCounts' => $suiteCounts,
        ],
        'tests' => $results,
    ];
}

function outputList(array $tests, string $format): void {
    if ($format === 'json') {
        echo json_encode(['tests' => array_map(static function(array $test): array {
            return [
                'id' => $test['id'],
                'label' => $test['label'],
                'description' => $test['description'],
                'type' => $test['type'],
                'path' => relativePath($test['path']),
                'suites' => $test['suites'],
            ];
        }, $tests)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    echo "=== INFO-HUB TEST RUNNER ===" . PHP_EOL . PHP_EOL;
    echo "Verfuegbare Tests:" . PHP_EOL;
    foreach ($tests as $test) {
        echo '- ' . $test['id'] . ' [' . implode(', ', $test['suites']) . '] {' . $test['type'] . '}' . PHP_EOL;
        echo '  ' . relativePath($test['path']) . PHP_EOL;
        if ($test['description'] !== '') {
            echo '  ' . $test['description'] . PHP_EOL;
        }
    }
}

function outputResults(array $results, string $format): void {
    if ($format === 'json') {
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    echo "=== INFO-HUB TEST RUNNER ===" . PHP_EOL . PHP_EOL;

    foreach ($results['tests'] as $test) {
        if ($test['status'] === 'manual') {
            $icon = 'MANUAL';
        } else {
            $icon = $test['status'] === 'passed' ? 'PASS' : 'FAIL';
        }

        echo '[' . $icon . '] ' . $test['id'] . ' (' . implode(', ', $test['suites']) . ') - ' . $test['durationMs'] . 'ms' . PHP_EOL;
        echo '  ' . $test['path'] . PHP_EOL;

        if ($test['status'] === 'manual' && $test['output'] !== '') {
            echo '  --- instructions ---' . PHP_EOL;
            foreach (explode(PHP_EOL, trim($test['output'])) as $line) {
                echo '  ' . $line . PHP_EOL;
            }
        }

        if ($test['status'] === 'failed' && $test['output'] !== '') {
            echo '  --- output ---' . PHP_EOL;
            foreach (explode(PHP_EOL, trim($test['output'])) as $line) {
                echo '  ' . $line . PHP_EOL;
            }
        }
    }

    echo PHP_EOL;
    echo 'Gesamt: ' . $results['summary']['total']
        . ' | Bestanden: ' . $results['summary']['passed']
        . ' | Manuell: ' . ($results['summary']['manual'] ?? 0)
        . ' | Fehlgeschlagen: ' . $results['summary']['failed']
        . ' | Dauer: ' . $results['summary']['durationMs'] . 'ms' . PHP_EOL;
}

function outputNoMatch(string $format, array $suites, array $tests): void {
    $payload = [
        'error' => 'Keine Tests fuer die angegebenen Filter gefunden.',
        'filters' => [
            'suites' => $suites,
            'tests' => $tests,
        ],
    ];

    if ($format === 'json') {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    echo $payload['error'] . PHP_EOL;
}

function printHelp(): void {
    echo "Info-Hub Test Runner" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Aufruf:" . PHP_EOL;
    echo '  php tests/run.php [--list] [--suite=render,api,e2e] [--test=section-render-contract] [--format=text|json]' . PHP_EOL;
}

function relativePath(string $path): string {
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $normalized = str_replace('\\', '/', $path);
    if (strpos($normalized, $root . '/') === 0) {
        return substr($normalized, strlen($root) + 1);
    }

    return $normalized;
}