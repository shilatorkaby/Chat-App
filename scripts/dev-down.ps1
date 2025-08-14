@echo off
echo Stopping and removing containers and volumes...
docker compose down -v
echo Done.
