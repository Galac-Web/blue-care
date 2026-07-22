$ErrorActionPreference = 'SilentlyContinue'
Get-CimInstance Win32_Process -Filter "name='undetected_chromedriver.exe'" | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
Get-CimInstance Win32_Process -Filter "name='chrome.exe'" |
  Where-Object { $_.CommandLine -like '*profil_gbg*' -or $_.CommandLine -like '*profil_pa_*' } |
  ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
foreach ($p in @('profil_gbg_gbg_user_01', 'profil_gbg_user_01', 'profil_pa_bluecar')) {
  $dir = "e:\laragon\www\blu-car.ro\robot\$p"
  foreach ($f in @('SingletonLock', 'SingletonCookie', 'SingletonSocket', 'lockfile')) {
    $path = Join-Path $dir $f
    if (Test-Path $path) { Remove-Item $path -Force -ErrorAction SilentlyContinue }
  }
}
Write-Output 'CHROME_CLEAN'
