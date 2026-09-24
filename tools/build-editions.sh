#!/usr/bin/env bash
# Sidrena source file.
# Author: Brendigo LTD Developer
# Author URI: https://brendigo.com/
# Plugin URI: https://sidrene-cijene.com.hr/
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
  python3 "$ROOT/tools/build-support-pdf.py" "$stage/docs/SIDRENA-PODRSKA.pdf"
}

WP_STAGE="$WORK/sidrena-wordpress"
copy_common "$WP_STAGE"
cp "$WP_MAIN" "$WP_STAGE/sidrena-wordpress.php"
cp "$WP_README" "$WP_STAGE/readme.txt"
cp "$ROOT/docs/UPUTE-WORDPRESS.md" "$WP_STAGE/docs/UPUTE.md"
rm -f   "$WP_STAGE/includes/class-sidrena-bulk.php"   "$WP_STAGE/includes/class-sidrena-history.php"   "$WP_STAGE/includes/class-sidrena-location-data.php"   "$WP_STAGE/includes/class-sidrena-location-history.php"   "$WP_STAGE/includes/class-sidrena-products.php"   "$WP_STAGE/includes/class-sidrena-woo-import-export.php"   "$WP_STAGE/includes/class-sidrena-compatibility.php"

WOO_STAGE="$WORK/sidrena-woocommerce"
copy_common "$WOO_STAGE"
cp "$WOO_MAIN" "$WOO_STAGE/sidrena-woocommerce.php"
cp "$WOO_README" "$WOO_STAGE/readme.txt"
cp "$ROOT/docs/UPUTE-WOOCOMMERCE.md" "$WOO_STAGE/docs/UPUTE.md"
rm -f "$WOO_STAGE/includes/class-sidrena-standalone.php"

(
  cd "$WORK"
  zip -qr "$OUTDIR/sidrena-wordpress-$VERSION.zip" sidrena-wordpress
  zip -qr "$OUTDIR/sidrena-woocommerce-$VERSION.zip" sidrena-woocommerce
)

sha256sum "$OUTDIR/sidrena-wordpress-$VERSION.zip" > "$OUTDIR/sidrena-wordpress-$VERSION.zip.sha256"
sha256sum "$OUTDIR/sidrena-woocommerce-$VERSION.zip" > "$OUTDIR/sidrena-woocommerce-$VERSION.zip.sha256"

echo "$WP_STAGE"
echo "$WOO_STAGE"
