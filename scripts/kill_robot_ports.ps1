$ErrorActionPreference = 'SilentlyContinue'
foreach ($port in 5000, 5007) {
    Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique |
        ForEach-Object { if ($_ -gt 0) { Stop-Process -Id $_ -Force } }
}
Start-Sleep -Seconds 2
Get-CimInstance Win32_Process -Filter "name='python.exe'" |
    Where-Object { $_.CommandLine -like '*robot1.py*' -or $_.CommandLine -like '*robot_pieseauto.py*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
Start-Sleep -Seconds 2
Write-Output 'PORTS_CLEARED'
