@echo off
REM Windows batch wrapper for bin/server.ps1
REM This allows running "bin/server" on Windows

powershell.exe -ExecutionPolicy Bypass -File "%~dp0server.ps1" %*

