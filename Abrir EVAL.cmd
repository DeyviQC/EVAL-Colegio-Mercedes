@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-laravel.ps1" -OpenBrowser
if errorlevel 1 pause

