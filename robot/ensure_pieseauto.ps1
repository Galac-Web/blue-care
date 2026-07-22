# Porneste UN singur robot_pieseauto.py (port din .env sau 5007).
$ErrorActionPreference = 'SilentlyContinue'
$robotDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$python = 'C:\laragon\bin\python\python-3.13\python.exe'
if (-not (Test-Path $python)) { $python = 'python' }
$port = 5007
$envFile = Join-Path (Split-Path $robotDir -Parent) '.env'
if (Test-Path $envFile) {
    $m = Select-String -Path $envFile -Pattern '^ROBOT_PIESEAUTO_PORT=(\d+)' | Select-Object -First 1
    if ($m) { $port = [int]$m.Matches[0].Groups[1].Value }
}
$pingUrl = "http://127.0.0.1:$port/verificare_sesiune"

function Test-RobotOnline {
    try {
        $r = Invoke-WebRequest -Uri $pingUrl -TimeoutSec 3 -UseBasicParsing
        return ($r.StatusCode -eq 200 -and $r.Content -match 'online')
    } catch { return $false }
}

if (Test-RobotOnline) { exit 0 }

Get-CimInstance Win32_Process -Filter "name='python.exe'" |
    Where-Object { $_.CommandLine -like '*robot_pieseauto.py*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Start-Sleep -Seconds 2

$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = $python
$psi.Arguments = '-u robot_pieseauto.py'
$psi.WorkingDirectory = $robotDir
$psi.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
$psi.CreateNoWindow = $true
$psi.UseShellExecute = $false
[void]$psi.EnvironmentVariables.Add('ROBOT_PIESEAUTO_PORT', [string]$port)
[System.Diagnostics.Process]::Start($psi) | Out-Null

for ($i = 0; $i -lt 25; $i++) {
    Start-Sleep -Seconds 1
    if (Test-RobotOnline) { exit 0 }
}
exit 1
