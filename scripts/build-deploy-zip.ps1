Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$rootDir = Split-Path -Parent $PSScriptRoot
$distDir = Join-Path $rootDir 'dist'

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Error 'git ist erforderlich.'
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

Push-Location $rootDir
try {
    $currentBranch = (& git branch --show-current 2>$null).Trim()
    $refName = if ($currentBranch) { $currentBranch } elseif ($env:GITHUB_REF_NAME) { $env:GITHUB_REF_NAME } else { 'detached' }
    $safeRef = $refName -replace '/', '-'

    $shortSha = if ($env:GITHUB_SHA) { $env:GITHUB_SHA } else { (& git rev-parse --short HEAD).Trim() }
    if ($shortSha.Length -gt 7) {
        $shortSha = $shortSha.Substring(0, 7)
    }

    $artifactBaseName = "info-hub-$safeRef-$shortSha"
    $archivePath = Join-Path $distDir ($artifactBaseName + '.zip')

    New-Item -ItemType Directory -Path $distDir -Force | Out-Null

    $pathspecs = @(
        '.',
        ':(exclude).github/**',
        ':(exclude)dist/**',
        ':(exclude)docs/**',
        ':(exclude)scripts/**',
        ':(exclude)tests/**'
    )

    $untrackedFiles = & git ls-files --others --exclude-standard -- @pathspecs
    if ($untrackedFiles) {
        $fileList = ($untrackedFiles | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }) -join [Environment]::NewLine
        Write-Error "Untracked files würden im Deploy-ZIP fehlen. Bitte zuerst git add ausführen oder die Dateien entfernen.`n$fileList"
    }

    $trackedFiles = & git ls-files -- @pathspecs

    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force
    }

    $archive = [System.IO.Compression.ZipFile]::Open($archivePath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($relativePath in $trackedFiles) {
            if ([string]::IsNullOrWhiteSpace($relativePath)) {
                continue
            }

            $sourcePath = Join-Path $rootDir $relativePath
            $entryName = (("$artifactBaseName/$relativePath") -replace '\\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                $sourcePath,
                $entryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $archive.Dispose()
    }

    [Console]::Error.WriteLine("Created $archivePath")
    Write-Output ("artifact_name={0}" -f [System.IO.Path]::GetFileName($archivePath))
    Write-Output ("artifact_path={0}" -f $archivePath)
}
finally {
    Pop-Location
}