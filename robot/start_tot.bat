@echo off
chcp 65001 >nul
title Blue-Car — Pornire roboți
cd /d "%~dp0"

echo.
echo  ============================================
echo   BLUE-CAR — Pornire roboți scan + PieseAuto
echo  ============================================
echo.

echo [1/3] Robot GBG (furnizor) port 5000...
wscript.exe //B "%~dp0start_robot_hidden.vbs"
timeout /t 3 /nobreak >nul

echo [2/3] Robot PieseAuto port 5007...
wscript.exe //B "%~dp0start_pieseauto_hidden.vbs"
timeout /t 5 /nobreak >nul

echo [3/3] Verificare servicii (fără deschidere browser)...
set PHP_BIN=
if exist "E:\laragon\bin\php\php-8.1.31-Win32-vs16-x64\php.exe" set PHP_BIN=E:\laragon\bin\php\php-8.1.31-Win32-vs16-x64\php.exe
if "%PHP_BIN%"=="" if exist "E:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe" set PHP_BIN=E:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe
if "%PHP_BIN%"=="" set PHP_BIN=php

"%PHP_BIN%" "%~dp0..\scripts\start_robots_prepare.php"
echo.

echo  Gata. Deschide admin:
echo    http://blu-car.test/admin/?page=robot-monitor
echo    sau https://blu-car.ro/admin/?page=robot-monitor
echo.
echo  Bifeaza: Publica automat pe PieseAuto dupa import
echo  Apoi: Lanseaza scanare
echo.
pause
