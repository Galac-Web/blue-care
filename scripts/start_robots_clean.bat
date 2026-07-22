@echo off
chcp 65001 >nul
cd /d "%~dp0\..\robot"
set ROBOT_FURNIZORI_PORT=5000
set ROBOT_PIESEAUTO_PORT=5007
set PYTHON_BIN=C:\laragon\bin\python\python-3.13\python.exe
if not exist "%PYTHON_BIN%" set PYTHON_BIN=python

start "Blue-Car GBG Robot" /MIN cmd /c "set ROBOT_FURNIZORI_PORT=5000&& %PYTHON_BIN% -u robot1.py >> robot_service.log 2>&1"
timeout /t 3 /nobreak >nul
start "Blue-Car PieseAuto Robot" /MIN cmd /c "set ROBOT_PIESEAUTO_PORT=5007&& %PYTHON_BIN% -u robot_pieseauto.py >> robot_pieseauto_service.log 2>&1"
echo START_OK
