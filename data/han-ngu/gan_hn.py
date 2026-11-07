"""Đưa giọng đọc lên web local và gắn cho từng câu thoại, từng bài khóa của giáo trình Hán ngữ.

  python gan_hn.py        # sau nghe_doi_chieu.py, viec_hn.py, doc_hn.py

Nút "Nghe" của bài khóa theo ra/nghe_gan.json: file ghi âm đọc khớp sách thì dùng (cắt đúng đoạn nếu là
file chung của cả bài), không thì ghép các câu thoại bằng giọng mẫu. Mọi bản đều cân về cùng độ to.
"""
from __future__ import annotations

import hashlib
import json
import subprocess
from collections import Counter

import numpy as np

from chung_hn import NAP_SACH, REPO, SACH_DIR, TEN_GIAO_TRINH, THU_MUC, ghi_json
from doc_edge import SR, can_to, ghi_mp3, giai  # data/nap-sach/doc_edge.py
from nap import Phien  # data/nap-sach/nap.py
from nghe_doi_chieu import THU_MUC_NGHE
from nghe_kiem import giai_ben
from viec_doc import duong

NGHI = 0.6
SR_BANG = 44100
PHP = "D:/zika-devtools/php/php.exe"
UPLOADS = REPO / "api" / "dev-uploads"  # config.php → paths.uploads_dir ở máy dev
KQ = NAP_SACH / "ra" / "doc_edge_kq.json"
DA_TAI = NAP_SACH / "ra" / "da_tai_edge.json"


def tai_nhanh() -> dict:
    """Nạp mp3 đã kiểm bằng api/app/tools/nhap_tts.php: server dev chỉ xử lý một yêu cầu một lúc nên qua HTTP rất chậm."""
    viec = json.loads((THU_MUC / "ra" / "doc_edge_hn.json").read_text(encoding="utf-8"))
    kq = json.loads(KQ.read_text(encoding="utf-8"))
    da = json.loads(DA_TAI.read_text(encoding="utf-8")) if DA_TAI.exists() else {}
    can, hong = [], []
    for v in viec:
        k = kq.get(v["out"], {})
        if k.get("trang_thai") not in ("dat", "nghi") or not (NAP_SACH / v["out"]).exists():
            hong.append(v["out"])
        elif da.get(v["out"], {}).get("sha") != k.get("sha"):
            can.append(v)
    ds, ra = THU_MUC / "ra" / "tai_nhanh.json", THU_MUC / "ra" / "tai_nhanh_kq.json"
    ghi_json(ds, [{"file": str(NAP_SACH / v["out"]), "text": v["text"], "voice": v["voice"], "reading": v.get("reading")} for v in can])
    if can:
        subprocess.run([PHP, "app/tools/nhap_tts.php", str(ds), str(ra)], cwd=REPO / "api", check=True)
        duong_moi = json.loads(ra.read_text(encoding="utf-8"))
        for v in can:
            p = duong_moi.get(str(NAP_SACH / v["out"]))
            if p:
                da[v["out"]] = {"path": p, "sha": kq[v["out"]]["sha"]}
        ghi_json(DA_TAI, da)
    print(f"Đã tải {len(can)} file mới · {len(hong)} file chưa đạt nên không tải", flush=True)
    return da


def luu_file(p) -> str:
    """Lưu như Upload::store nhưng không qua POST /admin/media/audio: form đó chặn 2 MB, còn bài khóa
    quyển 5–6 dài tới 8 phút (5–6 MB). Giới hạn chỉ để chống tải nhầm tay, không liên quan đến phát."""
    b = p.read_bytes()
    h = hashlib.sha1(b).hexdigest()
    dich = UPLOADS / "audio" / h[:2] / f"{h}.mp3"
    dich.parent.mkdir(parents=True, exist_ok=True)
    if not dich.exists():
        dich.write_bytes(b)
    return f"/uploads/audio/{h[:2]}/{h}.mp3"


def ghep(cau: list[dict], out) -> None:
    doan = []
    for c in cau:
        doan += [giai(NAP_SACH / duong(c["zh"], c["voice"])), np.zeros(int(SR * NGHI), np.float32)]
    ghi_mp3(can_to(np.concatenate(doan[:-1])), out)


if __name__ == "__main__":
    da = tai_nhanh()
    gan = json.loads((THU_MUC / "ra" / "nghe_gan.json").read_text(encoding="utf-8"))
    thoai = json.loads((THU_MUC / "ra" / "hoi_thoai_giong.json").read_text(encoding="utf-8"))
    s = Phien()
    s.dang_nhap()
    ds = s.goi("GET", "/admin/curricula")
    cid = next(c["id"] for c in (ds["curricula"] if isinstance(ds, dict) and "curricula" in ds else ds) if c["name"] == TEN_GIAO_TRINH)
    theo_bai: dict[tuple[int, int], dict[int, dict]] = {}
    for k in thoai:
        theo_bai.setdefault((k["quyen"], k["bai"]), {})[k["khoa"]] = k
    dem: Counter = Counter()
    thieu_cau = 0
    bao_cao = {}
    tam = THU_MUC / "ra" / "khoa"
    tam.mkdir(parents=True, exist_ok=True)
    for q in sorted({x for x, _ in theo_bai}):
        bai_co = {x["no"]: x["id"] for x in s.goi("GET", f"/admin/levels/{q}/lessons?curriculum={cid}")["lessons"]}
        for (qq, b), khoa in theo_bai.items():
            if qq != q or b not in bai_co:
                continue
            lid = bai_co[b]
            cac_khoa = s.goi("GET", f"/admin/lessons/{lid}/dialogue")["dialogues"]
            for kh in cac_khoa:
                k = khoa.get(kh["no"])
                if not k:
                    continue
                for l, c in zip(kh["lines"], k["cau"]):
                    p = da.get(duong(c["zh"], c["voice"]), {}).get("path")
                    l["audioPath"] = p
                    thieu_cau += p is None
                ten = f"q{q}-bai{b:02d}-khoa{kh['no']}"
                chon = gan.get(ten, {"cach": "tao"})
                out = tam / f"{ten}.mp3"
                if chon["cach"] in ("file", "cat"):
                    goc = SACH_DIR / THU_MUC_NGHE[q] / f"{chon['ten']}.mp3"
                    am = giai_ben(goc, SR_BANG)
                    # Cắt đúng đoạn bài khóa: bỏ phần đọc tiêu đề bài ở đầu file để giọng khớp câu chữ trên màn hình.
                    if chon.get("tu_giay") is not None:
                        am = am[int(chon["tu_giay"] * SR_BANG):int(chon["den_giay"] * SR_BANG)]
                    ghi_mp3(can_to(am, SR_BANG), out, SR_BANG, 96000)
                elif all(da.get(duong(c["zh"], c["voice"])) for c in k["cau"]):
                    ghep(k["cau"], out)
                else:
                    dem["thieu"] += 1
                    kh["audioPath"] = None
                    bao_cao[ten] = "thiếu"
                    continue
                kh["audioPath"] = luu_file(out)
                dem[chon["cach"]] += 1
                bao_cao[ten] = chon["cach"]
            s.goi("PUT", f"/admin/lessons/{lid}/dialogue", {"dialogues": cac_khoa})
    ghi_json(THU_MUC / "ra" / "gan_hn.json", bao_cao)
    print(f"Bài khóa: {dem['file']} dùng nguyên file ghi âm · {dem['cat']} cắt từ file chung · {dem['tao']} ghép giọng mẫu · {dem['thieu']} thiếu"
          f" · {thieu_cau} câu thoại thiếu file")
