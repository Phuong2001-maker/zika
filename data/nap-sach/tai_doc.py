"""Tải file đọc mẫu (doc_edge.py sinh và đã kiểm) lên web qua POST /admin/media/tts.

  python tai_doc.py                 # tải file đã kiểm mà chưa tải hoặc đã sinh lại (ra/da_tai_edge.json)
  python tai_doc.py --nhanh         # như trên nhưng nạp thẳng bằng PHP dòng lệnh (máy local, nhanh hơn nhiều)

Server lưu đúng tên sha1(câu chữ [+ pinyin]) nên web tự tìm ra file; tải lại cùng câu thì ghi đè.
Chỉ tải file đã qua bước kiểm (đạt hoặc chờ người nghe), không tải file còn lỗi kỹ thuật.
"""
from __future__ import annotations

import json
import subprocess
import sys
import urllib.error
import time
import urllib.request
import uuid

from chung import REPO, THU_MUC, ghi_json

PHP = "D:/zika-devtools/php/php.exe"
from nap import API, ORIGIN, Phien

DA_TAI = THU_MUC / "ra" / "da_tai_edge.json"
KQ = THU_MUC / "ra" / "doc_edge_kq.json"


def tai(s: Phien, v: dict) -> str:
    ranh = uuid.uuid4().hex
    phan = []
    for k in ("text", "voice", "reading"):
        if k not in v:
            continue
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
    viec = json.loads((THU_MUC / "ra" / "doc_edge.json").read_text(encoding="utf-8"))
    kq = json.loads(KQ.read_text(encoding="utf-8"))
    moi = bo = 0
    for v in viec:
        k = kq.get(v["out"], {})
        if k.get("trang_thai") not in ("dat", "nghi") or not (THU_MUC / v["out"]).exists():
            bo += 1
            continue
        if da.get(v["out"], {}).get("sha") == k.get("sha"):
            continue
        try:
            da[v["out"]] = {"path": tai(s, v), "sha": k["sha"]}
        except Exception as e:  # ghi lại rồi đi tiếp, lần chạy sau thử lại
            print(f"[lỗi] {v['out']} ({v['text'][:20]}): {e}", flush=True)
            continue
        moi += 1
        if moi % 500 == 0:
            ghi_json(DA_TAI, da)
            print(f"đã tải {moi} file", flush=True)
    ghi_json(DA_TAI, da)
    return moi, bo


def nhanh(da: dict) -> tuple[int, int]:
    """--nhanh: nạp thẳng bằng api/app/tools/nhap_tts.php (cùng cách đặt tên với API), không qua HTTP —
    server dev chỉ xử lý một yêu cầu một lúc nên 10.000 file qua API mất khoảng 2 giờ."""
    viec = json.loads((THU_MUC / "ra" / "doc_edge.json").read_text(encoding="utf-8"))
    kq = json.loads(KQ.read_text(encoding="utf-8"))
    can, bo = [], 0
    for v in viec:
        k = kq.get(v["out"], {})
        if k.get("trang_thai") not in ("dat", "nghi") or not (THU_MUC / v["out"]).exists():
            bo += 1
        elif da.get(v["out"], {}).get("sha") != k.get("sha"):
            can.append(v)
    ds = THU_MUC / "ra" / "tai_nhanh.json"
    ra = THU_MUC / "ra" / "tai_nhanh_kq.json"
    ghi_json(ds, [{"file": str(THU_MUC / v["out"]), "text": v["text"], "voice": v["voice"], "reading": v.get("reading")}
                  for v in can])
    subprocess.run([PHP, "app/tools/nhap_tts.php", str(ds), str(ra)], cwd=REPO / "api", check=True)
    duong = json.loads(ra.read_text(encoding="utf-8"))
    for v in can:
        p = duong.get(str(THU_MUC / v["out"]))
        if p:
            da[v["out"]] = {"path": p, "sha": kq[v["out"]]["sha"]}
    ghi_json(DA_TAI, da)
    return len(duong), bo


if __name__ == "__main__":
    da = json.loads(DA_TAI.read_text(encoding="utf-8")) if DA_TAI.exists() else {}
    if "--nhanh" in sys.argv:
        moi, bo = nhanh(da)
    else:
        s = Phien()
        s.dang_nhap()
        moi, bo = mot_luot(s, da)
    print(f"lượt này tải {moi}, tổng {len(da)} file trên web, {bo} file chưa đạt nên chưa tải", flush=True)
