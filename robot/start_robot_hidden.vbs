' Porneste robot1.py fara fereastra (Windows) — port din .env
Option Explicit

Dim fso, robotDir, projectRoot, envFile, pythonBin, logFile, WshShell, port, envText, re, m
Set fso = CreateObject("Scripting.FileSystemObject")
robotDir = fso.GetParentFolderName(WScript.ScriptFullName)
projectRoot = fso.GetParentFolderName(robotDir)
envFile = projectRoot & "\.env"
pythonBin = "C:\laragon\bin\python\python-3.13\python.exe"
If Not fso.FileExists(pythonBin) Then pythonBin = "python"
logFile = robotDir & "\robot_service.log"
port = "5000"

If fso.FileExists(envFile) Then
    envText = fso.OpenTextFile(envFile, 1).ReadAll
    Set re = CreateObject("VBScript.RegExp")
    re.Global = True
    re.IgnoreCase = True
    re.Pattern = "ROBOT_FURNIZORI_PORT=(\d+)"
    Set m = re.Execute(envText)
    If m.Count > 0 Then
        port = m(0).SubMatches(0)
    Else
        re.Pattern = "ROBOT_FURNIZORI_URL=https?://[^:/]+:(\d+)"
        Set m = re.Execute(envText)
        If m.Count > 0 Then port = m(0).SubMatches(0)
    End If
End If

Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = robotDir

Dim http, pingUrl
pingUrl = "http://127.0.0.1:" & port & "/status"
On Error Resume Next
Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
http.open "GET", pingUrl, False
http.setTimeouts 1500, 1500, 1500, 1500
http.send
If Err.Number = 0 And http.Status = 200 Then
    WScript.Quit 0
End If
On Error GoTo 0

WshShell.Run "cmd /c ""set ROBOT_FURNIZORI_PORT=" & port & "&& " & pythonBin & " robot1.py >> """ & logFile & """ 2>&1""", 0, False
