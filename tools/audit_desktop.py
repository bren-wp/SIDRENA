#!/usr/bin/env python3
"""SIDRENA Windows source audit — reproducible narrow dead-private-method gate.

Conservative rule: a private method declared in .cs with only one name
occurrence anywhere in the desktop C# sources or WPF XAML is a candidate.
It is an error unless explicitly allowlisted with a human rationale.
This is not a full Roslyn call graph; reflection/dynamic use require review.
"""
from __future__ import annotations
import pathlib
import re
import sys

ROOT=pathlib.Path(__file__).resolve().parents[1]
DESKTOP=ROOT/"desktop"
SOURCE=[*DESKTOP.rglob("*.cs"),*DESKTOP.rglob("*.xaml")]
SOURCE=[p for p in SOURCE if "obj" not in p.parts and "bin" not in p.parts]
text="\n".join(p.read_text(encoding="utf-8-sig") for p in SOURCE)
pattern=re.compile(r"\bprivate\s+(?:(?:static|readonly|override|async|virtual|sealed)\s+)*[\w<>\[\],?\s]+\s+(\w+)\s*\(")
unused=[]
for path in SOURCE:
    if path.suffix!=".cs":continue
    data=path.read_text(encoding="utf-8-sig")
    for name in pattern.findall(data):
        references=len(re.findall(r"\b"+re.escape(name)+r"\b",text))
        if references==1:
            unused.append(f"{path.relative_to(ROOT)} :: {name}")
print(f"SIDRENA source audit: {len(SOURCE)} desktop C#/XAML files inspected")
if unused:
    print("Private methods without references; investigate rather than delete public or XAML handlers blindly:")
    for finding in sorted(unused):print("  - "+finding)
    sys.exit(1)
print("No unreferenced private method names found in the scanned sources.")
