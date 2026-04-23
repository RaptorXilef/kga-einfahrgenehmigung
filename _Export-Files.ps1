# 1. Konfiguration: Auszuschließende Ordner
$excludedFolders = @(
    "vendor",
    "node_modules",
    ".git",
    "bin",
    "obj",
    "tools"
)

# 2. Dateiname generieren
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$outputFile = Join-Path -Path (Get-Location).Path -ChildPath "[$timestamp].txt"
$scriptName = $MyInvocation.MyCommand.Name

# 3. Dateien abrufen
$files = Get-ChildItem -Recurse -File | Where-Object {
    $fullName = $_.FullName
    $shouldExclude = $false
    
    foreach ($folder in $excludedFolders) {
        if ($fullName -like "*\$folder\*" -or $fullName -like "*\$folder") {
            $shouldExclude = $true
            break
        }
    }
    
    # Script selbst und die Output-Datei ausschließen
    if ($_.Name -eq (Split-Path $outputFile -Leaf) -or $_.Name -eq $scriptName) { $shouldExclude = $true }

    !$shouldExclude
}

Write-Host "Export gestartet..." -ForegroundColor Cyan

foreach ($file in $files) {
    $relativePath = $file.FullName.Replace((Get-Location).Path, "").TrimStart("\")

    try {
        # Start-Kommentar
        $startMsg = "// ========== START FILE: [$relativePath] ==========`r`n"
        $startMsg | Out-File -LiteralPath $outputFile -Append -Encoding utf8
        
        # Inhalt lesen und schreiben (-Raw für bessere Performance)
        if ($file.Length -gt 0) {
            $content = Get-Content -LiteralPath $file.FullName -Raw -ErrorAction Stop
            $content | Out-File -LiteralPath $outputFile -Append -Encoding utf8
        } else {
            "// [Datei ist leer]" | Out-File -LiteralPath $outputFile -Append -Encoding utf8
        }
        
        # Ende-Kommentar
        $endMsg = "`r`n// ========== END FILE: [$relativePath] ==========`r`n`r`n"
        $endMsg | Out-File -LiteralPath $outputFile -Append -Encoding utf8
        
        Write-Host "Erfolgreich: $relativePath" -ForegroundColor Green
    }
    catch {
        # Jetzt wird der echte Fehler ausgegeben
        $errorMessage = $_.Exception.Message
        Write-Host "Fehler bei $relativePath : $errorMessage" -ForegroundColor Red
    }
}

Write-Host "`nFertig! Datei erstellt: $outputFile" -ForegroundColor Yellow