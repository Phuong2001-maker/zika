"""Tải file nghe (mp3 của người dùng) lên web và gắn vào đúng bài khoá, theo kết quả nghe_gan.py.

  python gan_nghe.py 1 | python gan_nghe.py 2 | python gan_nghe.py 2 3

Gắn theo thứ tự file cố định của bộ nghe, kiểm chéo bằng chữ nhận dạng được (điểm ≥ 0.2).
File đọc bảng từ mới không gắn được (web chỉ có ô nghe cho từng từ) nên chỉ ghi vào báo cáo.
Kết quả: ra/gan_nghe.json.
"""
from __future__ import annotations

import json
import sys
import time
import urllib.error
import urllib.request
import uuid

from chung import SACH_DIR, THU_MUC, chon_bai, doc_bai, ghi_json
from nap import API, ORIGIN, Phien
from nghe_gan import danh_sach, diem, phan_cua_bai

NGUONG_CHEO = 0.2

# Chỗ nhận dạng giọng nói không đủ để kiểm chéo, đã xét tay: (cấp, bài) → {bài khoá: số thứ tự file}.
TAY: dict[tuple[int, int], dict[int, int]] = {
    # HSK 1 bài 2: bộ nghe là bản FLTRP chỉ có 3 đoạn hội thoại, tên nhân vật khác sách Hanu
    # ("我叫陈天中" thay "我叫小丽"). Chỉ đoạn 2-5 (李文/白家月) đúng nội dung sách → bài khoá 4.
    (1, 2): {4: 5},
    # HSK 1 bài 7 file 7-1: máy nghe ra "10:10" = 十点十分 của bài khoá 1, đúng vị trí file 1.
    (1, 7): {1: 1},
    # HSK 2 bài 8 file 4: đoạn văn kể đọc nhanh, máy nghe kém; đúng vị trí như 9 bài còn lại.
    (2, 8): {4: 4},
}


def tai_len(s: Phien, path) -> str:
    """POST multipart /admin/media/audio → đường dẫn /uploads/…"""
    ranh = uuid.uuid4().hex
    than = (f"--{ranh}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"bai.mp3\"\r\n"
            f"Content-Type: audio/mpeg\r\n\r\n").encode() + path.read_bytes() + f"\r\n--{ranh}--\r\n".encode()
    s.cho_luot()
    req = urllib.request.Request(API + "/admin/media/audio", data=than, method="POST")
    req.add_header("Content-Type", f"multipart/form-data; boundary={ranh}")
    req.add_header("X-CSRF-Token", s.csrf())
    req.add_header("Origin", ORIGIN)
    req.add_header("Accept", "application/json")
    # Script tải TTS ngay trước đó dùng chung hạn mức tài khoản dù là tiến trình khác.
    for lan in range(5):
        try:
            with s.op.open(req, timeout=120) as r:
                out = json.loads(r.read().decode("utf-8"))
            return out.get("data", out)["path"]
        except urllib.error.HTTPError as e:
            if e.code != 429 or lan == 4:
                raise
            time.sleep(15)
    raise RuntimeError("Chưa tải được âm thanh")


if __name__ == "__main__":
    nghe = json.loads((THU_MUC / "ra" / "nghe.json").read_text(encoding="utf-8"))
    s = Phien()
    s.dang_nhap()
    f_kq = THU_MUC / "ra" / "gan_nghe.json"
    kq = json.loads(f_kq.read_text(encoding="utf-8")) if f_kq.exists() else {}
    chon = chon_bai(sys.argv[1:])
    for cap in sorted({c for c, _ in chon}):
        bai_co = {x["no"]: x["id"] for x in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]}
        for c, so in chon:
            ten = f"hsk{c}-bai{so:02d}"
            if c != cap or ten not in nghe or so not in bai_co:
                continue
            # Thứ tự file cố định: HSK 1 bài khoá k = file 2k−1 (xen file đọc từ mới), HSK 2 bài khoá k = file k.
            # Nhận dạng giọng nói chỉ dùng để kiểm chéo: chữ nghe được phải trùng đủ với đúng bài khoá đó.
            d = doc_bai(cap, so)
            ph = phan_cua_bai(d)
            ds = nghe[ten]
            tot: dict[int, dict] = {}
            for b in d["bai_khoa"]:
                k = b["so"]
                vt = 2 * k - 1 if cap == 1 else k
                if vt > len(ds) or f"bai_khoa:{k}" not in ph:
                    continue
                x = ds[vt - 1]
                rieng = diem(x["nghe"], ph[f"bai_khoa:{k}"])
                if rieng >= NGUONG_CHEO:
                    tot[k] = {**x, "diem": rieng}
            for k, vt in TAY.get((cap, so), {}).items():
                tot[k] = {**ds[vt - 1], "diem": -1}
            lid = bai_co[so]
            khoi = s.goi("GET", f"/admin/lessons/{lid}/dialogue")["dialogues"]
            files = {p.name: p for p in danh_sach(cap, so)}
            gan = {}
            for b in khoi:
                x = tot.get(b["no"])
                b["audioPath"] = tai_len(s, files[x["file"]]) if x else None
                if x:
                    gan[b["no"]] = x["file"]
            s.goi("PUT", f"/admin/lessons/{lid}/dialogue", {"dialogues": khoi})
            khong = [x["file"] + " → " + x["phan"] for x in nghe[ten] if x["file"] not in gan.values()]
            kq[ten] = {"gan": gan, "khong_gan": khong}
            print(f"HSK {cap} · Bài {so}: gắn {len(gan)}/{len(khoi)} bài khoá {gan}" + (f" · không gắn: {', '.join(khong)}" if khong else ""))
    ghi_json(f_kq, kq)
