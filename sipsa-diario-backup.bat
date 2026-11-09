@echo off
setlocal

set "PROJECT=C:\xampp\htdocs\InventPalmas\inventario"
set "PHP=C:\xampp\php\php.exe"
set "MARKER=%LOCALAPPDATA%\InventPalmas\sipsa_ultima_ejecucion.txt"

for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set "TODAY=%%I"

if exist "%MARKER%" (
    set /p LAST_RUN=<"%MARKER%"
    if "%LAST_RUN%"=="%TODAY%" exit /b 0
)
)
cd /d "%PROJECT%"

"%PHP%" artisan sipsa:sync

if errorlevel 1 exit /b 1

if not exist "%LOCALAPPDATA%\InventPalmas" mkdir "%LOCALAPPDATA%\InventPalmas"

echo %TODAY%>"%MARKER%"

endlocal