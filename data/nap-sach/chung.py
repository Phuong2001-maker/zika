"""Phần dùng chung cho bộ nạp giáo trình Hanu HSK 1–3 vào Zìkǎ (xem README.md cùng thư mục)."""
from __future__ import annotations

import json
import os
import re
import sys
import unicodedata
from functools import lru_cache
from pathlib import Path

THU_MUC = Path(__file__).resolve().parent
REPO = THU_MUC.parents[1]
# Ba file PDF nằm cạnh thư mục repo (D:\zika-devtools\Project\…); đổi bằng biến ZIKA_SACH_DIR.
SACH_DIR = Path(os.environ.get("ZIKA_SACH_DIR", THU_MUC.parents[2]))

# Ảnh lấy từ bản gốc; chữ lấy từ lớp chữ: HSK 1 có chữ thật, HSK 2–3 là lớp chữ OCR (sai ~6–9%).
PDF_ANH = {
    1: "HSK1 3.0 Hanu 24.6.26.pdf",
    2: "HSK2 3.0 Hanu 29.6.26.pdf",
    3: "HSK3 3.0 Hanu 29.6.26.pdf",
}
PDF_CHU = {
    1: "HSK1 3.0 Hanu 24.6.26.pdf",
    2: "HSK2 3.0 Hanu 29.6.26 (có lớp chữ).pdf",
    3: "HSK3 3.0 Hanu 29.6.26 (có lớp chữ).pdf",
}

# Trang PDF (đếm từ 1) mở đầu từng bài, đo bằng trang "目标" và mục lục; số cuối là trang cuối sách.
TRANG_DAU = {
    1: [3, 13, 22, 32, 41, 51, 61, 72, 83, 92, 102, 111, 120, 130, 142],
    2: [4, 15, 26, 38, 49, 60, 73, 85, 97, 110],
    3: [6, 18, 29, 41, 56, 69, 82, 95, 108, 121, 134, 147, 160, 174, 186],
}
TRANG_CUOI = {1: 149, 2: 121, 3: 199}


def so_bai(cap: int) -> int:
    return len(TRANG_DAU[cap])


def trang_cua_bai(cap: int, bai: int) -> tuple[int, int]:
    dau = TRANG_DAU[cap]
    if not 1 <= bai <= len(dau):
        raise SystemExit(f"HSK {cap} không có bài {bai} (có {len(dau)} bài).")
    cuoi = dau[bai] - 1 if bai < len(dau) else TRANG_CUOI[cap]
    return dau[bai - 1], cuoi


def pdf(cap: int, loai: str = "anh") -> Path:
    ten = (PDF_ANH if loai == "anh" else PDF_CHU)[cap]
    p = SACH_DIR / ten
    if not p.exists():
        raise SystemExit(f"Không thấy {p}. Đặt biến ZIKA_SACH_DIR trỏ tới thư mục chứa 3 file PDF.")
    return p


def file_bai(cap: int, bai: int) -> Path:
    return THU_MUC / "bai" / f"hsk{cap}" / f"bai{bai:02d}.json"


def doc_bai(cap: int, bai: int) -> dict:
    return json.loads(file_bai(cap, bai).read_text(encoding="utf-8"))


def ghi_json(path: Path, data) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")


def chon_bai(args: list[str]) -> list[tuple[int, int]]:
    """'all' · '2' (cả quyển) · '2 5' (một bài) → danh sách (cấp, bài)."""
    if not args or args[0] == "all":
        return [(c, b) for c in (1, 2, 3) for b in range(1, so_bai(c) + 1)]
    cap = int(args[0])
    if len(args) == 1:
        return [(cap, b) for b in range(1, so_bai(cap) + 1)]
    return [(cap, int(b)) for b in args[1:]]


# ---------------------------------------------------------------- chữ Hán

_HAN = re.compile(r"[\u3400-\u4dbf\u4e00-\u9fff\uf900-\ufaff]")


def la_han(ch: str) -> bool:
    return bool(_HAN.fullmatch(ch))


def chu_han(s: str) -> list[str]:
    return _HAN.findall(s)


def chuan_hoa_han(s: str) -> str:
    """Đổi ký tự bộ thủ Khang Hy (U+2F00–U+2FD5, U+2E80–U+2EF3) về chữ Hán thường, giữ nguyên dấu câu."""
    return "".join(unicodedata.normalize("NFKC", c) if "\u2e80" <= c <= "\u2fd5" else c for c in s)


# ---------------------------------------------------------------- pinyin

_DAU = {
    "ā": "a1", "á": "a2", "ǎ": "a3", "à": "a4", "ē": "e1", "é": "e2", "ě": "e3", "è": "e4",
    "ī": "i1", "í": "i2", "ǐ": "i3", "ì": "i4", "ō": "o1", "ó": "o2", "ǒ": "o3", "ò": "o4",
    "ū": "u1", "ú": "u2", "ǔ": "u3", "ù": "u4", "ǖ": "ü1", "ǘ": "ü2", "ǚ": "ü3", "ǜ": "ü4",
}


def pinyin_tron(s: str) -> str:
    """Bỏ dấu thanh, khoảng trắng, dấu câu, viết thường: 'Shǒujī hào' → 'shoujihao'."""
    out = []
    for c in s.lower():
        if c in _DAU:
            out.append(_DAU[c][0].replace("ü", "v"))
        elif c == "ü":
            out.append("v")
        elif c.isalpha() and c.isascii():
            out.append(c)
    return "".join(out)


@lru_cache(maxsize=1)
def _pypinyin():
    # pypinyin chỉ có trong venv của kokoro-tts; thư viện thuần Python nên dùng chung được.
    for p in [THU_MUC.parents[2] / "kokoro-tts" / ".venv" / "Lib" / "site-packages"]:
        if p.exists() and str(p) not in sys.path:
            sys.path.append(str(p))
    try:
        from pypinyin import Style, pinyin  # type: ignore
    except ImportError:
        return None
    return pinyin, Style


def pinyin_may(han: str) -> str | None:
    """Pinyin máy đọc (không dấu, liền) để đối chiếu; None nếu không có pypinyin."""
    m = _pypinyin()
    if m is None:
        return None
    pinyin, Style = m
    return "".join(x[0] for x in pinyin(han, style=Style.NORMAL, errors="ignore")).replace("ü", "v")


def pinyin_co_dau(han: str) -> str | None:
    m = _pypinyin()
    if m is None:
        return None
    pinyin, Style = m
    return " ".join(x[0] for x in pinyin(han, style=Style.TONE, errors="ignore"))


# ---------------------------------------------------------------- kho chữ (Hán Việt, nghĩa, cấu tạo)

BO_CUC = {
    "⿰": "Trái phải", "⿱": "Trên dưới", "⿲": "Trái giữa phải", "⿳": "Trên giữa dưới",
    "⿴": "Bao quanh", "⿵": "Bao trên", "⿶": "Bao dưới", "⿷": "Bao trái", "⿸": "Bao trái trên",
    "⿹": "Bao phải trên", "⿺": "Bao trái dưới", "⿻": "Đan xen",
}


@lru_cache(maxsize=1)
def kho_chu() -> dict[str, dict]:
    """Gộp curated.js (soạn tay, ưu tiên) với hanzi-1/2.js (tự sinh từ Unihan, CVDICT, Make Me a Hanzi)."""
    kho: dict[str, dict] = {}
    for ten in ("hanzi-1.js", "hanzi-2.js"):
        for dong in re.findall(r'^"(.*)",?$', (THU_MUC / "tu-dien" / ten).read_text(encoding="utf-8"), re.M):
            f = dong.split("|")
            f += [""] * (12 - len(f))
            doc_khac = []
            for x in filter(None, f[4].split("¦")):
                p = x.split("~")
                if len(p) >= 2:
                    doc_khac.append({"pinyin": p[0], "hanViet": p[1], "meaning": p[2] if len(p) > 2 else ""})
            kho[f[0]] = {
                "pinyin": f[1], "hanViet": f[2], "meaning": f[3], "khac": doc_khac,
                "ids": f[8], "loai": f[9],
            }
    cur = (THU_MUC / "tu-dien" / "curated.js").read_text(encoding="utf-8")
    for dong in re.findall(r"^\s*(\{.*\}),?$", cur, re.M):
        d = json.loads(dong)
        k = kho.setdefault(d["char"], {"khac": [], "ids": "", "loai": ""})
        k.update({
            "pinyin": d.get("pinyin") or k.get("pinyin", ""),
            "hanViet": (d.get("hanViet") or k.get("hanViet", "")).lower(),
            "meaning": d.get("meaning") or k.get("meaning", ""),
            "thanh_phan": d.get("components") or [],
            "soan_tay": True,
        })
    return kho


def han_viet_chu(ch: str, py: str | None = None) -> str:
    """Âm Hán Việt của một chữ; có pinyin thì chọn cách đọc khớp âm (多音字). Nhiều âm thì lấy âm đầu."""
    k = kho_chu().get(ch)
    if not k:
        return ""
    hv = k.get("hanViet", "")
    if py:
        tron = pinyin_tron(py).rstrip("r") if ch != "儿" else pinyin_tron(py)
        for cach in [{"pinyin": k.get("pinyin", ""), "hanViet": hv}, *k.get("khac", [])]:
            if pinyin_tron(cach["pinyin"]) == tron and cach["hanViet"]:
                hv = cach["hanViet"]
                break
    return re.split(r"[,，/;]", hv)[0].strip()


def nghia_ngan(ch: str) -> str:
    """Nghĩa ngắn của một chữ: lấy nghĩa đầu, cắt gọn cho thẻ luyện viết."""
    k = kho_chu().get(ch, {})
    # Chữ họ như 李 có nghĩa chính để trống, nghĩa nằm ở cách đọc khác (lǐ = quả mận)
    m = k.get("meaning", "") or next((x["meaning"] for x in k.get("khac", []) if x.get("meaning")), "")
    m = re.split(r"[;；]", m)[0].strip()
    return m[:80]


def cau_tao(ch: str) -> str:
    """'⿰又鸟' → 'Trái phải: 又 + 鸟' (để trống nếu chữ không tách được)."""
    k = kho_chu().get(ch, {})
    ids = k.get("ids", "")
    if ids and ids[0] in BO_CUC:
        phan = [c for c in ids[1:] if c not in BO_CUC and c != ch]
        if phan:
            return f"{BO_CUC[ids[0]]}: {' + '.join(phan)}"
    return ""


def tach_am_tiet(py: str) -> list[str]:
    """Tách pinyin có dấu thành âm tiết: 'shǒujī hào' → ['shǒu', 'jī', 'hào'] (đủ dùng cho chữ HSK)."""
    nguyen_am = "aeiouüāáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ"
    ra = []
    for tu in re.split(r"[\s'’\-·]+", py.strip()):
        tu = re.sub(r"[^\w" + nguyen_am + "]", "", tu, flags=re.I)
        while tu:
            # âm đầu + nguyên âm + (ng|n) + r nhi hoá; n/ng/r chỉ là âm cuối khi sau nó không phải nguyên âm
            na = r"(?![" + nguyen_am + r"])"
            m = re.match(
                r"(zh|ch|sh|[bpmfdtnlgkhjqxrzcsyw])?([" + nguyen_am + r"]+)(ng" + na + r"|n" + na + r")?(r" + na + r")?",
                tu, re.I,
            )
            if not m or not m.group(0):
                ra.append(tu)
                break
            ra.append(m.group(0))
            tu = tu[len(m.group(0)):]
    return ra


def han_viet_tu(han: str, py: str = "") -> str:
    """Âm Hán Việt của cả từ, chọn cách đọc theo pinyin trong bài: 手机 shǒujī → 'Thủ cơ'."""
    chu = chu_han(han)
    am = tach_am_tiet(py) if py else []
    if len(am) != len(chu):
        am = [""] * len(chu)
    ra = [han_viet_chu(c, a) for c, a in zip(chu, am)]
    if not all(ra):
        return ""
    s = " ".join(ra)
    return s[:1].upper() + s[1:]
