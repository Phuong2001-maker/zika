"""Quét giáo trình Hán ngữ như người học thấy: mọi âm thanh và nét chữ phải tải được từ web và đúng bản đã kiểm.

  python quet_hn.py        # cần web local đang chạy; in tóm tắt, chi tiết ở ra/quet_hn.json

Khác data/nap-sach/quet.py ở chỗ quét đủ 6 quyển của đúng giáo trình này và chỉ đòi giọng mà màn học dùng
(lật thẻ luôn giọng nam). Từ không có ví dụ không tính là lỗi: giáo trình này chỉ lấy ví dụ có trong sách.
"""
from __future__ import annotations

import json
import re
from concurrent.futures import ThreadPoolExecutor

from chung_hn import BAI, TEN_GIAO_TRINH, THU_MUC, ghi_json
from nap import Phien
from quet import tai, tts, xet_am


def bo_nhi_hoa(text: str) -> str:
    """儿 nhi hoá (这儿, 一会儿) đọc liền âm trước, không thành âm tiết riêng; đếm vào sẽ báo oan là đọc nhanh."""
    return re.sub(r"(?<![女婴幼孤胎])儿(?![子童])", "", text)


def main() -> dict:
    s = Phien()
    s.dang_nhap()
    ds = s.goi("GET", "/admin/curricula")
    cid = next(c["id"] for c in (ds["curricula"] if isinstance(ds, dict) and "curricula" in ds else ds) if c["name"] == TEN_GIAO_TRINH)
    loi: dict[str, list[str]] = {}
    dem: dict[str, int] = {}

    def ghi(nhom: str, msg: str) -> None:
        loi.setdefault(nhom, []).append(msg)

    def cong(k: str) -> None:
        dem[k] = dem.get(k, 0) + 1

    am: dict[str, tuple[str, str]] = {}
    net: dict[str, tuple[str, int, str]] = {}
    for q in BAI:
        co = s.goi("GET", f"/admin/levels/{q}/lessons?curriculum={cid}")["lessons"]
        if sorted(b["no"] for b in co) != sorted(BAI[q]):
            ghi("Thiếu bài", f"Quyển {q}: có {sorted(b['no'] for b in co)}")
        for b in co:
            noi = f"Q{q} bài {b['no']}"
            cong("bài")
            tu = s.goi("GET", f"/learn/lessons/{b['id']}/flashcards")["words"]
            if not tu:
                ghi("Lật thẻ: bài không có từ", noi)
            for w in tu:
                cong("từ")
                am[tts(w["hanzi"], "nam", w["pinyin"])] = (w["hanzi"], f"{noi} · từ {w['hanzi']}")
                if not w.get("meaningVi") and not w.get("meaning"):
                    ghi("Lật thẻ: từ thiếu nghĩa", f"{noi} · {w['hanzi']}")
                for e in w["examples"]:
                    cong("câu ví dụ")
                    if not e.get("pinyin") or not e.get("vi"):
                        ghi("Lật thẻ: ví dụ thiếu pinyin/nghĩa", f"{noi} · {e['zh']}")
                    am[tts(e["zh"], "nam")] = (e["zh"], f"{noi} · ví dụ {e['zh']}")
            chu = s.goi("GET", f"/learn/lessons/{b['id']}/writing")["chars"]
            if not chu:
                ghi("Luyện viết: bài không có chữ", noi)
            for c in chu:
                cong("chữ luyện viết")
                for g in ("nu", "nam"):
                    am[tts(c["hanzi"], g)] = (c["hanzi"], f"{noi} · chữ {c['hanzi']}")
                if not c["strokeDataUrl"]:
                    ghi("Luyện viết: thiếu dữ liệu nét", f"{noi} · {c['hanzi']}")
                else:
                    net[c["strokeDataUrl"]] = (c["hanzi"], c["strokes"], noi)
                if not c.get("radicalInfo"):
                    ghi("Luyện viết: thiếu bộ thủ", f"{noi} · {c['hanzi']}")
                if not c.get("analysis"):
                    ghi("Luyện viết: thiếu phân tích chữ", f"{noi} · {c['hanzi']}")
            khoa = s.goi("GET", f"/learn/lessons/{b['id']}/dialogue")["dialogues"]
            if not khoa:
                ghi("Hội thoại: bài không có bài khóa", noi)
            for k in khoa:
                cong("bài khóa")
                if not k.get("audioUrl"):
                    ghi("Hội thoại: bài khóa chưa có file nghe", f"{noi} · khóa {k['no']}")
                else:
                    am[k["audioUrl"]] = ("", f"{noi} · bài khóa {k['no']}")
                for l in k["lines"]:
                    cong("câu thoại")
                    if not l.get("audioUrl"):
                        ghi("Hội thoại: câu thoại chưa có âm thanh", f"{noi} · {l['zh'][:30]}")
                    else:
                        am[l["audioUrl"]] = (l["zh"], f"{noi} · câu {l['zh'][:20]}")
            diem = s.goi("GET", f"/learn/lessons/{b['id']}/grammar")["points"]
            if not diem:
                ghi("Ngữ pháp: bài không có mục", noi)
            for p in diem:
                cong("mục ngữ pháp")
                for e in p["examples"]:
                    cong("ví dụ ngữ pháp")
                    for g in ("nu", "nam"):
                        am[tts(e["zh"], g)] = (e["zh"], f"{noi} · ngữ pháp {e['zh'][:20]}")

    def xet(item):
        url, (text, noi) = item
        return url, noi, xet_am(url, bo_nhi_hoa(text) if "/tts/" in url else "")

    with ThreadPoolExecutor(8) as ex:
        for url, noi, e in ex.map(xet, am.items()):
            if e:
                ghi(f"Âm thanh: {e.split(':')[0]}", f"{noi} · {url} · {e}")
    dem["file âm thanh"] = len(am)

    def xet_net(item):
        url, (h, so, noi) = item
        b = tai(url)
        if b is None:
            return f"{noi} · {h}: không tải được"
        try:
            d = json.loads(b)
        except ValueError:
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
    kq = {"dem": dem, "am_thanh": sorted(am), "net_chu": sorted(net), "loi": {k: sorted(set(v)) for k, v in sorted(loi.items())}}
    ghi_json(THU_MUC / "ra" / "quet_hn.json", kq)
    return kq


if __name__ == "__main__":
    kq = main()
    print(json.dumps(kq["dem"], ensure_ascii=False))
    for k, v in kq["loi"].items():
        print(f"{k}: {len(v)}  (vd: {v[0]})")
    if not kq["loi"]:
        print("Không có lỗi.")
