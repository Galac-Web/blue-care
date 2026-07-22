$ErrorActionPreference = 'Stop'

$sourceAdmin = Split-Path -Parent $MyInvocation.MyCommand.Path
$targetAdmin = 'C:\laragon\www\aibotpiese.online\admin'
$templateAssets = 'C:\laragon\www\blu-car.ro\assets'

if (-not (Test-Path -LiteralPath $targetAdmin)) {
    throw "Nu exista folderul admin: $targetAdmin"
}

Copy-Item -LiteralPath (Join-Path $sourceAdmin 'index.php') -Destination (Join-Path $targetAdmin 'index.php') -Force
Copy-Item -LiteralPath (Join-Path $sourceAdmin 'admin-lite.css') -Destination (Join-Path $targetAdmin 'admin-lite.css') -Force

if (-not (Test-Path -LiteralPath $templateAssets)) {
    throw "Nu exista folderul cu asset-uri template: $templateAssets"
}

Copy-Item -LiteralPath $templateAssets -Destination (Join-Path $targetAdmin 'assets') -Recurse -Force

Write-Host "Fix aplicat. Adminul foloseste acum asset-uri locale din: $targetAdmin\assets"
