#!/usr/bin/env bash
# Sidrena source file.
# Author: brendigo
# Author URI: https://brendigo.com/
# Plugin URI: https://brendigo.com/sidrene-cijene/
# Support: sidrena@brendigo.com

set -euo pipefail

if [[ $# -ne 3 ]]; then
  echo "Usage: $0 <plugin-dir> <public-slug> <results-json>" >&2
  exit 2
fi

PLUGIN_DIR="$(realpath "$1")"
PUBLIC_SLUG="$2"
RESULTS_FILE="$3"
PLUGIN_SLUG="$(basename "$PLUGIN_DIR")"
WORK_DIR="$(mktemp -d "${RUNNER_TEMP:-/tmp}/sidrena-plugin-check.XXXXXX")"
RESULTS_FILE="$(realpath -m "$RESULTS_FILE")"

cleanup() {
  if [[ -d "$WORK_DIR" ]]; then
    (
      cd "$WORK_DIR"
      if command -v wp-env >/dev/null 2>&1 && [[ -f .wp-env.json ]]; then
        printf 'y\n' | wp-env destroy >/dev/null 2>&1 || true
      fi
    )
    rm -rf "$WORK_DIR"
  fi
}
trap cleanup EXIT

mkdir -p "$(dirname "$RESULTS_FILE")"

cat > "$WORK_DIR/.wp-env.json" <<JSON
{
  "core": null,
  "port": 8880,
  "testsEnvironment": false,
  "mappings": {
    "wp-content/plugins/$PLUGIN_SLUG": "$PLUGIN_DIR"
  },
  "config": {
    "WP_DEBUG": false,
    "SCRIPT_DEBUG": false
  }
}
JSON

cd "$WORK_DIR"

wp-env start --update
wp-env run cli wp cli info

if wp-env run cli wp plugin is-installed plugin-check >/dev/null 2>&1; then
  wp-env run cli wp plugin activate plugin-check
else
  wp-env run cli wp plugin install plugin-check --activate
fi

DEPENDENCIES="$(wp-env run cli wp plugin get "$PLUGIN_SLUG" --field=requires_plugins 2>/dev/null | tail -n 1 | tr ',' ' ' | xargs || true)"
if [[ -n "$DEPENDENCIES" ]]; then
  echo "Installing declared plugin dependencies: $DEPENDENCIES"
  # shellcheck disable=SC2086
  wp-env run cli wp plugin install --activate $DEPENDENCIES
fi

wp-env run cli wp plugin activate "$PLUGIN_SLUG"
wp-env run cli wp plugin list-checks
wp-env run cli wp plugin list-check-categories

set +e
wp-env run cli wp plugin check "$PLUGIN_SLUG" \
  --format=json \
  --slug="$PUBLIC_SLUG" \
  --require=./wp-content/plugins/plugin-check/cli.php \
  > "$RESULTS_FILE"
CHECK_STATUS=$?
set -e

cat "$RESULTS_FILE"

python3 - "$RESULTS_FILE" <<'PY'
import json
import pathlib
import sys

path = pathlib.Path(sys.argv[1])
raw = path.read_text(encoding="utf-8", errors="replace").strip()
if not raw:
    raise SystemExit("Plugin Check returned no JSON output.")

starts = [pos for pos in (raw.find("{"), raw.find("[")) if pos >= 0]
if not starts:
    raise SystemExit("Plugin Check output does not contain JSON.")
raw = raw[min(starts):]

decoder = json.JSONDecoder()
try:
    payload, end = decoder.raw_decode(raw)
except json.JSONDecodeError as exc:
    raise SystemExit(f"Plugin Check returned invalid JSON: {exc}") from exc

trailing = raw[end:].strip()
if trailing:
    print("Plugin Check wrapper output after JSON:", trailing, file=sys.stderr)

issues = []

def visit(value):
    if isinstance(value, dict):
        issue_type = str(value.get("type", "")).upper()
        if issue_type in {"ERROR", "WARNING"}:
            issues.append(value)
        for child in value.values():
            visit(child)
    elif isinstance(value, list):
        for child in value:
            visit(child)

visit(payload)

errors = [item for item in issues if str(item.get("type", "")).upper() == "ERROR"]
warnings = [item for item in issues if str(item.get("type", "")).upper() == "WARNING"]

print(f"Plugin Check summary: {len(errors)} errors, {len(warnings)} warnings.")

if errors or warnings:
    for item in issues[:50]:
        code = item.get("code", "unknown")
        message = item.get("message", "")
        line = item.get("line", "")
        column = item.get("column", "")
        print(f"{item.get('type', 'ISSUE')} {code} line={line} column={column}: {message}", file=sys.stderr)
    raise SystemExit(1)
PY

if [[ "$CHECK_STATUS" -ne 0 ]]; then
  echo "Plugin Check WP-CLI command exited with status $CHECK_STATUS." >&2
  exit "$CHECK_STATUS"
fi

echo "Plugin Check passed for $PLUGIN_SLUG using public slug $PUBLIC_SLUG."
