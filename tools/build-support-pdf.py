#!/usr/bin/env python3
# Sidrena source file.
# Author: Brendigo LTD Developer
# Author URI: https://brendigo.com/
# Plugin URI: https://sidrene-cijene.com.hr/
# Support: sidrena@brendigo.com

import os
import sys
from reportlab.lib.pagesizes import A4
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.units import mm

if len(sys.argv) != 2:
    raise SystemExit("Usage: build-support-pdf.py <output.pdf>")

out = sys.argv[1]
os.makedirs(os.path.dirname(out), exist_ok=True)

font_pairs = [
    ("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"),
    ("/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf", "/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf"),
]
regular = bold = None
for r, b in font_pairs:
    if os.path.isfile(r) and os.path.isfile(b):
        regular, bold = r, b
        break
if not regular:
    raise SystemExit("A Unicode DejaVu/Noto Sans font is required to build the support PDF.")

pdfmetrics.registerFont(TTFont("SidrenaSans", regular))
pdfmetrics.registerFont(TTFont("SidrenaSansBold", bold))

styles = getSampleStyleSheet()
body = ParagraphStyle("body", parent=styles["BodyText"], fontName="SidrenaSans", fontSize=9.4, leading=14, textColor=colors.HexColor("#1f2937"), spaceAfter=6)
h1 = ParagraphStyle("h1", parent=styles["Title"], fontName="SidrenaSansBold", fontSize=24, leading=29, textColor=colors.HexColor("#111827"), alignment=TA_CENTER, spaceAfter=10)
h2 = ParagraphStyle("h2", parent=styles["Heading2"], fontName="SidrenaSansBold", fontSize=15, leading=20, textColor=colors.HexColor("#111827"), spaceBefore=8, spaceAfter=7)
small = ParagraphStyle("small", parent=body, fontSize=8, leading=11, textColor=colors.HexColor("#4b5563"))
center = ParagraphStyle("center", parent=body, alignment=TA_CENTER)
strong = ParagraphStyle("strong", parent=body, fontName="SidrenaSansBold", fontSize=10, leading=15)

def footer(canvas, doc):
    canvas.saveState()
    canvas.setFont("SidrenaSans", 7.5)
    canvas.setFillColor(colors.HexColor("#6b7280"))
    canvas.drawString(18*mm, 10*mm, "Sidrena 0.1.0 - Brendigo LTD Developer")
    canvas.drawRightString(192*mm, 10*mm, str(doc.page))
    canvas.restoreState()

def bullet(text):
    return Paragraph("• " + text, body)

doc = SimpleDocTemplate(out, pagesize=A4, rightMargin=18*mm, leftMargin=18*mm, topMargin=18*mm, bottomMargin=18*mm, title="Sidrena - Podrška i upute", author="Brendigo LTD Developer")
story = [
    Paragraph("SIDRENA", h1),
    Paragraph("Podrška, instalacija i brzi vodič", ParagraphStyle("sub", parent=center, fontName="SidrenaSansBold", fontSize=12, leading=17, textColor=colors.HexColor("#2563eb"))),
    Spacer(1, 5*mm),
]

contact = [
    [Paragraph("<b>Developer</b>", body), Paragraph("Brendigo LTD Developer", body)],
    [Paragraph("<b>E-mail podrška</b>", body), Paragraph("sidrena@brendigo.com", body)],
    [Paragraph("<b>WhatsApp</b>", body), Paragraph("+385 91 901 0092", body)],
    [Paragraph("<b>Instalacija i postavljanje</b>", body), Paragraph("80 EUR jednokratno", body)],
    [Paragraph("<b>Donacija</b>", body), Paragraph("Revolut - izravni gumb nalazi se u Sidrena adminu", body)],
    [Paragraph("<b>Javna objava</b>", body), Paragraph("Objava cjenika + CSV/XML + arhiva", body)],
]
table = Table(contact, colWidths=[55*mm, 105*mm], hAlign="CENTER")
table.setStyle(TableStyle([
    ("FONTNAME",(0,0),(-1,-1),"SidrenaSans"), ("VALIGN",(0,0),(-1,-1),"TOP"),
    ("BOX",(0,0),(-1,-1),0.5,colors.HexColor("#d1d5db")), ("INNERGRID",(0,0),(-1,-1),0.4,colors.HexColor("#e5e7eb")),
    ("BACKGROUND",(0,0),(0,-1),colors.HexColor("#f8fafc")),
    ("LEFTPADDING",(0,0),(-1,-1),7), ("RIGHTPADDING",(0,0),(-1,-1),7), ("TOPPADDING",(0,0),(-1,-1),6), ("BOTTOMPADDING",(0,0),(-1,-1),6),
]))
story.extend([table, Spacer(1, 5*mm), Paragraph("Koje izdanje instalirati?", h2)])
story.append(bullet("<b>Sidrena WordPress</b> - za WordPress web bez WooCommercea; koristi vlastiti katalog proizvoda i usluga."))
story.append(bullet("<b>Sidrena WooCommerce</b> - za WordPress + WooCommerce; proizvode i varijacije čita iz WooCommerce kataloga."))
story.append(Paragraph("Istodobno može biti aktivno samo jedno Sidrena izdanje. Plugin sadrži zaštitu od dvostruke aktivacije.", strong))
story.append(Paragraph("Brzi postupak nakon instalacije", h2))
steps = [
    "Otvorite <b>Sidrena → Postavke</b> i provjerite poslovni model, referentne datume, CSV/XML i vrijeme generiranja.",
    "U Postavkama po potrebi unesite <b>podatke obrta/tvrtke</b>: naziv, adresu, OIB, e-mail, telefon i podatke registra za javnu Objavu cjenika.",
    "Otvorite <b>Sidrena → Lokacije</b> i unesite svaku lokaciju odnosno webshop koji treba vlastiti cjenik.",
    "Popunite katalog i obvezne podatke: naziv, šifra, marka, cijena, sidrena cijena, barkod, dostupnost te jediničnu cijenu kada je primjenjiva.",
    "Otvorite <b>Sidrena → Usklađenost</b> i riješite upozorenja koja plugin može tehnički provjeriti.",
    "Otvorite <b>Sidrena → Cjenici</b>, generirajte datoteke i pokrenite provjeru javne dostupnosti.",
    "Izradite javnu WordPress stranicu <b>Objava cjenika</b> i provjerite aktualni cjenik, preuzimanje i arhivu.",
]
for i, item in enumerate(steps, 1):
    story.append(Paragraph(f"<b>{i}.</b> {item}", body))

story.extend([
    PageBreak(),
    Paragraph("Javna objava cjenika", h2),
    Paragraph("Sidrena može objaviti javnu WordPress stranicu Objava cjenika preko shortcodea <b>[sidrena_objava_cjenika]</b>. Prikaz objedinjuje podatke obrta/tvrtke kada su uključeni, aktualni HTML cjenik, CSV/XML preuzimanje i arhivu. Za strojni dohvat dostupan je REST API i JSON manifest kada je uključen.", body),
    Paragraph("Na javnoj stranici jasno se prikazuju aktualni cjenik, datum objave, lokacija, format datoteke i gumb Preuzmi. Arhivske datoteke grupiraju se po datumima radi jednostavnijeg pregleda.", body),
    Paragraph("Tehnička kontrolna lista", h2),
])
for item in [
    "CSV i/ili XML je uključen.",
    "Automatsko generiranje je postavljeno dovoljno prije 08:00, a sigurnosna provjera prati propuštenu dnevnu objavu.",
    "E-mail upozorenje za neuspjelu ili zakašnjelu objavu uključeno je ako ga želite koristiti; Sidrena ograničava ponavljanje upozorenja.",
    "Ako javna Objava cjenika prikazuje poslovni identitet, provjerite naziv, adresu, OIB, kontakt i podatke registra.",
    "Aktualna datoteka je javno dostupna bez prijave.",
    "Arhiva čuva objavljene datoteke najmanje 30 dana.",
    "Nazivi datoteka sadrže podatke o objektu/lokaciji i vremensku oznaku.",
    "REST/automatizirani dohvat radi kada je uključen.",
    "Sidrena cijena je jasno prikazana uz aktualnu cijenu na webu kada se proizvod ili usluga oglašava; WooCommerce izdanje prikazuje je automatski, a WordPress izdanje automatski na povezanim zapisima.",
]:
    story.append(bullet(item))

story.extend([
    Paragraph("Podaci obrta / tvrtke", h2),
    Paragraph("Naziv, sjedište/adresa, kontaktni podaci, podaci registra i PDV identifikacija pripadaju općoj transparentnosti internetske prodaje. Sidrena ih prikazuje odvojeno na javnoj stranici i ne dodaje ih kao izmišljene obvezne stupce NN 101/2026 CSV/XML cjenika.", body),
    Paragraph("Referentni datumi", h2),
    Paragraph("Za proizvode i usluge koji ranije nisu bili obuhvaćeni mjerom koristi se referentni datum 10.09.2026. Za ranije obuhvaćene kategorije hrane, pića, kozmetike, sredstava za čišćenje, toaletnih potrepština i proizvoda za kućanstvo ostaje 02.05.2025.", body),
    Paragraph("Sidrena je tehnički alat. Referentne, povijesne i druge poslovne cijene moraju dolaziti iz stvarne i provjerljive poslovne evidencije korisnika.", small),
    Paragraph("Podrška", h2),
    Paragraph("<b>E-mail:</b> sidrena@brendigo.com", body),
    Paragraph("<b>WhatsApp:</b> +385 91 901 0092", body),
    Paragraph("<b>Instalacija i početno postavljanje:</b> 80 EUR jednokratno.", body),
    Paragraph("<b>U pluginu:</b> Sidrena → Podrška s PDF-om, e-mailom i WhatsApp gumbom.", body),
    Paragraph("Za prijavu problema pošaljite verziju WordPressa, aktivno Sidrena izdanje, opis koraka i relevantnu poruku iz Sidrena Dnevnika. Ne šaljite lozinke ili pristupne podatke e-poštom.", small),
    Paragraph("Licenca", h2),
    Paragraph("Sidrena se koristi prema Sidrena Software License 1.0. Prodaja, preprodaja, sublicenciranje, redistribucija, white-label i rebrandiranje plugina nisu dopušteni bez pisanog odobrenja Brendigo LTD.", body),
    Spacer(1, 4*mm),
    Paragraph("Brendigo LTD Developer", ParagraphStyle("brand", parent=center, fontName="SidrenaSansBold", fontSize=12, leading=16, textColor=colors.HexColor("#111827"))),
    Paragraph("sidrena@brendigo.com · brendigo.com", center),
])
doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(out)
