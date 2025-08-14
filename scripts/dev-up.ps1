@echo off
setlocal
echo Building and starting containers...
docker compose up -d --build
if %errorlevel% neq 0 (
  echo Docker compose up failed.
  exit /b 1
)
echo Done.
endlocal
