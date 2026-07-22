' Pornire robot GBG pentru un furnizor (același robot1.py, profil Chrome separat per cont_id).
' Dublu-click sau: cscript start_robot_supplier.vbs gbg_user_01
Option Explicit
Dim contId, robotDir, pythonExe, shell
contId = "gbg_user_01"
If WScript.Arguments.Count > 0 Then contId = WScript.Arguments(0)
robotDir = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
pythonExe = "python"
On Error Resume Next
shell = CreateObject("WScript.Shell")
shell.Environment("PROCESS")("BLU_SUPPLIER_CONT_ID") = contId
shell.CurrentDirectory = robotDir
shell.Run pythonExe & " robot1.py", 0, False
