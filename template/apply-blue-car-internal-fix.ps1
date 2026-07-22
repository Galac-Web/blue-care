$ErrorActionPreference = 'Stop'

$sourceDir = 'C:\laragon\www\blu-car.ro\template\aibotpiese-admin-final'
$targetDir = 'C:\laragon\www\blu-car.ro'
$targetIndex = Join-Path $targetDir 'index.php'
$targetCss = Join-Path $targetDir 'admin-lite.css'

if (-not (Test-Path -LiteralPath $sourceDir)) {
    throw "Nu gasesc sursa: $sourceDir"
}

if (-not (Test-Path -LiteralPath $targetDir)) {
    throw "Nu gasesc proiectul Blue-Car: $targetDir"
}

Copy-Item -LiteralPath (Join-Path $sourceDir 'index.php') -Destination $targetIndex -Force
Copy-Item -LiteralPath (Join-Path $sourceDir 'admin-lite.css') -Destination $targetCss -Force

# Siguranta: pastreaza assets locale si UTF-8 fara BOM.
$content = [System.IO.File]::ReadAllText($targetIndex, [System.Text.Encoding]::UTF8)
$content = $content -replace "^\uFEFF", ""
$content = $content -replace '\$assetBase\s*=\s*''[^'']*'';', '$assetBase = ''assets'';'

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($targetIndex, $content, $utf8NoBom)
[System.IO.File]::WriteAllText($targetCss, [System.IO.File]::ReadAllText($targetCss), $utf8NoBom)

Write-Host "OK: Blue-Car admin actualizat."
Write-Host "Fisiere:"
Write-Host " - $targetIndex"
Write-Host " - $targetCss"
Write-Host "Deschide: https://blu-car.ro/?page=dashboard si fa Ctrl+F5"
