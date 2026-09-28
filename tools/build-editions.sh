#!/usr/bin/env bash
# Sidrena source file.
# Author: brendigo
# Author URI: https://brendigo.com/
# Plugin URI: https://brendigo.com/sidrene-cijene/
# Support: sidrena@brendigo.com

set -euo pipefail

VERSION="${1:-}"
OUTDIR="${2:-}"

if [[ -z "$VERSION" || -z "$OUTDIR" ]]; then
  echo "Usage: $0 <version> <output-dir>" >&2
  exit 2
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUTDIR="$(mkdir -p "$OUTDIR" && cd "$OUTDIR" && pwd)"
WORK="$OUTDIR/.sidrena-build"
NORMALIZED_EPOCH="${SOURCE_DATE_EPOCH:-946684800}"
MAX_ZIP_BYTES="${SIDRENA_MAX_ZIP_BYTES:-1572864}"
rm -rf "$WORK"
mkdir -p "$WORK"

WP_MAIN="$ROOT/editions/wordpress/sidrena-wordpress.php"
WOO_MAIN="$ROOT/editions/woocommerce/sidrena-woocommerce.php"
WP_README="$ROOT/editions/wordpress/readme.txt"
WOO_README="$ROOT/editions/woocommerce/readme.txt"

for file in "$WP_MAIN" "$WOO_MAIN" "$WP_README" "$WOO_README"; do
  test -f "$file"
done

header_version() {
  sed -n 's/^ \* Version: //p' "$1" | head -n1
}
stable_tag() {
  sed -n 's/^Stable tag: //p' "$1" | head -n1
}

test "$(header_version "$WP_MAIN")" = "$VERSION"
test "$(header_version "$WOO_MAIN")" = "$VERSION"
test "$(stable_tag "$WP_README")" = "$VERSION"
test "$(stable_tag "$WOO_README")" = "$VERSION"

copy_common() {
  local stage="$1"
  mkdir -p "$stage"
  cp "$ROOT/LICENSE" "$ROOT/uninstall.php" "$stage/"
  rsync -a "$ROOT/admin/" "$stage/admin/"
  rsync -a "$ROOT/assets/" "$stage/assets/"
  rsync -a "$ROOT/includes/" "$stage/includes/"
  rsync -a "$ROOT/languages/" "$stage/languages/"
  rsync -a "$ROOT/public/" "$stage/public/"
  mkdir -p "$stage/docs"
  cp "$ROOT/docs/legal-and-technical-notes.md" "$stage/docs/"
}

prepare_install_docs() {
  local source="$1"
  local target="$2"

  python3 - "$source" "$target" <<'PY'
from pathlib import Path
import re
import sys

source = Path(sys.argv[1])
target = Path(sys.argv[2])
text = source.read_text(encoding='utf-8')

# Runtime screenshots and WordPress.org marketing assets stay in the source
# repository. They are deliberately excluded from the install ZIP so common
# shared-hosting upload limits do not block plugin installation.
text = re.sub(r'^!\[[^\n]*\]\([^\n]*\)\s*$', '', text, flags=re.MULTILINE)
text = re.sub(
    r'\n## Galerija stvarnog sučelja\n.*?(?=\n## )',
    '\n',
    text,
    flags=re.DOTALL,
)
text = text.replace(
    '> Screenshot se automatski snima iz aktivnog WordPress admin sučelja pri pripremi WordPress.org asseta. U instalacijskom ZIP-u nalazi se kao `docs/images/screenshot-admin.png`.\n',
    ''
)
target.write_text(text.strip() + '\n', encoding='utf-8')
PY
}

prepare_package() {
  local stage="$1"
  local domain="$2"

  python3 - "$stage" "$domain" <<'PY'
from pathlib import Path
import sys

root = Path(sys.argv[1])
domain = sys.argv[2]

text_suffixes = {'.php', '.md', '.txt', '.css', '.js', '.svg', '.pot'}
for path in root.rglob('*'):
    if not path.is_file() or path.suffix.lower() not in text_suffixes:
        continue
    text = path.read_text(encoding='utf-8')
    if path.suffix.lower() == '.php':
        text = text.replace(", 'sidrena' )", f", '{domain}' )")
        text = text.replace("load_plugin_textdomain( 'sidrena',", f"load_plugin_textdomain( '{domain}',")
    path.write_text(text, encoding='utf-8')

pot = root / 'languages' / 'sidrena.pot'
if pot.exists():
    pot_text = pot.read_text(encoding='utf-8')
    pot_text = pot_text.replace('Text Domain: sidrena', f'Text Domain: {domain}')
    pot_text = pot_text.replace('X-Domain: sidrena', f'X-Domain: {domain}')
    if domain == 'sidrena':
        pot.write_text(pot_text, encoding='utf-8')
    else:
        target = root / 'languages' / f'{domain}.pot'
        target.write_text(pot_text, encoding='utf-8')
        pot.unlink()
PY
}

WP_STAGE="$WORK/brendigo-sidrene-cijene-digitalni-cjenici"
copy_common "$WP_STAGE"
cp "$WP_MAIN" "$WP_STAGE/brendigo-sidrene-cijene-digitalni-cjenici.php"
cp "$WP_README" "$WP_STAGE/readme.txt"
prepare_install_docs "$ROOT/docs/UPUTE-WORDPRESS.md" "$WP_STAGE/docs/UPUTE.md"
python3 "$ROOT/tools/build-support-pdf.py" "$VERSION" "$WP_STAGE/docs/SIDRENA-UPUTE.pdf" "wordpress" "$WP_STAGE/docs/UPUTE.md"
prepare_package "$WP_STAGE" "brendigo-sidrene-cijene-digitalni-cjenici"
rm -f \
  "$WP_STAGE/assets/images/logo-brendigo-store.svg" \
  "$WP_STAGE/assets/images/logo-brendigo-store-light.svg" \
  "$WP_STAGE/includes/class-sidrena-bulk.php" \
  "$WP_STAGE/includes/class-sidrena-history.php" \
  "$WP_STAGE/includes/class-sidrena-location-data.php" \
  "$WP_STAGE/includes/class-sidrena-location-history.php" \
  "$WP_STAGE/includes/class-sidrena-products.php" \
  "$WP_STAGE/includes/class-sidrena-woo-import-export.php" \
  "$WP_STAGE/includes/class-sidrena-compatibility.php"

WOO_STAGE="$WORK/brendigo-sidrene-cijene-cjenici"
copy_common "$WOO_STAGE"
cp "$WOO_MAIN" "$WOO_STAGE/brendigo-sidrene-cijene-cjenici.php"
cp "$WOO_README" "$WOO_STAGE/readme.txt"
prepare_install_docs "$ROOT/docs/UPUTE-WOOCOMMERCE.md" "$WOO_STAGE/docs/UPUTE.md"
python3 "$ROOT/tools/build-support-pdf.py" "$VERSION" "$WOO_STAGE/docs/SIDRENA-UPUTE.pdf" "woocommerce" "$WOO_STAGE/docs/UPUTE.md"
prepare_package "$WOO_STAGE" "brendigo-sidrene-cijene-cjenici"
rm -f \
  "$WOO_STAGE/assets/images/logo-brendigo-standalone.svg" \
  "$WOO_STAGE/assets/images/logo-brendigo-standalone-light.svg" \
  "$WOO_STAGE/includes/class-sidrena-standalone.php"

python3 - "$NORMALIZED_EPOCH" "$WP_STAGE" "$WOO_STAGE" <<'PY'
import os
import sys

epoch = int(sys.argv[1])
for root in sys.argv[2:]:
    for current, dirs, files in os.walk(root):
        for name in dirs + files:
            path = os.path.join(current, name)
            os.utime(path, (epoch, epoch), follow_symlinks=False)
        os.utime(current, (epoch, epoch), follow_symlinks=False)
PY

rm -f "$OUTDIR/brendigo-sidrene-cijene-digitalni-cjenici-$VERSION.zip" "$OUTDIR/brendigo-sidrene-cijene-cjenici-$VERSION.zip"
(
  cd "$WORK"
  LC_ALL=C find brendigo-sidrene-cijene-digitalni-cjenici -print | LC_ALL=C sort | zip -X -q "$OUTDIR/brendigo-sidrene-cijene-digitalni-cjenici-$VERSION.zip" -@
  LC_ALL=C find brendigo-sidrene-cijene-cjenici -print | LC_ALL=C sort | zip -X -q "$OUTDIR/brendigo-sidrene-cijene-cjenici-$VERSION.zip" -@
)

for zip_path in "$OUTDIR/brendigo-sidrene-cijene-digitalni-cjenici-$VERSION.zip" "$OUTDIR/brendigo-sidrene-cijene-cjenici-$VERSION.zip"; do
  zip_size="$(stat -c%s "$zip_path")"
  echo "$(basename "$zip_path"): $zip_size bytes"
  if (( zip_size > MAX_ZIP_BYTES )); then
    echo "Install ZIP exceeds the $MAX_ZIP_BYTES byte shared-hosting safety limit: $zip_path" >&2
    exit 1
  fi
done

(
  cd "$OUTDIR"
  sha256sum "brendigo-sidrene-cijene-digitalni-cjenici-$VERSION.zip" > "brendigo-sidrene-cijene-digitalni-cjenici-$VERSION.zip.sha256"
  sha256sum "brendigo-sidrene-cijene-cjenici-$VERSION.zip" > "brendigo-sidrene-cijene-cjenici-$VERSION.zip.sha256"
)

echo "$WP_STAGE"
echo "$WOO_STAGE"
