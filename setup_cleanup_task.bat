@echo off
REM ============================================
REM Auto Cleanup Task Scheduler Setup (Windows)
REM ============================================
REM This script sets up Windows Task Scheduler to run cleanup_old_logs.php daily

echo ========================================
echo Smart Home - Setup Auto Cleanup
echo ========================================
echo.

REM Check if running as Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Please run this script as Administrator!
    echo Right-click and select "Run as administrator"
    pause
    exit /b 1
)

echo Setting up Windows Task Scheduler...
echo.

REM Create scheduled task to run daily at 2:00 AM
schtasks /create /tn "SmartHome_LogCleanup" /tr "C:\laragon\bin\php\php-8.2.4-Win32-vs16-x64\php.exe C:\laragon\www\smarthome\cleanup_old_logs.php" /sc daily /st 02:00 /f /rl highest

if %errorLevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCESS! Task created successfully!
    echo ========================================
    echo.
    echo Task Name: SmartHome_LogCleanup
    echo Schedule: Daily at 2:00 AM
    echo Script: cleanup_old_logs.php
    echo.
    echo To view the task:
    echo - Open Task Scheduler (taskschd.msc)
    echo - Look for "SmartHome_LogCleanup"
    echo.
    echo To test manually:
    echo - Run: php cleanup_old_logs.php
    echo.
) else (
    echo.
    echo ERROR: Failed to create scheduled task!
    echo Please check the paths and try again.
    echo.
)

echo ========================================
pause
