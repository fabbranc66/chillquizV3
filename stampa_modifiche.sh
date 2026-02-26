#!/bin/bash

echo "File modificati:"
git diff --name-only

echo ""
echo "Contenuto file modificati:"
git diff --name-only | while read f; do
  echo "===== $f ====="
  cat "$f"
  echo ""
done