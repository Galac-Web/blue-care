$ErrorActionPreference = 'Stop'

$indexFile = 'C:\laragon\www\blu-car.ro\index.php'

if (-not (Test-Path -LiteralPath $indexFile)) {
    throw "Nu gasesc fisierul: $indexFile"
}

$content = [System.IO.File]::ReadAllText($indexFile, [System.Text.Encoding]::UTF8)

# Curata orice BOM/spatiu inainte de <?php.
$content = $content -replace "^\uFEFF", ""
$phpPos = $content.IndexOf('<?php')
if ($phpPos -gt 0) {
    $content = $content.Substring($phpPos)
}

$oldBlock = @'
$assetBase = '/blu-car.ro/assets';
if (is_file($adminRoot . '/../../assets/css/style.css')) {
    $assetBase = '../../assets';
} elseif (is_file($adminRoot . '/../../blu-car.ro/assets/css/style.css')) {
    $assetBase = '../../blu-car.ro/assets';
}
'@

$newBlock = @'
$assetBase = 'assets';
'@

if ($content.Contains($oldBlock)) {
    $content = $content.Replace($oldBlock, $newBlock)
} else {
    # Fallback: inlocuieste strict liniile 13-17 daca blocul are finaluri de linie diferite.
    $lines = [System.Collections.Generic.List[string]]::new()
    $content -split "\r?\n" | ForEach-Object { [void]$lines.Add($_) }

    $start = -1
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i].Trim() -eq '$assetBase = ''/blu-car.ro/assets'';') {
            $start = $i
            break
        }
    }

    if ($start -lt 0) {
        throw "Nu gasesc blocul vechi assetBase in index.php. Deschide fisierul si verifica primele 20 de linii."
    }

    $end = $start
    for ($i = $start; $i -lt [Math]::Min($start + 8, $lines.Count); $i++) {
        if ($lines[$i].Trim() -eq '}') {
            $end = $i
            break
        }
    }

    $lines.RemoveRange($start, $end - $start + 1)
    $lines.Insert($start, '$assetBase = ''assets'';')
    $content = $lines -join "`r`n"
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($indexFile, $content, $utf8NoBom)

Write-Host "OK: assetBase este acum local: assets"
Write-Host "Verificare:"
Select-String -Path $indexFile -Pattern 'assetBase|blu-car.ro/assets' | Select-Object LineNumber,Line
