#!/bin/bash

# Comprimir el proyecto excluyendo archivos y directorios específicos

FILENAME="socomarca-random-erp-sync-for-woocommerce.zip"

echo "Eliminando archivo anterior si existe..."
rm -f "$FILENAME"

echo "Comprimiendo proyecto: $FILENAME"

zip -r "$FILENAME" . \
    -x \
    ".circleci/*" \
    ".claude/*" \
    ".github/*" \
    ".git/*" \
    "Docker/*" \
    "logs/*" \
    "CLAUDE.md" \
    "GEMINI.md" \
    "docker-compose.yml" \
    "bin/*" \
    ".env.testing" \
    "node_modules/*" \
    "vendor/*" \
    "*.zip" \
    ".DS_Store" \
    "Thumbs.db"

if [ $? -eq 0 ]; then
    echo "Archivo comprimido exitosamente: $FILENAME"
    ls -lh "$FILENAME"
else
    echo "Error al comprimir el proyecto"
    exit 1
fi
