"""Bộ thủ, cấu tạo, chữ cùng bộ cho các chữ MỚI của giáo trình Hán ngữ (màn Luyện viết).

  python phan_tich_hn.py

Chữ đã có phân tích từ bộ HSK được giữ nguyên, vì bảng chữ dùng chung cho mọi giáo trình.
Dùng đúng các hàm của data/nap-sach/phan_tich_chu.py để nội dung cùng một kiểu.
"""
from __future__ import annotations

import json
import re

from chung_hn import BAI, NAP_SACH, THU_MUC, file_ra, ghi_json
from chung import chu_han, han_viet_chu, kho_chu, nghia_ngan
from nap import Phien  # data/nap-sach/nap.py
from phan_tich_chu import bang_bo, cau_tao_sach, doc_tu_dien, giai_thich, soan_tay  # data/nap-sach/phan_tich_chu.py


def main() -> None:
    bo, td, tay = bang_bo(), doc_tu_dien(), soan_tay()
    da_co = {x["hanzi"] for x in json.loads((NAP_SACH / "ra" / "phan_tich_chu.json").read_text(encoding="utf-8"))}
    cap_chu: dict[str, int] = {}
    tu_chua: dict[str, str] = {}
    can: list[str] = []
    for q in BAI:
        for b in BAI[q]:
            f = file_ra(q, b)
            if not f.exists():
                continue
            p = json.loads(f.read_text(encoding="utf-8"))
            for w in p["words"]:
                for c in chu_han(w["hanzi"]):
                    cap_chu.setdefault(c, q)
                    if len(w["hanzi"]) > 1 and (c not in tu_chua or len(w["hanzi"]) < len(tu_chua[c])):
                        tu_chua[c] = w["hanzi"]
            for x in p["writing"]:
                cap_chu.setdefault(x["hanzi"], q)
                if x["hanzi"] not in da_co and x["hanzi"] not in can:
                    can.append(x["hanzi"])

    def so_bo(c: str) -> int:
        return int(tay.get(c, {}).get("radicalNo") or td.get(c, {}).get("so_bo") or 0)

    theo_bo: dict[int, list[str]] = {}
    for c in td:
        theo_bo.setdefault(so_bo(c), []).append(c)

    ra = []
    for ch in can:
        t = td.get(ch, {})
        n = so_bo(ch)
        dang = tay.get(ch, {}).get("radical") or t.get("dang_bo") or (bo[n][0] if n in bo else "")
        cung = [c for c in theo_bo.get(n, []) if c != ch]
        cung.sort(key=lambda c: (cap_chu.get(c, 9), td.get(c, {}).get("thu_tu", 9999)))
        vd = [{
            "hanzi": c,
            "pinyin": kho_chu().get(c, {}).get("pinyin", "").split(",")[0],
            "hanViet": han_viet_chu(c).upper(),
            "meaning": re.split(r"[;；]", nghia_ngan(c))[0][:60] or (f"(trong {tu_chua[c]})" if c in tu_chua else ""),
            "word": tu_chua.get(c),
        } for c in cung[:4]]
        ra.append({"hanzi": ch, "radical": dang if n else "", "radicalNo": n or None, "structure": cau_tao_sach(ch),
                   "analysis": (tay.get(ch, {}).get("explain") or giai_thich(ch, td, bo)).strip(), "examples": vd if n else []})
    ghi_json(THU_MUC / "ra" / "phan_tich_chu.json", ra)
    s = Phien()
    s.dang_nhap()
    tong = 0
    for i in range(0, len(ra), 200):
        tong += s.goi("PUT", "/admin/characters/analysis", {"items": ra[i:i + 200]})["updated"]
    thieu = [x["hanzi"] for x in ra if not x["radicalNo"] or not x["analysis"]]
    print(f"{len(ra)} chữ mới được phân tích, {tong} chữ cập nhật · thiếu bộ/giải thích: {''.join(thieu) or 'không'}")


if __name__ == "__main__":
    main()
