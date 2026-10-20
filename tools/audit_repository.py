#!/usr/bin/env python3
"""Conservative cross-platform SIDRENA source/package audit.

This does not auto-delete suspect code: WordPress hooks, shortcodes and
reflection often contain legitimately non-textual call sites.
"""
from collections import Counter
from hashlib import sha256
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
plugin = root / "wordpress/brendigo-sidrena-cijena"
android = root / "android"
desktop = root / "desktop"
issues = []
for folder in (plugin, android, desktop):
    if not folder.is_dir():
        issues.append(f"Missing platform: {folder.relative_to(root)}")
for path in root.rglob("*"):
    if not path.is_file() or ".git" in path.parts:
        continue
    if path.name.endswith((".jks", ".keystore", ".p12")):
        issues.append(f"Signing key in repository: {path.relative_to(root)}")
    if path.name.endswith((".apk", ".aab", ".exe")) and "branding" not in path.parts:
        issues.append(f"Build binary in source tree: {path.relative_to(root)}")

# Verify that the official WordPress 1.5.0 source remains byte-for-byte
# consistent with the maintained plugin validation workflow.
files = sorted(p for p in plugin.rglob("*") if p.is_file())
if len(files) != 128:
    issues.append(f"Expected 128 official WordPress files, got {len(files)}")
source_digest = sha256("".join(
    f"{p.relative_to(plugin).as_posix()} {sha256(p.read_bytes()).hexdigest()}\n"
    for p in files
).encode()).hexdigest()
expected = "44b6ff17478f7769d9bda8157020269247f9dad2c186d92b82ddfbd21e996387"
if source_digest != expected:
    issues.append("Official WordPress source differs from the pinned 1.5.0 package.")
if not (plugin / "brendigo-sidrena-cijena.php").exists():
    issues.append("Official WordPress plugin entrypoint missing")

# Cross-platform release metadata must agree before expensive CI packaging.
import xml.etree.ElementTree as ET
csproj = root / "desktop/Sidrena.Desktop/Sidrena.Desktop.csproj"
desktop_version = ET.parse(csproj).getroot().findtext("./PropertyGroup/Version")
installer_text = (root / "desktop/installer/SIDRENA.iss").read_text(encoding="utf-8")
desktop_ci = (root / ".github/workflows/desktop-ci.yml").read_text(encoding="utf-8")
release_ci = (root / ".github/workflows/desktop-release.yml").read_text(encoding="utf-8")
android_gradle = (root / "android/app/build.gradle.kts").read_text(encoding="utf-8")
android_version_match = re.search(r'versionName\\s*=\\s*"([^"]+)"', android_gradle)
android_version = android_version_match.group(1) if android_version_match else None
if not desktop_version:
    issues.append("Missing Windows project version")
else:
    if f'#define DesktopVersion "{desktop_version}"' not in installer_text:
        issues.append("Windows installer version does not match project version")
    if f"WINDOWS_VERSION: '{desktop_version}'" not in release_ci:
        issues.append("Windows release version does not match project version")
if not android_version or f"ANDROID_VERSION: '{android_version}'" not in release_ci:
    issues.append("Android release version does not match Gradle versionName")
if "desktop_version=$desktopVersion" not in desktop_ci or "$desktopProject.Project.PropertyGroup.Version" not in desktop_ci:
    issues.append("Windows artifact manifest must derive version from desktop project")
if "WORDPRESS_VERSION: '1.5.0'" not in release_ci:
    issues.append("WordPress source must keep pinned version 1.5.0")

# Surface candidate duplicated class names without modifying any code.
for extension, roots, pattern in (
    (".cs", [desktop], r"\b(?:class|record|struct)\s+([A-Z]\w+)"),
    (".kt", [android], r"\b(?:class|object|interface)\s+([A-Z]\w+)"),
):
    names = Counter()
    for source in (p for folder in roots for p in folder.rglob("*") if p.suffix == extension and p.is_file()):
        if any(part in {"obj", "bin", "build", ".gradle"} for part in source.parts):
            continue
        names.update(re.findall(pattern, source.read_text(encoding="utf-8-sig")))
    for name, count in names.items():
        if count > 1:
            print(f"REVIEW: repeated declaration name {name} ({count} occurrences); may belong to different scopes")

print(f"SIDRENA source preflight: plugin files={len(files)}, WordPress digest={source_digest}")
if issues:
    for issue in issues:
        print("ERROR:", issue)
    sys.exit(1)
print("PASS: preserved WordPress plugin and no signed/private build artifacts checked into source")
