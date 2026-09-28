#!/usr/bin/env python3
# Sidrena source file.
# Author: brendigo
# Author URI: https://brendigo.com/
# Plugin URI: https://brendigo.com/sidrene-cijene/
# Support: sidrena@brendigo.com

"""Render and preflight a SIDRENA end-user PDF manual for release QA."""

from __future__ import annotations

import argparse
import json
import re
import subprocess
from pathlib import Path

from PIL import Image


def run(*args: str) -> str:
    completed = subprocess.run(
        args,
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    return completed.stdout


def natural_page_key(path: Path) -> int:
    match = re.search(r"-(\d+)\.png$", path.name)
    return int(match.group(1)) if match else 0


def edge_dark_ratio(image: Image.Image) -> float:
    gray = image.convert("L")
    width, height = gray.size
    strip = max(2, min(width, height) // 500)
    pixels = []
    pixels.extend(gray.crop((0, 0, width, strip)).getdata())
    pixels.extend(gray.crop((0, height - strip, width, height)).getdata())
    pixels.extend(gray.crop((0, strip, strip, height - strip)).getdata())
    pixels.extend(gray.crop((width - strip, strip, width, height - strip)).getdata())
    if not pixels:
        return 0.0
    return sum(1 for value in pixels if value < 235) / len(pixels)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--pdf", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--label", required=True)
    args = parser.parse_args()

    pdf = Path(args.pdf)
    output = Path(args.output_dir)
    output.mkdir(parents=True, exist_ok=True)

    if not pdf.is_file() or pdf.stat().st_size < 10_000:
        raise SystemExit(f"{args.label}: PDF is missing or unexpectedly small: {pdf}")

    info = run("pdfinfo", str(pdf))
    (output / "pdfinfo.txt").write_text(info, encoding="utf-8")
    match = re.search(r"^Pages:\s+(\d+)\s*$", info, flags=re.MULTILINE)
    if not match:
        raise SystemExit(f"{args.label}: could not determine PDF page count")
    pages = int(match.group(1))
    if pages < 8 or pages > 250:
        raise SystemExit(f"{args.label}: unexpected manual page count: {pages}")

    text_path = output / "manual.txt"
    subprocess.run(
        ["pdftotext", "-layout", str(pdf), str(text_path)],
        check=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.PIPE,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    extracted = text_path.read_text(encoding="utf-8", errors="replace")
    lowered = extracted.lower()
    for character in ("č", "ć", "ž", "š", "đ"):
        if character not in lowered:
            raise SystemExit(f"{args.label}: Croatian character {character!r} is missing from extracted PDF text")
    for required in ("sidrena@brendigo.com", "80 EUR", "sidrena"):
        if required.lower() not in lowered:
            raise SystemExit(f"{args.label}: required manual text is missing: {required}")

    prefix = output / "page"
    subprocess.run(
        ["pdftoppm", "-png", "-r", "130", str(pdf), str(prefix)],
        check=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.PIPE,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    pngs = sorted(output.glob("page-*.png"), key=natural_page_key)
    if len(pngs) != pages:
        raise SystemExit(f"{args.label}: rendered {len(pngs)} pages but PDF reports {pages}")

    expected_size = None
    page_reports = []
    for index, png in enumerate(pngs, start=1):
        with Image.open(png) as image:
            image.load()
            width, height = image.size
            if width < 900 or height < 1200:
                raise SystemExit(f"{args.label}: page {index} render is unexpectedly small: {width}x{height}")
            if expected_size is None:
                expected_size = (width, height)
            elif (width, height) != expected_size:
                raise SystemExit(
                    f"{args.label}: inconsistent page dimensions on page {index}: "
                    f"{width}x{height} != {expected_size[0]}x{expected_size[1]}"
                )

            gray = image.convert("L")
            histogram = gray.histogram()
            nonwhite = sum(histogram[:248])
            total = width * height
            content_ratio = nonwhite / total
            if content_ratio < 0.001:
                raise SystemExit(f"{args.label}: page {index} appears blank ({content_ratio:.5f} content ratio)")

            edge_ratio = edge_dark_ratio(image)
            if edge_ratio > 0.60:
                raise SystemExit(
                    f"{args.label}: page {index} has excessive content on the outer page edge "
                    f"({edge_ratio:.3f}); possible clipping/full-page render error"
                )

            page_reports.append(
                {
                    "page": index,
                    "width": width,
                    "height": height,
                    "content_ratio": round(content_ratio, 6),
                    "edge_dark_ratio": round(edge_ratio, 6),
                    "png": png.name,
                }
            )

        page_text = run("pdftotext", "-f", str(index), "-l", str(index), str(pdf), "-")
        if len(re.sub(r"\s+", "", page_text)) < 20:
            raise SystemExit(f"{args.label}: page {index} contains too little extractable text; possible blank/broken page")

    report = {
        "label": args.label,
        "pdf": pdf.name,
        "pages": pages,
        "render_dpi": 130,
        "page_size": expected_size,
        "checks": {
            "croatian_characters": True,
            "required_support_text": True,
            "nonblank_pages": True,
            "consistent_dimensions": True,
            "gross_edge_clipping": True,
        },
        "pages_detail": page_reports,
        "manual_review_required": "Inspect rendered PNG pages for overlaps, broken bullets, typography, page breaks and URL presentation.",
    }
    (output / "qa-report.json").write_text(
        json.dumps(report, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    print(f"{args.label}: PDF visual preflight passed ({pages} pages rendered to {output})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
