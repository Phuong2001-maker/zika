"""Đọc lại nội dung đã nạp qua API của người học và so với bai/*.json (bản chép từ sách).

  python doi_chieu.py all | python doi_chieu.py 2 | python doi_chieu.py 2 5

Bắt các chỗ nạp hụt / sai lệch trên đường sách → JSON → web: thiếu từ, sai pinyin/nghĩa, câu thoại
lệch, ngữ pháp thiếu, chữ luyện viết thiếu nét, số câu hỏi không khớp. Kết quả: ra/doi_chieu.json.
"""
from __future__ import annotations

import json
import sys

from chung import THU_MUC, chon_bai, doc_bai, file_bai, ghi_json, so_bai
from nap import Phien
from sinh import viet_thuong


def sach(s: str | None) -> str:
    return " ".join((s or "").split())


def so_bai_hoc(s: Phien, cap: int, so: int, lid: int, tu_truoc: set[str]) -> list[str]:
    d = doc_bai(cap, so)
    p = json.loads((THU_MUC / "ra" / f"hsk{cap}" / f"bai{so:02d}.json").read_text(encoding="utf-8"))
    lech: list[str] = []

    # Lật thẻ
    web = {w["hanzi"]: w for w in s.goi("GET", f"/learn/lessons/{lid}/flashcards")["words"]}
    da_xet: set[str] = set()
    for w in d["tu_moi"] + d["ten_rieng"]:
        h = w["hanzi"]
        if h in tu_truoc or h in da_xet:
            continue
        da_xet.add(h)
        if h not in web:
            lech.append(f"Lật thẻ thiếu {h}")
            continue
        if sach(web[h]["pinyin"]) != sach(w["pinyin"]):
            lech.append(f"Lật thẻ {h}: pinyin web “{web[h]['pinyin']}” ≠ sách “{w['pinyin']}”")
        if sach(w["nghia"]) not in sach(web[h]["meaning"]):  # nghĩa gộp khi sách in một chữ hai lần
            lech.append(f"Lật thẻ {h}: nghĩa web “{web[h]['meaning']}” ≠ sách “{w['nghia']}”")
    du = set(web) - {w["hanzi"] for w in d["tu_moi"] + d["ten_rieng"]}
    if du:
        lech.append(f"Lật thẻ thừa {'、'.join(sorted(du))}")

    # Hội thoại
    web_k = s.goi("GET", f"/learn/lessons/{lid}/dialogue")["dialogues"]
    sach_k = [k for k in d["bai_khoa"] if any(c.get("zh") for c in k["cau"])]
    if len(web_k) != len(sach_k):
        lech.append(f"Hội thoại: web {len(web_k)} bài khoá, sách {len(sach_k)}")
    for kw, ks in zip(web_k, sach_k):
        dong_web = kw["lines"]
        dong_sach = [c for c in ks["cau"] if c.get("zh")]
        if len(dong_web) != len(dong_sach):
            lech.append(f"Bài khoá {ks['so']}: web {len(dong_web)} câu, sách {len(dong_sach)}")
        for i, (a, b) in enumerate(zip(dong_web, dong_sach), 1):
            for kweb, ksach in (("zh", "zh"), ("pinyin", "pinyin"), ("vi", "vi")):
                if sach(a.get(kweb)) != sach(b.get(ksach)):
                    lech.append(f"Bài khoá {ks['so']} câu {i} {kweb}: web “{sach(a.get(kweb))[:30]}” ≠ sách “{sach(b.get(ksach))[:30]}”")

    # Ngữ pháp — điểm dài quá 6 ví dụ được tách thành "(tiếp)", nên so tên theo payload và so đủ mọi ví dụ của sách
    web_g = s.goi("GET", f"/learn/lessons/{lid}/grammar")["points"]
    if [sach(g.get("title")) for g in web_g] != [sach(g["title"]) for g in p["grammar"]]:
        lech.append(f"Ngữ pháp: web {len(web_g)} điểm, payload {len(p['grammar'])} điểm (hoặc tên lệch)")
    for gw, gp in zip(web_g, p["grammar"]):
        if sach(gw.get("explain")) != sach(gp["explain"]):
            lech.append(f"Ngữ pháp “{gp['title']}”: phần giải thích lệch")
    vd_web = {(sach(e.get("zh")), sach(e.get("vi"))) for g in web_g for e in g.get("examples", [])}
    for gs in d["ngu_phap"]:
        for b in gs.get("vi_du", []):
            if (sach(b["zh"]), sach(b.get("vi"))) not in vd_web:
                lech.append(f"Ngữ pháp “{gs['ten']}”: ví dụ “{b['zh'][:20]}” thiếu hoặc lệch trên web")

    # Luyện viết
    web_c = s.goi("GET", f"/learn/lessons/{lid}/writing")["chars"]
    can = {c["hanzi"] for c in p["writing"]}
    co = {c["hanzi"] for c in web_c}
    if can - co:
        lech.append(f"Luyện viết thiếu {''.join(sorted(can - co))}")
    thieu = [c["hanzi"] for c in web_c if not c.get("strokeDataUrl")]
    if thieu:
        lech.append(f"Luyện viết: chữ chưa có nét {''.join(thieu)}")
    rong = [c["hanzi"] for c in web_c if not c.get("hanViet") or not c.get("meaning")]
    if rong:
        lech.append(f"Luyện viết: chữ thiếu Hán Việt/nghĩa {''.join(rong)}")

    # Câu hỏi
    n_bt = len(s.goi("GET", f"/learn/lessons/{lid}/exercises")["questions"])
    if n_bt != len(p["bt"]):
        lech.append(f"Bài tập: web {n_bt} câu, payload {len(p['bt'])}")
    t = s.goi("GET", f"/learn/lessons/{lid}/test")
    if t["questionCount"] != len(p["kt"]):
        lech.append(f"Kiểm tra: web {t['questionCount']} câu, payload {len(p['kt'])}")
    if n_bt == 0:
        lech.append("Bài tập trống")
    if t["questionCount"] == 0:
        lech.append("Kiểm tra trống")
    return lech


if __name__ == "__main__":
    s = Phien()
    s.dang_nhap()
    chon = chon_bai(sys.argv[1:])
    ket_qua: dict[str, list[str]] = {}
    hien = {lv["id"]: lv for lv in s.goi("GET", "/learn/levels")} if True else {}
    for cap in sorted({c for c, _ in chon}):
        lv = hien.get(cap) or next((x for x in hien.values() if x.get("no") == cap), None)
        bai_co = {x["no"]: x for x in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]}
        tu_truoc: set[str] = set()
        for so in range(1, so_bai(cap) + 1):
            if not file_bai(cap, so).exists():
                continue
            d = doc_bai(cap, so)
            if (cap, so) in chon:
                ten = f"HSK {cap} · Bài {so}"
                b = bai_co.get(so)
                if b is None:
                    ket_qua[ten] = ["chưa nạp"]
                elif b["status"] != "published":
                    ket_qua[ten] = [f"đang ở trạng thái {b['status']}"]
                else:
                    ket_qua[ten] = so_bai_hoc(s, cap, so, b["id"], tu_truoc)
                print(f"{ten}: " + ("✓ khớp" if not ket_qua[ten] else f"{len(ket_qua[ten])} chỗ lệch"))
                for x in ket_qua[ten][:12]:
                    print(f"   - {x}")
            tu_truoc |= {w["hanzi"] for w in d["tu_moi"] + d["ten_rieng"]}
    f = THU_MUC / "ra" / "doi_chieu.json"
    gop = json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}
    gop.update(ket_qua)
    ghi_json(f, gop)
    n = sum(1 for v in ket_qua.values() if not v)
    print(f"\n{n}/{len(ket_qua)} bài khớp hoàn toàn")
