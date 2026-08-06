@echo off
setlocal
title Cai dat Docker Desktop - Doantotnghiep

echo.
echo  Script se yeu cau quyen Administrator (UAC).
echo  Bam Yes khi Windows hoi.
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-docker-windows.ps1" %*
set ERR=%ERRORLEVEL%

echo.
if %ERR% NEQ 0 (
  echo  Loi, ma thoat: %ERR%
) else (
  echo  Xong.
)
echo.
pause
exit /b %ERR%
