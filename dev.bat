@echo off
setlocal
cd /d "%~dp0"

start "Ahorro Semanal API" cmd /k "cd /d "%~dp0api" && serve.bat"
start "Ahorro Semanal App" cmd /k "cd /d "%~dp0app" && npm run dev"
