$ErrorActionPreference = 'Stop'

$indexFile = 'C:\laragon\www\blu-car.ro\index.php'

if (-not (Test-Path -LiteralPath $indexFile)) {
    throw "Nu gasesc fisierul: $indexFile"
}

$content = Get-Content -LiteralPath $indexFile -Raw

$content = $content -replace "\$assetBase\s*=\s*'/blu-car\.ro/assets';", "`$assetBase = 'assets';"
$content = $content -replace "(?s)\r?\nif \(is_file\(\$adminRoot \. '/\.\./\.\./assets/css/style\.css'\)\) \{\s*\r?\n\s*\$assetBase = '\.\./\.\./assets';\s*\r?\n\} elseif \(is_file\(\$adminRoot \. '/\.\./\.\./blu-car\.ro/assets/css/style\.css'\)\) \{\s*\r?\n\s*\$assetBase = '\.\./\.\./blu-car\.ro/assets';\s*\r?\n\}", ""

Set-Content -LiteralPath $indexFile -Value $content -Encoding UTF8

Write-Host "Fix aplicat in $indexFile"
Write-Host "Asset-urile se vor incarca acum din /assets/... nu din /blu-car.ro/assets/..."
