#!/usr/bin/env python3
"""Latinfo.hu médiaérték-módszertan PDF (előnézet / eseményoldal / további infó)."""

from __future__ import annotations

from pathlib import Path

from fpdf import FPDF
from fpdf.enums import TableCellFillMode, XPos, YPos
from fpdf.fonts import FontFace

FONT_DIR = Path(r"C:\Windows\Fonts")
OUT_PATH = Path(__file__).resolve().parent / "mediaertek-modszertan.pdf"

GREEN = (45, 90, 70)
GREEN_DARK = (28, 58, 46)
INK = (34, 34, 34)
MUTED = (96, 96, 96)
LINK = (26, 86, 138)
RULE = (210, 216, 212)
BOX_BG = (245, 247, 244)
GOLD = (140, 110, 55)


class MediaValuePdf(FPDF):
    def __init__(self) -> None:
        super().__init__(format="A4", unit="mm")
        self.set_auto_page_break(auto=True, margin=20)
        self.set_margins(18, 16, 18)
        self.add_font("Calibri", "", str(FONT_DIR / "calibri.ttf"))
        self.add_font("Calibri", "B", str(FONT_DIR / "calibrib.ttf"))
        self.add_font("Calibri", "I", str(FONT_DIR / "calibrii.ttf"))
        self.add_font("Calibri", "BI", str(FONT_DIR / "calibriz.ttf"))
        self.set_title("Médiaérték-módszertan – Előnézet, eseményoldal, további információ")
        self.set_author("Latinfo.hu")
        self.set_creator("Latinfo.hu")
        self.set_subject("Reklámhelyettesítési költség a nyilvános programnaptár emberi forgalmára")
        self.set_keywords("médiaérték, előnézet, landing page view, click-out, Latinfo")
        self.set_lang("hu")
        self._cover = True

    def header(self) -> None:
        if self._cover or self.page_no() == 1:
            return
        self.set_font("Calibri", "", 8)
        self.set_text_color(*MUTED)
        self.cell(0, 6, "Latinfo.hu  ·  Médiaérték-módszertan", new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        y = self.get_y()
        self.set_draw_color(*GREEN)
        self.set_line_width(0.35)
        self.line(18, y, 192, y)
        self.ln(4)
        self.set_text_color(*INK)

    def footer(self) -> None:
        if self.page_no() == 1:
            return
        self.set_y(-14)
        self.set_draw_color(*RULE)
        self.set_line_width(0.2)
        self.line(18, self.get_y(), 192, self.get_y())
        self.set_y(-12)
        self.set_font("Calibri", "", 8)
        self.set_text_color(*MUTED)
        self.cell(90, 6, "v1.0  ·  2026. szeptember 20.  ·  nem számla, helyettesítési költség")
        self.cell(84, 6, f"{self.page_no() - 1}. oldal", align="R")
        self.set_text_color(*INK)

    def h1(self, text: str) -> None:
        self.start_section(text, level=0)
        self.ln(2)
        self.set_font("Calibri", "B", 14)
        self.set_text_color(*GREEN_DARK)
        self.multi_cell(0, 7, text)
        self.set_draw_color(*GREEN)
        self.set_line_width(0.4)
        y = self.get_y()
        self.line(18, y, 78, y)
        self.ln(3)
        self.set_text_color(*INK)

    def h2(self, text: str) -> None:
        self.start_section(text, level=1)
        self.ln(1.5)
        self.set_font("Calibri", "B", 11.5)
        self.set_text_color(*GREEN)
        self.multi_cell(0, 6, text)
        self.ln(1)
        self.set_text_color(*INK)

    def body(self, text: str) -> None:
        self.set_font("Calibri", "", 10.5)
        self.set_text_color(*INK)
        self.multi_cell(0, 5.3, text)
        self.ln(1.4)

    def bullet(self, text: str) -> None:
        x = self.l_margin
        self.set_font("Calibri", "", 10.5)
        self.set_text_color(*INK)
        self.set_x(x)
        self.cell(6, 5.3, "•")
        self.multi_cell(0, 5.3, text)
        self.ln(0.6)

    def source(self, url: str, label: str = "Forrás") -> None:
        self.set_font("Calibri", "", 8)
        self.set_text_color(*MUTED)
        self.write(4.2, f"{label}: ")
        self.set_font("Calibri", "I", 8)
        self.set_text_color(*LINK)
        self.write(4.2, url, url)
        self.ln(5.2)
        self.set_text_color(*INK)

    def sources(self, urls: list[str]) -> None:
        for i, url in enumerate(urls):
            self.source(url, "Forrás" if i == 0 else "Ugyanott")

    def callout(self, title: str, body: str) -> None:
        self.ln(1)
        inner_w = self.epw - 8
        self.set_font("Calibri", "B", 10)
        title_h = float(self.multi_cell(inner_w, 5, title, dry_run=True, output="HEIGHT"))
        self.set_font("Calibri", "", 10)
        body_h = float(self.multi_cell(inner_w, 5, body, dry_run=True, output="HEIGHT"))
        box_h = 3 + title_h + 0.5 + body_h + 3
        if self.get_y() + box_h > self.page_break_trigger:
            self.add_page()
        start = self.get_y()
        self.set_fill_color(*BOX_BG)
        self.set_draw_color(*GREEN)
        self.set_line_width(0.4)
        self.rect(self.l_margin, start, self.epw, box_h, style="DF")
        self.set_fill_color(*GREEN)
        self.rect(self.l_margin, start, 1.4, box_h, style="F")
        self.set_xy(self.l_margin + 4, start + 3)
        self.set_font("Calibri", "B", 10)
        self.set_text_color(*GREEN_DARK)
        self.multi_cell(inner_w, 5, title)
        self.set_xy(self.l_margin + 4, self.get_y() + 0.5)
        self.set_font("Calibri", "", 10)
        self.set_text_color(*INK)
        self.multi_cell(inner_w, 5, body)
        self.set_y(start + box_h + 2)
        self.set_fill_color(255, 255, 255)

    def kv_table(
        self,
        headers: list[str],
        rows: list[list[str]],
        col_widths: tuple[float, ...] | None = None,
        url_col: int | None = None,
    ) -> None:
        self.ln(1)
        self.set_font("Calibri", "", 8.8)
        self.set_text_color(*INK)
        self.set_fill_color(255, 255, 255)
        self.set_draw_color(*GREEN)
        headings = FontFace(emphasis="BOLD", color=(255, 255, 255), fill_color=GREEN_DARK)
        aligns = ["LEFT"] * len(headers)
        with self.table(
            col_widths=col_widths,
            text_align=tuple(aligns),
            line_height=5.0,
            padding=2.0,
            first_row_as_headings=True,
            headings_style=headings,
            cell_fill_color=(248, 250, 247),
            cell_fill_mode=TableCellFillMode.ROWS,
            markdown=url_col is not None,
            borders_layout="SINGLE_TOP_LINE",
            v_align="TOP",
        ) as table:
            head = table.row()
            for cell in headers:
                head.cell(cell)
            for data in rows:
                row = table.row()
                for i, cell in enumerate(data):
                    if url_col is not None and i == url_col:
                        row.cell(f"[{cell}]({cell})")
                    else:
                        row.cell(cell)
        self.ln(3)
        self.set_font("Calibri", "", 10.5)
        self.set_text_color(*INK)
        self.set_fill_color(255, 255, 255)


def cover(pdf: MediaValuePdf) -> None:
    pdf.add_page()
    pdf.set_fill_color(*GREEN_DARK)
    pdf.rect(0, 0, 210, 58, style="F")
    pdf.set_fill_color(*GREEN)
    pdf.rect(0, 58, 210, 4, style="F")

    pdf.set_xy(18, 16)
    pdf.set_font("Calibri", "", 11)
    pdf.set_text_color(230, 236, 232)
    pdf.cell(0, 6, "LATINFO.HU")
    pdf.set_xy(18, 26)
    pdf.set_font("Calibri", "B", 26)
    pdf.set_text_color(255, 255, 255)
    pdf.multi_cell(174, 11, "Médiaérték-módszertan")
    pdf.set_xy(18, 47)
    pdf.set_font("Calibri", "", 12)
    pdf.set_text_color(220, 228, 222)
    pdf.cell(0, 6, "Előnézet  ·  Eseményoldal  ·  További információ")

    pdf.set_xy(18, 72)
    pdf.set_font("Calibri", "", 11)
    pdf.set_text_color(*INK)
    pdf.multi_cell(
        174,
        6,
        "Belső módszertani tanulmány és partneri indoklás.\n"
        "Reklámhelyettesítési költség a nyilvános programnaptár emberi forgalmára.\n"
        "Magyarország, piacvezető specialty programnaptár.",
    )

    pdf.set_xy(18, 96)
    pdf.set_font("Calibri", "I", 10)
    pdf.set_text_color(*MUTED)
    pdf.multi_cell(174, 5.2, "2026. szeptember 20.  ·  módszertan v1.0  ·  csak emberi forgalom, botok nélkül")

    pdf.set_xy(18, 112)
    pdf.set_fill_color(*BOX_BG)
    pdf.set_draw_color(*GREEN)
    pdf.set_line_width(0.5)
    pdf.rect(18, 112, 174, 42, style="DF")
    pdf.set_xy(24, 117)
    pdf.set_font("Calibri", "B", 10)
    pdf.set_text_color(*GREEN_DARK)
    pdf.cell(0, 6, "Ajánlott egységárak (emberi forgalom)")
    pdf.set_xy(24, 128)
    pdf.set_font("Calibri", "B", 16)
    pdf.set_text_color(*GOLD)
    pdf.cell(52, 8, "12 Ft")
    pdf.cell(58, 8, "110 Ft")
    pdf.cell(52, 8, "150 Ft")
    pdf.set_xy(24, 136)
    pdf.set_font("Calibri", "", 9)
    pdf.set_text_color(*MUTED)
    pdf.cell(52, 6, "előnézet")
    pdf.cell(58, 6, "eseményoldal")
    pdf.cell(52, 6, "további információ")
    pdf.set_xy(24, 144)
    pdf.set_font("Calibri", "", 8.5)
    pdf.set_text_color(*INK)
    pdf.cell(0, 5, "Képlet: (előnézet × 12) + (oldalmegnyitás × 110) + (további infó × 150)")

    pdf.set_xy(18, 164)
    pdf.set_font("Calibri", "", 10.5)
    pdf.set_text_color(*INK)
    pdf.multi_cell(
        174,
        5.4,
        "Ez a dokumentum annak szól, aki a forintösszeget kérdőre vonja. "
        "Minden állítás mellett ott van a forrás URL-je. A szám nem jegybevétel, "
        "nem márkaérték és nem AVE-szorzó: azt mutatja, mennyibe kerülne "
        "ugyanennyi, ugyanilyen szándékú figyelmet megvenni Meta- vagy Google-hirdetésből, "
        "specialty naptár-kontextusban.",
    )

    pdf.set_xy(18, 198)
    pdf.set_font("Calibri", "B", 10)
    pdf.set_text_color(*GREEN_DARK)
    pdf.cell(0, 6, "Tartalom", new_x=XPos.LMARGIN, new_y=YPos.NEXT)
    pdf.set_font("Calibri", "", 10)
    pdf.set_text_color(*INK)
    toc = [
        "1. Cél és keret",
        "2. Mit mérünk, és mit nem",
        "3. Módszertan: két árkeret, egy választás",
        "4. Ajánlott egységárak",
        "5. Előnézet — miért nem nulla, és miért nem 110 Ft",
        "6. Eseményoldal — 110 Ft",
        "7. További információ — 150 Ft",
        "8. Piacvezető programfelület",
        "9. Gyakori kifogások és válaszok",
        "10. Forrástábla és hivatkozásjegyzék",
        "11. Záradék",
    ]
    for item in toc:
        pdf.cell(0, 5.2, item, new_x=XPos.LMARGIN, new_y=YPos.NEXT)

    pdf.set_xy(18, 272)
    pdf.set_font("Calibri", "I", 8)
    pdf.set_text_color(*MUTED)
    pdf.cell(0, 4, "Nyilvános források alapján. Nem számla. A sávon belüli eltérés kampánycéltól függ.")
    pdf._cover = False


def chapter_1(pdf: MediaValuePdf) -> None:
    pdf.add_page()
    pdf.h1("1. Cél és keret")
    pdf.body(
        "Ez a dokumentum azt indokolja, miért és milyen forintösszeggel értékeljük "
        "a három mért emberi akciót a nyilvános programnaptárban."
    )
    pdf.bullet("Előnézet — a naptárban vagy listában szándékosan kinyitott előnézet-panel.")
    pdf.bullet("Eseményoldal — a részletes nyilvános eseményoldal betöltése (Detail Page View / landing page view).")
    pdf.bullet(
        "További információ — átkattintás a szervező külső oldalára vagy Facebook-eseményére "
        "(intent click / click-out)."
    )
    pdf.body(
        "A szám nem jegybevétel, nem márkaérték és nem „PR-érték-szorzó”. "
        "Azt mutatja: mennyibe kerülne ugyanennyi, ugyanilyen szándékú figyelmet "
        "megvenni fizetett hirdetésből."
    )
    pdf.body(
        "A nemzetközi méréselmélet szerint az Advertising Value Equivalent (AVE) nem a "
        "kommunikáció értéke. Partnerriportra mégis szükség van átlátható forintszámra. "
        "Ezért reklámhelyettesítési költséget közlünk, forrással, szorzó nélkül."
    )
    pdf.source("https://amecorg.com/2020/07/barcelona-principles-3-0/")
    pdf.body(
        "Az AMEC Barcelona Principles 3.0 5. elve: „AVEs are not the value of communication.” "
        "A 2017-es AMEC-útmutató 22 okot sorol fel, miért érvénytelen az AVE, "
        "köztük azt, hogy nincs egységes módszertan, és a költség nem egyenlő a hatással."
    )
    pdf.source("https://amecorg.com/2017/06/the-definitive-guide-why-aves-are-invalid/")
    pdf.body(
        "Az AMEC webinar szerint tilos AVE-t használni, tilos „pass-along” szorzót "
        "alkalmazni, és tilos AVE-t PR-értékként vagy médiaértékként átcímkézni. "
        "Ha összehasonlítás kell paid vs. earned között, az legyen transzparens, "
        "minőséggel együtt, szorzó nélkül."
    )
    pdf.source("https://amecorg.com/wp-content/uploads/2020/07/BP-Presentation-3.0-AMEC-webinar-10.07.20.pdf")
    pdf.callout(
        "Mit állítunk, és mit nem",
        "Állítjuk: ennyi lenne a helyettesítési költség Meta/Google aukción, specialty "
        "naptár-közönségen. Nem állítjuk, hogy ennyi a jegybevétel, a márka liftje, "
        "vagy hogy a partnernek ennyit kell fizetnie.",
    )


def chapter_2(pdf: MediaValuePdf) -> None:
    pdf.h1("2. Mit mérünk, és mit nem")
    pdf.body(
        "Csak emberi forgalom számít. A botok (keresőrobot, AI search, közösségi előnézet, "
        "scraper) User-Agent alapján külön soron vannak, és nem kerülnek a médiaértékbe."
    )
    pdf.kv_table(
        ["Akció", "Mi történik", "Reklámanalóg", "Szerepe"],
        [
            [
                "Előnézet",
                "A felhasználó kinyit egy panelt: kép, cím, dátum, helyszín, szervező, stílusok.",
                "Engaged / expanded view (gazdag natív kártya)",
                "Figyelem + első tartalmi érintkezés",
            ],
            [
                "Eseményoldal",
                "Teljes landing page betöltése.",
                "Meta Landing Page View; Google Search kattintás céloldalra",
                "Részletes megismerés",
            ],
            [
                "További infó",
                "Kilépés a szervező saját felületére.",
                "Intent click / site visit / click-out",
                "A szervező által megvásárolható forgalom",
            ],
        ],
        col_widths=(28, 52, 50, 44),
    )
    pdf.body("Nem számítjuk a médiaértékbe:")
    pdf.bullet(
        "a naptárcella puszta láthatóságát (passzív impression), mert nincs külön, "
        "auditálható „megjelenés / esemény” elszámolás;"
    )
    pdf.bullet("a botforgalmat;")
    pdf.bullet("3–5× editorial / pass-along szorzót.")
    pdf.source("https://amecorg.com/wp-content/uploads/2020/07/BP-Presentation-3.0-AMEC-webinar-10.07.20.pdf")
    pdf.body(
        "A Meta landing page view definíciója: a felhasználó rákattint a linkre, "
        "és a céloldal ténylegesen betöltődik. Ez nálunk az eseményoldal-megnyitás, "
        "nem a sima hirdetésklikkel azonos."
    )
    pdf.source("https://www.facebook.com/business/help/417293491972212")


def chapter_3(pdf: MediaValuePdf) -> None:
    pdf.h1("3. Módszertan: két árkeret, egy választás")
    pdf.body("A piacon két, gyakran összekevert árszint van. A vita többnyire azért csúszik félre, mert a két keretet egy kalap alá veszik.")
    pdf.h2("A) Buy-side — amit a szervező fizetne hirdetésre")
    pdf.body(
        "Aukciós CPC, CPM, landing page view. Ez a partner számára ellenőrizhető: "
        "„ennyiért venném meg Facebookon vagy Google-ön.” Ez a mi bázisunk."
    )
    pdf.h2("B) Sell-side — kiadói listaár")
    pdf.body(
        "Garantált ad view vagy click-through a portálok árlistáján. Ezek listaárak, "
        "jellemzően jelentős kedvezménnyel kelnek el. Nem a szervező aukciós valósága."
    )
    pdf.body("Magyar kiadói listaárak 2026-ban (összehasonlítás, nem bázis):")
    pdf.bullet("Infinety: 5,5–6 Ft / ad view, 700–750 Ft / átkattintás (listaár, ÁFA nélkül).")
    pdf.source("https://www.infinety.hu/upload/files/infinety_mediaajanlat.pdf")
    pdf.bullet("Mediaworks programmatic: 2,5 Ft (RON) → 8 Ft (70% láthatóság) → 13 Ft (100% viewable) / megjelenés.")
    pdf.source("https://mediaworks.hu/wp-content/uploads/2026/07/5251395Mediaworksonlinetarifa0722.pdf")
    pdf.bullet("Telex AV: 3,50–6 Ft.")
    pdf.source("https://sales.telex.hu/wp-content/uploads/2026/07/AKTUALIS_HIRDETESI_ARLISTA_2026_09.pdf")
    pdf.bullet("Mandiner banner CT mix listaár: 1200 Ft / CT.")
    pdf.source("https://mandiner.hu/uploads/Mandiner_Radelit_Sales_online_arlista.pdf")
    pdf.callout(
        "Miért a buy-side a védhető?",
        "A partner a saját Meta/Google-számláján tudja összevetni. A 700 Ft-os kiadói "
        "CT-listaár nem védhető, ha a szervező 50–80 Ft-ért vesz Facebook-klikket. "
        "A specialty naptár viszont nem általános display-hálózat: a felhasználó eseményt keres. "
        "Ezért a buy-side középérték fölé, a sáv felső harmadába pozicionálunk, "
        "kb. 1,3–1,8× minőségi felárral a hideg Facebook-forgalomhoz képest. "
        "Ez nem AVE-szorzó, hanem a célzott inventory felára.",
    )


def chapter_4(pdf: MediaValuePdf) -> None:
    pdf.h1("4. Ajánlott egységárak")
    pdf.kv_table(
        ["Akció", "Ajánlott", "Elfogadható sáv", "Mit helyettesít"],
        [
            ["Előnézet", "12 Ft", "8–15 Ft", "Viewable display + önkéntes kinyitás"],
            ["Eseményoldal", "110 Ft", "100–120 Ft", "Landing page view / minőségi Search-kattintás"],
            ["További infó", "150 Ft", "130–180 Ft", "Intent click a szervező saját oldalára"],
        ],
        col_widths=(36, 28, 38, 72),
    )
    pdf.body(
        "Képlet: médiaérték = (emberi előnézet × 12) + (emberi oldalmegnyitás × 110) + "
        "(emberi további infó × 150)."
    )
    pdf.body(
        "A tölcsér sorrendje szándékos: az átkattintás drágább, mint az oldal, mert ez "
        "a szervező által megvásárolható eredmény. Az előnézet olcsóbb, mert nem teljes landing page."
    )
    pdf.body("Ha a partner a duplaelszámolást kifogásolja (ugyanaz a user előnézet → oldal → átkattintás):")
    pdf.bullet("Alapriport: mindhárom külön soron, az összeg transzparens.")
    pdf.bullet(
        "Szigorú riport: az előnézet csak akkor számít, ha nem folytatódott oldalmegnyitással. "
        "Az oldal és a további infó változatlan."
    )
    pdf.kv_table(
        ["Szint", "Analóg", "Érték"],
        [
            ["Naptárcella látszik", "Display impression", "1–3 Ft — nálunk nincs a képletben"],
            ["Előnézet kinyílik", "Viewable AV + engagement", "12 Ft (sáv: 8–15)"],
            ["Eseményoldal betölt", "Landing page view", "110 Ft (sáv: 100–120)"],
            ["További infó", "Intent click / site visit", "150 Ft (sáv: 130–180)"],
        ],
        col_widths=(42, 72, 60),
    )


def chapter_5(pdf: MediaValuePdf) -> None:
    pdf.h1("5. Előnézet — miért nem nulla, és miért nem 110 Ft?")
    pdf.body(
        "Az előnézet önkéntes tartalmi érintkezés: kép, cím, időpont, helyszín, szervező, stílus. "
        "Több, mint egy banner-villanás, kevesebb, mint a teljes eseményoldal."
    )
    pdf.h2("Alsó korlát: magyar display / viewable AV, 2026")
    pdf.body("A magyar prémium és programmatic megjelenés 2026-ban 3,5–13 Ft.")
    pdf.bullet("Telex AV 3,50–6 Ft.")
    pdf.source("https://sales.telex.hu/wp-content/uploads/2026/07/AKTUALIS_HIRDETESI_ARLISTA_2026_09.pdf")
    pdf.bullet("Infinety 5,5–6 Ft / AV.")
    pdf.source("https://www.infinety.hu/upload/files/infinety_mediaajanlat.pdf")
    pdf.bullet("Mediaworks viewable 8–13 Ft.")
    pdf.source("https://mediaworks.hu/wp-content/uploads/2026/07/5251395Mediaworksonlinetarifa0722.pdf")
    pdf.h2("Felső korlát")
    pdf.body(
        "Az eseményoldal 100–120 Ft-ja. Az előnézet ennek kb. 10–15 százaléka. "
        "12 Ft = viewable AV (~6–8 Ft) + engagement-felár az önkéntes kinyitásért (~4–6 Ft). "
        "Nem cikkolvasás, nem landing page."
    )
    pdf.body(
        "Nemzetközi analogon: a display impression Entertainment-kategóriában kb. 2,5 Ft "
        "(CPM 7,05 USD / 1000 megjelenés, kb. 360 Ft/USD). Az előnézet ennél több, "
        "mert a user kinyitja, nem csak elsiklik felette."
    )
    pdf.source("https://www.stackmatix.com/blog/facebook-ads-cost-complete-guide")
    pdf.body(
        "A passzív naptárcellát szándékosan nem árazzuk: az a 1–3 Ft-os impression-sáv, "
        "és nincs külön auditált impression-számláló eseményenként."
    )
    pdf.body(
        "Az Eventbrite és a Resident Advisor nem ad dollárt a listing-preview-ra: "
        "impressiont, klikket és ROAS-t / jegyet riportolnak. A preview nálunk extra, "
        "mert külön, gazdag panel, mért emberi nyitással."
    )
    pdf.source("https://www.eventbrite.com/organizer/features/eventbrite-ads/")
    pdf.source("https://www.eventbrite.se/help/sv/articles/692484/understand-the-performance-of-your-eventbrite-ads-campaign/")


def chapter_6(pdf: MediaValuePdf) -> None:
    pdf.h1("6. Eseményoldal — 110 Ft")
    pdf.body(
        "Ez a Meta-terminológia szerinti landing page view: a céloldal ténylegesen betöltődik, "
        "nem csak a hirdetésre kattintanak. A landing page view a sima linkkattintásnál "
        "jellemzően 10–40 százalékkal drágább, mert nem minden klikk töltődik be."
    )
    pdf.source("https://www.facebook.com/business/help/417293491972212")
    pdf.h2("Magyar bázis")
    pdf.bullet("Meta Magyarország 2024: CPC 30–150 Ft, CPM 800–2000 Ft.")
    pdf.source("https://www.klikkmania.hu/ppc-hirdetes/facebook-hirdetes-arak-2024-ben/")
    pdf.bullet(
        "Meta Magyarország 2026 (ügynökségi árlista): traffic CPC 40–75 Ft, "
        "conversion 45–90 Ft, local service (étterem/szolgáltatás) 40–70 Ft, CPM 850–1400 Ft."
    )
    pdf.source("https://amarketingese.hu/facebook-hirdetes-ara-2026/")
    pdf.bullet("Google Ads Magyarország, művészetek és szórakozás: 60–150 Ft CPC.")
    pdf.source("https://marketing-consulting.hu/mennyibe-kerul-a-google-ads-2025-ben/")
    pdf.bullet("Google Ads Magyarország 2025, 72 073 kulcsszó: a hirdetők többsége 55–303 Ft CPC.")
    pdf.source("https://prjr.hu/google-ads-hirdetes-arak-mennyibe-kerul-a-google-hirdetes/")
    pdf.bullet("Konkrét magyar eseménykampány (Balatonfondo 2026): Google Ads átlag CPC 71 Ft.")
    pdf.source("https://www.jacsomedia.hu/sportesemeny-marketing-esettanulmany-balatonfondo-2026/")
    pdf.bullet("Google Ads Magyarország 2026: Search átlag 90–450 Ft iparágtól függően; Display 25–90 Ft; vendéglátás 40–250 Ft.")
    pdf.source("https://www.socialpro.hu/google-ads-kampanyok-koltsegei/")
    pdf.bullet("Webma 2026: tipikus magyar CPC 55–300 Ft.")
    pdf.source("https://webma.hu/blog/google-ads-arak-2026-ban-kattintasi-dijak-kampanykezelesi-arak-es-realis-havi-budzse-magyarorszagon/")
    pdf.body(
        "Számítás: 70 Ft-os minőségi traffic-CPC × 1,25 (LPV-felár) ≈ 88 Ft; "
        "specialty naptár-felárral (1,25–1,4×) → 110 Ft. "
        "Ez a magyar szórakoztatóipari Search-sáv (60–150 Ft) felső közepe, "
        "nem az amerikai aukció."
    )
    pdf.h2("Nemzetközi ellenőrző sáv (kb. 360 Ft / USD)")
    pdf.bullet("Entertainment Facebook CPC 0,39 USD ≈ 140 Ft.")
    pdf.source("https://www.stackmatix.com/blog/facebook-ads-cost-complete-guide")
    pdf.bullet("Arts & Entertainment CPC 0,43 USD ≈ 155 Ft.")
    pdf.source("https://www.webtonic.io/blog/facebook-ads-benchmarks")
    pdf.bullet("LocaliQ 2025 Arts & Entertainment traffic CPC 0,49 USD ≈ 176 Ft.")
    pdf.source("https://venuera.com/facebook-google-ads-for-events-benchmark-data/")
    pdf.bullet("CARMA / Muck Rack iparági AVE: 0,37 USD / olvasó ≈ 133 Ft.")
    pdf.source("https://help.carma.com/article-metrics")
    pdf.source("https://help.muckrack.com/en/articles/9362689-advertising-value-equivalency-ave-in-coverage-reports")
    pdf.callout(
        "Miért nem 140–176 Ft az oldal?",
        "A 133–176 Ft-os sáv amerikai aukció és vásárlóerő. Magyar piacra a 110 Ft a védhető "
        "közép: magasabb, mint a 40–75 Ft-os olcsó Facebook-klik, alacsonyabb, mint a US "
        "entertainment CPC. A 180–900 Ft-os „magyar Facebook CPC” cikkek globális dollársáv "
        "Ft-ra váltása; magyar kampányadatokkal nem egyeznek, nem használjuk bázisként.",
    )
    pdf.source("https://optimalizalt.hu/facebook-hirdetes-arak-2025-ben/")


def chapter_7(pdf: MediaValuePdf) -> None:
    pdf.h1("7. További információ — 150 Ft")
    pdf.body(
        "Ez a tölcsér legdrágább lépése: a user már látta a naptárt, kinyitotta vagy "
        "megnyitotta az oldalt, és átmegy a szervező saját felületére. Ezt a forgalmat "
        "a szervező Meta/Google-kampányból venné meg."
    )
    pdf.body(
        "Miért ne legyen olcsóbb, mint az oldal? Mert a click-out a szervező üzleti "
        "eredménye (látogató a saját eseményére), az oldal pedig a naptár saját inventoryja. "
        "A helyettesítési költség a szervező oldalán a click-outnál a magasabb."
    )
    pdf.h2("Magyar bázis")
    pdf.bullet("Meta traffic 40–75 Ft, conversion 45–90 Ft.")
    pdf.source("https://amarketingese.hu/facebook-hirdetes-ara-2026/")
    pdf.bullet("Google szórakoztatás 60–150 Ft.")
    pdf.source("https://marketing-consulting.hu/mennyibe-kerul-a-google-ads-2025-ben/")
    pdf.bullet("Search átlag 80–400 Ft; Display 30–150 Ft.")
    pdf.source("https://marketing-consulting.hu/mennyibe-kerul-a-google-ads-2025-ben/")
    pdf.body(
        "150 Ft = a magyar szórakoztatóipari Search-sáv teteje specialty naptár-kontextusban, "
        "anélkül, hogy US entertainment CPC-re (140–176 Ft) vagy londoni RA-licitre ugranánk."
    )
    pdf.h2("Nemzetközi analogon: Resident Advisor Bumps")
    pdf.body(
        "A világ legnagyobb elektronikus zenei naptára a kiemelt listinget CPC-aukcióban adja. "
        "Csak a klikkért fizetsz, nem a megjelenésért. A dokumentáció példája: "
        "0,50 GBP vs 0,70 GBP CPC (kb. 235–330 Ft, 470 Ft/GBP nagyságrend). "
        "Magyar vásárlóerővel ez nem 250 Ft-os bázis, de azt mutatja: a specialty "
        "naptár-kattintás drágább, mint az átlagos Facebook-klik."
    )
    pdf.source("https://pro.ra.co/bump")
    pdf.source("https://support.ra.co/article/259-bump-promoted-event-listings-faqs")
    pdf.body(
        "Az Eventbrite Ads nem közöl dollárt listing-megtekintésenként; impressiont, klikket "
        "és ROAS-t riportol. Nincs ellentmondás: ők jegyeladást optimalizálnak, mi helyettesítési költséget."
    )
    pdf.source("https://www.eventbrite.com/organizer/features/eventbrite-ads/")
    pdf.callout(
        "Felső határ, amit nem lépünk át",
        "Infinety 700–750 Ft / CT és Mandiner 1200 Ft / CT listaárak. Ezek kiadói rate cardok, "
        "nem aukciós valóság. 200 Ft click-out még védhető felső; 250+ már listaáras logika, "
        "amit egy szervező, aki tényleg hirdet, azonnal lebont.",
    )
    pdf.source("https://www.infinety.hu/upload/files/infinety_mediaajanlat.pdf")
    pdf.source("https://mandiner.hu/uploads/Mandiner_Radelit_Sales_online_arlista.pdf")


def chapter_8(pdf: MediaValuePdf) -> None:
    pdf.h1("8. Piacvezető programfelület")
    pdf.body(
        "A magyar piacvezető jegyportál heti kiemelést árul, nem akciós mikroárat. "
        "A Jegy.hu 2026-os médiaajánlata:"
    )
    pdf.bullet("Slider: 180 000 Ft + áfa / hét")
    pdf.bullet("Kiemelt: 150 000 Ft + áfa / hét")
    pdf.bullet("Normál 1–4. sor: 100 000–60 000 Ft + áfa / hét")
    pdf.source("https://www.jegy.hu/articles/758/jegyhu-mediaajanlat-2026")
    pdf.body(
        "Ez azt mutatja: a specialty programfelület nem a legolcsóbb Facebook-CPC-n "
        "értékesít. A mi egységárunk a statisztikai elszámoláshoz kell (megtekintés / kattintás), "
        "ezért buy-side bázisú, de a felső magyar sávban."
    )
    pdf.body("A piacvezetőség indoka tehát nem ötszörös szorzó, hanem:")
    pdf.bullet("szándékos közönség — eseményt böngészik, nem hideg feedet;")
    pdf.bullet("specialty inventory — kevés valódi helyettesítő;")
    pdf.bullet("a tölcsér alján mért, emberi, botmentes akció.")
    pdf.body(
        "A Resident Advisor marketingoldala ugyanezt a specialty pozíciót árulja: "
        "magas vásárlási szándékú naptárböngészők, CPC-alapú kiemelés, minimális kampány £100 / €100 / $100."
    )
    pdf.source("https://pro.ra.co/bump")
    pdf.source("https://pro.ra.co/marketing")


def chapter_9(pdf: MediaValuePdf) -> None:
    pdf.h1("9. Gyakori kifogások és válaszok")

    pdf.h2("„Facebookon 50 Ft a klikk, miért 150?”")
    pdf.body(
        "Az 50 Ft hideg vagy széles traffic-kampány. Itt a user már a programnaptárban van, "
        "és a szervező saját eseményére lép. A magyar conversion / Search szórakoztatás "
        "60–150 Ft; a 150 Ft ennek a teteje, nem US-ár."
    )
    pdf.source("https://amarketingese.hu/facebook-hirdetes-ara-2026/")
    pdf.source("https://marketing-consulting.hu/mennyibe-kerul-a-google-ads-2025-ben/")

    pdf.h2("„Az előnézet csak egy popup, annak nincs értéke.”")
    pdf.body(
        "A felhasználó szándékosan kinyitja, és tartalmat lát (kép, helyszín, szervező, stílus). "
        "A magyar viewable megjelenés 3,5–13 Ft; az önkéntes kinyitás fölötte van. "
        "12 Ft a DPV kb. 11 százaléka."
    )
    pdf.source("https://mediaworks.hu/wp-content/uploads/2026/07/5251395Mediaworksonlinetarifa0722.pdf")
    pdf.source("https://www.infinety.hu/upload/files/infinety_mediaajanlat.pdf")

    pdf.h2("„Háromszor számoljátok ugyanazt a usert.”")
    pdf.body(
        "Három különböző inventory-szint. Kérésre az előnézet csak a nem továbbkattintó "
        "usereken számít. Az oldal és a további infó külön akciós helyettesítés. "
        "Az alapriportban mindhárom külön soron látszik, nincs elrejtett szorzó."
    )

    pdf.h2("„A PR-szakma szerint az AVE érvénytelen.”")
    pdf.body(
        "Egyetértünk. Ezért nem AVE-t és nem 3–5× szorzót közlünk, hanem helyettesítési "
        "költséget, forrással. Az AMEC 5. elve pontosan ezt kéri: ne add el a költséget értéknek."
    )
    pdf.source("https://amecorg.com/2020/07/barcelona-principles-3-0/")
    pdf.source("https://amecorg.com/2017/06/the-definitive-guide-why-aves-are-invalid/")

    pdf.h2("„Muck Rack 0,37 dollár / olvasó, az 130 Ft, miért nem annyi az oldal?”")
    pdf.body(
        "Amerikai híroldal-AVE, nem magyar eseménynaptár. Magyar vásárlóerőn és aukción "
        "a 110 Ft a védhető. A 0,37 USD ráadásul AVE, amit az AMEC elutasít; csak "
        "ellenőrző sávnak idézzük, nem bázisnak."
    )
    pdf.source("https://help.muckrack.com/en/articles/9362689-advertising-value-equivalency-ave-in-coverage-reports")
    pdf.source("https://help.carma.com/article-metrics")

    pdf.h2("„RA-n 0,50–0,70 font a klikk.”")
    pdf.body(
        "Globális electronic-naptár, más jegyár, más vásárlóerő. Irány jelzi, hogy a specialty "
        "klikk drágább a Facebook-átlagnál; magyar defaultnak a 150 Ft az arányos."
    )
    pdf.source("https://support.ra.co/article/259-bump-promoted-event-listings-faqs")

    pdf.h2("„A Jegy.hu hetet árul, ti megtekintést.”")
    pdf.body(
        "Igen. Ők csomagot adnak el (60–180 ezer Ft / hét). Nekünk akciós egységár kell a "
        "statisztikához. A csomagár azt igazolja, hogy a specialty felület nem olcsó "
        "Facebook-CPC-n megy; az egységárunk ettől még buy-side marad, hogy a partner "
        "össze tudja vetni a saját hirdetési számlájával."
    )
    pdf.source("https://www.jegy.hu/articles/758/jegyhu-mediaajanlat-2026")


def chapter_10(pdf: MediaValuePdf) -> None:
    pdf.h1("10. Forrástábla és hivatkozásjegyzék")
    pdf.body("Az alábbi tábla a fő állításokat és a közvetlen URL-t köti össze. Minden link kattintható.")
    pdf.kv_table(
        ["Állítás", "Szám", "Forrás URL"],
        [
            ["Magyar Meta traffic CPC", "40–75 Ft", "https://amarketingese.hu/facebook-hirdetes-ara-2026/"],
            ["Magyar Meta CPC 2024 sáv", "30–150 Ft", "https://www.klikkmania.hu/ppc-hirdetes/facebook-hirdetes-arak-2024-ben/"],
            ["Google HU, művészet/szórakozás", "60–150 Ft", "https://marketing-consulting.hu/mennyibe-kerul-a-google-ads-2025-ben/"],
            ["Google HU, hirdetők többsége", "55–303 Ft", "https://prjr.hu/google-ads-hirdetes-arak-mennyibe-kerul-a-google-hirdetes/"],
            ["Magyar eseménykampány CPC", "71 Ft", "https://www.jacsomedia.hu/sportesemeny-marketing-esettanulmany-balatonfondo-2026/"],
            ["HU display / viewable AV", "2,5–13 Ft", "https://mediaworks.hu/wp-content/uploads/2026/07/5251395Mediaworksonlinetarifa0722.pdf"],
            ["Infinety AV / CT listaár", "5,5–6 / 700–750 Ft", "https://www.infinety.hu/upload/files/infinety_mediaajanlat.pdf"],
            ["Jegy.hu heti kiemelés", "60–180 ezer Ft", "https://www.jegy.hu/articles/758/jegyhu-mediaajanlat-2026"],
            ["US Entertainment CPC", "0,39 USD", "https://www.stackmatix.com/blog/facebook-ads-cost-complete-guide"],
            ["Arts & Entertainment CPC", "0,43–0,49 USD", "https://www.webtonic.io/blog/facebook-ads-benchmarks"],
            ["Iparági AVE / olvasó", "0,37 USD", "https://help.carma.com/article-metrics"],
            ["RA Bumps példa CPC", "0,50–0,70 GBP", "https://support.ra.co/article/259-bump-promoted-event-listings-faqs"],
        ],
        col_widths=(48, 32, 94),
        url_col=2,
    )
    pdf.h2("Teljes hivatkozásjegyzék")
    refs = [
        "AMEC. Barcelona Principles 3.0. 2020. https://amecorg.com/2020/07/barcelona-principles-3-0/",
        "AMEC. The Definitive Guide: Why AVEs are invalid. 2017. https://amecorg.com/2017/06/the-definitive-guide-why-aves-are-invalid/",
        "AMEC. Barcelona Principles 3.0 webinar prezentáció. 2020. https://amecorg.com/wp-content/uploads/2020/07/BP-Presentation-3.0-AMEC-webinar-10.07.20.pdf",
        "Meta. About landing page view optimization. https://www.facebook.com/business/help/417293491972212",
        "aMarketingese. Facebook hirdetés ára 2026. 2026. május 15. https://amarketingese.hu/facebook-hirdetes-ara-2026/",
        "Klikkmania. Facebook hirdetés árak 2024-ben. 2024. október 9. https://www.klikkmania.hu/ppc-hirdetes/facebook-hirdetes-arak-2024-ben/",
        "Marketing Consulting. Mennyibe kerül a Google Ads 2025-ben? 2025. június 20. https://marketing-consulting.hu/mennyibe-kerul-a-google-ads-2025-ben/",
        "PRJR. Google Ads hirdetés árak 2025-ben. https://prjr.hu/google-ads-hirdetes-arak-mennyibe-kerul-a-google-hirdetes/",
        "SocialPro. Google Ads költségek 2026. https://www.socialpro.hu/google-ads-kampanyok-koltsegei/",
        "Webma. Google Ads árak 2026-ban. https://webma.hu/blog/google-ads-arak-2026-ban-kattintasi-dijak-kampanykezelesi-arak-es-realis-havi-budzse-magyarorszagon/",
        "Jacsomedia. Sportesemény marketing esettanulmány, Balatonfondo 2026. https://www.jacsomedia.hu/sportesemeny-marketing-esettanulmany-balatonfondo-2026/",
        "Infinety. Médiaajánlat 2026. https://www.infinety.hu/upload/files/infinety_mediaajanlat.pdf",
        "Mediaworks. Online árlista. 2026. július. https://mediaworks.hu/wp-content/uploads/2026/07/5251395Mediaworksonlinetarifa0722.pdf",
        "Telex Sales. Hirdetési árlista, 2026. szeptember 1-től. https://sales.telex.hu/wp-content/uploads/2026/07/AKTUALIS_HIRDETESI_ARLISTA_2026_09.pdf",
        "Jegy.hu. Jegy.hu Médiaajánlat – 2026. https://www.jegy.hu/articles/758/jegyhu-mediaajanlat-2026",
        "Radelit / Mandiner. Online árlista. https://mandiner.hu/uploads/Mandiner_Radelit_Sales_online_arlista.pdf",
        "Stackmatix. Facebook Ads Cost Complete Guide 2026. https://www.stackmatix.com/blog/facebook-ads-cost-complete-guide",
        "Web Tonic. Facebook Ads Benchmarks by Industry. https://www.webtonic.io/blog/facebook-ads-benchmarks",
        "Venuera. Do Facebook & Google Ads Sell Event Tickets? https://venuera.com/facebook-google-ads-for-events-benchmark-data/",
        "CARMA. Article Metrics. https://help.carma.com/article-metrics",
        "Muck Rack. Advertising Value Equivalency (AVE) in Coverage Reports. https://help.muckrack.com/en/articles/9362689-advertising-value-equivalency-ave-in-coverage-reports",
        "Resident Advisor. Sell more tickets with RA Bumps. https://pro.ra.co/bump",
        "RA Pro Support. Bump (promoted event listings) FAQs. https://support.ra.co/article/259-bump-promoted-event-listings-faqs",
        "RA Pro. Marketing. https://pro.ra.co/marketing",
        "Eventbrite. Event Advertising Platform. https://www.eventbrite.com/organizer/features/eventbrite-ads/",
        "Eventbrite Help. Understand the performance of your Eventbrite Ads campaign. https://www.eventbrite.se/help/sv/articles/692484/understand-the-performance-of-your-eventbrite-ads-campaign/",
        "Optimalizált. Facebook hirdetés árak 2025-ben. (negatív kontroll: globális USD-sáv, nem magyar bázis.) https://optimalizalt.hu/facebook-hirdetes-arak-2025-ben/",
    ]
    for i, ref in enumerate(refs, start=1):
        url = ""
        if "https://" in ref:
            url = "https://" + ref.split("https://", 1)[1].strip()
        pdf.set_font("Calibri", "", 8.5)
        pdf.set_text_color(*INK)
        label = f"[{i}] {ref}"
        if url:
            pdf.set_x(pdf.l_margin)
            pdf.write(4.3, f"[{i}] ")
            pdf.write(4.3, ref.split("https://")[0])
            pdf.set_text_color(*LINK)
            pdf.set_font("Calibri", "I", 8)
            pdf.write(4.3, url, url)
            pdf.set_text_color(*INK)
            pdf.set_font("Calibri", "", 8.5)
            pdf.ln(4.8)
        else:
            pdf.multi_cell(0, 4.3, label)
            pdf.ln(0.4)
    pdf.ln(2)


def chapter_11(pdf: MediaValuePdf) -> None:
    pdf.h1("11. Záradék")
    pdf.body(
        "A 12 / 110 / 150 Ft becsült helyettesítési költség, 2026. szeptemberi nyilvános "
        "magyar és nemzetközi források alapján, piacvezető specialty naptárra. "
        "Nem számla, nem jegybevétel, nem garancia."
    )
    pdf.body(
        "A sávon belüli eltérés kampánycéltól, szezontól és célzástól függ. "
        "A default a sáv védhető közepe, nem a maximuma."
    )
    pdf.body(
        "Ha a partner saját, auditált magyar Meta/Google CPC-t mutat ugyanerre a közönségre, "
        "az egységár attól a méréstől újratárgyalható. Addig a fenti források a nyilvános indoklás."
    )
    pdf.callout(
        "Röviden, ha csak egy bekezdést olvasnak el",
        "Előnézet 12 Ft, mert önkéntes kinyitás, de nem teljes oldal. "
        "Eseményoldal 110 Ft, mert magyar minőségi landing page view specialty naptáron. "
        "További infó 150 Ft, mert ez a szervező által megvásárolható intent click. "
        "Nincs AVE-szorzó. Minden szám mellett ott a forrás.",
    )
    pdf.set_font("Calibri", "I", 9)
    pdf.set_text_color(*MUTED)
    pdf.multi_cell(
        0,
        5,
        "Latinfo.hu  ·  Médiaérték-módszertan v1.0  ·  2026. szeptember 20.",
    )


def main() -> None:
    pdf = MediaValuePdf()
    cover(pdf)
    chapter_1(pdf)
    chapter_2(pdf)
    chapter_3(pdf)
    chapter_4(pdf)
    chapter_5(pdf)
    chapter_6(pdf)
    chapter_7(pdf)
    chapter_8(pdf)
    chapter_9(pdf)
    chapter_10(pdf)
    chapter_11(pdf)
    pdf.output(str(OUT_PATH))
    print(f"Wrote {OUT_PATH} ({OUT_PATH.stat().st_size} bytes, {pdf.pages_count} pages)")


if __name__ == "__main__":
    main()
