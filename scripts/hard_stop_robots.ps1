# Stop complet + curatare scanate
$ErrorActionPreference = 'SilentlyContinue'

@('http://127.0.0.1:5000/stop?cont_id=gbg_user_01', 'http://127.0.0.1:5007/stop_total?cont_id=bluecar') | ForEach-Object {
    try { Invoke-WebRequest -Uri $_ -Method POST -TimeoutSec 3 -UseBasicParsing | Out-Null } catch {}
}

Start-Sleep -Seconds 2

Get-CimInstance Win32_Process -Filter "name='python.exe'" |
    Where-Object {
        $c = $_.CommandLine
        $c -like '*robot1.py*' -or $c -like '*robot_pieseauto.py*' -or $c -like '*watchdog*'
    } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Get-CimInstance Win32_Process -Filter "name='cmd.exe'" |
    Where-Object { $_.CommandLine -like '*run_pieseauto_service*' -or $_.CommandLine -like '*robot1.py*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Get-CimInstance Win32_Process -Filter "name='undetected_chromedriver.exe'" |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Start-Sleep -Seconds 2

$robotDir = 'e:\laragon\www\blu-car.ro\robot'
Get-ChildItem -Path $robotDir -Filter 'scanate_*.json' -ErrorAction SilentlyContinue | Remove-Item -Force

$stateFile = Join-Path $robotDir 'robot_state.json'
@{
    status_clienti = @{}
    jurnal_clienti = @{}
    step_counters = @{}
    running = @{}
    scan_active = @{}
    active_cont_id = ''
    updated_at = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
} | ConvertTo-Json -Depth 5 | Set-Content -Path $stateFile -Encoding UTF8

Write-Output 'HARD_STOP_OK'
