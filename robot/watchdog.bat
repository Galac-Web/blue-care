@echo off
title Blue-Car Robots Watchdog (Furnizori + PieseAuto)
cd /d "%~dp0"

:loop
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:5000/status' -UseBasicParsing -TimeoutSec 3; exit ([int]($r.StatusCode -ge 200 -and $r.StatusCode -lt 500)) } catch { exit 1 }" >nul 2>&1
if errorlevel 1 (
    wscript //B "%~dp0start_robot_hidden.vbs"
    timeout /t 8 /nobreak >nul
)

powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:5003/verificare_sesiune' -UseBasicParsing -TimeoutSec 3; exit ([int]($r.StatusCode -ge 200 -and $r.StatusCode -lt 500)) } catch { exit 1 }" >nul 2>&1
if errorlevel 1 (
    wscript //B "%~dp0start_pieseauto_hidden.vbs"
    timeout /t 8 /nobreak >nul
)

timeout /t 25 /nobreak >nul
goto loop
