@echo off
REM Сначала k6.exe в корне репозитория (..\ от этой папки), иначе типичная установка winget.
set "K6_EXE=%~dp0..\k6.exe"
if exist "%K6_EXE%" (
  "%K6_EXE%" %*
) else (
  "C:\Program Files\k6\k6.exe" %*
)
