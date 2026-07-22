# Porneste UN singur robot1.py — port din .env (ROBOT_FURNIZORI_PORT / URL).
$ErrorActionPreference = 'SilentlyContinue'
$robotDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $robotDir
$envFile = Join-Path $projectRoot '.env'
$python = 'C:\laragon\bin\python\python-3.13\python.exe'
if (-not (Test-Path $python)) { $python = 'python' }

$port = 5000
if (Test-Path $envFile) {
    $envText = Get-Content -Raw -Path $envFile
    if ($envText -match '(?m)^ROBOT_FURNIZORI_PORT=(\d+)') {
        $port = [int]$Matches[1]
    } elseif ($envText -match '(?m)^ROBOT_FURNIZORI_URL=https?://[^:/]+:(\d+)') {
        $port = [int]$Matches[1]
    }
}

$pingUrl = "http://127.0.0.1:$port/status"

function Test-RobotOnline {
    try {
        $r = Invoke-WebRequest -Uri $pingUrl -TimeoutSec 3 -UseBasicParsing
        return ($r.StatusCode -eq 200)
    } catch { return $false }
}

if (Test-RobotOnline) { exit 0 }

Get-CimInstance Win32_Process -Filter "name='python.exe'" |
    Where-Object { $_.CommandLine -like '*robot1.py*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Start-Sleep -Seconds 2

$log = Join-Path $robotDir 'robot_service.log'
$arg = "/c `"cd /d `"$robotDir`" && set ROBOT_FURNIZORI_PORT=$port&& `"$python`" -u robot1.py >> `"$log`" 2>&1`""
Start-Process -FilePath 'cmd.exe' -ArgumentList $arg -WindowStyle Hidden -WorkingDirectory $robotDir | Out-Null

for ($i = 0; $i -lt 15; $i++) {
    Start-Sleep -Seconds 2
    if (Test-RobotOnline) { exit 0 }
}
exit 1
