@echo off
title Proxy IPTV
cd /d "%~dp0"

where node >nul 2>nul
if errorlevel 1 (
  echo.
  echo   [!] Node.js n'est pas installe.
  echo.
  echo   Telecharge-le ici : https://nodejs.org  ^(bouton vert "LTS"^)
  echo   Installe-le, puis relance ce fichier.
  echo.
  pause
  exit /b
)

echo.
echo   Proxy IPTV demarre.
echo   Dans le lecteur, mets ce prefixe proxy : http://localhost:8787/
echo.
echo   Laisse cette fenetre OUVERTE tant que tu regardes la TV.
echo   Ferme-la pour arreter le proxy.
echo.
node "%~dp0iptv-proxy.js"
pause
