@echo off
powershell.exe -NoProfile -File "%~dp0EVAL-local.ps1" -Role vice_principal
if errorlevel 1 pause
