"""Kiểm câu ví dụ lật thẻ tự soạn (vi-du/hsk{n}.json) trước khi nạp.

  python kiem_vi_du.py 1          # in lỗi; thoát mã 1 nếu còn lỗi
  python kiem_vi_du.py 3 --bai 1-8
  python kiem_vi_du.py --xuat     # ghi ra/tu_hsk{n}.json (danh sách từ hiện có trên web) để soạn

Luật: mỗi từ 1–2 câu; câu phải chứa đúng từ đó; ngắn theo cấp; chỉ dùng chữ có trong từ vựng
của các cấp ≤ cấp đang soạn (người học HSK 1 không gặp chữ HSK 3); pinyin khớp từng chữ.
"""
from __future__ import annotations

import json
import sys

from chung import THU_MUC, chu_han, ghi_json
from kiem_tra import so_pinyin

# Console Windows mặc định cp1252/cp1258 nên in chữ Hán là sập; ép UTF-8 ngay từ đầu.
sys.stdout.reconfigure(encoding="utf-8")

DAI_TOI_DA = {1: 12, 2: 16, 3: 22}  # số chữ Hán của một câu


def xuat() -> None:
    from nap import Phien
    s = Phien()
    s.dang_nhap()
    for cap in (1, 2, 3):
        ds = []
        for b in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]:
            for w in s.goi("GET", f"/learn/lessons/{b['id']}/flashcards")["words"]:
                ds.append({"bai": b["no"], "hanzi": w["hanzi"], "pinyin": w["pinyin"], "nghia": w["meaning"],
                           "loai": w["pos"], "vi_du_sach": [e["zh"] for e in w["examples"]]})
        ghi_json(THU_MUC / "ra" / f"tu_hsk{cap}.json", ds)
        print(f"HSK {cap}: {len(ds)} từ")


def kiem(cap: int, tu_bai: int = 1, den_bai: int = 99) -> int:
    tu = [w for w in json.loads((THU_MUC / "ra" / f"tu_hsk{cap}.json").read_text(encoding="utf-8"))
          if tu_bai <= w["bai"] <= den_bai]
    duoc = set()
    for c in range(1, cap + 1):
        for w in json.loads((THU_MUC / "ra" / f"tu_hsk{c}.json").read_text(encoding="utf-8")):
            duoc.update(chu_han(w["hanzi"]))
    vd: dict = {}
    for f in sorted((THU_MUC / "vi-du").glob(f"hsk{cap}*.json")):  # soạn tách nhiều file cũng được
        vd.update(json.loads(f.read_text(encoding="utf-8")))
    loi = 0
    for w in tu:
        h = w["hanzi"]
        cau = vd.get(h)
        if not cau:
            print(f"[thiếu] {h}")
            loi += 1
            continue
        if not 1 <= len(cau) <= 2:
            print(f"[số câu] {h}: {len(cau)}")
            loi += 1
        for e in cau:
            zh, py, vi = e.get("zh", ""), e.get("pinyin", ""), e.get("vi", "")
            ghi = []
            if h not in zh:
                ghi.append("không chứa từ")
            n = len(chu_han(zh))
            if n > DAI_TOI_DA[cap]:
                ghi.append(f"dài {n} chữ > {DAI_TOI_DA[cap]}")
            ngoai = sorted({c for c in chu_han(zh) if c not in duoc})
            if ngoai:
                ghi.append("chữ ngoài cấp: " + "".join(ngoai))
            if not vi.strip():
                ghi.append("thiếu nghĩa")
            ghi += so_pinyin(zh, py)
            if ghi:
                print(f"[{h}] {zh} · {py} → " + "; ".join(ghi))
                loi += 1
    thua = set(vd) - {w["hanzi"] for w in json.loads((THU_MUC / "ra" / f"tu_hsk{cap}.json").read_text(encoding="utf-8"))}
    if thua:
        print("[thừa] " + " ".join(sorted(thua)))
    print(f"HSK {cap}: {len(tu)} từ, {loi} lỗi")
    return loi


if __name__ == "__main__":
    if "--xuat" in sys.argv:
        xuat()
    else:
        a = sys.argv[1:]
        tu_bai, den_bai = 1, 99
        if "--bai" in a:
            i = a.index("--bai")
            tu_bai, den_bai = map(int, a[i + 1].split("-"))
            del a[i:i + 2]
        sys.exit(1 if any([kiem(int(c), tu_bai, den_bai) for c in a]) else 0)
