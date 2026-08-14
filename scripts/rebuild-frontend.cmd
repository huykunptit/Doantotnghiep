@echo off
setlocal
title Rebuild frontend (bust Docker + browser cache)

cd /d "%~dp0.."

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set NUXT_PUBLIC_BUILD_ID=%%i

echo.
echo  Build ID: %NUXT_PUBLIC_BUILD_ID%
echo  [1/3] Rebuild frontend image...
docker compose build frontend
if errorlevel 1 goto :fail

echo.
echo  [2/3] Recreate frontend + nginx...
docker compose up -d --force-recreate frontend nginx
if errorlevel 1 goto :fail

echo.
echo  [3/3] Done. Build ID = %NUXT_PUBLIC_BUILD_ID%
echo.
echo  Hard refresh: Ctrl+Shift+R  (hoac Incognito)
echo  URL: http://localhost/student/courses
echo.
pause
exit /b 0

:fail
echo.
echo  THAT BAI. Kiem tra Docker Desktop dang chay.
echo.
pause
exit /b 1
