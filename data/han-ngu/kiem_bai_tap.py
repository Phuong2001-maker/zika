"""Kiểm bai-tap/q{n}/*.json theo DAC-TA-BAI-TAP.md (cùng giới hạn với QuestionPayload sau khi nới 15/11). Thoát 1 nếu còn lỗi.

  python kiem_bai_tap.py q3        python kiem_bai_tap.py all
"""
from __future__ import annotations

import json
import re
import sys

from chung_hn import THU_MUC

HAN = re.compile(r"[㐀-鿿]")
DAU = re.compile(r"[\s，。！？、；：“”‘’（）《》,.!?;:'\"()\-]")
DANG = {"blank", "order", "match", "tf", "pick", "pinyin", "audio"}
NGUON = {"sach", "lixin", "tu_giai", "lixin_sai_da_sua"}


def pa(p: dict, toi_da: int, loi: list[str], noi: str, chi_han: bool = False) -> None:
    o = p.get("options")
    if not isinstance(o, list) or not 2 <= len(o) <= toi_da or any(not str(x).strip() for x in o):
        loi.append(f"{noi}: options phải 2–{toi_da} mục không rỗng")
        return
    if len(set(o)) != len(o):
        loi.append(f"{noi}: options trùng nhau")
    if not isinstance(p.get("answer"), int) or not 0 <= p["answer"] < len(o):
        loi.append(f"{noi}: answer không trỏ vào options")
    if chi_han and any(not HAN.search(str(x)) for x in o):
        loi.append(f"{noi}: options phải là chữ Hán")


def kiem_cau(c: dict, noi: str, loi: list[str]) -> None:
    t, p = c.get("type"), c.get("payload") or {}
    if t not in DANG:
        loi.append(f"{noi}: type lạ {t}")
        return
    if c.get("dap_an") not in NGUON:
        loi.append(f"{noi}: dap_an phải là một trong {sorted(NGUON)}")
    if not str(c.get("muc", "")).strip():
        loi.append(f"{noi}: thiếu muc")
    if t == "blank":
        s = p.get("sentence", "")
        if s.count("___") != 1 or len(s) > 255:
            loi.append(f"{noi}: sentence phải có đúng một ___ và ≤ 255 ký tự")
        pa(p, 16, loi, noi)
    elif t == "order":
        tiles, ans = p.get("tiles") or [], p.get("answer", "")
        if not 2 <= len(tiles) <= 12 or any(not str(x).strip() or len(x) > 120 for x in tiles):
            loi.append(f"{noi}: tiles 2–12 mảnh, mỗi mảnh ≤ 120 ký tự")
        if not ans or len(ans) > 400:
            loi.append(f"{noi}: answer rỗng hoặc > 400 ký tự")
        # Ghép mảnh theo thứ tự nào đó phải ra đáp án: ít nhất cùng một bộ chữ (bỏ dấu câu).
        if tiles and sorted(DAU.sub("", "".join(tiles))) != sorted(DAU.sub("", ans)):
            loi.append(f"{noi}: các mảnh không ghép đủ đúng chữ của đáp án")
    elif t == "match":
        pr = p.get("pairs") or []
        if not 2 <= len(pr) <= 6 or any(len(x) != 2 or not x[0] or not x[1] or len(x[0]) > 32 for x in pr):
            loi.append(f"{noi}: pairs 2–6 cặp, vế trái ≤ 32 ký tự")
        elif len({x[0] for x in pr}) != len(pr) or len({x[1] for x in pr}) != len(pr):
            loi.append(f"{noi}: hai cặp trùng vế")
    elif t == "tf":
        if not p.get("passage") or len(p["passage"]) > 2000 or not p.get("statement") or len(p["statement"]) > 300:
            loi.append(f"{noi}: passage ≤ 2000, statement ≤ 300, không rỗng")
        if not isinstance(p.get("answer"), bool):
            loi.append(f"{noi}: answer phải true/false")
    elif t == "pick":
        if not p.get("ask") or len(p["ask"]) > 200:
            loi.append(f"{noi}: ask rỗng hoặc > 200 ký tự")
        pa(p, 4, loi, noi)
    elif t == "pinyin":
        if not HAN.search(p.get("word", "")):
            loi.append(f"{noi}: word phải là chữ Hán")
        pa(p, 4, loi, noi)
    elif t == "audio":
        if not p.get("audioText") or len(p["audioText"]) > 32:
            loi.append(f"{noi}: audioText rỗng hoặc > 32 ký tự")
        pa(p, 4, loi, noi, chi_han=True)
        o, a = p.get("options") or [], p.get("answer")
        if isinstance(a, int) and 0 <= a < len(o) and o[a] != p.get("audioText"):
            loi.append(f"{noi}: đáp án phải đúng là từ được đọc")


def kiem(q: str) -> tuple[int, list[str]]:
    loi: list[str] = []
    so = 0
    for f in sorted((THU_MUC / "bai-tap" / q).glob("bai*.json")):
        try:
            d = json.loads(f.read_text(encoding="utf-8"))
        except json.JSONDecodeError as e:
            loi.append(f"{f.name}: JSON hỏng ({e})")
            continue
        for i, c in enumerate(d.get("cau") or []):
            so += 1
            kiem_cau(c, f"{q}/{f.stem} câu {i + 1} ({c.get('muc', '')})", loi)
    return so, loi


if __name__ == "__main__":
    ds = [f"q{i}" for i in range(1, 7)] if sys.argv[1:] == ["all"] else sys.argv[1:]
    tong = 0
    for q in ds:
        so, loi = kiem(q)
        tong += len(loi)
        print(f"{q}: {so} câu, {len(loi)} lỗi")
        for x in loi[:50]:
            print("  -", x)
    sys.exit(1 if tong else 0)
