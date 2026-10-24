#!/usr/bin/env python3
"""Reproducible cross-platform SIDRENA security / dead-code preflight.

Catches concrete regressions and reports suspicious unused names. It is not a
taint-analysis engine and never deletes dynamically referenced WordPress hooks.
"""
from collections import Counter
from pathlib import Path
import json
import re
import sys

ROOT=Path(__file__).resolve().parents[1]
WP=ROOT/"wordpress/brendigo-sidrena-cijena"
ANDROID=ROOT/"android/app/src/main"
WIN=ROOT/"desktop"
errors=[]
warnings=[]

def read(path):
    return (ROOT/path).read_text(encoding="utf-8-sig")

main=read("wordpress/brendigo-sidrena-cijena/brendigo-sidrena-cijena.php")
readme=read("wordpress/brendigo-sidrena-cijena/readme.txt")
api=read("wordpress/brendigo-sidrena-cijena/includes/class-public-api.php")
legal=read("wordpress/brendigo-sidrena-cijena/includes/class-legal.php")
android=read("android/app/src/main/java/com/brendigo/sidrena/data/LegalContract.kt")
win=read("desktop/Sidrena.Core/OfficialLegalContract.cs")
guard=read("android/app/src/main/java/com/brendigo/sidrena/data/PublicNetworkGuard.kt")
network=read("desktop/Sidrena.Core/OfficialPluginDiscovery.cs")
manifest=read("android/app/src/main/AndroidManifest.xml")
release=read(".github/workflows/desktop-release.yml")

for version in ("1.5.2",):
    if "Version: "+version not in main or "Stable tag: "+version not in readme:
        errors.append("Mismatch in official WordPress version")
for token in ("2026-11-17","2025-05-02","2026-09-10"):
    if any(token not in source for source in (legal,android,win)):
        errors.append("Cross-platform legal date drift: "+token)
for token in ("NN-101-2026-NN-110-2026", "NN-59-2026", "NN-105-2026"):
    if any(token not in source for source in (legal,android,win)):
        errors.append("Cross-platform rule revision drift")
if "const ARCHIVE_MIN_DAYS = 30" not in legal:
    errors.append("WordPress archive legal minimum changed unexpectedly")
if "'permission_callback' => '__return_true'" not in api and "'permission_callback' => '__return_true'" not in api.replace("             "," "):
    warnings.append("Confirm public REST permission callbacks are read-only")
if "WP_REST_Server::CREATABLE" in api or "WP_REST_Server::EDITABLE" in api or "WP_REST_Server::DELETABLE" in api:
    errors.append("Unexpected writable endpoint in public REST API")
if "usesCleartextTraffic=\"false\"" not in manifest or "allowBackup=\"false\"" not in manifest:
    errors.append("Android transport / backup policy not enforced")
android_network=read("android/app/src/main/java/com/brendigo/sidrena/data/CatalogRepository.kt")
if not all(token in android_network for token in (
    "Dns.SYSTEM.lookup(hostname)", "PublicNetworkGuard::isPublic",
    ".followRedirects(false)", ".proxy(Proxy.NO_PROXY)",
)):
    errors.append("Android socket-bound DNS filtering, redirects or proxy policy missing")
# Require identical versioned, read-only discovery checks on both clients.
android_discovery=read("android/app/src/main/java/com/brendigo/sidrena/data/CatalogDiscovery.kt")
if not all(token in android_discovery for token in (
    'optInt("api_version", -1) == 1', "seen.add(code.lowercase())",
    "rawOutlets.length() <= MAX_OUTLETS", "site.approved(csv)",
)):
    errors.append("Android official API contract version, outlet or same-origin checks missing")
if 'root.TryGetProperty("api_version"' not in network or "version!=1" not in network:
    errors.append("Windows official API v1 validation missing")
if "ConnectCallback=PublicNetworkAddress.ConnectAsync" not in network:
    errors.append("Windows socket-level public IP validation missing")
if "ORIGINAL_PLUGIN_SHA" not in release or "sha256sum -c" not in release:
    errors.append("Release missing baseline fingerprint or artifact verification")
if "gh release create" not in release:
    errors.append("Cross-platform release creation missing")

candidate_patterns=[
    (r"\beval\s*\(", "PHP eval"),
    (r"\bshell_exec\s*\(", "PHP shell command"),
    (r"\b(?:passthru|system|exec)\s*\(", "Potential PHP command execution"),
]
php_sources=list(WP.rglob("*.php"))
for path in php_sources:
    source=path.read_text(encoding="utf-8-sig")
    for pattern,label in candidate_patterns:
        if re.search(pattern,source):
            warnings.append(f"Review {label}: {path.relative_to(ROOT)}")
    # A text-only scan can miss reflection and hook registrations; candidates
    # are intentionally warnings, never automatically deleted.
    if re.search(r'\$_(?:GET|POST|REQUEST)\[',source) and not any(
        term in source for term in ('sanitize_', 'wp_unslash', 'esc_', 'absint', 'intval', 'nonce')
    ):
        warnings.append(f"Review raw input handling: {path.relative_to(ROOT)}")

for extension,folder,pattern in (
    (".cs",WIN,r"\bprivate\s+(?:(?:static|readonly|async|sealed)\s+)*[\w<>,?]+\s+(\w+)\s*\("),
    (".kt",ANDROID,r"\bprivate\s+(?:suspend\s+)?fun\s+(\w+)\s*\("),
):
    sources=[p for p in folder.rglob("*") if p.suffix==extension and not any(x in p.parts for x in ("obj","bin","build"))]
    texts={p:p.read_text(encoding="utf-8-sig") for p in sources}
    merged="\\n".join(texts.values())
    # WPF event handlers are referenced through XAML attribute names rather
    # than ordinary C# call sites; do not misclassify them as dead code.
    if extension==".cs":
        merged+="\\n"+"\\n".join(
            p.read_text(encoding="utf-8-sig") for p in folder.rglob("*.xaml")
        )
    count=Counter(re.findall(pattern,merged))
    for name,declared in count.items():
        if len(re.findall(r"\b"+re.escape(name)+r"\b",merged))<=declared:
            warnings.append(f"Potential dead {extension} private method {name}: check hooks/reflection")

result={
    "php_files_checked":len(php_sources),
    "android_sources_checked":len(list(ANDROID.rglob("*.kt"))),
    "windows_sources_checked":len([p for p in WIN.rglob("*.cs") if "obj" not in p.parts]),
    "errors":errors,
    "review_candidates":warnings[:150],
    "limitations":"Static heuristics, not a penetration test or full cross-platform dead-code proof.",
}
print(json.dumps(result,ensure_ascii=False,indent=2))
sys.exit(1 if errors else 0)
