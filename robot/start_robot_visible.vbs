' Porneste robot1.py cu fereastra vizibila (pentru debug Chrome)
Option Explicit

Dim fso, robotDir, pythonBin, logFile, WshShell
Set fso = CreateObject("Scripting.FileSystemObject")
robotDir = fso.GetParentFolderName(WScript.ScriptFullName)
pythonBin = "python"
logFile = robotDir & "\robot_service.log"

Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = robotDir

Dim http
On Error Resume Next
Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
http.open "GET", "http://127.0.0.1:5000/status", False
http.setTimeouts 1500, 1500, 1500, 1500
http.send
If Err.Number = 0 And http.Status >= 200 And http.Status < 500 Then
    WScript.Quit 0
End If
On Error GoTo 0

' 1 = fereastra normala (vezi consola Python)
WshShell.Run "cmd /k ""set ROBOT_FURNIZORI_PORT=5000&& " & pythonBin & " robot1.py""", 1, False
