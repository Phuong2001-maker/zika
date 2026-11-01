"""So từng chuỗi trong bai/*.json với lớp chữ của chính các trang sách đó.

  python doi_chieu_sach.py all | python doi_chieu_sach.py 2 | python doi_chieu_sach.py 2 5

HSK 1 có chữ thật trong PDF → chuỗi chép đúng phải nằm nguyên trong lớp chữ (so khớp tuyệt đối).
HSK 2–3 chỉ có lớp chữ OCR → so theo độ phủ cặp ký tự liền nhau (bigram): chữ Hán, pinyin bỏ dấu
thanh, tiếng Việt bỏ dấu. Chuỗi phủ thấp bị đánh dấu "cần nhìn lại ảnh" — lỗi có thể nằm ở bản chép
hoặc ở OCR, nên đây là danh sách để soát chứ không phải danh sách lỗi.
Kết quả: ra/doi_chieu_sach.json + tóm tắt in ra màn hình.
"""
from __future__ import annotations

import json
import re
import sys
import unicodedata

import pymupdf

from chung import THU_MUC, chon_bai, chuan_hoa_han, doc_bai, file_bai, ghi_json, la_han, pdf, trang_cua_bai

NGUONG = {"han": 0.8, "py": 0.75, "vi": 0.7}


def bo_dau(s: str) -> str:
    s = unicodedata.normalize("NFD", s.replace("đ", "d").replace("Đ", "D"))
    return "".join(c for c in s if unicodedata.category(c) != "Mn")


def chuan(s: str, loai: str) -> str:
    s = chuan_hoa_han(s)
    if loai == "han":
        return "".join(c for c in s if la_han(c))
    s = bo_dau(s).lower()
    return re.sub(r"[^a-z0-9]", "", s)


def cap_doi(s: str) -> set[str]:
    return {s[i:i + 2] for i in range(len(s) - 1)} if len(s) > 1 else {s}


def chuoi_cua_bai(d: dict):
    """(loại, vị trí, chuỗi) cho mọi thứ chép từ sách."""
    for i, w in enumerate(d["tu_moi"] + d["ten_rieng"], 1):
        yield "han", f"từ {w['hanzi']}", w["hanzi"]
        yield "py", f"từ {w['hanzi']} · pinyin", w.get("pinyin", "")
        yield "vi", f"từ {w['hanzi']} · nghĩa", w.get("nghia", "")
    for k in d["bai_khoa"]:
        for j, c in enumerate(k["cau"], 1):
            n = f"bài khoá {k['so']} câu {j}"
            yield "han", n, c.get("zh", "")
            # HSK 3 không in pinyin/bản dịch bài khoá: phần tự thêm không có gì trong sách để so
            if d["cap"] != 3:
                yield "py", n + " · pinyin", c.get("pinyin", "")
            if not c.get("vi_tu_dich"):
                yield "vi", n + " · dịch", c.get("vi", "")
    for g in d["ngu_phap"]:
        yield "vi", f"ngữ pháp “{g['ten'][:20]}” · giải thích", g.get("giai_thich", "")
        for v in g.get("vi_du", []):
            yield "han", f"ngữ pháp “{g['ten'][:20]}” · ví dụ", v.get("zh", "")
            if not v.get("tu_dich"):
                yield "py", f"ngữ pháp · ví dụ {v.get('zh', '')[:10]} · pinyin", v.get("pinyin", "")
                yield "vi", f"ngữ pháp · ví dụ {v.get('zh', '')[:10]} · dịch", v.get("vi", "")
    for m in d["mau_cau"]:
        for v in m.get("vi_du", []):
            yield "han", "mẫu câu", v
    for x in d["nhan_biet"]:
        yield "han", "nhận biết", x


def doc_trang(cap: int, bai: int) -> str:
    dau, cuoi = trang_cua_bai(cap, bai)
    th = THU_MUC / "anh" / f"hsk{cap}" / f"bai{bai:02d}"
    out = []
    doc = None
    for so in range(dau, cuoi + 1):
        f = th / f"p{so:03d}.txt"
        if f.exists():
            out.append(f.read_text(encoding="utf-8"))
        else:
            doc = doc or pymupdf.open(pdf(cap, "chu"))
            out.append(doc[so - 1].get_text())
    return "\n".join(out)


def so(cap: int, bai: int) -> dict:
    d = doc_bai(cap, bai)
    goc = doc_trang(cap, bai)
    nen = {k: chuan(goc, k) for k in NGUONG}
    cap_nen = {k: cap_doi(v) for k, v in nen.items()}
    tot = tong = 0
    co: list[dict] = []
    for loai, vi_tri, s in chuoi_cua_bai(d):
        x = chuan(s, loai)
        if not x:
            continue
        tong += 1
        if x in nen[loai]:
            tot += 1
            continue
        phu = len(cap_doi(x) & cap_nen[loai]) / len(cap_doi(x))
        # HSK 1 chữ thật: không nằm nguyên khối thường chỉ vì PDF ngắt dòng/xếp cột; phủ đủ cặp ký tự là khớp
        if phu < (0.98 if cap == 1 else NGUONG[loai]):
            co.append({"vi_tri": vi_tri, "chuoi": s[:120], "do_phu": round(phu, 2)})
        else:
            tot += 1
    return {"tong": tong, "khop": tot, "can_soat": co}


if __name__ == "__main__":
    kq = {}
    for c, b in chon_bai(sys.argv[1:]):
        if not file_bai(c, b).exists():
            continue
        r = so(c, b)
        kq[f"HSK {c} · Bài {b}"] = r
        print(f"HSK {c} · Bài {b}: {r['khop']}/{r['tong']} chuỗi khớp lớp chữ sách"
              + (f", {len(r['can_soat'])} cần nhìn lại ảnh" if r["can_soat"] else ""))
        for x in r["can_soat"][:6]:
            print(f"   ? {x['vi_tri']}: “{x['chuoi'][:50]}” (phủ {x['do_phu']})")
    f = THU_MUC / "ra" / "doi_chieu_sach.json"
    gop = json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}
    gop.update(kq)
    ghi_json(f, gop)
    t = sum(v["tong"] for v in kq.values())
    k = sum(v["khop"] for v in kq.values())
    if t:
        print(f"\nTổng: {k}/{t} chuỗi khớp ({100 * k / t:.1f}%), {t - k} chuỗi cần nhìn lại ảnh")
