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
import subprocess

root = Path(__file__).resolve().parents[1]
plugin = root / "wordpress/brendigo-sidrena-cijena"
android = root / "android"
desktop = root / "desktop"
issues = []
for folder in (plugin, android, desktop):
    if not folder.is_dir():
        issues.append(f"Missing platform: {folder.relative_to(root)}")
# Audit only files actually tracked by Git. Release jobs download already
# verified APK/AAB/EXE bundles into an untracked release/ directory.
# Including transient build outputs would mislabel legitimate artifacts as
# accidentally committed executable binaries.
tracked_files = subprocess.check_output(
    ["git", "ls-files", "-z"], cwd=root
).decode("utf-8").split("\0")
for relative in tracked_files:
    if not relative:
        continue
    path = root / relative
    if not path.is_file():
        continue
    if path.name.lower().endswith((".jks", ".keystore", ".p12")):
        issues.append(f"Signing key tracked in source tree: {relative}")
    if path.name.lower().endswith((".apk", ".aab", ".exe")) and "branding" not in path.parts:
        issues.append(f"Build binary tracked in source tree: {relative}")

# Validate the changed official plugin with consistent version fields.
files = sorted(p for p in plugin.rglob('*') if p.is_file())
if len(files) < 129:
    issues.append('WordPress plugin files unexpectedly missing')
source_digest = sha256(''.join(
    f'{p.relative_to(plugin).as_posix()} {sha256(p.read_bytes()).hexdigest()}\n'
    for p in files
).encode()).hexdigest()
main_php = (plugin / 'brendigo-sidrena-cijena.php').read_text(encoding='utf-8')
readme = (plugin / 'readme.txt').read_text(encoding='utf-8')
plugin_version = '1.5.3'
if f' * Version: {plugin_version}\n' not in main_php or f'Stable tag: {plugin_version}\n' not in readme:
    issues.append('Plugin header and stable tag mismatch')
if f"BRENDIGO_SIDRENA_CIJENA_VERSION', '{plugin_version}'" not in main_php:
    issues.append('Plugin runtime version mismatch')
if not (plugin / 'includes/class-legal.php').is_file():
    issues.append('Missing legal contract')
if any(p.is_symlink() for p in plugin.rglob('*')):
    issues.append('Symlinks are forbidden in packaged plugin')
# Cross-platform release metadata must agree before expensive CI packaging.
import xml.etree.ElementTree as ET
csproj = root / "desktop/Sidrena.Desktop/Sidrena.Desktop.csproj"
desktop_version = ET.parse(csproj).getroot().findtext("./PropertyGroup/Version")
installer_text = (root / "desktop/installer/SIDRENA.iss").read_text(encoding="utf-8")
desktop_ci = (root / ".github/workflows/desktop-ci.yml").read_text(encoding="utf-8")
release_ci = (root / ".github/workflows/desktop-release.yml").read_text(encoding="utf-8")
android_gradle = (root / "android/app/build.gradle.kts").read_text(encoding="utf-8")
android_version_match = re.search(r'versionName\s*=\s*"([^"]+)"', android_gradle)
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
if ("wordpress_plugin_version=$pluginVersion" not in desktop_ci or
        "wordpress/brendigo-sidrena-cijena/brendigo-sidrena-cijena.php" not in desktop_ci or
        "wordpress/brendigo-sidrena-cijena/readme.txt" not in desktop_ci or
        "Stable tag:" not in desktop_ci):
    issues.append("Windows artifact WordPress metadata must derive from official plugin and verify stable tag")
if "wordpress_plugin_version='1.5.2'" in desktop_ci:
    issues.append("Stale hard-coded WordPress 1.5.2 in Windows release metadata")
if f"WORDPRESS_VERSION: '{plugin_version}'" not in release_ci:
    issues.append("WordPress version must match the current source")

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
