$ErrorActionPreference = 'Stop'

$source = Split-Path -Parent $MyInvocation.MyCommand.Path
$target = 'C:\laragon\www\aibotpiese.online\admin'
$expectedRoot = 'C:\laragon\www\aibotpiese.online'

$resolvedSource = (Resolve-Path -LiteralPath $source).Path
$resolvedTargetParent = (Resolve-Path -LiteralPath $expectedRoot).Path

if (-not (Test-Path -LiteralPath $target)) {
    New-Item -ItemType Directory -Path $target | Out-Null
}

$resolvedTarget = (Resolve-Path -LiteralPath $target).Path
if (-not $resolvedTarget.StartsWith($resolvedTargetParent, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Targetul nu este in proiectul asteptat: $resolvedTarget"
}

$backup = Join-Path $resolvedTargetParent ('admin_legacy_backup_' + (Get-Date -Format 'yyyyMMdd_HHmmss'))
if (Test-Path -LiteralPath $resolvedTarget) {
    Move-Item -LiteralPath $resolvedTarget -Destination $backup
}

New-Item -ItemType Directory -Path $resolvedTarget | Out-Null
Copy-Item -LiteralPath (Join-Path $resolvedSource 'index.php') -Destination (Join-Path $resolvedTarget 'index.php') -Force
Copy-Item -LiteralPath (Join-Path $resolvedSource 'admin-lite.css') -Destination (Join-Path $resolvedTarget 'admin-lite.css') -Force

@'
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

Options -Indexes

<FilesMatch "\.(env|ini|log|sh|bak|sql)$">
  Order allow,deny
  Deny from all
</FilesMatch>
'@ | Set-Content -LiteralPath (Join-Path $resolvedTarget '.htaccess') -Encoding UTF8

Write-Host "Admin instalat in $resolvedTarget"
Write-Host "Backup admin vechi: $backup"
