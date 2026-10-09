@echo off
powershell.exe -NoProfile -File "%~dp0EVAL-local.ps1" -Role student
if errorlevel 1 pause
