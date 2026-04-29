<#
.SYNOPSIS
    Master-Skript zur Zusammenfassung verschiedener Dateitypen.
    Inklusive Modus 5 für eine vollständige Projekt-Zusammenfassung.
#>

# --- 1. Grundkonfiguration ---
$scriptPath = $PSScriptRoot
$basePath = Split-Path $scriptPath -Parent
$debugFolder = Join-Path $basePath ".debug"

# Standardeinstellung: Dateien direkt im Root ignorieren?
$GlobalIncludeRootFiles = $false

# Version aus package.json lesen
$packageJsonPath = Join-Path $basePath "package.json"
$version = "unknown"
if (Test-Path $packageJsonPath) {
    try {
        $packageJson = Get-Content $packageJsonPath -Raw | ConvertFrom-Json
        $version = $packageJson.version
    } catch {
        Write-Warning "package.json gefunden, aber Fehler beim Parsen."
    }
}

# --- 2. Zentrale Verarbeitungsfunktion ---
function Start-FileCollection {
    param (
        [string]$FileTypeName,
        [string]$FileFilter,
        [string]$Extension,
        [array]$ExclDirs,
        [array]$ExclFiles,
        [bool]$IncludeRoot
    )

    $timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
    $outputFileName = "$($FileTypeName)_v.$($version)_$($timestamp)$($Extension)"
    $outputFilePath = Join-Path $debugFolder $outputFileName

    if (-not (Test-Path $debugFolder)) { New-Item -Path $debugFolder -ItemType Directory | Out-Null }

    Write-Host "`n>>> Starte Sammlung für $FileTypeName ($FileFilter)..." -ForegroundColor Cyan
    Write-Host "    (Root-Dateien einbeziehen: $($IncludeRoot ? 'JA' : 'NEIN'))" -ForegroundColor Gray

    $allFiles = Get-ChildItem -Path $basePath -Filter $fileFilter -Recurse -File -Exclude $ExclFiles

    $filteredFiles = $allFiles | Where-Object {
        # Check: Liegt die Datei direkt im Root?
        $isDirectlyInRoot = $_.Directory.FullName -eq $basePath
        if (-not $IncludeRoot -and $isDirectlyInRoot) {
            return $false
        }

        # Verzeichnis-Filter
        $relativeDirPath = $_.Directory.FullName.Substring($basePath.Length)
        $pathSegments = $relativeDirPath.Split([System.IO.Path]::DirectorySeparatorChar) | Where-Object { $_.Length -gt 0 }

        $isExcluded = $false
        foreach ($segment in $pathSegments) {
            foreach ($pattern in $ExclDirs) {
                if ($segment -like $pattern -or $segment.StartsWith('.')) {
                    $isExcluded = $true
                    break
                }
            }
            if ($isExcluded) { break }
        }
        -not $isExcluded
    }

    if ($filteredFiles.Count -eq 0) {
        Write-Host "Keine Dateien gefunden." -ForegroundColor Yellow
        return
    }

    $contentArray = @()
    foreach ($file in $filteredFiles) {
        $relativePath = $file.FullName.Substring($basePath.Length).TrimStart([System.IO.Path]::DirectorySeparatorChar)

        # Versuchen den Inhalt zu lesen (überspringen bei Binärdateien/Fehlern)
        try {
            $fileContent = Get-Content -Path $file.FullName -Raw -ErrorAction Stop
            $contentArray += "// ========== START FILE: [$relativePath] =========="
            $contentArray += $fileContent
            $contentArray += "// ========== END FILE: [$relativePath] =========="
            $contentArray += ""
            Write-Host " + $relativePath"
        } catch {
            Write-Host " ! Überspringe (evtl. Binärdatei): $relativePath" -ForegroundColor DarkGray
        }
    }

    $contentArray | Set-Content -Path $outputFilePath -Encoding UTF8
    Write-Host "ERFOLG: $outputFilePath erstellt ($($filteredFiles.Count) Dateien)." -ForegroundColor Green
}

# --- 3. Menü-Definitionen ---
$commonDirs = @('*backup*', '*alt*', '*notizen*', 'notes', 'vendor', 'node_modules', '_Commits', '*- Kopie*', '*debug*', 'scripts')

# --- 4. Menü-Schleife ---
do {
    # Parameter-Definitionen (werden bei jedem Schleifendurchlauf aktualisiert)
    $paramsJS    = @("JsCode", "*.js", ".js", ($commonDirs + "public" + "assets_backup"), @('*backup*', '*Backup*', '*- Kopie*', '*Notizen*', '*notes*', '*svgo.config*', '*purgecss.config*', '*eslint.config*', '*commitlint.config*'), $GlobalIncludeRootFiles)
    $paramsPHP   = @("PhpCode", "*.php", ".php", ($commonDirs + "tests"), @('*backup*', '*Backup*', '*- Kopie*', '*Notizen*', '*notes*', 'php-cs-fixer.dist*', 'rector*', '*.php-cs-fixer.dist*'), $GlobalIncludeRootFiles)
    $paramsPHTML = @("PhtmlCode", "*.phtml", ".phtml", $commonDirs, @('*backup*', '*Backup*', '*- Kopie*', '*Notizen*', '*notes*', '*debug*'), $GlobalIncludeRootFiles)
    $paramsSCSS  = @("ScssCode", "*.scss", ".scss", $commonDirs, @('*backup*', '*Backup*', '*- Kopie*', '*Notizen*', '*notes*'), $GlobalIncludeRootFiles)

    # Modus 5: Projektweite Zusammenfassung
    # Ignoriert lock-Dateien und nutzt txt-Endung
    $paramsProject = @("ProjektZusammenfassung", "*.*", ".txt", $commonDirs, @('*.lock', '*-lock.json', '*backup*'), $GlobalIncludeRootFiles)

    Clear-Host
    Write-Host "===============================================" -ForegroundColor Yellow
    Write-Host "   DATEI-ZUSAMMENFASSUNG (Master-Script)       " -ForegroundColor Yellow
    Write-Host "   Root: $basePath"
    Write-Host "   Version: $version"
    Write-Host "===============================================" -ForegroundColor Yellow
    Write-Host "1) JavaScript (*.js)"
    Write-Host "2) PHP (*.php)"
    Write-Host "3) PHTML (*.phtml)"
    Write-Host "4) SCSS (*.scss)"
    Write-Host "5) PROJEKT-ZUSAMMENFASSUNG (*.* -> .txt)" -ForegroundColor Cyan
    Write-Host "-----------------------------------------------"
    Write-Host "T) Toggle Root-Files: $(if ($GlobalIncludeRootFiles) { 'AN' } else { 'AUS' })" -ForegroundColor ($GlobalIncludeRootFiles ? "Green" : "Gray")
    Write-Host "A) ALLE nacheinander ausführen (1-4)"
    Write-Host "Q) Beenden"
    Write-Host "-----------------------------------------------"

    $choice = (Read-Host "Wähle eine Option").ToUpper()

    switch ($choice) {
        "1" { Start-FileCollection @paramsJS; Pause }
        "2" { Start-FileCollection @paramsPHP; Pause }
        "3" { Start-FileCollection @paramsPHTML; Pause }
        "4" { Start-FileCollection @paramsSCSS; Pause }
        "5" { Start-FileCollection @paramsProject; Pause }
        "T" { $GlobalIncludeRootFiles = -not $GlobalIncludeRootFiles }
        "A" {
            Write-Host "Starte Komplett-Durchlauf (Typ 1-4)..." -ForegroundColor Magenta
            Start-FileCollection @paramsJS
            Start-FileCollection @paramsPHP
            Start-FileCollection @paramsPHTML
            Start-FileCollection @paramsSCSS
            Write-Host "`nAlle Sammlungen abgeschlossen!" -ForegroundColor Green
            Pause
        }
        "Q" { exit }
        default { if ($choice -ne "T") { Write-Host "Ungültige Auswahl!" -ForegroundColor Red; Start-Sleep -Seconds 1 } }
    }
} while ($true)
