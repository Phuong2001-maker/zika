"""Băng thu thật của giáo trình (HSK 1–2) có đọc đúng chữ bài khoá đang hiện không.

  python kiem_bang_that.py        → ra/bang_that.json, in các khoá lệch

Sách Hanu sửa lời cho người Việt (越南人, số điện thoại, tên) nhưng băng vẫn đọc lời gốc. Khoá nào
băng lệch sách thì gan_thoai.py dùng bản ghép giọng đọc mẫu thay cho băng, khoá khớp thì giữ băng.
Lệch khi: một câu của sách chỉ nghe thấy dưới 70% âm tiết, cả bài dưới 90%, hoặc băng đọc thêm
nhiều chữ không có trong sách. FunASR có thể nghe sai tên riêng — xem cột "nghe" trước khi tin.
"""
from __future__ import annotations

import json
from difflib import SequenceMatcher

from chung import REPO, THU_MUC, ghi_json
from doc_edge import am_khong_dau, asr, giai
from nap import Phien

UPLOADS = REPO / "api" / "dev-uploads"


def xet(nghe: str, cau: list[str]) -> dict:
    sach = [am_khong_dau(c) for c in cau]
    phang = [a for c in sach for a in c]
    duoc = am_khong_dau(nghe)
    sm = SequenceMatcher(None, phang, duoc, autojunk=False)
    trung = [False] * len(phang)
    for b in sm.get_matching_blocks():
        for i in range(b.a, b.a + b.size):
            trung[i] = True
    phu_cau, k = [], 0
    for c in sach:
        phu_cau.append(sum(trung[k:k + len(c)]) / max(1, len(c)))
        k += len(c)
    so_trung = sum(trung)
    thua = len(duoc) - so_trung
    phu = so_trung / max(1, len(phang))
    lech = phu < 0.9 or min(phu_cau, default=1) < 0.7 or thua > max(3, 0.25 * len(phang))
    return {"phu": round(phu, 2), "cau_thap": round(min(phu_cau, default=1), 2), "thua": thua, "lech": lech}


if __name__ == "__main__":
    gan = json.loads((THU_MUC / "ra" / "gan_nghe.json").read_text(encoding="utf-8"))
    s = Phien()
    s.dang_nhap()
    kq = {}
    for cap in (1, 2):
        for b in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]:
            co = gan.get(f"hsk{cap}-bai{b['no']:02d}", {}).get("gan", {})
            for k in s.goi("GET", f"/learn/lessons/{b['id']}/dialogue")["dialogues"]:
                if str(k["no"]) not in co or not k.get("audioUrl"):
                    continue
                f = UPLOADS / k["audioUrl"].split("?")[0].removeprefix("/uploads/")
                am = giai(f, 16000)
                nghe = "".join(x.get("text", "") for x in asr(True).generate(input=am, disable_pbar=True)).replace(" ", "")
                cau = [l["zh"] for l in k["lines"]]
                kq[f"hsk{cap}-bai{b['no']:02d}-khoa{k['no']}"] = {
                    "lessonId": b["id"], "khoa": k["no"], "audioUrl": k["audioUrl"], "file_goc": co[str(k["no"])],
                    **xet(nghe, cau), "sach": "".join(cau), "nghe": nghe}
    ghi_json(THU_MUC / "ra" / "bang_that.json", kq)
    lech = {k: v for k, v in kq.items() if v["lech"]}
    print(f"{len(kq)} bài khoá có băng thật, {len(lech)} khoá lệch sách:")
    for k, v in lech.items():
        print(f"  {k}: phủ {v['phu']}, câu thấp nhất {v['cau_thap']}, thừa {v['thua']}\n    sách: {v['sach'][:70]}\n    nghe: {v['nghe'][:70]}")
