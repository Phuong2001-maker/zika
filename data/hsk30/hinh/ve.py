"""Vẽ kho hình minh hoạ cho đề Thi thử HSK 3.0 → web/static/hinh-hsk/<mã>.svg.

  python data/hsk30/hinh/ve.py

Một phong cách cho cả bộ để khớp giao diện Zìkǎ: khung 160×120 bo góc 14, nền màu nhạt theo nhóm, nét đậm 3px
bo tròn màu mực #2B2F38, tô màu theo bảng màu của web (son, ngọc, trời, vàng…). Không dùng chữ trong hình (máy người
học có thể thiếu phông chữ Hán). Danh sách mã phải khớp web/src/lib/learn/thi-hsk/hinh.ts.
"""
from __future__ import annotations

import math
import re
import sys
from pathlib import Path

GOC = Path(__file__).resolve().parents[3]
RA = GOC / "web/static/hinh-hsk"

MUC = "#2B2F38"
SON, NGOC, TROI, VANG, CAM, TIM, NAU, XAM = "#E5484D", "#16A34A", "#3B82F6", "#F5B829", "#F97316", "#8B5CF6", "#A16207", "#9AA3B2"
DA, TOC, TRANG, KEM = "#F7D2B0", "#3A2E2A", "#FFFFFF", "#FFF6E5"
NEN = {
    "an": "#FFF1E0", "vat": "#EAF2FF", "xe": "#E7F6EE", "noi": "#F1ECFF", "troi": "#E3F2FF",
    "vat-nuoi": "#FFF4D6", "nguoi": "#FFECEA", "lam": "#FFF7E6", "gio": "#F2F3F5",
}


def svg(nen: str, ruot: str) -> str:
    return (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" width="160" height="120">'
        f'<rect x="2" y="2" width="156" height="116" rx="14" fill="{NEN[nen]}"/>'
        f'<g fill="none" stroke="{MUC}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">{ruot}</g></svg>'
    )


# ---------------------------------------------------------------- người

def dau(x: float, y: float, r: float = 12, mat: str = "vui", toc: str = TOC) -> str:
    """Đầu tròn, tóc, mắt, miệng. mat: vui | cuoi | buon | ngu."""
    s = f'<circle cx="{x}" cy="{y}" r="{r}" fill="{DA}"/>'
    s += f'<path d="M{x - r} {y - 1} a{r} {r} 0 0 1 {2 * r} 0 q{-r} {-r * 0.55} {-2 * r} 0z" fill="{toc}"/>'
    if mat == "ngu":
        s += f'<path d="M{x - 6} {y + 2} q2 2 4 0 M{x + 2} {y + 2} q2 2 4 0" stroke-width="2"/>'
    else:
        s += f'<circle cx="{x - 4.5}" cy="{y + 2}" r="1.4" fill="{MUC}" stroke="none"/><circle cx="{x + 4.5}" cy="{y + 2}" r="1.4" fill="{MUC}" stroke="none"/>'
    if mat == "vui":
        s += f'<path d="M{x - 4} {y + 6.5} q4 3.5 8 0" stroke-width="2"/>'
    elif mat == "cuoi":
        s += f'<path d="M{x - 6} {y + 5.5} q6 7 12 0z" fill="{SON}" stroke-width="2"/>'
    elif mat == "buon":
        s += f'<path d="M{x - 4} {y + 8.5} q4 -3.5 8 0" stroke-width="2"/>'
    return s


def nguoi(x: float = 80, ao: str = TROI, tay: str = "xuong", chan: str = "dung", mat: str = "vui", cao: float = 0, quan: str = "#475569") -> str:
    """Người đứng, chân chạm y≈104. tay: xuong | truoc | phai-len | hai-len | vay | cam-mic. chan: dung | buoc | chay | da."""
    y = cao
    s = ""
    chan_ds = {
        "dung": [(x - 6, 80, x - 8, 104), (x + 6, 80, x + 8, 104)],
        "buoc": [(x - 5, 80, x - 14, 103), (x + 5, 80, x + 10, 104)],
        "chay": [(x - 5, 80, x - 18, 98), (x + 5, 80, x + 14, 104)],
        "da": [(x - 5, 80, x - 8, 104), (x + 5, 80, x + 22, 92)],
    }[chan]
    for a, b, c, d in chan_ds:
        s += f'<path d="M{a} {b + y} L{c} {d + y}" stroke="{quan}" stroke-width="5"/>'
    s += f'<rect x="{x - 12}" y="{48 + y}" width="24" height="34" rx="9" fill="{ao}"/>'
    l, r = x - 11, x + 11
    tay_ds = {
        "xuong": [f"M{l} {54 + y} L{x - 20} {76 + y}", f"M{r} {54 + y} L{x + 20} {76 + y}"],
        "truoc": [f"M{l} {55 + y} Q{x - 14} {70 + y} {x - 3} {66 + y}", f"M{r} {55 + y} Q{x + 14} {70 + y} {x + 3} {66 + y}"],
        "phai-len": [f"M{l} {54 + y} L{x - 20} {76 + y}", f"M{r} {54 + y} Q{x + 22} {44 + y} {x + 10} {38 + y}"],
        "hai-len": [f"M{l} {54 + y} L{x - 22} {30 + y}", f"M{r} {54 + y} L{x + 22} {30 + y}"],
        "vay": [f"M{l} {54 + y} L{x - 22} {66 + y}", f"M{r} {54 + y} L{x + 22} {44 + y}"],
        "cam-mic": [f"M{l} {54 + y} L{x - 20} {76 + y}", f"M{r} {55 + y} Q{x + 14} {62 + y} {x + 6} {50 + y}"],
    }[tay]
    for d in tay_ds:
        s += f'<path d="{d}" stroke="{DA}" stroke-width="5"/>'
    s += dau(x, 34 + y, mat=mat)
    return s


def ban_truoc(x: float = 80, y: float = 82, rong: float = 90) -> str:
    """Mặt bàn che nửa dưới người ngồi."""
    return (f'<rect x="{x - rong / 2}" y="{y}" width="{rong}" height="8" rx="3" fill="{NAU}"/>'
            f'<path d="M{x - rong / 2 + 8} {y + 8} L{x - rong / 2 + 8} 108 M{x + rong / 2 - 8} {y + 8} L{x + rong / 2 - 8} 108"/>')


def not_nhac(x: float, y: float, mau: str = TIM) -> str:
    return f'<path d="M{x} {y} L{x} {y - 14} L{x + 9} {y - 17} L{x + 9} {y - 3}" stroke="{mau}"/><circle cx="{x - 3}" cy="{y}" r="3.2" fill="{mau}" stroke="{mau}"/><circle cx="{x + 6}" cy="{y - 3}" r="3.2" fill="{mau}" stroke="{mau}"/>'


def hoi_nuoc(x: float, y: float) -> str:
    return "".join(f'<path d="M{x + dx} {y} q-4 -5 0 -10 q4 -5 0 -10" stroke="{XAM}" stroke-width="2.5"/>' for dx in (-6, 0, 6))


# ---------------------------------------------------------------- đồ ăn uống

def bat(x: float, y: float, mau: str = TRANG, rong: float = 44) -> str:
    sau = rong * 0.42  # bát nông/sâu theo bề rộng: bát nhỏ ở biển hiệu không thành hình chiếc răng
    return f'<path d="M{x - rong / 2} {y} h{rong} q-3 {sau} -{rong / 2} {sau} q-{rong / 2 - 3} 0 -{rong / 2} -{sau}z" fill="{mau}"/>'


def dua(x: float, y: float) -> str:
    return f'<path d="M{x} {y} L{x + 38} {y - 26} M{x + 6} {y + 2} L{x + 42} {y - 22}" stroke="{NAU}"/>'


HINH: dict[str, tuple[str, str]] = {}


def hinh(ma: str, nen: str):
    def dk(f):
        HINH[ma] = (nen, f())
        return f
    return dk


@hinh("tao", "an")
def _():
    return (f'<path d="M80 40 c-22 -14 -44 2 -38 30 c4 22 22 36 38 28 c16 8 34 -6 38 -28 c6 -28 -16 -44 -38 -30z" fill="{SON}"/>'
            f'<path d="M80 40 q2 -12 8 -18"/><path d="M84 30 q14 -12 24 -2 q-12 10 -24 2z" fill="{NGOC}"/>'
            f'<path d="M58 56 q2 -8 8 -11" stroke="{TRANG}" stroke-width="3"/>')


@hinh("cha", "an")
def _():
    return (f'<path d="M30 60 h46 q-2 30 -23 30 q-21 0 -23 -30z" fill="{TRANG}"/>'
            f'<path d="M34 68 h38" stroke="#7FB069"/>'
            f'<path d="M92 50 h40 l-4 40 h-32z" fill="#7FB069"/><path d="M90 50 h44"/><path d="M132 60 q14 4 6 18 l-8 2"/>'
            f'<path d="M96 50 q16 -14 32 0" fill="#7FB069"/><circle cx="112" cy="40" r="3" fill="{MUC}" stroke="none"/>' + hoi_nuoc(53, 54))


@hinh("ca-phe", "an")
def _():
    return (f'<path d="M50 44 h50 v38 q0 14 -14 14 h-22 q-14 0 -14 -14z" fill="{TRANG}"/><path d="M100 54 q20 0 14 18 q-4 8 -14 8"/>'
            f'<ellipse cx="75" cy="47" rx="23" ry="4" fill="#5B3A1E" stroke="none"/>'
            + "".join(f'<ellipse cx="{cx}" cy="{cy}" rx="7" ry="5" transform="rotate(-25 {cx} {cy})" fill="#6B4423"/><path d="M{cx - 4} {cy + 2} q4 -3 8 -4" stroke="{KEM}" stroke-width="1.5"/>'
                      for cx, cy in ((126, 92), (36, 92)))
            + hoi_nuoc(75, 38))


@hinh("com", "an")
def _():
    return (bat(80, 62, TRANG, 64) + '<path d="M50 62 q30 -26 60 0" fill="#FFFFFF"/>'
            + "".join(f'<ellipse cx="{cx}" cy="{cy}" rx="3" ry="2" fill="{KEM}" stroke-width="1.5"/>' for cx, cy in ((64, 54), (74, 48), (86, 49), (96, 55), (80, 56)))
            + dua(96, 44) + f'<path d="M58 72 h44" stroke="{TROI}" stroke-width="2.5"/>')


@hinh("mi", "an")
def _():
    return (bat(80, 64, "#FDE68A", 66) + '<path d="M50 64 h60" />'
            + f'<path d="M86 64 q-4 -10 2 -18 q6 -8 0 -16 M94 64 q-4 -10 2 -18 q6 -8 0 -16" stroke="#E2B04A" stroke-width="3"/>'
            + f'<path d="M80 26 L112 14 M84 30 L116 18" stroke="{NAU}"/><circle cx="64" cy="58" r="5" fill="{NGOC}"/>')


@hinh("nuoc", "an")
def _():
    return (f'<path d="M58 22 h44 l-6 80 h-32z" fill="{TRANG}"/><path d="M61 48 h38 l-4.5 54 h-29z" fill="#9CC9FF" stroke="none"/>'
            f'<path d="M58 22 h44 l-6 80 h-32z"/><path d="M68 60 v20" stroke="{TRANG}" stroke-width="3"/>'
            f'<path d="M122 40 q8 12 0 18 q-8 -6 0 -18z" fill="{TROI}"/>')


@hinh("sua", "an")
def _():
    return (f'<path d="M58 40 l12 -16 h24 l12 16 v62 h-48z" fill="{TRANG}"/><path d="M58 40 h48"/><path d="M70 24 l-2 -6 h28 l-2 6" fill="{TROI}"/>'
            f'<rect x="64" y="56" width="36" height="28" rx="6" fill="{TROI}"/><path d="M74 72 q8 -12 16 0 q-8 8 -16 0z" fill="{TRANG}" stroke="{TRANG}"/>')


@hinh("trung", "an")
def _():
    return (f'<ellipse cx="62" cy="68" rx="20" ry="26" fill="{KEM}"/><ellipse cx="100" cy="72" rx="20" ry="24" fill="{TRANG}"/>'
            f'<path d="M30 96 h100" stroke="{NAU}"/><path d="M54 52 q4 -6 10 -6" stroke="{TRANG}" stroke-width="3"/>')


@hinh("ca", "an")
def _():
    return (f'<path d="M30 60 q30 -34 76 0 q-46 34 -76 0z" fill="{CAM}"/><path d="M104 60 l26 -18 v36z" fill="{CAM}"/>'
            f'<circle cx="48" cy="56" r="3" fill="{MUC}" stroke="none"/><path d="M62 46 q8 14 0 28 M76 44 q8 16 0 32" stroke="#FDBA74" stroke-width="2.5"/>'
            f'<circle cx="24" cy="44" r="3"/><circle cx="18" cy="34" r="2"/>')


@hinh("dua-hau", "an")
def _():
    return (f'<path d="M24 46 h112 q-6 52 -56 52 q-50 0 -56 -52z" fill="{NGOC}"/><path d="M30 46 h100 q-6 42 -50 42 q-44 0 -50 -42z" fill="#F87171"/>'
            + "".join(f'<ellipse cx="{cx}" cy="{cy}" rx="2" ry="3.4" fill="{MUC}" stroke="none"/>' for cx, cy in ((56, 58), (72, 66), (88, 66), (104, 58), (80, 76), (64, 54), (96, 54))))


@hinh("banh-ngot", "an")
def _():
    return (f'<rect x="38" y="58" width="84" height="40" rx="8" fill="#FBCFE8"/><path d="M38 70 q10 10 21 0 q10 10 21 0 q10 10 21 0 q10 10 21 0" fill="{TRANG}"/>'
            f'<path d="M30 98 h100"/><rect x="76" y="38" width="8" height="20" rx="2" fill="{TROI}"/><path d="M80 24 q-6 6 0 12 q6 -6 0 -12z" fill="{VANG}"/>'
            f'<circle cx="56" cy="88" r="3" fill="{SON}" stroke="none"/><circle cx="104" cy="88" r="3" fill="{SON}" stroke="none"/>')


# ---------------------------------------------------------------- đồ vật

@hinh("sach", "vat")
def _():
    return (f'<path d="M80 34 q-24 -10 -48 0 v58 q24 -10 48 0z" fill="{TRANG}"/><path d="M80 34 q24 -10 48 0 v58 q-24 -10 -48 0z" fill="{TRANG}"/>'
            f'<path d="M80 34 v58"/><path d="M42 48 q14 -5 28 0 M42 58 q14 -5 28 0 M42 68 q14 -5 28 0 M90 48 q14 -5 28 0 M90 58 q14 -5 28 0 M90 68 q14 -5 28 0" stroke="{XAM}" stroke-width="2.5"/>'
            f'<path d="M28 98 q26 -10 52 0 q26 -10 52 0" stroke="{SON}"/>')


@hinh("but", "vat")
def _():
    return (f'<path d="M40 90 l64 -64 l12 12 l-64 64 l-16 4z" fill="{TROI}"/><path d="M40 90 l12 12" /><path d="M36 106 l4 -16 l12 12z" fill="{MUC}"/>'
            f'<path d="M96 34 l12 12" stroke="{TRANG}"/><path d="M104 26 l12 12" /><path d="M112 22 l8 -8 l6 6 l-8 8" fill="{SON}"/>')


@hinh("ban", "vat")
def _():
    return (f'<path d="M26 50 h108 l-8 12 h-92z" fill="{NAU}"/><path d="M40 62 v40 M120 62 v40 M50 62 v28 M110 62 v28" stroke="{NAU}" stroke-width="5"/>'
            f'<rect x="64" y="38" width="20" height="12" rx="2" fill="{TROI}"/><path d="M96 36 q4 -8 12 -6 v14 h-12z" fill="{NGOC}"/>')


@hinh("ghe", "vat")
def _():
    return (f'<rect x="56" y="18" width="48" height="44" rx="8" fill="{CAM}"/><rect x="50" y="62" width="60" height="12" rx="4" fill="{CAM}"/>'
            f'<path d="M58 74 v30 M102 74 v30" stroke="{NAU}" stroke-width="5"/><path d="M64 30 h32 M64 42 h32" stroke="#FDBA74" stroke-width="2.5"/>')


@hinh("dien-thoai", "vat")
def _():
    return (f'<rect x="58" y="14" width="44" height="92" rx="9" fill="{MUC}"/><rect x="63" y="24" width="34" height="66" rx="3" fill="#BFDBFE" stroke="none"/>'
            f'<path d="M74 18 h12" stroke="{XAM}" stroke-width="2"/><circle cx="80" cy="98" r="3" stroke="{XAM}" stroke-width="2"/>'
            f'<rect x="68" y="32" width="10" height="10" rx="2" fill="{NGOC}" stroke="none"/><rect x="82" y="32" width="10" height="10" rx="2" fill="{SON}" stroke="none"/><rect x="68" y="46" width="10" height="10" rx="2" fill="{VANG}" stroke="none"/>')


@hinh("may-tinh", "vat")
def _():
    return (f'<rect x="40" y="24" width="80" height="54" rx="5" fill="{MUC}"/><rect x="46" y="30" width="68" height="42" rx="2" fill="#BFDBFE" stroke="none"/>'
            f'<path d="M28 92 h104 l-10 -14 h-84z" fill="{XAM}"/><path d="M70 86 h20" stroke="{MUC}" stroke-width="2"/>'
            f'<path d="M54 40 h30 M54 50 h44 M54 60 h22" stroke="{TROI}" stroke-width="3"/>')


@hinh("ti-vi", "vat")
def _():
    return (f'<rect x="28" y="22" width="104" height="66" rx="8" fill="{MUC}"/><rect x="35" y="29" width="90" height="52" rx="3" fill="#93C5FD" stroke="none"/>'
            f'<path d="M64 88 l-8 14 M96 88 l8 14 M50 102 h60"/><path d="M60 22 l-12 -14 M100 22 l12 -14"/>'
            f'<path d="M50 70 l18 -22 l12 14 l10 -8 l22 16z" fill="{NGOC}" stroke="none"/><circle cx="104" cy="44" r="6" fill="{VANG}" stroke="none"/>')


@hinh("o", "vat")
def _():
    return (f'<path d="M24 62 q56 -60 112 0 q-9 -8 -18 0 q-10 -8 -20 0 q-9 -8 -18 0 q-10 -8 -20 0 q-9 -8 -18 0 q-9 -8 -18 0z" fill="{SON}"/>'
            f'<path d="M80 62 v34 q0 10 -10 10 q-8 0 -8 -8"/><path d="M80 18 v-6"/><path d="M80 18 q-14 16 -16 44 M80 18 q14 16 16 44" stroke="#FCA5A5" stroke-width="2.5"/>')


@hinh("coc", "vat")
def _():
    return (f'<path d="M50 34 h56 l-6 64 q-2 6 -8 6 h-28 q-6 0 -8 -6z" fill="{TRANG}"/><path d="M53 50 h50" stroke="{XAM}" stroke-width="2"/>'
            f'<path d="M66 60 v28" stroke="#DBEAFE" stroke-width="4"/><path d="M40 104 h76" stroke="{XAM}"/>')


@hinh("ao", "vat")
def _():
    return (f'<path d="M58 22 q22 14 44 0 l30 16 l-12 22 l-14 -6 v54 h-52 v-54 l-14 6 l-12 -22z" fill="{TROI}"/>'
            f'<path d="M66 22 q14 10 28 0" stroke="{TRANG}" stroke-width="2.5"/><rect x="88" y="60" width="12" height="10" rx="2" stroke="{TRANG}" stroke-width="2"/>')


@hinh("giay", "vat")
def _():
    return (f'<path d="M26 92 v-26 q0 -8 8 -8 h22 q8 0 12 8 q10 14 40 18 q22 4 24 14 v4 h-106z" fill="{SON}"/>'
            f'<path d="M26 92 h106 v8 h-106z" fill="{TRANG}"/><path d="M60 66 l10 -4 M66 72 l10 -4 M72 78 l10 -4" stroke="{TRANG}" stroke-width="2.5"/>')


@hinh("mu", "vat")
def _():
    return (f'<path d="M36 76 q0 -42 44 -42 q44 0 44 42z" fill="{NGOC}"/><path d="M28 76 h112 q-6 12 -40 12 h-72z" fill="#15803D"/>'
            f'<path d="M80 34 v42" stroke="#86EFAC" stroke-width="2.5"/><circle cx="80" cy="32" r="4" fill="{NGOC}"/>')


@hinh("tui", "vat")
def _():
    return (f'<path d="M58 50 q0 -26 22 -26 q22 0 22 26"/><rect x="38" y="50" width="84" height="52" rx="10" fill="{TIM}"/>'
            f'<path d="M38 66 h84" stroke="#C4B5FD"/><rect x="72" y="60" width="16" height="12" rx="3" fill="{VANG}"/>')


@hinh("ve", "vat")
def _():
    return (f'<path d="M24 38 h112 v14 q-8 0 -8 8 q0 8 8 8 v14 h-112 v-14 q8 0 8 -8 q0 -8 -8 -8z" fill="{VANG}"/>'
            f'<path d="M104 40 v40" stroke-dasharray="4 5" stroke="{NAU}"/><path d="M36 52 h50 M36 62 h36 M36 72 h44" stroke="{NAU}" stroke-width="2.5"/>'
            f'<circle cx="120" cy="60" r="7" stroke="{NAU}" stroke-width="2.5"/>')


@hinh("tien", "vat")
def _():
    return (f'<rect x="26" y="40" width="86" height="46" rx="6" fill="#86EFAC" transform="rotate(-8 69 63)"/>'
            f'<rect x="34" y="46" width="86" height="46" rx="6" fill="#4ADE80"/><circle cx="77" cy="69" r="12" stroke="#166534"/><path d="M77 61 v16 M72 65 q5 -4 10 0 q-10 4 0 8 q-5 4 -10 0" stroke="#166534" stroke-width="2"/>'
            f'<circle cx="124" cy="90" r="12" fill="{VANG}"/><circle cx="124" cy="90" r="6" stroke="{NAU}" stroke-width="2"/>')


# ---------------------------------------------------------------- xe

def banh(x: float, y: float, r: float = 9) -> str:
    return f'<circle cx="{x}" cy="{y}" r="{r}" fill="{MUC}"/><circle cx="{x}" cy="{y}" r="{r / 2.6}" fill="{XAM}" stroke="none"/>'


@hinh("xe-buyt", "xe")
def _():
    return (f'<rect x="18" y="30" width="124" height="56" rx="10" fill="{VANG}"/>'
            + "".join(f'<rect x="{x}" y="38" width="18" height="18" rx="3" fill="#BFDBFE"/>' for x in (28, 52, 76, 100))
            + f'<rect x="124" y="38" width="12" height="38" rx="3" fill="#BFDBFE"/><path d="M18 66 h106" stroke="{CAM}"/>'
            + banh(44, 88) + banh(112, 88) + '<path d="M10 100 h140" stroke-width="2.5"/>')


@hinh("tau-hoa", "xe")
def _():
    return (f'<rect x="20" y="40" width="58" height="40" rx="6" fill="{SON}"/><rect x="82" y="40" width="58" height="40" rx="6" fill="{SON}"/>'
            f'<rect x="28" y="48" width="16" height="14" rx="2" fill="#BFDBFE"/><rect x="52" y="48" width="16" height="14" rx="2" fill="#BFDBFE"/>'
            f'<rect x="90" y="48" width="16" height="14" rx="2" fill="#BFDBFE"/><rect x="114" y="48" width="16" height="14" rx="2" fill="#BFDBFE"/>'
            + banh(36, 86, 7) + banh(62, 86, 7) + banh(98, 86, 7) + banh(124, 86, 7)
            + '<path d="M78 66 h4"/><path d="M8 96 h144" stroke-width="3"/><path d="M16 102 h8 M40 102 h8 M64 102 h8 M88 102 h8 M112 102 h8 M136 102 h8" stroke-width="2.5"/>')


@hinh("may-bay", "xe")
def _():
    return (f'<path d="M22 64 q0 -10 14 -10 h80 q20 0 26 10 q-6 10 -26 10 h-80 q-14 0 -14 -10z" fill="{TRANG}"/>'
            f'<path d="M66 56 l18 -30 h10 l-6 30z" fill="{TROI}"/><path d="M66 72 l18 30 h10 l-6 -30z" fill="{TROI}"/>'
            f'<path d="M30 56 l-6 -16 h8 l10 16z" fill="{TROI}"/>'
            + "".join(f'<circle cx="{x}" cy="62" r="3" fill="#BFDBFE" stroke-width="1.5"/>' for x in (54, 66, 78, 90, 102))
            + f'<path d="M128 60 q6 1 8 4" stroke="{TROI}"/>')


@hinh("taxi", "xe")
def _():
    return (f'<path d="M22 80 v-14 q0 -6 6 -8 l14 -4 l14 -18 h48 l16 18 l14 4 q6 2 6 8 v14z" fill="{VANG}"/>'
            f'<path d="M62 40 h18 v14 h-26z M86 40 h16 l12 14 h-28z" fill="#BFDBFE"/><rect x="66" y="24" width="28" height="12" rx="3" fill="{MUC}"/>'
            + "".join(f'<rect x="{68 + 6 * i}" y="{26 + (i % 2) * 4}" width="6" height="4" fill="{VANG}" stroke="none"/>' for i in range(4))
            + f'<path d="M22 68 h116" stroke="{MUC}" stroke-width="2"/>' + banh(46, 82) + banh(114, 82) + '<path d="M10 96 h140" stroke-width="2.5"/>')


@hinh("xe-dap", "xe")
def _():
    return (f'<circle cx="44" cy="80" r="22"/><circle cx="116" cy="80" r="22"/><circle cx="44" cy="80" r="3" fill="{MUC}"/><circle cx="116" cy="80" r="3" fill="{MUC}"/>'
            f'<path d="M44 80 L68 50 h40 L116 80 M68 50 L80 80 L108 50 M80 80 L44 80" stroke="{SON}" stroke-width="4"/>'
            f'<path d="M62 42 h14 M104 50 l-4 -12 h12" stroke="{MUC}" stroke-width="4"/>')


# ---------------------------------------------------------------- nơi chốn

def nha_khung(mau: str, mai: str) -> str:
    return (f'<path d="M30 54 L80 20 L130 54" fill="{mai}"/><rect x="38" y="52" width="84" height="52" fill="{mau}"/>')


@hinh("nha", "noi")
def _():
    return (nha_khung("#FDE68A", SON) + f'<rect x="70" y="72" width="20" height="32" rx="2" fill="{NAU}"/><circle cx="86" cy="88" r="1.5" fill="{MUC}"/>'
            f'<rect x="46" y="64" width="16" height="16" rx="2" fill="#BFDBFE"/><rect x="98" y="64" width="16" height="16" rx="2" fill="#BFDBFE"/>'
            f'<rect x="104" y="26" width="10" height="18" fill="{NAU}"/><path d="M14 104 h132" stroke="{NGOC}"/>')


@hinh("truong-hoc", "noi")
def _():
    return (f'<rect x="24" y="44" width="112" height="60" fill="#FECACA"/><path d="M20 44 h120 L80 22z" fill="{SON}"/>'
            + "".join(f'<rect x="{x}" y="56" width="16" height="14" rx="2" fill="#BFDBFE"/>' for x in (34, 58, 86, 110))
            + f'<rect x="70" y="78" width="20" height="26" fill="{NAU}"/><path d="M80 22 v-14"/><path d="M80 8 h18 v10 h-18" fill="{SON}"/>'
            f'<path d="M14 104 h132" stroke="{NGOC}"/>')


@hinh("benh-vien", "noi")
def _():
    return (f'<rect x="30" y="28" width="100" height="76" fill="{TRANG}"/><rect x="66" y="80" width="28" height="24" fill="#BFDBFE"/><path d="M80 80 v24"/>'
            f'<rect x="68" y="36" width="24" height="24" rx="3" fill="{SON}" stroke="{SON}"/><path d="M80 41 v14 M73 48 h14" stroke="{TRANG}" stroke-width="4"/>'
            + "".join(f'<rect x="{x}" y="{y}" width="14" height="12" rx="2" fill="#BFDBFE"/>' for x, y in ((38, 40), (108, 40), (38, 62), (108, 62)))
            + f'<path d="M14 104 h132" stroke="{NGOC}"/>')


@hinh("cua-hang", "noi")
def _():
    return (f'<rect x="30" y="50" width="100" height="54" fill="{TRANG}"/>'
            + "".join(f'<path d="M{30 + 20 * i} 34 h20 v16 q-10 8 -20 0z" fill="{SON if i % 2 == 0 else TRANG}"/>' for i in range(5))
            + f'<rect x="40" y="62" width="44" height="30" rx="2" fill="#BFDBFE"/><rect x="94" y="62" width="26" height="42" fill="{NAU}"/>'
            f'<circle cx="54" cy="80" r="5" fill="{SON}"/><circle cx="68" cy="82" r="5" fill="{VANG}"/><path d="M14 104 h132" stroke="{NGOC}"/>')


@hinh("nha-hang", "noi")
def _():
    return (f'<rect x="30" y="46" width="100" height="58" fill="#FDE68A"/><path d="M24 46 h112 l-8 -14 h-96z" fill="{SON}"/>'
            f'<circle cx="80" cy="66" r="15" fill="{TRANG}"/>' + bat(80, 64, KEM, 20)
            + f'<path d="M86 60 L97 49 M89 62 L100 51" stroke="{NAU}" stroke-width="2.5"/>'
            f'<rect x="40" y="82" width="22" height="22" fill="{NAU}"/><rect x="98" y="82" width="22" height="16" rx="2" fill="#BFDBFE"/><path d="M14 104 h132" stroke="{NGOC}"/>')


@hinh("san-bay", "noi")
def _():
    return (f'<rect x="28" y="70" width="104" height="34" fill="{TRANG}"/>'
            + "".join(f'<rect x="{x}" y="78" width="14" height="14" rx="2" fill="#BFDBFE"/>' for x in (38, 58, 78, 98))
            + f'<path d="M116 70 v-36"/><path d="M106 34 h20 l-4 -12 h-12z" fill="#BFDBFE"/>'
            f'<path d="M24 40 q0 -6 8 -6 h34 q10 0 13 6 q-3 6 -13 6 h-34 q-8 0 -8 -6z" fill="{TRANG}"/><path d="M46 36 l8 -12 h5 l-2 12 M46 44 l8 12 h5 l-2 -12" fill="{TROI}"/>'
            f'<path d="M14 104 h132" stroke="{XAM}"/>')


# ---------------------------------------------------------------- thời tiết

def may(x: float, y: float, mau: str = TRANG, k: float = 1) -> str:
    return (f'<path d="M{x - 30 * k} {y} q-14 0 -14 -12 q0 -14 16 -12 q4 -18 24 -14 q8 -12 22 -6 q14 4 12 18 q14 2 12 14 q-2 12 -14 12z" '
            f'fill="{mau}" transform="translate({x * (1 - k)} {y * (1 - k)}) scale({k})"/>')


@hinh("nang", "troi")
def _():
    tia = "".join(
        f'<path d="M{80 + 30 * math.cos(a):.1f} {60 + 30 * math.sin(a):.1f} L{80 + 44 * math.cos(a):.1f} {60 + 44 * math.sin(a):.1f}" stroke="{CAM}" stroke-width="4"/>'
        for a in [i * math.pi / 4 for i in range(8)]
    )
    return tia + f'<circle cx="80" cy="60" r="22" fill="{VANG}"/><circle cx="72" cy="56" r="2" fill="{MUC}" stroke="none"/><circle cx="88" cy="56" r="2" fill="{MUC}" stroke="none"/><path d="M72 66 q8 7 16 0" stroke-width="2.5"/>'


@hinh("mua", "troi")
def _():
    return may(84, 56, "#CBD5E1") + "".join(
        f'<path d="M{x} {y} q-4 7 0 10 q4 -3 0 -10z" fill="{TROI}" stroke="{TROI}" stroke-width="2"/>' for x, y in ((56, 70), (76, 76), (96, 70), (116, 78), (66, 92), (86, 96), (106, 92)))


@hinh("tuyet", "troi")
def _():
    bong = ""
    for x, y in ((56, 78), (84, 90), (112, 78), (70, 102), (100, 102)):
        bong += "".join(f'<path d="M{x + 7 * math.cos(a):.1f} {y + 7 * math.sin(a):.1f} L{x - 7 * math.cos(a):.1f} {y - 7 * math.sin(a):.1f}" stroke="{TROI}" stroke-width="2.2"/>'
                        for a in (0, math.pi / 3, 2 * math.pi / 3))
    return may(84, 54, "#E2E8F0") + bong


@hinh("am-u", "troi")
def _():
    return may(64, 58, "#CBD5E1") + may(104, 78, "#94A3B8") + f'<path d="M24 96 h40 M90 102 h44" stroke="{XAM}" stroke-width="2.5"/>'


@hinh("gio", "troi")
def _():
    return (f'<path d="M22 44 h70 q14 0 14 -12 q0 -10 -10 -10 q-8 0 -10 8" stroke="{TROI}" stroke-width="4"/>'
            f'<path d="M22 62 h96 q14 0 14 12 q0 10 -10 10 q-8 0 -10 -8" stroke="{TROI}" stroke-width="4"/>'
            f'<path d="M30 82 h44 q10 0 10 8 q0 8 -8 8" stroke="{TROI}" stroke-width="4"/>'
            f'<path d="M116 30 q14 -6 18 6 q-12 6 -18 -6z" fill="{NGOC}"/><path d="M128 96 q10 -10 18 0 q-10 8 -18 0z" fill="{VANG}"/>')


# ---------------------------------------------------------------- con vật

@hinh("meo", "vat-nuoi")
def _():
    return (f'<path d="M46 50 l-4 -30 l24 14 h28 l24 -14 l-4 30 q8 14 0 30 q-12 22 -34 22 q-22 0 -34 -22 q-8 -16 0 -30z" fill="{CAM}"/>'
            f'<path d="M48 26 l12 10 M112 26 l-12 10" stroke="#FDBA74" stroke-width="3"/>'
            f'<ellipse cx="66" cy="62" rx="4" ry="6" fill="{MUC}" stroke="none"/><ellipse cx="94" cy="62" rx="4" ry="6" fill="{MUC}" stroke="none"/>'
            f'<path d="M76 74 h8 l-4 5z" fill="#F472B6" stroke-width="2"/><path d="M80 79 q-5 6 -10 2 M80 79 q5 6 10 2" stroke-width="2"/>'
            f'<path d="M34 70 h22 M36 80 l20 -4 M126 70 h-22 M124 80 l-20 -4" stroke-width="2"/>')


@hinh("cho", "vat-nuoi")
def _():
    return (f'<path d="M50 44 q0 -22 30 -22 q30 0 30 22 v26 q0 30 -30 30 q-30 0 -30 -30z" fill="#FCD34D"/>'
            f'<path d="M50 34 q-20 -4 -18 22 q2 20 14 16 z M110 34 q20 -4 18 22 q-2 20 -14 16z" fill="{NAU}"/>'
            f'<circle cx="68" cy="56" r="4" fill="{MUC}" stroke="none"/><circle cx="92" cy="56" r="4" fill="{MUC}" stroke="none"/>'
            f'<ellipse cx="80" cy="74" rx="16" ry="12" fill="{KEM}"/><ellipse cx="80" cy="70" rx="6" ry="4" fill="{MUC}" stroke="none"/>'
            f'<path d="M80 74 v6 q-6 6 -10 0 M80 80 q6 6 10 0" stroke-width="2"/><path d="M78 84 q2 8 6 0" fill="#F87171" stroke-width="2"/>')


@hinh("chim", "vat-nuoi")
def _():
    return (f'<path d="M20 90 q60 -10 124 -4" stroke="{NAU}" stroke-width="5"/><path d="M110 86 q8 -8 16 -4" stroke="{NGOC}" stroke-width="3"/><path d="M118 82 q6 -10 14 -6 q-6 8 -14 6z" fill="{NGOC}"/>'
            f'<ellipse cx="74" cy="62" rx="28" ry="20" fill="{TROI}"/><circle cx="96" cy="48" r="13" fill="{TROI}"/>'
            f'<path d="M108 46 l10 4 l-10 4z" fill="{VANG}"/><circle cx="99" cy="45" r="2.2" fill="{MUC}" stroke="none"/>'
            f'<path d="M58 58 q14 -14 26 4 q-14 8 -26 -4z" fill="#93C5FD"/><path d="M46 62 l-16 -6 l4 14z" fill="{TROI}"/>'
            f'<path d="M70 80 v8 M80 80 v8" stroke="{CAM}"/>')


# ---------------------------------------------------------------- người

@hinh("bac-si", "nguoi")
def _():
    return (nguoi(70, TRANG, "xuong") + f'<path d="M64 52 q6 18 12 0" stroke="{XAM}" stroke-width="2.5"/><circle cx="70" cy="68" r="3" fill="{XAM}"/>'
            f'<rect x="104" y="40" width="30" height="30" rx="6" fill="{SON}" stroke="{SON}"/><path d="M119 46 v18 M110 55 h18" stroke="{TRANG}" stroke-width="5"/>')


@hinh("giao-vien", "nguoi")
def _():
    return (f'<rect x="14" y="16" width="86" height="56" rx="4" fill="#166534"/><path d="M14 72 h86" stroke="{NAU}" stroke-width="4"/>'
            f'<path d="M26 32 q10 -6 20 0 q10 6 20 0 M26 46 q10 -6 20 0 M26 58 h30" stroke="{TRANG}" stroke-width="2.5"/>'
            + nguoi(126, TIM, "vay") + f'<path d="M148 44 L96 24" stroke="{NAU}" stroke-width="2.5"/>')


@hinh("hoc-sinh", "nguoi")
def _():
    return (f'<rect x="88" y="50" width="22" height="30" rx="7" fill="{SON}"/><path d="M90 54 q10 -8 18 0" stroke="#FCA5A5" stroke-width="2.5"/>'
            + nguoi(78, TROI, "xuong") + f'<path d="M70 50 l10 12 M86 50 l-6 12" stroke="{SON}" stroke-width="3"/>')


@hinh("em-be", "nguoi")
def _():
    return (f'<path d="M70 104 l-2 -14 M90 104 l2 -14" stroke="#475569" stroke-width="5"/><rect x="66" y="64" width="28" height="28" rx="11" fill="{VANG}"/>'
            f'<path d="M67 70 l-12 10 M93 70 l12 -12" stroke="{DA}" stroke-width="5"/>' + dau(80, 46, 17, "cuoi")
            + f'<circle cx="66" cy="54" r="3" fill="#FCA5A5" stroke="none"/><circle cx="94" cy="54" r="3" fill="#FCA5A5" stroke="none"/>'
            f'<circle cx="114" cy="40" r="9" fill="{SON}"/><path d="M110 50 l2 10" stroke-width="2"/>')


# ---------------------------------------------------------------- hoạt động

@hinh("an-com", "lam")
def _():
    return nguoi(80, CAM, "truoc") + ban_truoc() + bat(80, 70, TRANG, 34) + '<path d="M66 70 q14 -12 28 0" fill="#FFFFFF"/>' + f'<path d="M88 66 L110 40 M92 68 L114 42" stroke="{NAU}"/>'


@hinh("uong-tra", "lam")
def _():
    return nguoi(70, NGOC, "phai-len") + f'<path d="M82 38 h18 q-1 14 -9 14 q-8 0 -9 -14z" fill="{TRANG}"/>' + hoi_nuoc(98, 32) + f'<path d="M86 42 h10" stroke="#7FB069"/>'


@hinh("doc-sach", "lam")
def _():
    return nguoi(80, TIM, "truoc") + f'<path d="M80 60 q-14 -6 -28 0 v20 q14 -6 28 0z M80 60 q14 -6 28 0 v20 q-14 -6 -28 0z" fill="{TRANG}"/><path d="M80 60 v20"/>'


@hinh("xem-tivi", "lam")
def _():
    return (f'<rect x="12" y="30" width="62" height="42" rx="5" fill="{MUC}"/><rect x="17" y="35" width="52" height="32" rx="2" fill="#93C5FD" stroke="none"/>'
            f'<path d="M30 72 l-4 10 M56 72 l4 10"/><path d="M28 60 l12 -12 l8 8 l6 -4 l12 8z" fill="{NGOC}" stroke="none"/>'
            + nguoi(116, SON, "xuong") + f'<rect x="92" y="78" width="48" height="16" rx="6" fill="{TIM}"/>')


@hinh("ngu", "lam")
def _():
    return (f'<rect x="20" y="70" width="120" height="16" rx="4" fill="{NAU}"/><path d="M24 86 v14 M136 86 v14" stroke="{NAU}" stroke-width="5"/>'
            f'<rect x="20" y="44" width="10" height="42" rx="3" fill="{NAU}"/><rect x="34" y="56" width="26" height="16" rx="7" fill="{TRANG}"/>'
            + dau(48, 52, 11, "ngu") + f'<path d="M58 56 h76 q6 0 6 8 v8 h-82z" fill="{TROI}"/>'
            f'<path d="M86 26 h12 l-12 12 h12 M108 16 h9 l-9 9 h9" stroke="{TIM}" stroke-width="2.5"/>')


@hinh("goi-dien", "lam")
def _():
    return nguoi(74, CAM, "phai-len") + f'<rect x="80" y="26" width="12" height="22" rx="3" fill="{MUC}"/>' + f'<path d="M100 26 q8 8 0 16 M108 20 q14 14 0 28" stroke="{TROI}" stroke-width="2.5"/>'


@hinh("viet-chu", "lam")
def _():
    return (nguoi(80, NGOC, "truoc") + ban_truoc() + f'<rect x="58" y="70" width="40" height="14" rx="1" fill="{TRANG}" transform="rotate(-4 78 77)"/>'
            f'<path d="M64 76 q4 -3 8 0 q4 3 8 0" stroke="{XAM}" stroke-width="2"/><path d="M88 74 L102 56" stroke="{TROI}" stroke-width="4"/>')


@hinh("chay-bo", "lam")
def _():
    return nguoi(84, SON, "vay", "chay") + f'<path d="M24 50 h24 M18 64 h26 M26 78 h20" stroke="{XAM}" stroke-width="2.5"/><path d="M14 106 h132" stroke="{NGOC}"/>'


@hinh("boi", "lam")
def _():
    return (dau(76, 62, 12) + f'<path d="M92 66 q14 -24 30 -16" stroke="{DA}" stroke-width="5"/>'
            f'<path d="M14 74 q12 -8 24 0 q12 8 24 0 q12 -8 24 0 q12 8 24 0 q12 -8 24 0 q12 8 22 0 v32 h-142z" fill="#93C5FD" stroke="{TROI}"/>'
            f'<path d="M24 92 q10 -6 20 0 M70 98 q10 -6 20 0 M110 90 q10 -6 20 0" stroke="{TRANG}" stroke-width="2.5"/>')


@hinh("da-bong", "lam")
def _():
    return (nguoi(64, TROI, "vay", "da") + f'<circle cx="106" cy="92" r="11" fill="{TRANG}"/><path d="M106 85 l5 4 l-2 6 h-6 l-2 -6z" fill="{MUC}" stroke-width="1.5"/>'
            f'<path d="M120 84 h12 M122 94 h14" stroke="{XAM}" stroke-width="2.5"/><path d="M14 106 h132" stroke="{NGOC}"/>')


@hinh("hat", "lam")
def _():
    return nguoi(72, TIM, "cam-mic", mat="cuoi") + f'<path d="M78 48 l-4 -10" stroke="{MUC}" stroke-width="3"/><circle cx="73" cy="36" r="4.5" fill="{XAM}"/>' + not_nhac(104, 50) + not_nhac(124, 34, SON)


@hinh("nau-an", "lam")
def _():
    return (nguoi(64, TRANG, "truoc") + f'<path d="M50 30 q14 -18 28 0 v6 h-28z" fill="{TRANG}"/>'
            f'<rect x="24" y="80" width="112" height="24" rx="4" fill="{XAM}"/><circle cx="100" cy="80" r="2" fill="{SON}" stroke="none"/>'
            f'<path d="M80 72 h40 q-2 10 -20 10 q-18 0 -20 -10z" fill="{MUC}"/><path d="M80 74 l-14 -6" stroke="{MUC}" stroke-width="4"/>' + hoi_nuoc(100, 66))


@hinh("mua-do", "lam")
def _():
    return (nguoi(70, CAM, "xuong") + f'<path d="M84 76 q0 -10 8 -10 q8 0 8 10"/><rect x="82" y="76" width="24" height="24" rx="3" fill="{SON}"/>'
            f'<path d="M48 76 q0 -8 6 -8 q6 0 6 8"/><rect x="44" y="76" width="20" height="20" rx="3" fill="{VANG}"/><path d="M92 86 h8" stroke="{TRANG}"/>')


@hinh("lam-viec", "lam")
def _():
    return (nguoi(80, "#475569", "truoc") + ban_truoc() + f'<rect x="56" y="58" width="48" height="24" rx="3" fill="{MUC}"/><rect x="60" y="62" width="40" height="17" rx="1" fill="#BFDBFE" stroke="none"/>'
            f'<path d="M50 82 h60" stroke="{XAM}" stroke-width="4"/>')


@hinh("nghe-nhac", "lam")
def _():
    return (nguoi(72, TROI, "xuong", mat="cuoi") + f'<path d="M58 34 q14 -26 28 0" stroke="{MUC}" stroke-width="4"/>'
            f'<rect x="54" y="30" width="7" height="12" rx="3" fill="{SON}"/><rect x="83" y="30" width="7" height="12" rx="3" fill="{SON}"/>' + not_nhac(106, 46) + not_nhac(124, 70, NGOC))


@hinh("chup-anh", "lam")
def _():
    return (nguoi(80, NGOC, "truoc") + f'<rect x="58" y="44" width="44" height="28" rx="5" fill="{MUC}"/><circle cx="80" cy="58" r="9" fill="#BFDBFE"/>'
            f'<rect x="64" y="40" width="10" height="5" rx="1" fill="{MUC}"/><path d="M112 34 l6 -6 M116 44 h8 M112 54 l6 6" stroke="{VANG}" stroke-width="3"/>')


@hinh("di-bo", "lam")
def _():
    return nguoi(80, VANG, "vay", "buoc") + f'<path d="M14 106 h132" stroke="{NGOC}"/><path d="M30 92 q4 -12 8 0 M122 94 q4 -12 8 0" stroke="{NGOC}" stroke-width="2.5"/>'


@hinh("lai-xe", "lam")
def _():
    return (f'<path d="M20 86 v-18 q0 -6 6 -8 l18 -6 l18 -24 h50 l18 24 l10 4 q6 2 6 8 v20z" fill="{SON}"/>'
            f'<path d="M64 34 h24 v20 h-34z" fill="#BFDBFE"/>' + dau(76, 46, 9) + f'<path d="M94 34 h16 l14 20 h-30z" fill="#BFDBFE"/>'
            f'<circle cx="88" cy="52" r="5" stroke="{MUC}" stroke-width="2.5"/>' + banh(48, 88) + banh(118, 88) + '<path d="M10 100 h140" stroke-width="2.5"/>')


@hinh("om", "lam")
def _():
    return (f'<rect x="20" y="70" width="120" height="16" rx="4" fill="{NAU}"/><path d="M24 86 v14 M136 86 v14" stroke="{NAU}" stroke-width="5"/>'
            f'<rect x="34" y="56" width="26" height="16" rx="7" fill="{TRANG}"/>' + dau(48, 52, 11, "buon")
            + f'<circle cx="41" cy="56" r="2.5" fill="#F87171" stroke="none"/><circle cx="55" cy="56" r="2.5" fill="#F87171" stroke="none"/>'
            f'<path d="M56 58 l14 -4" stroke="{XAM}" stroke-width="2.5"/><circle cx="71" cy="54" r="2" fill="{SON}" stroke="none"/>'
            f'<path d="M58 56 h76 q6 0 6 8 v8 h-82z" fill="{TROI}"/><rect x="40" y="38" width="16" height="8" rx="3" fill="#BFDBFE"/>')


@hinh("cuoi", "nguoi")
def _():
    return (f'<circle cx="80" cy="60" r="38" fill="{VANG}"/><path d="M60 50 q6 -8 12 0 M88 50 q6 -8 12 0" stroke-width="3.5"/>'
            f'<path d="M58 66 q22 30 44 0z" fill="{SON}"/><path d="M62 70 h36" stroke="{TRANG}" stroke-width="3"/>'
            f'<circle cx="54" cy="64" r="5" fill="#FCA5A5" stroke="none"/><circle cx="106" cy="64" r="5" fill="#FCA5A5" stroke="none"/>')


# ---------------------------------------------------------------- giờ

def dong_ho(gio: int, phut: int) -> str:
    s = f'<circle cx="80" cy="60" r="44" fill="{TRANG}" stroke-width="4"/>'
    for i in range(12):
        a = i * math.pi / 6
        r1 = 36 if i % 3 else 32
        s += f'<path d="M{80 + r1 * math.sin(a):.1f} {60 - r1 * math.cos(a):.1f} L{80 + 40 * math.sin(a):.1f} {60 - 40 * math.cos(a):.1f}" stroke-width="{3 if i % 3 == 0 else 2}"/>'
    ag = (gio % 12 + phut / 60) * math.pi / 6
    ap = phut * math.pi / 30
    s += f'<path d="M80 60 L{80 + 22 * math.sin(ag):.1f} {60 - 22 * math.cos(ag):.1f}" stroke-width="6"/>'
    s += f'<path d="M80 60 L{80 + 33 * math.sin(ap):.1f} {60 - 33 * math.cos(ap):.1f}" stroke="{SON}" stroke-width="3.5"/>'
    s += f'<circle cx="80" cy="60" r="4" fill="{MUC}"/>'
    return s


for _ma, (_g, _p) in {"gio-7": (7, 0), "gio-8": (8, 0), "gio-9-30": (9, 30), "gio-12": (12, 0), "gio-3": (3, 0), "gio-6-30": (6, 30)}.items():
    HINH[_ma] = ("gio", dong_ho(_g, _p))


def main() -> int:
    ts = (GOC / "web/src/lib/learn/thi-hsk/hinh.ts").read_text(encoding="utf-8")
    can = re.findall(r"\['([a-z0-9-]+)', '", ts)
    thieu = [m for m in can if m not in HINH]
    du = [m for m in HINH if m not in can]
    if thieu or du:
        print("Lệch danh mục — thiếu:", thieu, "thừa:", du)
        return 1
    RA.mkdir(parents=True, exist_ok=True)
    for ma, (nen, ruot) in HINH.items():
        (RA / f"{ma}.svg").write_text(svg(nen, ruot), encoding="utf-8")
    print(f"Đã vẽ {len(HINH)} hình vào {RA}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
