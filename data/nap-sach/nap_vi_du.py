"""Nạp câu ví dụ lật thẻ tự soạn (vi-du/hsk*.json, đã qua kiem_vi_du.py) thay cho ví dụ cũ của từng từ.

  python nap_vi_du.py 1 2 3
"""
from __future__ import annotations

import json
import sys

from chung import THU_MUC
from nap import Phien

if __name__ == "__main__":
    s = Phien()
    s.dang_nhap()
    for cap in map(int, sys.argv[1:] or ["1", "2", "3"]):
        vd: dict = {}
        for f in sorted((THU_MUC / "vi-du").glob(f"hsk{cap}*.json")):
            vd.update(json.loads(f.read_text(encoding="utf-8")))
        n = 0
        for b in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]:
            for w in s.goi("GET", f"/learn/lessons/{b['id']}/flashcards")["words"]:
                cau = vd.get(w["hanzi"])
                if not cau:
                    continue
                cu = s.goi("GET", f"/admin/words/{w['id']}")
                cu["examples"] = [{"zh": e["zh"], "pinyin": e["pinyin"], "vi": e["vi"]} for e in cau]
                s.goi("PUT", f"/admin/words/{w['id']}", cu)
                n += 1
        print(f"HSK {cap}: nạp ví dụ cho {n} từ", flush=True)
