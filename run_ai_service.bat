@echo off
title Layanan AI Python YOLO - PLN UP3 Tegal
color 0B
echo ============================================================
echo   PT PLN (PERSERO) UP3 TEGAL
echo   Layanan Deteksi Objek AI (Python) - Port 8001
echo ============================================================
echo Memulai server HTTP AI Service...
echo.

python ai_service/app.py 127.0.0.1 8001

pause
