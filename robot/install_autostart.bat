@echo off
setlocal
title Instalare pornire automata robot Blue-Car

set "ROBOT_DIR=%~dp0"
set "ROBOT_DIR=%ROBOT_DIR:~0,-1%"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "LINK_NAME=Blue-Car Robot Watchdog.lnk"

echo.
echo === Blue-Car — pornire automata 2 roboti ===
echo.
echo   Port 5000 = Furnizori GBG (robot1.py) - Monitor Robot
echo   Port 5003 = PieseAuto (robot_pieseauto.py) - PieseAuto Robot
echo.
echo Folder robot: %ROBOT_DIR%
echo Startup Windows: %STARTUP%
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut('%STARTUP%\%LINK_NAME%');" ^
  "$s.TargetPath = 'wscript.exe';" ^
  "$s.Arguments = '//B \"%ROBOT_DIR%\\start_watchdog_hidden.vbs\"';" ^
  "$s.WorkingDirectory = '%ROBOT_DIR%';" ^
  "$s.WindowStyle = 7;" ^
  "$s.Description = 'Blue-Car robot GBG watchdog';" ^
  "$s.Save()"

if errorlevel 1 (
    echo Eroare la crearea shortcut-ului in Startup.
    pause
    exit /b 1
)

echo OK — la urmatoarea logare Windows, robotul va porni singur.
echo.
echo Pornesc acum robotul + watchdog...
wscript //B "%ROBOT_DIR%\start_watchdog_hidden.vbs"
timeout /t 3 /nobreak >nul
powershell -NoProfile -Command "try { (Invoke-WebRequest -Uri 'http://127.0.0.1:5000/status' -UseBasicParsing -TimeoutSec 4).StatusCode } catch { 'OFFLINE' }"
echo.
echo Daca vezi 200, robotul ruleaza.
echo.
pause
