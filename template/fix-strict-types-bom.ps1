$ErrorActionPreference = 'Stop'

$indexFile = 'C:\laragon\www\blu-car.ro\index.php'

if (-not (Test-Path -LiteralPath $indexFile)) {
    throw "Nu gasesc fisierul: $indexFile"
}

$bytes = [System.IO.File]::ReadAllBytes($indexFile)
$content = [System.Text.Encoding]::UTF8.GetString($bytes)

# Scoate BOM, spatii sau linii goale aparute inainte de <?php.
$content = $content -replace "^\uFEFF", ""
$phpPos = $content.IndexOf('<?php')
if ($phpPos -gt 0) {
    $content = $content.Substring($phpPos)
}

# Pastreaza fixul pentru asset-uri locale.
$content = $content -replace "\$assetBase\s*=\s*'/blu-car\.ro/assets';", "`$assetBase = 'assets';"
$content = $content -replace "(?s)\r?\nif \(is_file\(\$adminRoot \. '/\.\./\.\./assets/css/style\.css'\)\) \{\s*\r?\n\s*\$assetBase = '\.\./\.\./assets';\s*\r?\n\} elseif \(is_file\(\$adminRoot \. '/\.\./\.\./blu-car\.ro/assets/css/style\.css'\)\) \{\s*\r?\n\s*\$assetBase = '\.\./\.\./blu-car\.ro/assets';\s*\r?\n\}", ""

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($indexFile, $content, $utf8NoBom)

Write-Host "OK: index.php rescris UTF-8 fara BOM si cu assetBase='assets'."
