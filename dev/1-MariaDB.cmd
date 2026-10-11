@echo off
title Zika - MariaDB (cong 3306)
netstat -ano | findstr /R /C:":3306 .*LISTENING" >nul
if not errorlevel 1 (
  echo MariaDB dang chay san roi, khong can bat lai.
  timeout /t 5 >nul
  exit /b
)
echo Dang bat MariaDB o cong 3306. Dong cua so nay la tat MariaDB.
"D:\zika-devtools\mariadb\bin\mariadbd.exe" --defaults-file="D:\zika-devtools\mariadb-data\my.ini" --console
pause
