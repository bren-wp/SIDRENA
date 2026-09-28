#!/usr/bin/env bash
# Sidrena source file.
# Author: brendigo
# Author URI: https://brendigo.com/
# Plugin URI: https://brendigo.com/sidrene-cijene/
# Support: sidrena@brendigo.com

set -euo pipefail

PREFIX="${1:-${RUNNER_TEMP:-/tmp}/sidrena-wp-env}"
rm -rf "$PREFIX"
mkdir -p "$PREFIX"

cat > "$PREFIX/package.json" <<'JSON'
{
  "private": true,
  "dependencies": {
    "@wordpress/env": "11.15.0"
  },
  "overrides": {
    "@wp-playground/cli": "3.1.55",
    "@php-wasm/node": "3.1.55"
  }
}
JSON

npm install --prefix "$PREFIX" --no-audit --no-fund

BIN_DIR="$PREFIX/node_modules/.bin"
test -x "$BIN_DIR/wp-env"

if [[ -n "${GITHUB_PATH:-}" ]]; then
  echo "$BIN_DIR" >> "$GITHUB_PATH"
fi

"$BIN_DIR/wp-env" --version
npm --prefix "$PREFIX" ls @wordpress/env @wp-playground/cli @php-wasm/node
