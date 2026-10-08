@echo off
powershell.exe -NoProfile -File "%~dp0EVAL-local.ps1" -Role director_admin
if errorlevel 1 pause
