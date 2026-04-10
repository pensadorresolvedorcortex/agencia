#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT/.."

echo "[1/7] PHP lint"
find nafloresta-buy -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

echo "[2/7] JS syntax"
node --check nafloresta-buy/assets/js/front/app.js
node --check nafloresta-buy/assets/js/front/components/drawer.js
node --check nafloresta-buy/assets/js/front/utils/events.js
node --check nafloresta-buy/assets/js/admin/product-config.js

echo "[3/7] Tests"
php nafloresta-buy/tests/unit/run.php >/dev/null
php nafloresta-buy/tests/integration/run.php >/dev/null
node nafloresta-buy/tests/e2e/smoke.js >/dev/null

echo "[4/7] Version consistency"
rg -n "Version: 1\.0\.0" nafloresta-buy/nafloresta-buy.php >/dev/null
rg -n "NAFB_VERSION', '1\.0\.0'" nafloresta-buy/nafloresta-buy.php >/dev/null
rg -n "Stable tag: 1\.0\.0" nafloresta-buy/readme.txt >/dev/null
rg -n "## 1\.0\.0" nafloresta-buy/CHANGELOG.md >/dev/null

echo "[5/7] Production JS sanity"
if rg -n "console\.log|console\.debug" nafloresta-buy/assets/js; then
  echo "Console logs found"
  exit 1
fi

echo "[6/7] Package freshness"
rm -f dist/nafloresta-buy-v1.0.0.zip
TMPDIR="$(mktemp -d)"
rsync -a nafloresta-buy/ "$TMPDIR/nafloresta-buy/" \
  --exclude tests --exclude bin --exclude docs --exclude '*.log' \
  --exclude '.git' --exclude '.DS_Store' --exclude '.distignore' --exclude 'composer.json'
(cd "$TMPDIR" && zip -rq /workspace/agencia/dist/nafloresta-buy-v1.0.0.zip nafloresta-buy)
rm -rf "$TMPDIR"

if unzip -l dist/nafloresta-buy-v1.0.0.zip | rg "nafloresta-buy/tests|nafloresta-buy/bin|nafloresta-buy/docs"; then
  echo "Package contains excluded folders"
  exit 1
fi

echo "[7/7] Done"
echo "Release audit OK"
