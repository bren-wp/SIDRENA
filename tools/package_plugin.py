#!/usr/bin/env python3
"""Deterministically assemble the official SIDRENA WordPress plugin ZIP.

Build only from tracked plugin source, with a stable entry ordering and timestamp.
Never follow symlinks. Generated package is independently verified by CI.
"""
from pathlib import Path
from hashlib import sha256
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import argparse
import re

def package(root: Path, output: Path, version: str) -> str:
    if not re.fullmatch(r"\d+\.\d+\.\d+", version):
        raise ValueError("Neispravna WordPress verzija")
    source = root / "wordpress" / "brendigo-sidrena-cijena"
    header = (source / "brendigo-sidrena-cijena.php").read_text(encoding="utf-8")
    readme = (source / "readme.txt").read_text(encoding="utf-8")
    if f" * Version: {version}\n" not in header or f"Stable tag: {version}\n" not in readme:
        raise ValueError("WordPress oznake verzije nisu usklađene")
    files = sorted(p for p in source.rglob("*") if p.is_file())
    if len(files) < 120:
        raise ValueError("Paket dodatka je neočekivano nepotpun")
    if any(p.is_symlink() for p in source.rglob("*")):
        raise ValueError("Simboličke poveznice nisu dopuštene u paketu")
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output, "w", ZIP_DEFLATED, compresslevel=9) as zipout:
        for path in files:
            relative = "brendigo-sidrena-cijena/" + path.relative_to(source).as_posix()
            info = ZipInfo(relative, date_time=(2026, 1, 1, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            zipout.writestr(info, path.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)
    with ZipFile(output) as check:
        assert len(check.namelist()) == len(files)
        assert check.testzip() is None
    digest = sha256(output.read_bytes()).hexdigest()
    checksum = output.with_suffix(output.suffix + ".sha256")
    checksum.write_text(f"{digest}  {output.name}\n", encoding="utf-8")
    return digest

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--version", required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    print("Plugin SHA-256:", package(Path(__file__).resolve().parents[1], args.output, args.version))
