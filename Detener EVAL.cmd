@echo off
powershell.exe -NoProfile -File "%~dp0EVAL-local.ps1" -Stop
if errorlevel 1 pause
