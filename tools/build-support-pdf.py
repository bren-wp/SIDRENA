#!/usr/bin/env python3
# Sidrena source file.
# Author: Brendigo
# Author URI: https://brendigo.com/
# Plugin URI: https://brendigo.com/sidrene-cijene/
# Support: sidrena@brendigo.com

import os
import sys
from reportlab import rl_config
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

rl_config.invariant = 1

if len(sys.argv) != 3:
    raise SystemExit("Usage: build-support-pdf.py <version> <output.pdf>")

version = sys.argv[1].strip()
out = sys.argv[2]
if not version:
    raise SystemExit("Sidrena version is required.")
os.makedirs(os.path.dirname(out) or ".", exist_ok=True)

font_pairs = [
    ("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"),
    ("/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf", "/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf"),
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

base = ParagraphStyle("base", fontName="SidrenaSans", fontSize=9.6, leading=13.4, textColor=TEXT, spaceAfter=4)
h1 = ParagraphStyle("h1", fontName="SidrenaSansBold", fontSize=23, leading=27, textColor=NAVY, spaceAfter=8)
h2 = ParagraphStyle("h2", fontName="SidrenaSansBold", fontSize=13.7, leading=17, textColor=NAVY, spaceBefore=6, spaceAfter=7)
kicker = ParagraphStyle("kicker", fontName="SidrenaSansBold", fontSize=8.3, leading=10, textColor=MUTED, spaceAfter=5)
small = ParagraphStyle("small", parent=base, fontSize=8.1, leading=10.8, textColor=MUTED)
label = ParagraphStyle("label", parent=base, fontName="SidrenaSansBold", fontSize=8.3, leading=10.5, textColor=MUTED)
value = ParagraphStyle("value", parent=base, fontName="SidrenaSansBold", fontSize=9.6, leading=12, textColor=NAVY)
button = ParagraphStyle("button", fontName="SidrenaSansBold", fontSize=9.3, leading=12, alignment=TA_LEFT, textColor=colors.white)

def paragraph(text, style=base):
    return Paragraph(text, style)

def link(text, url, style=value):
    return Paragraph(f'<link href="{url}" color="#0B2B45"><u>{text}</u></link>', style)

def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.5)
    canvas.line(18 * mm, 13.5 * mm, 192 * mm, 13.5 * mm)
    canvas.setFont("SidrenaSans", 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(18 * mm, 8 * mm, "Sidrena - Brendigo")
    canvas.drawRightString(192 * mm, 8 * mm, f"Stranica {doc.page}")
    canvas.restoreState()

doc = SimpleDocTemplate(
    out,
    pagesize=A4,
    leftMargin=20 * mm,
    rightMargin=20 * mm,
    topMargin=18 * mm,
    bottomMargin=20 * mm,
    title="Sidrena - Podrška i instalacija",
    author="Brendigo",
    subject="Podrška i instalacija za Sidrena WordPress i Sidrena WooCommerce",
    creator="Brendigo",
)

story = [
    paragraph("SIDRENA", kicker),
    paragraph("Podrška i instalacija", h1),
    paragraph("Službeni kontakt za pomoć pri instalaciji, početnom postavljanju, provjeri javnog cjenika i radu Sidrena WordPress / Sidrena WooCommerce plugina."),
    Spacer(1, 4 * mm),
]

contact = [
    [paragraph("E-MAIL PODRŠKA", label), link("sidrena@brendigo.com", "mailto:sidrena@brendigo.com")],
    [paragraph("WHATSAPP PODRŠKA", label), link("+385 91 901 0092", "https://wa.me/385919010092")],
    [paragraph("VERZIJA", label), paragraph(version, value)],
    [paragraph("INSTALACIJA I POČETNO POSTAVLJANJE", label), paragraph("80 EUR jednokratno", value)],
    [paragraph("AUTOR", label), paragraph("Brendigo", value)],
]
contact_table = Table(contact, colWidths=[62 * mm, 108 * mm], hAlign="LEFT")
contact_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), SOFT),
    ("GRID", (0, 0), (-1, -1), 0.5, LINE),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("TOPPADDING", (0, 0), (-1, -1), 7),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
]))
story.extend([
    contact_table,
    Spacer(1, 2.5 * mm),
    paragraph("Što uključuje jednokratna instalacija", h2),
])

for item in [
    "instalaciju odgovarajućeg Sidrena izdanja na WordPress web stranicu",
    "osnovno postavljanje lokacija, javnog cjenika i arhive",
    "provjeru prikaza sidrene cijene na web stranici i mobilnim uređajima",
    "provjeru generiranja CSV/XML datoteka i javne dostupnosti",
    "osnovnu provjeru WordPress cron rasporeda i postavki plugina",
]:
    story.append(paragraph("• " + item))

story.extend([Spacer(1, 1.5 * mm), paragraph("Brzi kontakt", h2)])

def button_link(text, url):
    return Paragraph(f'<link href="{url}" color="#FFFFFF"><b>{text}</b></link>', button)

buttons = Table([
    [button_link("Pošalji e-mail", "mailto:sidrena@brendigo.com"), button_link("Otvori WhatsApp", "https://wa.me/385919010092")],
    [button_link("Revolut donacija", "https://revolut.me/catanyus?currency=EUR&amount=1000&note=Sidrena%20WordPress%20plugin%20-%20donacija"), button_link("Sidrena web", "https://brendigo.com/sidrene-cijene/")],
], colWidths=[85 * mm, 85 * mm], rowHeights=[13 * mm, 13 * mm])
buttons.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (0, 0), NAVY),
    ("BACKGROUND", (1, 0), (1, 0), ORANGE),
    ("BACKGROUND", (0, 1), (0, 1), GREEN),
    ("BACKGROUND", (1, 1), (1, 1), DARK),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LEFTPADDING", (0, 0), (-1, -1), 28),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("BOX", (0, 0), (-1, -1), 1.2, colors.white),
    ("INNERGRID", (0, 0), (-1, -1), 1.2, colors.white),
]))
story.extend([
    buttons,
    Spacer(1, 2.5 * mm),
    paragraph("Prije javljanja podršci", h2),
    paragraph("Pripremite adresu WordPress web stranice, verziju WordPressa, naziv Sidrena izdanja koje koristite te kratak opis problema. Nemojte slati lozinke e-mailom ili WhatsAppom. Ako je za intervenciju potreban pristup, način sigurnog privremenog pristupa dogovara se zasebno."),
    Spacer(1, 2 * mm),
    paragraph("Napomena", h2),
    paragraph("Sidrena tehnički podržava unos, prikaz i objavu podataka prema provjerenim službenim izvorima. Softver sam po sebi ne može jamčiti potpunu pravnu usklađenost konkretnog poslovanja jer ona ovisi o stvarnim podacima, poslovnom modelu, robi/uslugama i važećim propisima.", small),
])

doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(out)
