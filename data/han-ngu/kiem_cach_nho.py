"""Kiểm file cach-nho/q{n}.json theo DAC-TA-CACH-NHO.md. Thoát mã 1 nếu còn lỗi.

  python kiem_cach_nho.py q1          python kiem_cach_nho.py all
"""
from __future__ import annotations

import json
import re
import sys

from chung_hn import THU_MUC

DAU_THANH = re.compile(r"[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ]")


def kiem(q: str) -> list[str]:
    vao = json.loads((THU_MUC / "ra" / "cach-nho-vao" / f"{q}.json").read_text(encoding="utf-8"))
    f = THU_MUC / "cach-nho" / f"{q}.json"
    if not f.exists():
        return [f"{q}: chưa có {f.name}"]
    ra = json.loads(f.read_text(encoding="utf-8"))
    loi: list[str] = []
    if [x["hanzi"] for x in ra] != [x["hanzi"] for x in vao]:
        loi.append(f"{q}: danh sách chữ khác đầu vào (thiếu, thừa hoặc sai thứ tự)")
    goc = {x["hanzi"]: x for x in vao}
    for x in ra:
        h = x.get("hanzi", "?")
        g = goc.get(h)
        if not g:
            continue
        phan = x.get("phan") or []
        mot = (not g["net_bo"]) or g["net_bo_phu_ca_chu"]
        if mot and [p.get("mau") for p in phan] != ["a"]:
            loi.append(f"{h}: chữ không tách bộ phải có đúng 1 phần mau=a")
        if not mot and [p.get("mau") for p in phan] != ["a", "b"]:
            loi.append(f"{h}: phải có đúng 2 phần mau a rồi b")
        for p in phan:
            for k in ("vi_tri", "chu", "ten", "nghia"):
                if not str(p.get(k, "")).strip():
                    loi.append(f"{h}: phần {p.get('mau')} thiếu {k}")
        lt = str(x.get("lien_tuong", "")).strip()
        if not lt:
            loi.append(f"{h}: thiếu lien_tuong")
        elif len(lt) > 220:
            loi.append(f"{h}: lien_tuong dài {len(lt)} ký tự (> 220)")
        tg = x.get("tu_ghep") or []
        if not 3 <= len(tg) <= 5:
            loi.append(f"{h}: tu_ghep có {len(tg)} từ (cần 3–5)")
        for t in tg:
            if h not in t.get("hanzi", "") or t.get("hanzi") == h:
                loi.append(f"{h}: từ ghép {t.get('hanzi')} không chứa chữ hoặc là chính chữ")
            if not DAU_THANH.search(t.get("pinyin", "")) and not re.fullmatch(r"[a-z ]+", t.get("pinyin", "") or "x"):
                loi.append(f"{h}: pinyin {t.get('hanzi')} lạ")
            if not str(t.get("nghia", "")).strip():
                loi.append(f"{h}: từ {t.get('hanzi')} thiếu nghĩa")
            elif len(t["nghia"]) > 60:
                loi.append(f"{h}: nghĩa {t['hanzi']} dài quá")
    return loi


if __name__ == "__main__":
    ds = [f"q{i}" for i in range(1, 7)] if sys.argv[1:] == ["all"] else sys.argv[1:]
    tong = 0
    for q in ds:
        loi = kiem(q)
        tong += len(loi)
        print(f"{q}: {len(loi)} lỗi")
        for x in loi[:40]:
            print("  -", x)
    sys.exit(1 if tong else 0)
