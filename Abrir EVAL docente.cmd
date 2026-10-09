@echo off
powershell.exe -NoProfile -File "%~dp0EVAL-local.ps1" -Role teacher
if errorlevel 1 pause
