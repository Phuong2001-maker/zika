"""Sinh lại chậm hơn những câu đọc nhanh (giọng nam đọc câu ngắn nhanh hơn băng giáo trình).

  python doc_cham_lai.py [ngưỡng=5.8]

Đo tốc độ phần có tiếng của mọi câu (kind "cau", từ 5 âm tiết) đã sinh; câu nào nhanh hơn ngưỡng
âm tiết/giây thì sinh lại chậm thêm 15% so với lần trước, kiểm lại như doc_edge.py, ghi doc_edge_kq.json.
Sau đó chạy tai_doc.py --nhanh rồi gan_thoai.py.
"""
from __future__ import annotations

import asyncio
import json
import sys

import numpy as np

import doc_edge as d
from chung import THU_MUC, ghi_json


def toc_do(v: dict) -> float:
    a = d.giai(THU_MUC / v["out"])
    nguong = float(np.abs(a).max()) * 0.05
    co = np.flatnonzero(np.abs(a) > nguong)
    return len(d.am_khong_dau(v["text"])) / max(0.1, (co[-1] - co[0]) / d.SR)


async def main() -> None:
    nguong = float(sys.argv[1]) if len(sys.argv) > 1 else 5.8
    viec = json.loads(d.F_VIEC.read_text(encoding="utf-8"))
    kq = json.loads(d.F_KQ.read_text(encoding="utf-8"))
    can = [v for v in viec if v["kind"] == "cau" and len(d.am_khong_dau(v["text"])) >= 5
           and (THU_MUC / v["out"]).exists() and toc_do(v) > nguong]
    d.log(f"doc_cham_lai: {len(can)} câu nhanh hơn {nguong} âm tiết/giây → chậm thêm 15%")
    sem = asyncio.Semaphore(6)

    async def mot(v: dict) -> None:
        async with sem:
            k = kq.setdefault(v["out"], {"ck": d.chu_ky(v), "lech": 0, "ep": False})
            k["lech"] = int(k.get("lech", 0)) - 15
            k.update(await d.sinh(v, k["lech"], None))

    for i in range(0, len(can), 200):
        dot = can[i:i + 200]
        await asyncio.gather(*(mot(v) for v in dot))
        for v in dot:
            k = await d.kiem(v)
            k["trang_thai"] = d.trang_thai(v, k)
            if k["trang_thai"] == "loi" and not any(m.startswith(d.KY_THUAT) for m in k["loi"]):
                k["trang_thai"] = "nghi"
            kq[v["out"]].update(k)
            kq[v["out"]]["sha"] = d.sha(THU_MUC / v["out"])
        ghi_json(d.F_KQ, kq)
        d.log(f"  doc_cham_lai: xong {i + len(dot)}/{len(can)}")
    con = sum(1 for v in can if toc_do(v) > 6.5)
    d.log(f"doc_cham_lai xong: {len(can)} câu sinh lại, {con} câu còn nhanh hơn 6,5 âm tiết/giây")


if __name__ == "__main__":
    asyncio.run(main())
