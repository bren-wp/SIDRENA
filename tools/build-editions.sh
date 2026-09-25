#!/usr/bin/env bash
# Sidrena source file.
# Author: Brendigo
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
  python3 "$ROOT/tools/build-support-pdf.py" "$VERSION" "$stage/docs/SIDRENA-PODRSKA.pdf"
}

copy_branding_bundle() {
  local stage="$1"
  local edition="$2"

  mkdir -p "$stage/branding"
  cp "$ROOT/branding/BRAND-GUIDE.md" "$stage/branding/"
  cp "$ROOT/branding/email-header.svg" "$stage/branding/"
  cp "$ROOT/branding/support-cover.svg" "$stage/branding/"
  cp "$ROOT/branding/website-hero-$edition.svg" "$stage/branding/"
  cp "$ROOT/branding/plugin-cover-$edition.svg" "$stage/branding/"
  cp "$ROOT/branding/social-$edition.svg" "$stage/branding/"
  cp "$ROOT/branding/docs-cover-$edition.svg" "$stage/branding/"
  cp "$ROOT/branding/cta-$edition.svg" "$stage/branding/"
  cp "$ROOT/branding/app-card-$edition.svg" "$stage/branding/"
  cp "$ROOT/branding/compact-$edition.svg" "$stage/branding/"
}

prepare_wporg_package() {
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
    text = text.replace('https://sidrene-cijene.com.hr/', 'https://brendigo.com/sidrene-cijene/')
    if path.suffix.lower() == '.php':
        text = text.replace(", 'sidrena' )", f", '{domain}' )")
        text = text.replace("load_plugin_textdomain( 'sidrena',", f"load_plugin_textdomain( '{domain}',")
    path.write_text(text, encoding='utf-8')

pot = root / 'languages' / 'sidrena.pot'
if pot.exists():
    target = root / 'languages' / f'{domain}.pot'
    target.write_text(
        pot.read_text(encoding='utf-8').replace('Text Domain: sidrena', f'Text Domain: {domain}'),
        encoding='utf-8'
    )
    pot.unlink()
PY
}

WP_STAGE="$WORK/sidrena-wordpress"
copy_common "$WP_STAGE"
copy_branding_bundle "$WP_STAGE" "wordpress"
cp "$WP_MAIN" "$WP_STAGE/sidrena-wordpress.php"
cp "$WP_README" "$WP_STAGE/readme.txt"
cp "$ROOT/docs/UPUTE-WORDPRESS.md" "$WP_STAGE/docs/UPUTE.md"
mkdir -p "$WP_STAGE/docs/images"
cp "$ROOT/docs/media/screenshot-wordpress.png" "$WP_STAGE/docs/images/screenshot-admin.png"
cp "$ROOT/wporg-assets/sidrena-wordpress/assets/"screenshot-*.png "$WP_STAGE/docs/images/"
sed -i 's#media/screenshot-wordpress.png#images/screenshot-admin.png#g' "$WP_STAGE/docs/UPUTE.md"
prepare_wporg_package "$WP_STAGE" "sidrena-wordpress"
rm -f   "$WP_STAGE/includes/class-sidrena-bulk.php"   "$WP_STAGE/includes/class-sidrena-history.php"   "$WP_STAGE/includes/class-sidrena-location-data.php"   "$WP_STAGE/includes/class-sidrena-location-history.php"   "$WP_STAGE/includes/class-sidrena-products.php"   "$WP_STAGE/includes/class-sidrena-woo-import-export.php"   "$WP_STAGE/includes/class-sidrena-compatibility.php"

WOO_STAGE="$WORK/sidrena-woocommerce"
copy_common "$WOO_STAGE"
copy_branding_bundle "$WOO_STAGE" "woocommerce"
cp "$WOO_MAIN" "$WOO_STAGE/sidrena-woocommerce.php"
cp "$WOO_README" "$WOO_STAGE/readme.txt"
cp "$ROOT/docs/UPUTE-WOOCOMMERCE.md" "$WOO_STAGE/docs/UPUTE.md"
mkdir -p "$WOO_STAGE/docs/images"
cp "$ROOT/docs/media/screenshot-woocommerce.png" "$WOO_STAGE/docs/images/screenshot-admin.png"
cp "$ROOT/wporg-assets/sidrena-woocommerce/assets/"screenshot-*.png "$WOO_STAGE/docs/images/"
sed -i 's#media/screenshot-woocommerce.png#images/screenshot-admin.png#g' "$WOO_STAGE/docs/UPUTE.md"
prepare_wporg_package "$WOO_STAGE" "sidrena-woocommerce"
rm -f "$WOO_STAGE/includes/class-sidrena-standalone.php"

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

rm -f "$OUTDIR/sidrena-wordpress-$VERSION.zip" "$OUTDIR/sidrena-woocommerce-$VERSION.zip"
(
  cd "$WORK"
  LC_ALL=C find sidrena-wordpress -print | LC_ALL=C sort | zip -X -q "$OUTDIR/sidrena-wordpress-$VERSION.zip" -@
  LC_ALL=C find sidrena-woocommerce -print | LC_ALL=C sort | zip -X -q "$OUTDIR/sidrena-woocommerce-$VERSION.zip" -@
)

(
  cd "$OUTDIR"
  sha256sum "sidrena-wordpress-$VERSION.zip" > "sidrena-wordpress-$VERSION.zip.sha256"
  sha256sum "sidrena-woocommerce-$VERSION.zip" > "sidrena-woocommerce-$VERSION.zip.sha256"
)

echo "$WP_STAGE"
echo "$WOO_STAGE"
