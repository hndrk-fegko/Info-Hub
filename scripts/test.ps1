param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$RunnerArgs
)

$root = Split-Path -Parent $PSScriptRoot
$runner = Join-Path $root 'tests\run.php'

$phpCandidates = @()
if ($env:PHP_BIN) {
    $phpCandidates += $env:PHP_BIN
}
$phpCandidates += 'C:\xampp\php\php.exe'
$phpCandidates += 'php'

$phpExecutable = $null
foreach ($candidate in $phpCandidates) {
    if ($candidate -eq 'php') {
        $command = Get-Command php -ErrorAction SilentlyContinue
        if ($command) {
            $phpExecutable = $command.Source
            break
        }
        continue
    }

    if (Test-Path $candidate) {
        $phpExecutable = $candidate
        break
    }
}

if (-not $phpExecutable) {
    Write-Error 'Kein PHP-Interpreter gefunden. Setze PHP_BIN oder installiere php in PATH.'
    exit 2
}

if (-not $RunnerArgs -or $RunnerArgs.Count -eq 0) {
    # Default: schneller Kernlauf ohne browser/manual E2E, damit sofort sichtbare Ergebnisse kommen.
    $RunnerArgs = @('--suite=integration,contracts,unit')
    Write-Host 'Keine Argumente uebergeben - nutze Default-Suites: integration, contracts, unit'
}

& $phpExecutable $runner @RunnerArgs
exit $LASTEXITCODE