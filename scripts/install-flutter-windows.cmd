@echo off
setlocal
title Cai dat Flutter SDK - Doantotnghiep

echo.
echo  Script nay KHONG can quyen Administrator.
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-flutter-windows.ps1" %*
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
