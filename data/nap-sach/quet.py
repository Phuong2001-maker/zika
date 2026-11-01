"""Quét lại toàn bộ dữ liệu học trên web: âm thanh, nét chữ, phân tích chữ, ví dụ — báo lỗi.

  python quet.py            # in tóm tắt, ghi chi tiết ra ra/quet.json và cập nhật mục "Lần quét gần nhất"
                            # trong NHAT-KY-CAP-NHAT.md ở gốc repo

Chạy sau mỗi lần nạp/sửa nội dung. Mọi kiểm tra đi qua API học viên như người học thật thấy,
và tải thử từng file (âm thanh, nét chữ) từ web chứ không chỉ xem file trên máy.
"""
from __future__ import annotations

import hashlib
import io
import json
import re
import sys
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime

from chung import REPO, SACH_DIR, THU_MUC, chu_han, ghi_json
from nap import Phien

sys.path.append(str(SACH_DIR / "kokoro-tts" / ".venv" / "Lib" / "site-packages"))
import numpy as np  # noqa: E402
import soundfile as sf  # noqa: E402

WEB = "http://localhost:5173"
GIONG = ("nu", "nam")


def tts(text: str, giong: str) -> str:
    h = hashlib.sha1(text.strip().encode("utf-8")).hexdigest()
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
    try:
        a, sr = sf.read(io.BytesIO(b), dtype="float32")
    except Exception:
        return "file hỏng, không giải mã được"
    if a.ndim > 1:
        a = a.mean(axis=1)
    dai = len(a) / sr
    if np.abs(a).max() < 0.02:
        return "file câm"
    n = max(1, len(chu_han(text)) + len(re.findall(r"\d", text)))  # số đọc thành âm tiết
    # Người đọc chậm cho người học ~3 âm tiết/giây (đo toàn bộ: trung vị 2,9). Báo khi quá 4,5
    # (nhanh khó nghe, hoặc bị cắt cụt) hay dưới 1,2 (kéo dài bất thường). Từ 1–3 chữ có khoảng lặng
    # đầu cuối nên chỉ xét câu từ 4 chữ.
    if text and n >= 4 and not 1.2 <= n / dai <= 4.5:
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
                    am[tts(w["hanzi"], g)] = (w["hanzi"], f"{noi} · từ {w['hanzi']}")
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
