# Opreste tot: roboți Python, Chrome robot, watchdog.
$ErrorActionPreference = 'SilentlyContinue'
$robotDir = Join-Path (Split-Path (Split-Path $MyInvocation.MyCommand.Path -Parent) -Parent) 'robot'
if (-not (Test-Path $robotDir)) { $robotDir = 'e:\laragon\www\blu-car.ro\robot' }

function Invoke-RobotStop($baseUrl, $contId) {
    if (-not $contId) { return }
    try {
        Invoke-WebRequest -Uri "$baseUrl/stop?cont_id=$contId" -Method POST -TimeoutSec 4 -UseBasicParsing | Out-Null
    } catch {}
}

@('gbg_user_01', 'bluecar', 'furnizor_oepiesa', 'furnizor_autonet') | ForEach-Object {
    Invoke-RobotStop 'http://127.0.0.1:5000' $_
}
@('bluecar') | ForEach-Object {
    Invoke-RobotStop 'http://127.0.0.1:5007' $_
    try {
        Invoke-WebRequest -Uri "http://127.0.0.1:5007/stop_total?cont_id=$_" -Method POST -TimeoutSec 4 -UseBasicParsing | Out-Null
    } catch {}
}

Start-Sleep -Seconds 2

$patterns = @('*robot1.py*', '*robot_pieseauto.py*', '*watchdog*')
Get-CimInstance Win32_Process -Filter "name='python.exe'" |
    Where-Object {
        $cmd = $_.CommandLine
        foreach ($p in $patterns) { if ($cmd -like $p) { return $true } }
        return $false
    } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Get-CimInstance Win32_Process -Filter "name='cmd.exe'" |
    Where-Object { $_.CommandLine -like '*run_pieseauto_service*' -or $_.CommandLine -like '*robot1.py*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Start-Sleep -Seconds 1

Get-CimInstance Win32_Process -Filter "name='undetected_chromedriver.exe'" |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

$profiles = @('profil_pa_bluecar', 'profil_pa_gbg_user_01', 'profil_gbg_user_01', 'profil_bluecar')
foreach ($prof in $profiles) {
    $ps = @"
Get-CimInstance Win32_Process -Filter "name='chrome.exe'" |
  Where-Object { `$_.CommandLine -and `$_.CommandLine -like '*$prof*' } |
  ForEach-Object { Stop-Process -Id `$_.ProcessId -Force -ErrorAction SilentlyContinue }
"@
    powershell -NoProfile -NonInteractive -Command $ps | Out-Null
}

Start-Sleep -Seconds 2
Write-Output 'STOP_OK'
