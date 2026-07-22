' Watchdog ascuns — mentine ambele roboti porniti
Option Explicit

Dim fso, robotDir, WshShell
Set fso = CreateObject("Scripting.FileSystemObject")
robotDir = fso.GetParentFolderName(WScript.ScriptFullName)

Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = robotDir
WshShell.Run "cmd /c """ & robotDir & "\watchdog.bat""", 0, False

WshShell.Run "wscript.exe //B """ & robotDir & "\start_robot_hidden.vbs""", 0, False
WshShell.Run "wscript.exe //B """ & robotDir & "\start_pieseauto_hidden.vbs""", 0, False
