@echo off
rem Starts the ROP Registry on this computer and opens it in the default browser.
rem Keep this window open while the registry is in use; close it to stop the registry.

cd /d "%~dp0"

set "PHP=%USERPROFILE%\.config\herd\bin\php85\php.exe"
if not exist "%PHP%" set "PHP=php"

start "" http://localhost:8000
"%PHP%" artisan serve --port=8000
