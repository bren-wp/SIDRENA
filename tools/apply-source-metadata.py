#!/usr/bin/env python3
# Sidrena source metadata manager.
# Author: Brendigo LTD Developer
# Author URI: https://brendigo.com/
# Plugin URI: https://sidrene-cijene.com.hr/
# Support: sidrena@brendigo.com

from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
AUTHOR = "Brendigo LTD Developer"
AUTHOR_URI = "https://brendigo.com/"
PLUGIN_URI = "https://sidrene-cijene.com.hr/"
SUPPORT = "sidrena@brendigo.com"

SKIP_DIRS = {".git"}
SKIP_FILES = {
    pathlib.Path("LICENSE"),
    pathlib.Path("editions/wordpress/readme.txt"),
    pathlib.Path("editions/woocommerce/readme.txt"),
}
TEXT_SUFFIXES = {".php", ".css", ".js", ".yml", ".yaml", ".sh", ".py", ".svg", ".md", ".txt", ".pot"}
ENTRYPOINTS = {
    pathlib.Path("editions/wordpress/sidrena-wordpress.php"),
    pathlib.Path("editions/woocommerce/sidrena-woocommerce.php"),
}

PHP_HEADER = """/**
 * Sidrena source file.
 *
 * @package Sidrena
 * @author Brendigo LTD Developer
 * @link https://sidrene-cijene.com.hr/
 * @see https://brendigo.com/
 */
"""

BLOCK_HEADER = """/**
 * Sidrena source file.
 * Author: Brendigo LTD Developer
 * Author URI: https://brendigo.com/
 * Plugin URI: https://sidrene-cijene.com.hr/
 * Support: sidrena@brendigo.com
 */
"""

HASH_HEADER = """# Sidrena source file.
# Author: Brendigo LTD Developer
# Author URI: https://brendigo.com/
# Plugin URI: https://sidrene-cijene.com.hr/
# Support: sidrena@brendigo.com
"""

MD_HEADER = """<!--
Sidrena source file.
Author: Brendigo LTD Developer
Author URI: https://brendigo.com/
Plugin URI: https://sidrene-cijene.com.hr/
Support: sidrena@brendigo.com
-->
"""

SVG_HEADER = """<!-- Sidrena source file | Author: Brendigo LTD Developer | Author URI: https://brendigo.com/ | Plugin URI: https://sidrene-cijene.com.hr/ | Support: sidrena@brendigo.com -->"""


def relative(path: pathlib.Path) -> pathlib.Path:
    return path.relative_to(ROOT)


def should_process(path: pathlib.Path) -> bool:
    rel = relative(path)
    if any(part in SKIP_DIRS for part in rel.parts):
        return False
    if len(rel.parts) >= 3 and rel.parts[0] == ".github" and rel.parts[1] == "workflows":
        return False
    if rel in SKIP_FILES:
        return False
    if path.suffix.lower() in TEXT_SUFFIXES:
        return True
    return path.name == ".gitignore"


def has_metadata(text: str) -> bool:
    head = "\n".join(text.splitlines()[:24])
    return AUTHOR in head and PLUGIN_URI in head and AUTHOR_URI in head


def apply_entrypoint(text: str) -> str:
    replacements = {
        r"(?m)^ \* Author:.*$": " * Author: Brendigo LTD Developer",
        r"(?m)^ \* Author URI:.*$": " * Author URI: https://brendigo.com/",
        r"(?m)^ \* Plugin URI:.*$": " * Plugin URI: https://sidrene-cijene.com.hr/",
    }
    for pattern, value in replacements.items():
        text = re.sub(pattern, value, text, count=1)

    if " * Author: Brendigo LTD Developer" not in text:
        raise RuntimeError("Plugin entrypoint is missing its Author field.")
    if " * Author URI: https://brendigo.com/" not in text:
        raise RuntimeError("Plugin entrypoint is missing its Author URI field.")
    if " * Plugin URI: https://sidrene-cijene.com.hr/" not in text:
        raise RuntimeError("Plugin entrypoint is missing its Plugin URI field.")
    return text


def add_after_shebang(text: str, header: str) -> str:
    if text.startswith("#!"):
        first, sep, rest = text.partition("\n")
        return first + "\n" + header + ("\n" if rest else "") + rest
    return header + "\n" + text


def apply_metadata(path: pathlib.Path, text: str) -> str:
    rel = relative(path)
    if rel in ENTRYPOINTS:
        return apply_entrypoint(text)
    if has_metadata(text):
        return text

    suffix = path.suffix.lower()
    if suffix == ".php":
        if not text.startswith("<?php"):
            raise RuntimeError(f"{rel}: PHP file does not start with <?php")
        return text.replace("<?php\n", "<?php\n" + PHP_HEADER + "\n", 1)

    if suffix in {".css", ".js"}:
        return BLOCK_HEADER + "\n" + text

    if suffix in {".yml", ".yaml"} or path.name == ".gitignore":
        return HASH_HEADER + "\n" + text

    if suffix in {".sh", ".py"}:
        return add_after_shebang(text, HASH_HEADER)

    if suffix == ".svg":
        if text.startswith("<?xml"):
            first, sep, rest = text.partition("\n")
            return first + "\n" + SVG_HEADER + ("\n" if rest else "") + rest
        return SVG_HEADER + "\n" + text

    if suffix == ".md":
        return MD_HEADER + "\n" + text

    if suffix == ".pot":
        return HASH_HEADER + "\n" + text

    if suffix == ".txt":
        return (
            "Sidrena source file\n"
            "Author: Brendigo LTD Developer\n"
            "Author URI: https://brendigo.com/\n"
            "Plugin URI: https://sidrene-cijene.com.hr/\n"
            "Support: sidrena@brendigo.com\n\n"
            + text
        )

    return text


def scan(check: bool) -> int:
    changed = []
    missing = []

    for path in sorted(ROOT.rglob("*")):
        if not path.is_file() or not should_process(path):
            continue

        text = path.read_text(encoding="utf-8")
        updated = apply_metadata(path, text)

        if updated != text:
            if check:
                missing.append(str(relative(path)))
            else:
                path.write_text(updated, encoding="utf-8")
                changed.append(str(relative(path)))

    if check and missing:
        print("Missing/outdated Sidrena source metadata:", file=sys.stderr)
        for item in missing:
            print(f" - {item}", file=sys.stderr)
        return 1

    if changed:
        print("Updated Sidrena source metadata:")
        for item in changed:
            print(f" - {item}")
    else:
        print("Sidrena source metadata is up to date.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    return scan(args.check)


if __name__ == "__main__":
    raise SystemExit(main())
