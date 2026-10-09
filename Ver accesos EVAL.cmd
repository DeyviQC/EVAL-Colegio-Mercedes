@echo off
powershell.exe -NoProfile -File "%~dp0EVAL-local.ps1" -Accounts
if errorlevel 1 pause
