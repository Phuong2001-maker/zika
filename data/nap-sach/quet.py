"""Quét lại toàn bộ dữ liệu học trên web: âm thanh, nét chữ, phân tích chữ, ví dụ — báo lỗi.

  python quet.py            # in tóm tắt, ghi chi tiết ra ra/quet.json và cập nhật mục "Lần quét gần nhất"
                            # trong NHAT-KY-CAP-NHAT.md ở gốc repo

Chạy sau mỗi lần nạp/sửa nội dung. Mọi kiểm tra đi qua API học viên như người học thật thấy,
và tải thử từng file (âm thanh, nét chữ) từ web chứ không chỉ xem file trên máy.
"""
from __future__ import annotations

import hashlib
import json
import re
import sys
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime

import numpy as np

from chung import REPO, THU_MUC, chu_han, ghi_json
from doc_edge import am_khong_dau, giai
from viec_doc import khoa_doc
from nap import Phien

# Vite chỉ nghe IPv4; localhost thử ::1 trước làm mỗi lần tải trên Windows chậm khoảng 2 giây.
WEB = "http://127.0.0.1:5173"
GIONG = ("nu", "nam")
# File doc_edge.py đã kiểm: web phải trả đúng từng byte của bản đó (sha256), không phải bản cũ.
_KQ = THU_MUC / "ra" / "doc_edge_kq.json"
DA_KIEM = {"/uploads/tts/" + o.split("/tts-edge/", 1)[1]: k.get("sha")
           for o, k in (json.loads(_KQ.read_text(encoding="utf-8")).items() if _KQ.exists() else [])}


def tts(text: str, giong: str, reading: str | None = None) -> str:
    h = khoa_doc(text, reading)
    return f"/uploads/tts/{giong}/{h[:2]}/{h}.mp3"


def tai(url: str) -> bytes | None:
    try:
        with urllib.request.urlopen(WEB + urllib.parse.quote(url.split("?")[0]), timeout=20) as r:
            return r.read() if r.status == 200 else None
    except Exception:
        return None


def xet_am(url: str, text: str) -> str | None:
    """None = ổn; còn lại là mô tả lỗi."""
    b = tai(url)
    if b is None:
        return "thiếu file"
    goc = url.split("?")[0]
    if DA_KIEM and "/tts/" in goc and DA_KIEM.get(goc) != hashlib.sha256(b).hexdigest():
        return "khác bản đã kiểm" if goc in DA_KIEM else "file không do doc_edge.py sinh"
    try:
        sr = 24000
        a = giai(b, sr)
    except Exception:
        return "file hỏng, không giải mã được"
    if np.abs(a).max() < 0.02:
        return "file câm"
    # Chỉ tính phần có tiếng (bỏ khoảng lặng đầu cuối), câu từ 5 âm tiết. Trần 6,5 âm tiết/giây: không
    # nhanh hơn băng giáo trình thật (đo 03/11/2026 bỏ khoảng nghỉ: băng thật trung vị 6,22; giọng mới
    # nữ 5,39, nam 5,74). Bài khoá thu thật/ghép không xét tốc độ (text rỗng).
    nguong = float(np.abs(a).max()) * 0.05
    co = np.flatnonzero(np.abs(a) > nguong)
    dai = max(0.1, (co[-1] - co[0]) / sr)
    n = max(1, len(am_khong_dau(text))) if text else 0
    if text and n >= 5 and not 1.8 <= n / dai <= 6.5:
        return f"tốc độ lạ: {n / dai:.1f} âm tiết/giây"
    return None


def main() -> dict:
    s = Phien()
    s.dang_nhap()
    loi: dict[str, list[str]] = {}
    dem: dict[str, int] = {}

    def ghi(nhom: str, msg: str) -> None:
        loi.setdefault(nhom, []).append(msg)

    am: dict[str, tuple[str, str]] = {}  # url → (text, nơi dùng)
    net: dict[str, tuple[str, int, str]] = {}
    for cap in (1, 2, 3):
        for b in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]:
            noi = f"HSK {cap} bài {b['no']}"
            for w in s.goi("GET", f"/learn/lessons/{b['id']}/flashcards")["words"]:
                dem["từ"] = dem.get("từ", 0) + 1
                for g in GIONG:
                    am[tts(w["hanzi"], g, w["pinyin"])] = (w["hanzi"], f"{noi} · từ {w['hanzi']}")
                if not w["examples"]:
                    ghi("Lật thẻ: từ thiếu ví dụ", f"{noi} · {w['hanzi']}")
                for e in w["examples"]:
                    dem["câu ví dụ"] = dem.get("câu ví dụ", 0) + 1
                    if w["hanzi"] not in e["zh"]:
                        ghi("Lật thẻ: ví dụ không chứa từ", f"{noi} · {w['hanzi']} · {e['zh']}")
                    if not e.get("pinyin") or not e.get("vi"):
                        ghi("Lật thẻ: ví dụ thiếu pinyin/nghĩa", f"{noi} · {e['zh']}")
                    for g in GIONG:
                        am[tts(e["zh"], g)] = (e["zh"], f"{noi} · ví dụ {e['zh']}")
            for c in s.goi("GET", f"/learn/lessons/{b['id']}/writing")["chars"]:
                dem["chữ luyện viết"] = dem.get("chữ luyện viết", 0) + 1
                for g in GIONG:
                    am[tts(c["hanzi"], g)] = (c["hanzi"], f"{noi} · chữ {c['hanzi']}")
                if not c["strokeDataUrl"]:
                    ghi("Luyện viết: thiếu dữ liệu nét", f"{noi} · {c['hanzi']}")
                else:
                    net[c["strokeDataUrl"]] = (c["hanzi"], c["strokes"], noi)
                if not c.get("radicalInfo"):
                    ghi("Luyện viết: thiếu bộ thủ", f"{noi} · {c['hanzi']}")
                if not c.get("analysis"):
                    ghi("Luyện viết: thiếu phân tích chữ", f"{noi} · {c['hanzi']}")
                if "？" in c.get("structure", ""):
                    ghi("Luyện viết: cấu tạo có ký tự lạ", f"{noi} · {c['hanzi']} · {c['structure']}")
            for k in s.goi("GET", f"/learn/lessons/{b['id']}/dialogue")["dialogues"]:
                dem["bài khoá"] = dem.get("bài khoá", 0) + 1
                if not k.get("audioUrl"):
                    ghi("Hội thoại: bài khoá chưa có file nghe", f"{noi} · khoá {k['no']}")
                else:
                    am[k["audioUrl"]] = ("".join(l["zh"] for l in k["lines"]), f"{noi} · bài khoá {k['no']}")
                for l in k["lines"]:
                    dem["câu thoại"] = dem.get("câu thoại", 0) + 1
                    if not l.get("audioUrl"):
                        ghi("Hội thoại: câu thoại chưa có âm thanh", f"{noi} · {l['zh'][:30]}")
                    else:
                        am[l["audioUrl"]] = (l["zh"], f"{noi} · câu thoại {l['zh'][:20]}")
            for p in s.goi("GET", f"/learn/lessons/{b['id']}/grammar")["points"]:
                for e in p["examples"]:
                    dem["ví dụ ngữ pháp"] = dem.get("ví dụ ngữ pháp", 0) + 1
                    am[tts(e["zh"], "nu")] = (e["zh"], f"{noi} · ngữ pháp {e['zh'][:20]}")
            for phan in ("bt", "kt"):
                for q in s.goi("GET", f"/admin/lessons/{b['id']}/questions?part={phan}")["questions"]:
                    t = (q.get("payload") or {}).get("audioText")
                    if t:
                        dem["câu hỏi nghe"] = dem.get("câu hỏi nghe", 0) + 1
                        am[tts(t, "nu")] = (t, f"{noi} · câu hỏi nghe {t[:20]}")

    # Bài khoá thu thật dài hơn nhiều so với số chữ in trong sách (có đọc tên bài, nhạc) — chỉ xét có/câm.
    def xet(item):
        url, (text, noi) = item
        e = xet_am(url, text if "/tts/" in url else "")
        return (url, noi, e)

    with ThreadPoolExecutor(8) as ex:
        for url, noi, e in ex.map(xet, am.items()):
            if e:
                ghi(f"Âm thanh: {e.split(':')[0]}", f"{noi} · {url}")
    dem["file âm thanh"] = len(am)

    def xet_net(item):
        url, (h, so, noi) = item
        b = tai(url)
        if b is None:
            return f"{noi} · {h}: không tải được"
        try:
            d = json.loads(b)
        except Exception:
            return f"{noi} · {h}: JSON hỏng"
        if len(d.get("strokes", [])) != len(d.get("medians", [])):
            return f"{noi} · {h}: strokes/medians lệch"
        if so and len(d["strokes"]) != so:
            return f"{noi} · {h}: dữ liệu {len(d['strokes'])} nét, thẻ ghi {so} nét"
        return None

    with ThreadPoolExecutor(8) as ex:
        for e in ex.map(xet_net, net.items()):
            if e:
                ghi("Luyện viết: nét chữ lỗi", e)
    dem["file nét chữ"] = len(net)

    kq = {"luc": datetime.now().strftime("%d/%m/%Y %H:%M"), "dem": dem,
          "loi": {k: sorted(set(v)) for k, v in sorted(loi.items())}}
    ghi_json(THU_MUC / "ra" / "quet.json", kq)
    return kq


def ghi_nhat_ky(kq: dict) -> None:
    f = REPO / "NHAT-KY-CAP-NHAT.md"
    dong = [f"## Lần quét gần nhất — {kq['luc']}", "",
            "Sinh tự động bởi `data/nap-sach/quet.py`, đừng sửa tay mục này.", "",
            "| Hạng mục | Số lượng |", "|---|---|"]
    dong += [f"| {k} | {v:,} |".replace(",", ".") for k, v in kq["dem"].items()]
    dong += ["", "| Lỗi | Số chỗ | Ví dụ |", "|---|---|---|"]
    if not kq["loi"]:
        dong.append("| Không có lỗi | 0 | |")
    for k, v in kq["loi"].items():
        dong.append(f"| {k} | {len(v)} | {v[0].replace('|', '/')} |")
    moi = "\n".join(dong) + "\n"
    cu = f.read_text(encoding="utf-8") if f.exists() else ""
    mau = re.compile(r"## Lần quét gần nhất.*?(?=\n## |\Z)", re.S)
    f.write_text(mau.sub(lambda _: moi.rstrip("\n"), cu) if mau.search(cu) else cu.rstrip("\n") + "\n\n" + moi, encoding="utf-8")


if __name__ == "__main__":
    kq = main()
    print(json.dumps(kq["dem"], ensure_ascii=False))
    for k, v in kq["loi"].items():
        print(f"{k}: {len(v)}  (vd: {v[0]})")
    if not kq["loi"]:
        print("Không có lỗi.")
    ghi_nhat_ky(kq)
