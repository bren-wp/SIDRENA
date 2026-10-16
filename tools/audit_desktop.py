#!/usr/bin/env python3
"""SIDRENA desktop privacy, resource and dead-code preliminary audit.

Cross-reference all WPF XAML and C# sources for unreferenced private
methods, underscore-prefixed fields and nested private types.
Fail on raw exception leakage and prohibited developer-facing copy.
Complements (does not replace) Roslyn code-style semantic analysis.
"""
from __future__ import annotations
import pathlib
import re
import sys

ROOT=pathlib.Path(__file__).resolve().parents[1]
SOURCE_ROOT=ROOT/"desktop"
FILES=sorted(
    p for p in SOURCE_ROOT.rglob("*")
    if p.is_file() and p.suffix in {".cs",".xaml"}
    and not any(part in {"obj","bin","generated"} for part in p.parts)
)
contents={p:p.read_text(encoding="utf-8-sig") for p in FILES}
combined="\n".join(contents.values())
findings=[]

# References in source code, WPF handlers, generated binding declarations
# and strings are intentionally included: avoid deleting reflection/XAML targets.
checks={
    "private method": re.compile(
        r"\bprivate\s+(?:(?:static|readonly|override|async|virtual|sealed)\s+)*"
        r"[\w<>\[\],?\s]+\s+(\w+)\s*\("),
    "private field": re.compile(
        r"\bprivate\s+(?:(?:static|readonly|const|volatile)\s+)*"
        r"[\w<>\[\],?\.]+\s+(_\w+)\s*(?:[=;])"),
    "private nested type": re.compile(
        r"\bprivate\s+(?:(?:static|sealed|readonly)\s+)*"
        r"(?:record(?:\s+struct)?|class|struct|enum)\s+(\w+)")
}
for kind,pattern in checks.items():
    for path,data in contents.items():
        if path.suffix!=".cs":
            continue
        for name in pattern.findall(data):
            # Refuse to remove anything automatically; fail and request review.
            count=len(re.findall(r"\b"+re.escape(name)+r"\b",combined))
            if count<=1:
                findings.append(
                    f"Unreferenced {kind}: {path.relative_to(ROOT)} :: {name}")

ui_cs=contents.get(SOURCE_ROOT/"Sidrena.Desktop"/"MainWindow.xaml.cs","")
ui_xaml=contents.get(SOURCE_ROOT/"Sidrena.Desktop"/"MainWindow.xaml","")
for text in (ui_cs,ui_xaml):
    for forbidden in (
        "SQLite katalog nalazi se",
        "Lokalni katalog i radni cjenici",
        "Lokalna baza spremna",
        "Javni zakonski serializer",
        "Otvori mapu podataka",
        "Bit će omogućena nakon implementacije",
    ):
        if forbidden in text:
            findings.append(f"Developer-facing placeholder string: {forbidden}")
if re.search(r"\b(?:ex|exception)\.Message\b",ui_cs):
    findings.append("Raw exception.Message is used in a Windows UI code path")
if not (SOURCE_ROOT/"Sidrena.Core"/"UserFacingError.cs").exists():
    findings.append("User-facing error redaction policy is missing")
if not (SOURCE_ROOT/"Sidrena.Core"/"DocumentOpenPolicy.cs").exists():
    # DocumentOpenPolicy is currently in UserFacingError.cs.
    if "class DocumentOpenPolicy" not in contents.get(
            SOURCE_ROOT/"Sidrena.Core"/"UserFacingError.cs",""):
        findings.append("Document-launch policy is missing")
if "DocumentOpenPolicy.Validate(path)" not in ui_cs:
    findings.append("Local document launch bypasses extension allowlist")

print("SIDRENA desktop code audit")
print(f"  Source/XAML files: {len(FILES)}")
print("  Scope: private declarations, WPF named handlers, raw UI errors, dev copy and document launch")
print("  Semantic analysis: CI also runs Roslyn unused-member rules for Sidrena.Core")
if findings:
    for issue in sorted(set(findings)):
        print("ERROR:",issue)
    sys.exit(1)
print("PASS: no obvious unreferenced private declarations or forbidden customer-facing internals")
