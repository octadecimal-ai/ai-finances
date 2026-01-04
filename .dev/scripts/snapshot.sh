#!/bin/bash

# Skrypt do tworzenia snapshotów zmian w projekcie

if [ -z "$1" ]; then
    echo "Użycie: $0 <opis_zmian>"
    echo "Przykład: $0 'dodanie_integracji_nordigen'"
    exit 1
fi

DESCRIPTION="$1"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
SNAPSHOT_NAME="snapshot_${TIMESTAMP}_${DESCRIPTION// /_}"

echo "Tworzenie snapshotu: $SNAPSHOT_NAME"

# Tworzenie katalogu dla snapshotów jeśli nie istnieje
mkdir -p .dev/snapshots

# Tworzenie snapshotu z git
git add .
git commit -m "Snapshot: $DESCRIPTION - $TIMESTAMP"

# Zapisywanie informacji o snapshocie
echo "Snapshot: $SNAPSHOT_NAME" > ".dev/snapshots/${SNAPSHOT_NAME}.txt"
echo "Opis: $DESCRIPTION" >> ".dev/snapshots/${SNAPSHOT_NAME}.txt"
echo "Data: $(date)" >> ".dev/snapshots/${SNAPSHOT_NAME}.txt"
echo "Hash: $(git rev-parse HEAD)" >> ".dev/snapshots/${SNAPSHOT_NAME}.txt"

echo "Snapshot utworzony pomyślnie: $SNAPSHOT_NAME"
echo "Hash commit: $(git rev-parse HEAD)" 