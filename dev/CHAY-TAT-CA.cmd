@echo off
title Zika - bat tat ca
echo Bat MariaDB, API PHP va giao dien web (moi phan 1 cua so rieng)...
start "Zika - MariaDB" cmd /c "%~dp01-MariaDB.cmd"
timeout /t 4 /nobreak >nul
start "Zika - API PHP" cmd /c "%~dp02-API.cmd"
start "Zika - Web" cmd /c "%~dp03-Web.cmd"
echo Xong. Trinh duyet se tu mo http://localhost:5173/login
timeout /t 5 >nul
