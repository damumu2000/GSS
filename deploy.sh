#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

echo "[deploy] project: $ROOT_DIR"

if [[ ! -f ".env" ]]; then
  echo "[deploy] missing .env, aborting."
  exit 1
fi

if [[ ! -f "artisan" ]]; then
  echo "[deploy] artisan not found, aborting."
  exit 1
fi

if [[ ! -d ".git" ]]; then
  echo "[deploy] .git directory not found, aborting."
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "[deploy] repository has local changes, aborting."
  echo "[deploy] commit or discard local tracked-file changes before deploy."
  exit 1
fi

CURRENT_BRANCH="$(git branch --show-current)"

if [[ -z "$CURRENT_BRANCH" ]]; then
  echo "[deploy] cannot detect current git branch, aborting."
  exit 1
fi

echo "[deploy] branch: $CURRENT_BRANCH"

echo "[deploy] fetching latest code..."
git fetch --prune origin
git pull --ff-only origin "$CURRENT_BRANCH"

if command -v composer >/dev/null 2>&1; then
  echo "[deploy] installing composer dependencies..."
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress
else
  echo "[deploy] composer not found, aborting."
  exit 1
fi

if [[ ! -f "bootstrap/cache/.gitignore" ]]; then
  mkdir -p bootstrap/cache
fi

echo "[deploy] running safe database migrations..."
php artisan migrate --force

echo "[deploy] refreshing caches..."
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

if [[ ! -L "public/storage" ]]; then
  echo "[deploy] ensuring storage symlink..."
  php artisan storage:link
fi

echo "[deploy] done."
