@echo off
chcp 65001 >nul
cd /d "%~dp0"
set ROBOT_PIESEAUTO_PORT=5007
set PYTHON_BIN=C:\laragon\bin\python\python-3.13\python.exe
if not exist "%PYTHON_BIN%" set PYTHON_BIN=python

:loop
echo [%date% %time%] Pornesc robot_pieseauto.py pe port %ROBOT_PIESEAUTO_PORT%...
"%PYTHON_BIN%" -u robot_pieseauto.py >> robot_pieseauto_service.log 2>&1
echo [%date% %time%] Robot oprit — repornesc in 3 secunde...
timeout /t 3 /nobreak >nul
goto loop
