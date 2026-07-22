$ErrorActionPreference = 'Stop'

$indexFile = 'C:\laragon\www\blu-car.ro\index.php'

if (-not (Test-Path -LiteralPath $indexFile)) {
    throw "Nu gasesc fisierul: $indexFile"
}

$lines = [System.Collections.Generic.List[string]]::new()
[System.IO.File]::ReadAllLines($indexFile) | ForEach-Object { [void]$lines.Add($_) }

$start = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "^\s*\$assetBase\s*=\s*'/blu-car\.ro/assets';\s*$") {
        $start = $i
        break
    }
}

if ($start -lt 0) {
    throw "Nu am gasit linia veche `$assetBase = '/blu-car.ro/assets';"
}

$end = $start
for ($i = $start; $i -lt [Math]::Min($start + 8, $lines.Count); $i++) {
    if ($lines[$i] -match "^\s*\}\s*$") {
        $end = $i
        break
    }
}

$removeCount = $end - $start + 1
$lines.RemoveRange($start, $removeCount)
$lines.Insert($start, "`$assetBase = 'assets';")

$content = ($lines -join "`r`n")
$content = $content -replace "^\uFEFF", ""
$phpPos = $content.IndexOf('<?php')
if ($phpPos -gt 0) {
    $content = $content.Substring($phpPos)
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($indexFile, $content, $utf8NoBom)

Write-Host "OK. Blocul assetBase a fost inlocuit cu: `$assetBase = 'assets';"
