#!/usr/bin/env python3
"""Conservative Android source/security preflight. Lint/compile remains authoritative."""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1] / "android"
sources = list((root / "app/src/main").rglob("*.kt"))
tests = list((root / "app/src/test").rglob("*.kt"))
manifest = root / "app/src/main/AndroidManifest.xml"
issues = []
if not sources or not tests:
    issues.append("Missing Android application sources or unit tests")
if manifest.exists():
    xml = manifest.read_text(encoding="utf-8")
    if 'android:usesCleartextTraffic="false"' not in xml:
        issues.append("Cleartext traffic must be disabled")
    if "android.permission.INTERNET" not in xml:
        issues.append("Missing network permission for REST reader")
    if "android:allowBackup=\"false\"" not in xml:
        issues.append("Backups must stay disabled until reviewed")
else:
    issues.append("Missing Android manifest")
code = "\n".join(p.read_text(encoding="utf-8") for p in sources)
for pattern, label in (
    (r"(?i)http://[^\s\"']+", "Potential HTTP endpoint"),
    (r"(?i)(?:password|secret|api_key)\s*=\s*\"[^\"]+\"", "Possible embedded credential"),
    (r"android\.webkit\.WebView", "Native-only policy violated"),
    (r"Log\.[devwi]\s*\(", "Potential logging of user data"),
):
    if re.search(pattern, code):
        issues.append(label)
# Flag, but do not delete, private declarations that appear only once across Kotlin sources.
# Compose entry points, overrides and reflection must be confirmed by Android lint/compilation.
identifiers = re.findall(r"\bprivate\s+(?:suspend\s+)?fun\s+(\w+)\s*\(", code)
for name in set(identifiers):
    if len(re.findall(r"\b" + re.escape(name) + r"\b", code)) == 1:
        issues.append(f"Review possibly unused private method: {name}")
print(f"Android preflight: {len(sources)} Kotlin app files, {len(tests)} test files")
for issue in issues:
    print("ISSUE:", issue)
if issues:
    sys.exit(1)
print("PASS: source-level preflight (not a replacement for Android lint or security review)")
