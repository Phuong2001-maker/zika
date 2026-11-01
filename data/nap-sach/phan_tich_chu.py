"""Phân tích chữ cho màn Luyện viết: bộ thủ, cấu tạo, câu giải thích, vài chữ khác cùng bộ.

  python phan_tich_chu.py          # sinh ra/phan_tich_chu.json rồi nạp qua PUT /admin/characters/analysis
  python phan_tich_chu.py --xem 河  # in phần phân tích của vài chữ, không nạp

Nguồn: tu-dien/curated.js (soạn tay, có mẹo nhớ — ưu tiên) và hanzi-1/2.js (Unihan + Make Me a Hanzi:
số bộ, dạng bộ, IDS, loại chữ, phần nghĩa, phần âm). Chữ cùng bộ ưu tiên chữ trong từ vựng HSK 1–3
(cấp thấp trước) để người học gặp lại chữ quen; thiếu mới lấy thêm chữ thông dụng.
"""
from __future__ import annotations

import json
import re
import sys

from chung import REPO, THU_MUC, BO_CUC, chu_han, ghi_json, han_viet_chu, kho_chu, nghia_ngan

# Dạng biến thể của bộ khi đứng trong chữ (氵 là 水…). 阝 và 月 tuỳ chữ, lấy theo số bộ của từ điển.
BIEN_THE = {
    "氵": 85, "氺": 85, "亻": 9, "扌": 64, "忄": 61, "㣺": 61, "讠": 149, "訁": 149, "纟": 120, "糹": 120,
    "钅": 167, "釒": 167, "饣": 184, "飠": 184, "艹": 140, "⺾": 140, "辶": 162, "⻌": 162, "礻": 113,
    "衤": 145, "犭": 94, "刂": 18, "灬": 86, "罒": 122, "⺲": 122, "⺮": 118, "𥫗": 118, "耂": 125,
    "⻊": 157, "攵": 66, "牜": 93, "冫": 15, "丬": 90, "爫": 87, "覀": 146, "⺌": 42, "⺍": 42,
    "见": 147, "贝": 154, "车": 159, "门": 169, "页": 181, "马": 187, "鱼": 195, "鸟": 196, "风": 182,
    "长": 168, "韦": 178, "飞": 183, "齿": 211, "龙": 212, "麦": 199, "黄": 201, "卤": 197, "龟": 213,
    "齐": 210, "黾": 205, "𧾷": 157, "⺈": 18, "⺊": 25, "𠂉": 4, "彐": 58, "彑": 58, "㔾": 26,
}
TEN_LOAI = {"p": "tượng hình", "i": "hội ý", "s": "hình thanh"}


def bang_bo() -> dict[int, tuple[str, str, str]]:
    """Đọc bảng 214 bộ từ Radicals.php để không phải giữ hai bản."""
    src = (REPO / "api" / "app" / "src" / "Services" / "Radicals.php").read_text(encoding="utf-8")
    return {int(n): (g, t, y) for n, g, t, y in re.findall(r"(\d+) => \['(.+?)', '(.+?)', '(.+?)'\]", src)}


def doc_tu_dien() -> dict[str, dict]:
    """hanzi-1/2.js: chữ → số bộ, dạng bộ, IDS, loại, phần nghĩa, phần âm, thứ tự (gần với độ thông dụng)."""
    ra: dict[str, dict] = {}
    for ten in ("hanzi-1.js", "hanzi-2.js"):
        for dong in re.findall(r'^"(.*)",?$', (THU_MUC / "tu-dien" / ten).read_text(encoding="utf-8"), re.M):
            f = dong.split("|") + [""] * 12
            ra.setdefault(f[0], {
                "pinyin": f[1], "nets": int(f[5] or 0), "so_bo": int(f[6] or 0), "dang_bo": f[7], "ids": f[8],
                "loai": f[9], "nghia_phan": f[10], "am_phan": f[11], "thu_tu": len(ra),
            })
    return ra


def soan_tay() -> dict[str, dict]:
    cur = (THU_MUC / "tu-dien" / "curated.js").read_text(encoding="utf-8")
    return {d["char"]: d for d in (json.loads(x) for x in re.findall(r"^\s*(\{.*\}),?$", cur, re.M))}


def nghia_phan(p: str, bo: dict) -> str:
    """Nghĩa ngắn của một thành phần: bộ thủ thì lấy tên bộ, chữ thường thì lấy nghĩa đầu."""
    if p in BIEN_THE and BIEN_THE[p] in bo:
        g, t, y = bo[BIEN_THE[p]]
        return f"bộ {t}, {y}"
    for n, (g, t, y) in bo.items():
        if g == p:
            return f"bộ {t}, {y}"
    m = re.split(r"[,，;；(]", nghia_ngan(p))[0].strip()
    return m


def cau_tao_sach(ch: str) -> str:
    """'⿱人？' → 'Trên dưới: 人 + …' — bỏ thành phần Unicode không có (máy ghi ？)."""
    ids = kho_chu().get(ch, {}).get("ids", "")
    if not ids or ids[0] not in BO_CUC:
        return ""
    phan = [c for c in ids[1:] if c not in BO_CUC and c != ch]
    biet = [c for c in phan if c not in "？?" and not ("⿰" <= c <= "⿿")]
    if not biet:
        return BO_CUC[ids[0]]
    return f"{BO_CUC[ids[0]]}: {' + '.join(biet)}" + (" + …" if len(biet) < len(phan) else "")


def giai_thich(ch: str, td: dict, bo: dict) -> str:
    t = td.get(ch, {})
    loai = t.get("loai", "")
    hv = (han_viet_chu(ch) or "").upper()
    if loai == "s" and t.get("nghia_phan") and t.get("am_phan"):
        a = t["am_phan"]
        a_py = kho_chu().get(a, {}).get("pinyin", "")
        return (f"Chữ hình thanh: {t['nghia_phan']} ({nghia_phan(t['nghia_phan'], bo)}) gợi nghĩa, "
                f"{a}{f' ({a_py})' if a_py else ''} gợi cách đọc.")
    ids = t.get("ids", "")
    phan = [c for c in ids[1:] if c not in BO_CUC and c not in "？?"] if ids[:1] in BO_CUC else []
    if loai == "i" and len(phan) >= 2:
        return "Chữ hội ý: " + " + ".join(f"{p} ({nghia_phan(p, bo)})" for p in phan) + f" → {nghia_ngan(ch)}."
    if loai == "p":
        return f"Chữ tượng hình: nét chữ vẽ lại hình ảnh “{nghia_ngan(ch)}”."
    if len(phan) >= 2:
        return "Gồm " + " + ".join(f"{p} ({nghia_phan(p, bo)})" for p in phan) + "."
    return f"Chữ {hv}: {nghia_ngan(ch)}." if hv else ""


def main() -> None:
    bo = bang_bo()
    td = doc_tu_dien()
    tay = soan_tay()

    # Chữ trong từ vựng HSK 1–3: cấp thấp nhất chữ xuất hiện, kèm một từ ngắn để gợi nhớ.
    cap_chu: dict[str, int] = {}
    tu_chua: dict[str, str] = {}
    for cap in (1, 2, 3):
        for w in json.loads((THU_MUC / "ra" / f"tu_hsk{cap}.json").read_text(encoding="utf-8")):
            for c in chu_han(w["hanzi"]):
                cap_chu.setdefault(c, cap)
                if len(w["hanzi"]) > 1 and (c not in tu_chua or len(w["hanzi"]) < len(tu_chua[c])):
                    tu_chua[c] = w["hanzi"]

    def so_bo(c: str) -> int:
        return int(tay.get(c, {}).get("radicalNo") or td.get(c, {}).get("so_bo") or 0)

    theo_bo: dict[int, list[str]] = {}
    for c in td:
        theo_bo.setdefault(so_bo(c), []).append(c)

    ra = []
    for ch in sorted(cap_chu, key=lambda c: (cap_chu[c], td.get(c, {}).get("thu_tu", 9999))):
        t = td.get(ch, {})
        n = so_bo(ch)
        dang = tay.get(ch, {}).get("radical") or t.get("dang_bo") or (bo[n][0] if n in bo else "")
        # Chữ cùng bộ: trong từ vựng (cấp thấp trước), rồi chữ thông dụng; tối đa 4.
        cung = [c for c in theo_bo.get(n, []) if c != ch]
        cung.sort(key=lambda c: (cap_chu.get(c, 9), td.get(c, {}).get("thu_tu", 9999)))
        vd = []
        for c in cung[:4]:
            vd.append({
                "hanzi": c,
                "pinyin": kho_chu().get(c, {}).get("pinyin", "").split(",")[0],
                "hanViet": han_viet_chu(c).upper(),
                # Chữ chỉ đứng trong một từ (馒 của 馒头) thì từ điển không có nghĩa riêng.
                "meaning": re.split(r"[;；]", nghia_ngan(c))[0][:60] or (f"(trong {tu_chua[c]})" if c in tu_chua else ""),
                "word": tu_chua.get(c),
            })
        ra.append({
            "hanzi": ch,
            "radical": dang if n else "",
            "radicalNo": n or None,
            "structure": cau_tao_sach(ch),
            "analysis": (tay.get(ch, {}).get("explain") or giai_thich(ch, td, bo)).strip(),
            "examples": vd if n else [],
        })
    ghi_json(THU_MUC / "ra" / "phan_tich_chu.json", ra)
    return ra


if __name__ == "__main__":
    ra = main()
    if "--xem" in sys.argv:
        can = set("".join(sys.argv[sys.argv.index("--xem") + 1:]))
        for x in ra:
            if x["hanzi"] in can:
                print(json.dumps(x, ensure_ascii=False, indent=1))
        sys.exit(0)
    from nap import Phien
    s = Phien()
    s.dang_nhap()
    tong = 0
    for i in range(0, len(ra), 200):
        tong += s.goi("PUT", "/admin/characters/analysis", {"items": ra[i:i + 200]})["updated"]
    print(f"{len(ra)} chữ phân tích, {tong} chữ cập nhật trên web")
