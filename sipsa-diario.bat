@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "PROJECT=C:\xampp\htdocs\InventPalmas\inventario"
set "PHP=C:\php\php.exe"
set "DATA_DIR=%LOCALAPPDATA%\InventPalmas"
set "MARKER=%DATA_DIR%\sipsa_ultima_ejecucion.txt"
set "LOG=%DATA_DIR%\sipsa_ejecuciones.log"

if not exist "%DATA_DIR%" mkdir "%DATA_DIR%"

for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set "TODAY=%%I"

if not defined TODAY (
    echo [%date% %time%] ERROR: No se pudo obtener la fecha. >> "%LOG%"
    exit /b 1
)

echo. >> "%LOG%"
echo ================================================== >> "%LOG%"
echo [%date% %time%] Inicio de sincronizacion SIPSA >> "%LOG%"

if exist "%MARKER%" (
    set /p LAST_RUN=<"%MARKER%"
    if "!LAST_RUN!"=="!TODAY!" (
        echo [%date% %time%] OMITIDO: Ya se ejecuto correctamente hoy. >> "%LOG%"
        exit /b 0
    )
)

if not exist "%PHP%" (
    echo [%date% %time%] ERROR: No se encuentra PHP: %PHP% >> "%LOG%"
    exit /b 1
)

if not exist "%PROJECT%\artisan" (
    echo [%date% %time%] ERROR: No se encuentra Laravel en %PROJECT% >> "%LOG%"
    exit /b 1
)

cd /d "%PROJECT%"

echo [%date% %time%] Ejecutando php artisan sipsa:sync... >> "%LOG%"

"%PHP%" artisan sipsa:sync >> "%LOG%" 2>&1

set "RESULT=!ERRORLEVEL!"

if not "!RESULT!"=="0" (
    echo [%date% %time%] ERROR: Artisan termino con codigo !RESULT! >> "%LOG%"
    exit /b !RESULT!
)

echo %TODAY%>"%MARKER%"

if errorlevel 1 (
    echo [%date% %time%] ERROR: No se pudo guardar el marcador diario. >> "%LOG%"
    exit /b 1
)

echo [%date% %time%] SINCRONIZACION COMPLETADA CORRECTAMENTE. >> "%LOG%"

endlocal
exit /b 0