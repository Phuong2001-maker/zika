@echo off
title Zika - Web (cong 5173)
netstat -ano | findstr /R /C:":5173 .*LISTENING" >nul
if not errorlevel 1 (
  echo Web dang chay san roi. Mo trinh duyet vao http://localhost:5173
  start "" http://localhost:5173/login
  timeout /t 5 >nul
  exit /b
)
cd /d "%~dp0..\web"
echo Dang bat giao dien web o http://localhost:5173 . Dong cua so nay la tat web.
call npm run dev -- --open /login
pause
