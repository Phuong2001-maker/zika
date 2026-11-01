"""Kiểm bai/hsk<cấp>/baiNN.json trước khi nạp.

  python kiem_tra.py 2 5      một bài
  python kiem_tra.py 2        cả quyển
  python kiem_tra.py all      cả 3 quyển

✗ = lỗi (phải sửa, mã thoát 1) · ! = cảnh báo (soát lại với ảnh; đúng như sách thì để nguyên).
"""
import json
import re
import sys

from chung import _pypinyin, chon_bai, chu_han, file_bai, la_han, tach_am_tiet, trang_cua_bai

DANG_LT = {"chon_tu_dien", "sap_xep", "dung_sai", "chon", "dich"}
DANG_CH = {"dung_sai", "chon"}
DAU_CAU = "，。？！：；、“”‘’（）《》…—,.?!:;\"'()- "
_THANH = {c: t for t, nhom in enumerate(["āēīōūǖ", "áéíóúǘ", "ǎěǐǒǔǚ", "àèìòùǜ"], 1) for c in nhom}


def thanh(am: str) -> int:
    return next((_THANH[c] for c in am if c in _THANH), 5)


def khong_dau(am: str) -> str:
    s = am.lower()
    for nhom, goc in [("āáǎà", "a"), ("ēéěè", "e"), ("īíǐì", "i"), ("ōóǒò", "o"), ("ūúǔù", "u"), ("ǖǘǚǜü", "v")]:
        for c in nhom:
            s = s.replace(c, goc)
    return re.sub(r"[^a-z]", "", s)


def so_pinyin(han: str, py: str) -> list[str]:
    """So pinyin sách với mọi cách đọc có thể của từng chữ (đa âm, biến điệu 一/不, thanh nhẹ)."""
    m = _pypinyin()
    if m is None or not py.strip():
        return []
    pinyin, Style = m
    chu = [c for c in han if la_han(c)]
    if not chu or re.search(r"\d", han):
        return []
    am = tach_am_tiet(py)
    if "儿" in chu and len(am) == len(chu) - chu.count("儿"):
        chu = [c for c in chu if c != "儿"]  # nhi hoá: 一点儿 = yīdiǎnr
    if len(am) != len(chu):
        return [f"số âm tiết pinyin ({len(am)}) khác số chữ ({len(chu)}): {han} · {py}"]
    loi = []
    doc = pinyin("".join(chu), style=Style.TONE3, heteronym=True, neutral_tone_with_five=True, errors="ignore")
    for c, a, cach in zip(chu, am, doc):
        a0 = khong_dau(a)
        if a.lower().endswith("r") and a0 not in ("er",) and c != "儿":
            a0 = a0[:-1] if a0[:-1] in {re.sub(r"\d", "", x).replace("ü", "v") for x in cach} else a0
        am_may = {re.sub(r"\d", "", x).replace("ü", "v") for x in cach}
        if a0 not in am_may:
            loi.append(f"{c} đọc “{a}”, máy biết {'/'.join(sorted(am_may))}")
            continue
        t = thanh(a)
        thanh_may = {int(x[-1]) for x in cach if x[-1].isdigit() and re.sub(r"\d", "", x).replace("ü", "v") == a0}
        if t != 5 and thanh_may and t not in thanh_may and c not in "一不":
            loi.append(f"{c} “{a}” thanh {t}, máy biết thanh {'/'.join(map(str, sorted(thanh_may)))}")
    return loi


def kiem(cap: int, bai: int) -> tuple[int, int]:
    f = file_bai(cap, bai)
    ten = f"HSK {cap} · Bài {bai}"
    if not f.exists():
        print(f"{ten}: CHƯA CÓ {f.name}")
        return 1, 0
    try:
        d = json.loads(f.read_text(encoding="utf-8"))
    except json.JSONDecodeError as e:
        print(f"{ten}: ✗ JSON hỏng — {e}")
        return 1, 0
    loi: list[str] = []
    canh: list[str] = []

    def can(dk: bool, msg: str) -> bool:
        if not dk:
            loi.append(msg)
        return dk

    for k in ("cap", "bai", "trang", "ten_zh", "ten_vi", "tu_moi", "ten_rieng", "bai_khoa", "ngu_phap",
              "luyen_tap", "mau_cau", "nhan_biet", "loi_sach"):
        can(k in d, f"thiếu khoá “{k}”")
    if loi:
        print(f"{ten}: ✗ " + "; ".join(loi))
        return len(loi), 0
    can(d["cap"] == cap and d["bai"] == bai, "cap/bai không khớp tên file")
    can(list(d["trang"]) == list(trang_cua_bai(cap, bai)), f"trang phải là {list(trang_cua_bai(cap, bai))}")
    can(bool(d["ten_zh"].strip()) and bool(d["ten_vi"].strip()), "thiếu tên bài")

    # ---- Từ mới + tên riêng
    tu = d["tu_moi"] + d["ten_rieng"]
    thay = set()
    for i, w in enumerate(tu, 1):
        nhan = f"từ {w.get('hanzi', '?')}"
        h = w.get("hanzi", "")
        if not can(bool(h) and all(la_han(c) for c in h), f"{nhan}: “hanzi” phải toàn chữ Hán"):
            continue
        can(bool(w.get("pinyin", "").strip()), f"{nhan}: thiếu pinyin")
        can(bool(w.get("nghia", "").strip()), f"{nhan}: thiếu nghĩa")
        if w in d["tu_moi"] and not w.get("loai", "").strip():
            canh.append(f"{nhan}: thiếu loại từ")
        if h in thay:
            canh.append(f"{nhan}: lặp lại trong bảng từ")
        thay.add(h)
        canh += [f"{nhan}: {x}" for x in so_pinyin(h, w.get("pinyin", ""))]

    # ---- Bài khoá
    so_cau = 0
    for k in d["bai_khoa"]:
        nhan = f"bài khoá {k.get('so', '?')}"
        can(k.get("loai") in ("hoi_thoai", "doan_van"), f"{nhan}: “loai” phải là hoi_thoai/doan_van")
        cau = k.get("cau", [])
        if not can(1 <= len(cau) <= 60, f"{nhan}: cần 1–60 câu"):
            continue
        nguoi = {c.get("nguoi", "") for c in cau}
        can(len(nguoi) <= 6, f"{nhan}: quá 6 nhân vật")
        for j, c in enumerate(cau, 1):
            n2 = f"{nhan} câu {j}"
            if k.get("loai") == "hoi_thoai":
                can(bool(c.get("nguoi", "").strip()), f"{n2}: thiếu người nói")
            if not can(bool(chu_han(c.get("zh", ""))), f"{n2}: thiếu câu tiếng Trung"):
                continue
            if not c.get("pinyin", "").strip():
                canh.append(f"{n2}: thiếu pinyin")
            if not c.get("vi", "").strip():
                canh.append(f"{n2}: thiếu bản dịch")
            canh += [f"{n2}: {x}" for x in so_pinyin(c["zh"], c.get("pinyin", ""))]
            so_cau += 1
        for q in k.get("cau_hoi", []):
            can(q.get("dang") in DANG_CH, f"{nhan}: câu hỏi dạng lạ “{q.get('dang')}”")
            kiem_cau_hoi(q, nhan, can)

    # ---- Ngữ pháp
    so_vd = 0
    for g in d["ngu_phap"]:
        nhan = f"ngữ pháp “{g.get('ten', '?')}”"
        can(bool(g.get("ten", "").strip()), "ngữ pháp thiếu tên")
        can(bool(g.get("giai_thich", "").strip()), f"{nhan}: thiếu giải thích")
        can(isinstance(g.get("cong_thuc", []), list), f"{nhan}: “cong_thuc” phải là danh sách")
        for v in g.get("vi_du", []):
            if not can(bool(chu_han(v.get("zh", ""))), f"{nhan}: ví dụ thiếu câu tiếng Trung"):
                continue
            so_vd += 1
            if not v.get("pinyin", "").strip():
                canh.append(f"{nhan}: ví dụ “{v['zh']}” thiếu pinyin")
            if not v.get("vi", "").strip():
                canh.append(f"{nhan}: ví dụ “{v['zh']}” thiếu nghĩa")
            canh += [f"{nhan}: {x}" for x in so_pinyin(v["zh"], v.get("pinyin", ""))]
        if not g.get("vi_du"):
            canh.append(f"{nhan}: không có ví dụ")

    # ---- Luyện tập
    for b in d["luyen_tap"]:
        dang = b.get("dang")
        nhan = f"luyện tập “{dang}”"
        if not can(dang in DANG_LT, f"luyện tập dạng lạ “{dang}”"):
            continue
        if dang == "chon_tu_dien":
            bank = b.get("tu", [])
            can(len(bank) >= 2, f"{nhan}: cần ít nhất 2 từ cho sẵn")
            for c in b.get("cau", []):
                can(c.get("cau", "").count("___") == 1, f"{nhan}: “{c.get('cau')}” phải có đúng một ___")
                can(c.get("dap_an") in bank, f"{nhan}: đáp án “{c.get('dap_an')}” không nằm trong các từ cho sẵn")
        elif dang == "sap_xep":
            for c in b.get("cau", []):
                o = c.get("o", [])
                can(2 <= len(o) <= 8, f"{nhan}: “{c.get('dap_an')}” cần 2–8 ô")
                ghep = "".join(o)
                tron = lambda s: "".join(ch for ch in s if ch not in DAU_CAU)  # noqa: E731
                can(tron(ghep) == tron(c.get("dap_an", "")), f"{nhan}: các ô ghép lại ≠ “{c.get('dap_an')}”")
                if len(c.get("dap_an", "")) > 60:
                    canh.append(f"{nhan}: “{c['dap_an'][:20]}…” dài quá 60 ký tự, sẽ bị bỏ")
        elif dang in ("dung_sai", "chon"):
            if len(b.get("doan", "")) > 400:
                canh.append(f"{nhan}: đoạn văn dài quá 400 ký tự, sẽ bị bỏ")
            for c in b.get("cau", []):
                kiem_cau_hoi({"dang": dang, **c}, nhan, can)
        elif dang == "dich":
            for c in b.get("cau", []):
                can(bool(c.get("zh")) and bool(c.get("vi")), f"{nhan}: cần đủ câu Trung và câu Việt sách in")

    # ---- Mẫu câu, nhận biết
    for m in d["mau_cau"]:
        can(bool(m.get("mau", "").strip()) and isinstance(m.get("vi_du", []), list), "mẫu câu thiếu “mau”/“vi_du”")
    tu_han = [w["hanzi"] for w in tu if w.get("hanzi")]
    if cap == 1:
        co = set("".join(tu_han))
        thieu = [c for c in d["nhan_biet"] if c not in co]
        if thieu:
            canh.append(f"会认字 có {len(thieu)} chữ không nằm trong từ mới/tên riêng: {''.join(thieu)}")
    else:
        thieu = [w for w in d["nhan_biet"] if w not in tu_han]
        if thieu:
            canh.append(f"会认词 có {len(thieu)} từ không nằm trong từ mới: {'、'.join(thieu)}")
    for x in d["loi_sach"]:
        can(all(k in x for k in ("o_dau", "sach_in", "sua_thanh")), "loi_sach thiếu o_dau/sach_in/sua_thanh")

    so_lt = sum(len(b.get("cau", [])) for b in d["luyen_tap"])
    so_ch = sum(len(k.get("cau_hoi", [])) for k in d["bai_khoa"])
    print(f"{ten}: {len(d['tu_moi'])} từ mới, {len(d['ten_rieng'])} tên riêng, {len(d['bai_khoa'])} bài khoá "
          f"({so_cau} câu, {so_ch} câu hỏi), {len(d['ngu_phap'])} ngữ pháp ({so_vd} ví dụ), {so_lt} câu luyện tập, "
          f"{len(d['mau_cau'])} mẫu câu, {len(d['nhan_biet'])} nhận biết — {len(loi)} lỗi, {len(canh)} cảnh báo")
    for x in loi:
        print(f"   ✗ {x}")
    for x in canh:
        print(f"   ! {x}")
    return len(loi), len(canh)


def kiem_cau_hoi(q: dict, nhan: str, can) -> None:
    if q["dang"] == "dung_sai":
        can(bool(q.get("cau", "").strip()), f"{nhan}: câu đúng/sai trống")
        can(isinstance(q.get("dap_an"), bool), f"{nhan}: “{q.get('cau')}” — dap_an phải là true/false")
    else:
        pa = q.get("phuong_an", [])
        can(bool(q.get("hoi", "").strip()), f"{nhan}: câu hỏi chọn thiếu “hoi”")
        can(2 <= len(pa) <= 4 and len(set(pa)) == len(pa), f"{nhan}: “{q.get('hoi')}” cần 2–4 phương án khác nhau")
        can(isinstance(q.get("dap_an"), int) and 0 <= q.get("dap_an", -1) < len(pa), f"{nhan}: “{q.get('hoi')}” dap_an sai chỉ số")


if __name__ == "__main__":
    tong_loi = tong_canh = 0
    for c, b in chon_bai(sys.argv[1:]):
        l, w = kiem(c, b)
        tong_loi += l
        tong_canh += w
    if len(chon_bai(sys.argv[1:])) > 1:
        print(f"\nTổng: {tong_loi} lỗi, {tong_canh} cảnh báo")
    sys.exit(1 if tong_loi else 0)
