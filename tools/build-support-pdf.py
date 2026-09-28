#!/usr/bin/env python3
# Sidrena source file.
# Author: brendigo
# Author URI: https://brendigo.com/
# Plugin URI: https://brendigo.com/sidrene-cijene/
# Support: sidrena@brendigo.com

import os
import re
import sys
from xml.sax.saxutils import escape

from reportlab import rl_config
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import PageBreak, Paragraph, Preformatted, SimpleDocTemplate, Spacer, Table, TableStyle

rl_config.invariant = 1

if len(sys.argv) != 5:
    raise SystemExit(
        "Usage: build-support-pdf.py <version> <output.pdf> <wordpress|woocommerce> <guide.md>"
    )

version = sys.argv[1].strip()
out = sys.argv[2]
edition = sys.argv[3].strip().lower()
guide_path = sys.argv[4]

if not version:
    raise SystemExit("Sidrena version is required.")
if edition not in {"wordpress", "woocommerce"}:
    raise SystemExit("Edition must be wordpress or woocommerce.")
if not os.path.isfile(guide_path):
    raise SystemExit("Prepared edition guide is required.")

os.makedirs(os.path.dirname(out) or ".", exist_ok=True)

font_pairs = [
    (
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    ),
    (
        "/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf",
        "/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf",
    ),
]
regular = bold = None
for regular_path, bold_path in font_pairs:
    if os.path.isfile(regular_path) and os.path.isfile(bold_path):
        regular, bold = regular_path, bold_path
        break
if not regular:
    raise SystemExit("A Unicode DejaVu/Noto Sans font is required to build the support PDF.")

pdfmetrics.registerFont(TTFont("SidrenaSans", regular))
pdfmetrics.registerFont(TTFont("SidrenaSansBold", bold))

NAVY = colors.HexColor("#0B2B45")
TEXT = colors.HexColor("#202833")
MUTED = colors.HexColor("#5E6B79")
LINE = colors.HexColor("#D8E0E8")
SOFT = colors.HexColor("#F4F7FA")
ORANGE = colors.HexColor("#FF661F")
GREEN = colors.HexColor("#218D65")
DARK = colors.HexColor("#384552")
WOO = colors.HexColor("#7F54B3")
ACCENT = WOO if edition == "woocommerce" else colors.HexColor("#1677FF")

edition_label = (
    "brendigo SIDRENA - sidrene cijene i cjenici za WooCommerce"
    if edition == "woocommerce"
    else "brendigo SIDRENA - sidrene cijene i digitalni cjenici"
)
edition_description = (
    "Izdanje za hrvatske web trgovine koje koristi postojeće proizvode i varijacije iz WooCommerce kataloga."
    if edition == "woocommerce"
    else "Samostalno izdanje za hrvatske WordPress stranice s vlastitim SIDRENA katalogom proizvoda i usluga."
)

base = ParagraphStyle(
    "base",
    fontName="SidrenaSans",
    fontSize=9.3,
    leading=13.2,
    textColor=TEXT,
    spaceAfter=5,
)
h1 = ParagraphStyle(
    "h1",
    fontName="SidrenaSansBold",
    fontSize=23,
    leading=27,
    textColor=NAVY,
    spaceAfter=8,
)
h2 = ParagraphStyle(
    "h2",
    fontName="SidrenaSansBold",
    fontSize=14,
    leading=17.5,
    textColor=NAVY,
    spaceBefore=8,
    spaceAfter=6,
    keepWithNext=True,
)
h3 = ParagraphStyle(
    "h3",
    fontName="SidrenaSansBold",
    fontSize=11.2,
    leading=14.2,
    textColor=ACCENT,
    spaceBefore=5,
    spaceAfter=4,
    keepWithNext=True,
)
h4 = ParagraphStyle(
    "h4",
    fontName="SidrenaSansBold",
    fontSize=9.8,
    leading=12.5,
    textColor=DARK,
    spaceBefore=4,
    spaceAfter=3,
    keepWithNext=True,
)
kicker = ParagraphStyle(
    "kicker",
    fontName="SidrenaSansBold",
    fontSize=8.1,
    leading=10,
    textColor=MUTED,
    spaceAfter=5,
)
small = ParagraphStyle(
    "small",
    parent=base,
    fontSize=8.0,
    leading=10.7,
    textColor=MUTED,
)
label = ParagraphStyle(
    "label",
    parent=base,
    fontName="SidrenaSansBold",
    fontSize=8.1,
    leading=10.5,
    textColor=MUTED,
)
value_style = ParagraphStyle(
    "value",
    parent=base,
    fontName="SidrenaSansBold",
    fontSize=9.4,
    leading=12,
    textColor=NAVY,
)
bullet = ParagraphStyle(
    "bullet",
    parent=base,
    leftIndent=12,
    firstLineIndent=-7,
    spaceAfter=2,
)
numbered = ParagraphStyle(
    "numbered",
    parent=base,
    leftIndent=15,
    firstLineIndent=-10,
    spaceAfter=2,
)
quote = ParagraphStyle(
    "quote",
    parent=base,
    leftIndent=10,
    rightIndent=6,
    textColor=MUTED,
    borderColor=ACCENT,
    borderWidth=1,
    borderPadding=(4, 6, 4, 8),
    spaceBefore=3,
    spaceAfter=6,
)
code_style = ParagraphStyle(
    "code",
    fontName="SidrenaSans",
    fontSize=7.8,
    leading=10.3,
    textColor=DARK,
    backColor=SOFT,
    borderColor=LINE,
    borderWidth=0.5,
    borderPadding=6,
    leftIndent=4,
    rightIndent=4,
    spaceBefore=3,
    spaceAfter=6,
)
button = ParagraphStyle(
    "button",
    fontName="SidrenaSansBold",
    fontSize=9.1,
    leading=12,
    alignment=TA_CENTER,
    textColor=colors.white,
)


def inline_markup(text):
    value = escape(str(text), {"'": "&#39;", '"': "&quot;"})
    value = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", value)
    value = re.sub(r"\x60([^\x60]+)\x60", r'<font name="SidrenaSans">\1</font>', value)

    def link_replace(match):
        label_text = match.group(1)
        url = match.group(2)
        return f'<link href="{url}" color="#0B2B45"><u>{label_text}</u></link>'

    value = re.sub(r"\[([^\]]+)\]\((https?://[^)]+)\)", link_replace, value)
    return value


def paragraph(text, style=base):
    return Paragraph(inline_markup(text), style)


def link(text, url, style=value_style):
    return Paragraph(
        f'<link href="{escape(url)}" color="#0B2B45"><u>{escape(text)}</u></link>',
        style,
    )


def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.5)
    canvas.line(18 * mm, 13.5 * mm, 192 * mm, 13.5 * mm)
    canvas.setFont("SidrenaSans", 7.2)
    canvas.setFillColor(MUTED)
    canvas.drawString(18 * mm, 8 * mm, f"{edition_label} {version} - brendigo")
    canvas.drawRightString(192 * mm, 8 * mm, f"Stranica {doc.page}")
    canvas.restoreState()


def button_link(text, url):
    return Paragraph(
        f'<link href="{escape(url)}" color="#FFFFFF"><b>{escape(text)}</b></link>',
        button,
    )


def support_buttons():
    table = Table(
        [
            [
                button_link("Pošalji e-mail", "mailto:sidrena@brendigo.com"),
                button_link("Otvori WhatsApp", "https://wa.me/385919010092"),
            ],
            [
                button_link(
                    "Revolut donacija",
                    "https://revolut.me/catanyus?currency=EUR&amount=1000&note=SIDRENA%20plugin%20-%20donacija",
                ),
                button_link("Sidrena web", "https://brendigo.com/sidrene-cijene/"),
            ],
        ],
        colWidths=[85 * mm, 85 * mm],
        rowHeights=[13 * mm, 13 * mm],
    )
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (0, 0), NAVY),
                ("BACKGROUND", (1, 0), (1, 0), ORANGE),
                ("BACKGROUND", (0, 1), (0, 1), GREEN),
                ("BACKGROUND", (1, 1), (1, 1), DARK),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("BOX", (0, 0), (-1, -1), 1.0, colors.white),
                ("INNERGRID", (0, 0), (-1, -1), 1.0, colors.white),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
            ]
        )
    )
    return table


def clean_markdown(text):
    text = re.sub(r"<!--.*?-->", "", text, flags=re.DOTALL)
    text = re.sub(r"<table>.*?</table>", "", text, flags=re.DOTALL | re.IGNORECASE)
    text = re.sub(r"^!\[[^\n]*\]\([^\n]*\)\s*$", "", text, flags=re.MULTILINE)
    text = re.sub(r"<[^>]+>", "", text)
    text = text.replace("\u2013", "-").replace("\u2014", "-").replace("\u2212", "-")
    return text.replace("\r\n", "\n").replace("\r", "\n")


def markdown_story(text):
    result = []
    lines = clean_markdown(text).split("\n")
    buffer = []
    in_code = False
    code_lines = []

    def flush_paragraph():
        nonlocal buffer
        if buffer:
            joined = " ".join(part.strip() for part in buffer if part.strip()).strip()
            if joined:
                result.append(paragraph(joined))
            buffer = []

    def flush_code():
        nonlocal code_lines
        if code_lines:
            result.append(Preformatted("\n".join(code_lines), code_style))
            code_lines = []

    for raw in lines:
        line = raw.rstrip()

        if line.strip().startswith("~~~"):
            flush_paragraph()
            if in_code:
                flush_code()
                in_code = False
            else:
                in_code = True
            continue

        if in_code:
            code_lines.append(line)
            continue

        stripped = line.strip()
        if not stripped:
            flush_paragraph()
            continue

        if stripped.startswith("# "):
            flush_paragraph()
            continue
        if stripped.startswith("## "):
            flush_paragraph()
            result.append(paragraph(stripped[3:].strip(), h2))
            continue
        if stripped.startswith("### "):
            flush_paragraph()
            result.append(paragraph(stripped[4:].strip(), h3))
            continue
        if stripped.startswith("#### "):
            flush_paragraph()
            result.append(paragraph(stripped[5:].strip(), h4))
            continue
        if stripped.startswith("> "):
            flush_paragraph()
            result.append(paragraph(stripped[2:].strip(), quote))
            continue
        if re.match(r"^[-*]\s+", stripped):
            flush_paragraph()
            result.append(
                Paragraph(
                    inline_markup(re.sub(r"^[-*]\s+", "", stripped)),
                    bullet,
                    bulletText="•",
                )
            )
            continue
        numbered_match = re.match(r"^(\d+)\.\s+(.+)$", stripped)
        if numbered_match:
            flush_paragraph()
            result.append(
                Paragraph(
                    inline_markup(numbered_match.group(2)),
                    numbered,
                    bulletText=numbered_match.group(1) + ".",
                )
            )
            continue
        if stripped in {"---", "***", "___"}:
            flush_paragraph()
            result.append(Spacer(1, 2 * mm))
            continue

        buffer.append(stripped)

    flush_paragraph()
    flush_code()
    return result


with open(guide_path, "r", encoding="utf-8") as guide_file:
    guide_text = guide_file.read()

doc = SimpleDocTemplate(
    out,
    pagesize=A4,
    leftMargin=20 * mm,
    rightMargin=20 * mm,
    topMargin=18 * mm,
    bottomMargin=20 * mm,
    title=f"{edition_label} - detaljne upute i podrška",
    author="brendigo",
    subject=f"Detaljne upute za krajnjeg korisnika - {edition_label}",
    creator="brendigo",
)

story = [
    paragraph("SIDRENA", kicker),
    paragraph(edition_label, h1),
    paragraph("Detaljne upute za krajnjeg korisnika", h2),
    paragraph(edition_description),
    Spacer(1, 3 * mm),
]

contact = [
    [paragraph("VERZIJA", label), paragraph(version, value_style)],
    [paragraph("IZDANJE", label), paragraph(edition_label, value_style)],
    [paragraph("E-MAIL PODRŠKA", label), link("sidrena@brendigo.com", "mailto:sidrena@brendigo.com")],
    [paragraph("WHATSAPP PODRŠKA", label), link("+385 91 901 0092", "https://wa.me/385919010092")],
    [paragraph("OPCIONALNO POČETNO POSTAVLJANJE", label), paragraph("80 EUR jednokratno", value_style)],
]
contact_table = Table(contact, colWidths=[68 * mm, 102 * mm], hAlign="LEFT")
contact_table.setStyle(
    TableStyle(
        [
            ("BACKGROUND", (0, 0), (-1, -1), SOFT),
            ("GRID", (0, 0), (-1, -1), 0.5, LINE),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("TOPPADDING", (0, 0), (-1, -1), 7),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ]
    )
)

story.extend(
    [
        contact_table,
        Spacer(1, 4 * mm),
        paragraph("Kako koristiti ovaj priručnik", h2),
        paragraph(
            "Čitajte ga redom pri prvom postavljanju. Nakon toga koristite naslov poglavlja koji odgovara radnji koju želite napraviti. Zakonski važni tehnički izlazi u Sidreni automatizirani su kako ih krajnji korisnik ne bi slučajno isključio."
        ),
        paragraph(
            "Plaćeno početno postavljanje i dobrovoljna donacija nisu uvjet za korištenje plugina niti znače pravno jamstvo. Plugin možete potpuno samostalno instalirati i koristiti prema ovim uputama.",
            quote,
        ),
        PageBreak(),
    ]
)

story.extend(markdown_story(guide_text))

story.extend(
    [
        PageBreak(),
        paragraph("Podrška, donacija i opcionalno postavljanje", h2),
        paragraph(
            "Ako nakon uputa trebate pomoć, pripremite adresu WordPress web-stranice, verziju WordPressa, naziv Sidrena izdanja, opis problema i relevantnu poruku iz Sidrena Dnevnika. Nemojte slati administratorske lozinke e-mailom ili WhatsAppom."
        ),
        Spacer(1, 2 * mm),
        support_buttons(),
        Spacer(1, 3 * mm),
        paragraph("Opcionalno jednokratno početno postavljanje: 80 EUR.", value_style),
        paragraph(
            "Plaćeno postavljanje je dobrovoljna usluga brendigo podrške i nije uvjet za rad plugina, pristup funkcijama ili tehničku usklađenost."
        ),
        paragraph(
            "Revolut donacija je dobrovoljna podrška razvoju. Donacija nije naknada za instalaciju, podršku ili pravno jamstvo."
        ),
        paragraph("Pravna napomena", h3),
        paragraph(
            "Sidrena tehnički podržava unos, provjeru, evidenciju, automatizaciju i objavu podataka prema ugrađenim pravilima. Softver ne može zamijeniti stvarnu poslovnu evidenciju ni pravni savjet. Primjenjivost na konkretne proizvode, usluge, lokacije i poslovni model mora provjeriti odgovorna osoba.",
            small,
        ),
    ]
)

doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(out)
