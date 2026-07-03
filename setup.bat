@echo off
title Setup Gym Management - Novi Prime Athletics
echo ===================================================
echo  SETUP DATABASE GYM MANAGEMENT - NOVI PRIME
echo ===================================================
echo.
echo Mencoba mengimpor database menggunakan MySQL Laragon...
echo.

C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe -u root < database\schema.sql

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ===================================================
    echo  [OK] DATABASE BERHASIL DIIMPOR!
    echo ===================================================
    echo.
    echo  Aplikasi siap digunakan.
    echo  Akses melalui browser: http://localhost/project-gym-main/
    echo  Demo Credentials: admin / admin123
    echo.
) else (
    echo.
    echo ===================================================
    echo  [ERROR] GAGAL MENGIMPOR DATABASE!
    echo ===================================================
    echo  Pastikan service MySQL di Laragon sudah berjalan.
    echo.
)

pause
