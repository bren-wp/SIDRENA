#!/usr/bin/env python3
"""Focused regression checks for Plugin Check findings reported against SIDRENA."""

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
PHP_FILES = list((ROOT / "includes").glob("*.php")) + list((ROOT / "editions").glob("*/*.php")) + [ROOT / "uninstall.php"]

def fail(message: str) -> None:
    raise SystemExit(message)

joined = "\n".join(p.read_text(encoding="utf-8") for p in PHP_FILES if p.exists())

if "load_plugin_textdomain(" in joined:
    fail("Discouraged manual load_plugin_textdomain() call returned.")

public = (ROOT / "includes/class-sidrena-public.php").read_text(encoding="utf-8")
if 'href="<?php echo $url; ?>"' in public:
    fail("Unescaped public URL output returned.")
if "esc_url( $url )" not in public:
    fail("Expected esc_url() protection for public download URL is missing.")

uninstall = (ROOT / "uninstall.php").read_text(encoding="utf-8")
if "DROP TABLE IF EXISTS %i" not in uninstall:
    fail("Identifier placeholder is missing from destructive uninstall SQL.")

# Translators comments must never become visible HTML artifacts.
for path in (ROOT / "includes").glob("*.php"):
    lines = path.read_text(encoding="utf-8").splitlines()
    in_php = True
    for number, line in enumerate(lines, 1):
        stripped = line.strip()
        if stripped.startswith("/* translators:") and not in_php:
            fail(f"Translator comment outside PHP: {path}:{number}")
        pos = 0
        while pos < len(line):
            opening = line.find("<?php", pos)
            closing = line.find("?>", pos)
            if opening < 0 and closing < 0:
                break
            if opening >= 0 and (closing < 0 or opening < closing):
                in_php = True
                pos = opening + 5
            else:
                in_php = False
                pos = closing + 2

# Catch the exact interpolated custom-table patterns from the supplied report.
bad_sql = re.compile(r"(?:FROM|JOIN|UPDATE|INTO|TABLE|DELETE\s+FROM)\s+\{\$(?:table|product_table|service_table|location_table)\}")
for path in (ROOT / "includes").glob("*.php"):
    source = path.read_text(encoding="utf-8")
    if bad_sql.search(source):
        fail(f"Interpolated custom-table identifier remains in {path}")

print("SIDRENA Plugin Check regression guard passed.")
