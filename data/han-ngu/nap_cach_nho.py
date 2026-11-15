"""Nạp "Cách nhớ" + "Từ ghép" (cach-nho/q{n}.json, đặc tả DAC-TA-CACH-NHO.md) vào bảng characters qua API quản trị.

  python nap_cach_nho.py           (mọi quyển đã có file)        python nap_cach_nho.py q1 q2

Gọi PUT /admin/characters/cach-nho theo lô 100 chữ; server tự xoá cache nội dung sau khi nạp. Cần MariaDB + API.
"""
from __future__ import annotations

import json
import sys

from chung_hn import THU_MUC
from chung import THU_MUC as _  # noqa: F401
from nap import Phien  # type: ignore  # data/nap-sach/nap.py


def main(ds: list[str]) -> int:
    ds = ds or [f.stem for f in sorted((THU_MUC / "cach-nho").glob("q*.json"))]
    items = []
    for q in ds:
        for x in json.loads((THU_MUC / "cach-nho" / f"{q}.json").read_text(encoding="utf-8")):
            items.append({
                "hanzi": x["hanzi"],
                "phan": x.get("phan", []),
                "lienTuong": x.get("lien_tuong", ""),
                "tuGhep": [{"hanzi": t["hanzi"], "pinyin": t["pinyin"], "nghia": t["nghia"]} for t in x.get("tu_ghep", [])],
            })
    s = Phien()
    s.dang_nhap()
    xong, bo = 0, []
    for i in range(0, len(items), 100):
        kq = s.goi("PUT", "/admin/characters/cach-nho", {"items": items[i:i + 100]}, timeout=300)
        xong += kq.get("updated", 0)
        bo += [str(x) for x in kq.get("skipped", [])]
    print(f"{', '.join(ds)}: gửi {len(items)} chữ, cập nhật {xong}" + (f", bỏ {len(bo)}: {'; '.join(bo[:5])}" if bo else ""))
    return 1 if bo else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
