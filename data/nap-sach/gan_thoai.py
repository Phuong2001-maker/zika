"""Gắn file đọc mẫu cho từng câu thoại, và ghép cả bài khoá cho khoá chưa có file thu thật.

  python gan_thoai.py

Chạy sau viec_doc.py web → doc_mau.py → tai_doc.py. Bấm một câu thoại thì nghe câu đó bằng giọng
đúng nhân vật; nút "Nghe" của bài khoá: khoá có file thu thật (HSK 1, 2) giữ nguyên file thật,
khoá chưa có (HSK 3, HSK 1 bài 2 khoá 1–3) dùng file ghép các câu, nghỉ 0,6 giây giữa hai người nói.
"""
from __future__ import annotations

import io
import json
import sys

from chung import SACH_DIR, THU_MUC, ghi_json
from gan_nghe import tai_len
from nap import Phien
from viec_doc import duong

sys.path.append(str(SACH_DIR / "kokoro-tts" / ".venv" / "Lib" / "site-packages"))
import numpy as np  # noqa: E402
import soundfile as sf  # noqa: E402

NGHI = 0.6


def ghep(cau: list[dict], out) -> None:
    doan = []
    for c in cau:
        a, sr = sf.read(THU_MUC / duong(c["zh"], c["voice"]), dtype="float32")
        doan += [a, np.zeros(int(sr * NGHI), dtype="float32")]
    sf.write(out, np.concatenate(doan[:-1]), sr, format="MP3")


if __name__ == "__main__":
    da = json.loads((THU_MUC / "ra" / "da_tai.json").read_text(encoding="utf-8"))
    thoai = json.loads((THU_MUC / "ra" / "hoi_thoai_giong.json").read_text(encoding="utf-8"))
    # File thu thật là những khoá gan_nghe.py đã gắn; khoá khác có file là bản ghép lần trước → ghép lại.
    that = json.loads((THU_MUC / "ra" / "gan_nghe.json").read_text(encoding="utf-8"))
    s = Phien()
    s.dang_nhap()
    theo_bai: dict[int, dict[int, dict]] = {}
    for k in thoai:
        theo_bai.setdefault(k["lessonId"], {})[k["khoa"]] = k
    thieu = ghep_n = 0
    for lid, khoa in theo_bai.items():
        ds = s.goi("GET", f"/admin/lessons/{lid}/dialogue")["dialogues"]
        for b in ds:
            k = khoa.get(b["no"])
            if not k:
                continue
            for l, c in zip(b["lines"], k["cau"]):
                p = da.get(duong(c["zh"], c["voice"]))
                l["audioPath"] = p
                thieu += p is None
            if str(b["no"]) in that.get(f"hsk{k['cap']}-bai{k['bai']:02d}", {}).get("gan", {}):
                b["audioPath"] = b["audioUrl"]  # file thu thật: giữ nguyên
            elif all(da.get(duong(c["zh"], c["voice"])) for c in k["cau"]):
                tam = THU_MUC / "ra" / "tts-khoa" / f"{lid}-{b['no']}.mp3"
                tam.parent.mkdir(parents=True, exist_ok=True)
                ghep(k["cau"], tam)
                b["audioPath"] = tai_len(s, tam)
                ghep_n += 1
        s.goi("PUT", f"/admin/lessons/{lid}/dialogue", {"dialogues": ds})
    print(f"Đã gắn câu thoại cho {len(theo_bai)} bài, ghép {ghep_n} bài khoá chưa có file thu thật, {thieu} câu thiếu file")
