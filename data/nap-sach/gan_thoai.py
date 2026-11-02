"""Gắn file đọc mẫu cho từng câu thoại và đặt file nghe cả bài khoá.

  python gan_thoai.py

Chạy sau viec_doc.py tu web → doc_edge.py → tai_doc.py. Bấm một câu thoại thì nghe câu đó bằng giọng
riêng của nhân vật. Nút "Nghe" của bài khoá:
  - khoá có băng thu thật (HSK 1, 2) đọc khớp sách: giữ băng, cân cùng độ to với giọng đọc mẫu;
  - khoá băng đọc lệch sách (bang_lech_sach.json) hoặc chưa có băng: ghép các câu, nghỉ 0,6 giây
    giữa hai lượt nói.
"""
from __future__ import annotations

import json

import numpy as np

from chung import SACH_DIR, THU_MUC, ghi_json
from doc_edge import SR, can_to, ghi_mp3, giai
from gan_nghe import tai_len
from nap import Phien
from viec_doc import duong

NGHI = 0.6
SR_BANG = 44100


def ghep(cau: list[dict], out) -> None:
    doan = []
    for c in cau:
        doan += [giai(THU_MUC / duong(c["zh"], c["voice"])), np.zeros(int(SR * NGHI), np.float32)]
    ghi_mp3(can_to(np.concatenate(doan[:-1])), out)


def bang_goc(cap: int, bai: int, ten: str):
    return SACH_DIR / "hsk1" / ten if cap == 1 else SACH_DIR / f"hsk{cap}" / f"Bài {bai}" / ten


if __name__ == "__main__":
    da = json.loads((THU_MUC / "ra" / "da_tai_edge.json").read_text(encoding="utf-8"))
    thoai = json.loads((THU_MUC / "ra" / "hoi_thoai_giong.json").read_text(encoding="utf-8"))
    that = json.loads((THU_MUC / "ra" / "gan_nghe.json").read_text(encoding="utf-8"))
    lech = json.loads((THU_MUC / "bang_lech_sach.json").read_text(encoding="utf-8"))
    s = Phien()
    s.dang_nhap()
    theo_bai: dict[int, dict[int, dict]] = {}
    for k in thoai:
        theo_bai.setdefault(k["lessonId"], {})[k["khoa"]] = k
    thieu = ghep_n = giu_n = 0
    bao_cao = {}
    for lid, khoa in theo_bai.items():
        ds = s.goi("GET", f"/admin/lessons/{lid}/dialogue")["dialogues"]
        for b in ds:
            k = khoa.get(b["no"])
            if not k:
                continue
            for l, c in zip(b["lines"], k["cau"]):
                p = da.get(duong(c["zh"], c["voice"]), {}).get("path")
                l["audioPath"] = p
                thieu += p is None
            ma = f"hsk{k['cap']}-bai{k['bai']:02d}"
            ten = that.get(ma, {}).get("gan", {}).get(str(b["no"]))
            if ten and f"{ma}-khoa{b['no']}" not in lech:
                tam = THU_MUC / "ra" / "tts-khoa" / f"that-{lid}-{b['no']}.mp3"
                tam.parent.mkdir(parents=True, exist_ok=True)
                ghi_mp3(can_to(giai(bang_goc(k["cap"], k["bai"], ten), SR_BANG), SR_BANG), tam, SR_BANG, 96000)
                b["audioPath"] = tai_len(s, tam)
                giu_n += 1
                bao_cao[f"{ma}-khoa{b['no']}"] = "băng thật"
            elif all(da.get(duong(c["zh"], c["voice"])) for c in k["cau"]):
                tam = THU_MUC / "ra" / "tts-khoa" / f"{lid}-{b['no']}.mp3"
                tam.parent.mkdir(parents=True, exist_ok=True)
                ghep(k["cau"], tam)
                b["audioPath"] = tai_len(s, tam)
                ghep_n += 1
                bao_cao[f"{ma}-khoa{b['no']}"] = "ghép giọng đọc mẫu" + (" (băng lệch sách)" if ten else "")
        s.goi("PUT", f"/admin/lessons/{lid}/dialogue", {"dialogues": ds})
    ghi_json(THU_MUC / "ra" / "gan_thoai.json", bao_cao)
    print(f"Đã gắn câu thoại cho {len(theo_bai)} bài · {giu_n} khoá giữ băng thật · {ghep_n} khoá ghép giọng đọc mẫu"
          f" · {thieu} câu thiếu file")
