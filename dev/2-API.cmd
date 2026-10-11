@echo off
title Zika - API PHP (cong 8787)
netstat -ano | findstr /R /C:":8787 .*LISTENING" >nul
if not errorlevel 1 (
  echo API PHP dang chay san roi, khong can bat lai.
  timeout /t 5 >nul
  exit /b
)
cd /d "%~dp0..\api"
echo Dang bat API PHP o http://127.0.0.1:8787 . Dong cua so nay la tat API.
"D:\zika-devtools\php\php.exe" -S 127.0.0.1:8787 dev-router.php
pause
