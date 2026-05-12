#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-$(command -v php || true)}"

if [[ -z "$PHP_BIN" ]]; then
  echo "php command not found. Set PHP_BIN explicitly and retry." >&2
  exit 1
fi

cd "$ROOT_DIR"

exec "$PHP_BIN" artisan queue:work --sleep=3 --tries=3 --timeout=120
