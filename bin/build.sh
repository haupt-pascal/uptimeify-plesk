#!/usr/bin/env bash
#
# Build a distributable Plesk extension package (uptimeify-<version>.zip).
#
# The ZIP contains exactly the runtime layout Plesk expects (meta.xml at the
# root next to plib/ and htdocs/), with production composer dependencies bundled
# under vendor/ and all dev tooling stripped out.
#
# Usage: bin/build.sh [output-dir]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$ROOT/dist}"
cd "$ROOT"

VERSION="$(grep -oE '<version>[^<]+' meta.xml | sed 's/<version>//')"
PKG_NAME="uptimeify-${VERSION}.zip"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "==> Building uptimeify Plesk extension v${VERSION}"

# 1. Install production-only dependencies into a clean vendor/ tree.
echo "==> Installing composer dependencies (no-dev)"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 2. Stage the runtime files.
echo "==> Staging files"
mkdir -p "$STAGE"
cp meta.xml "$STAGE/"
cp -R plib "$STAGE/"
cp -R htdocs "$STAGE/"
# Composer deps must live under plib/vendor so Plesk auto-loads them at runtime.
cp -R vendor "$STAGE/plib/vendor"
cp -R _meta "$STAGE/"
cp DESCRIPTION.md "$STAGE/" 2>/dev/null || true
cp LICENSE "$STAGE/" 2>/dev/null || true
cp README.md "$STAGE/" 2>/dev/null || true

# 3. Prune anything that should never ship.
find "$STAGE" -type d -name '.git' -prune -exec rm -rf {} +
find "$STAGE" -type d -name 'tests' -prune -exec rm -rf {} +
find "$STAGE" -name '*.dist' -delete
find "$STAGE" -name '.gitkeep' -delete

# 4. Zip it up.
mkdir -p "$OUT_DIR"
rm -f "$OUT_DIR/$PKG_NAME"
(cd "$STAGE" && zip -rq "$OUT_DIR/$PKG_NAME" .)

echo "==> Done: $OUT_DIR/$PKG_NAME"
