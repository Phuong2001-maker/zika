"""Tải file đọc mẫu (doc_mau.py sinh ra) lên web qua POST /admin/media/tts.

  python tai_doc.py                 # tải mọi file đã sinh mà chưa tải (ghi nhớ ở ra/da_tai.json)
  python tai_doc.py --doi           # chờ doc_mau.py sinh tiếp, tải dần tới khi đủ

Server lưu đúng tên sha1(câu chữ) nên web tự tìm ra file; tải lại cùng câu thì ghi đè.
"""
from __future__ import annotations

import json
import sys
import urllib.error
import time
import urllib.request
import uuid

from chung import THU_MUC, ghi_json
from nap import API, ORIGIN, Phien

DA_TAI = THU_MUC / "ra" / "da_tai.json"


def tai(s: Phien, v: dict) -> str:
    ranh = uuid.uuid4().hex
    phan = []
    for k in ("text", "voice"):
        phan.append(f"--{ranh}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v[k]}\r\n".encode())
    phan.append(f"--{ranh}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"a.mp3\"\r\n"
                f"Content-Type: audio/mpeg\r\n\r\n".encode() + (THU_MUC / v["out"]).read_bytes() + b"\r\n")
    phan.append(f"--{ranh}--\r\n".encode())
    for lan in range(5):
        s.cho_luot()
        req = urllib.request.Request(API + "/admin/media/tts", data=b"".join(phan), method="POST")
        req.add_header("Content-Type", f"multipart/form-data; boundary={ranh}")
        req.add_header("X-CSRF-Token", s.csrf())
        req.add_header("Origin", ORIGIN)
        req.add_header("Accept", "application/json")
        try:
            with s.op.open(req, timeout=60) as r:
                out = json.loads(r.read().decode("utf-8"))
            return out.get("data", out)["path"]
        except urllib.error.HTTPError as e:
            # 429: quá nhịp; 415/5xx: server dev (php -S) thỉnh thoảng đọc hụt file tạm khi tải dồn dập.
            if e.code not in (429, 415, 500, 502, 503) or lan == 4:
                raise
            time.sleep(15 if e.code == 429 else 2)
    raise RuntimeError("không tải được")


def mot_luot(s: Phien, da: dict) -> tuple[int, int]:
    viec = []
    for ten in ("doc_mau.json", "doc_mau_web.json"):
        f = THU_MUC / "ra" / ten
        if f.exists():
            viec += json.loads(f.read_text(encoding="utf-8"))
    moi = con = 0
    for v in viec:
        if v["out"] in da:
            continue
        if not (THU_MUC / v["out"]).exists():
            con += 1
            continue
        try:
            da[v["out"]] = tai(s, v)
        except Exception as e:  # ghi lại rồi đi tiếp, lần chạy sau thử lại
            print(f"[lỗi] {v['out']} ({v['text'][:20]}): {e}", flush=True)
            continue
        moi += 1
        if moi % 200 == 0:
            ghi_json(DA_TAI, da)
            print(f"đã tải {len(da)} file", flush=True)
    ghi_json(DA_TAI, da)
    return moi, con


if __name__ == "__main__":
    s = Phien()
    s.dang_nhap()
    da = json.loads(DA_TAI.read_text(encoding="utf-8")) if DA_TAI.exists() else {}
    while True:
        moi, con = mot_luot(s, da)
        print(f"lượt này tải {moi}, tổng {len(da)}, còn {con} file chưa sinh", flush=True)
        if "--doi" not in sys.argv or con == 0:
            break
        time.sleep(60)
