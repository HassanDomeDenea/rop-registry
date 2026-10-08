@echo off
rem Puts camera images into the inbox of the ROP Registry.
rem Set this file as the application the virtual printer runs after printing, with the
rem saved file paths as its parameters. Run without parameters, it takes over whatever
rem waits in the camera export folder.

cd /d "%~dp0"

set "PHP=%USERPROFILE%\.config\herd\bin\php85\php.exe"
if not exist "%PHP%" set "PHP=php"

"%PHP%" artisan registry:capture %* >> "storage\logs\capture.log" 2>&1
