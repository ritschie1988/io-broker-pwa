#!/bin/bash
# Automatisiertes Build-Script für Vite-Projekt auf dem Raspberry Pi
# Usage: ./build.sh

set -e

cd /var/www/html/progpfad/io-broker-pwa/

echo "Leere dist/assets und public/assets ..."
rm -rf dist/assets/* || true
rm -rf public/assets/* || true

echo "Starte npm run build ..."
npm run build

echo "Kopiere dist/* nach public/ ..."
cp -r dist/* public/

echo "Build und Deployment abgeschlossen!"
