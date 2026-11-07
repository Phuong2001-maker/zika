"""Kiểm bai/q<quyển>/baiNN.json trước khi nạp.

  python kiem_tra.py 2 16     một bài
  python kiem_tra.py 2        cả quyển
  python kiem_tra.py all      cả 6 quyển

✗ = lỗi (phải sửa, mã thoát 1) · ! = cảnh báo (soát lại với ảnh; đúng như sách thì để nguyên).
Pinyin được so với máy theo từng âm tiết nên bắt được chữ đọc nhầm khi chép từ ảnh.
"""
from __future__ import annotations

import json
import sys

from chung_hn import chon_bai, file_bai, trang_cua_bai
from chung import chu_han, la_han  # noqa: E402  (nằm ở data/nap-sach, chung_hn đã thêm vào sys.path)
from kiem_tra_goc import so_pinyin

KHOA = ("quyen", "bai", "trang", "ten_zh", "ten_vi", "tu_moi", "ten_rieng", "bai_khoa", "ngu_phap", "viet_chu", "loi_sach", "ghi_chu")


def kiem(q: int, bai: int) -> tuple[int, int]:
    f = file_bai(q, bai)
    ten = f"Quyển {q} · Bài {bai}"
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

    for k in KHOA:
        can(k in d, f"thiếu khoá “{k}”")
    if loi:
        print(f"{ten}: ✗ " + "; ".join(loi))
        return len(loi), 0
    can(d["quyen"] == q and d["bai"] == bai, "quyen/bai không khớp tên file")
    can(list(d["trang"]) == list(trang_cua_bai(q, bai)), f"trang phải là {list(trang_cua_bai(q, bai))}")
    can(bool(d["ten_zh"].strip()) and bool(d["ten_vi"].strip()), "thiếu tên bài")

    # ---- Từ mới + tên riêng → Lật thẻ
    thay = set()
    for w in d["tu_moi"] + d["ten_rieng"]:
        h = w.get("hanzi", "")
        nhan = f"từ {h or '?'}"
        # Từ dạng khung như 太…了 giữ dấu … đúng như sách in.
        if not can(bool(chu_han(h)) and all(la_han(c) or c == "…" for c in h) and "……" not in h, f"{nhan}: “hanzi” chỉ gồm chữ Hán và một dấu …"):
            continue
        can(bool(w.get("pinyin", "").strip()), f"{nhan}: thiếu pinyin")
        can(bool(w.get("nghia", "").strip()), f"{nhan}: thiếu nghĩa")
        if h in thay:
            canh.append(f"{nhan}: lặp lại trong bảng từ")
        thay.add(h)
        canh += [f"{nhan}: {x}" for x in so_pinyin(h, w.get("pinyin", ""))]

    # ---- Bài khoá → Đọc hội thoại
    so_cau = 0
    can(bool(d["bai_khoa"]) or q == 1, "không có bài khoá")
    for k in d["bai_khoa"]:
        nhan = f"bài khoá {k.get('so', '?')}"
        can(k.get("loai") in ("hoi_thoai", "doan_van"), f"{nhan}: “loai” phải là hoi_thoai/doan_van")
        cau = k.get("cau", [])
        if not can(1 <= len(cau) <= 60, f"{nhan}: cần 1–60 câu (nay {len(cau)})"):
            continue
        can(len({c.get("nguoi", "") for c in cau}) <= 6, f"{nhan}: quá 6 nhân vật")
        for j, c in enumerate(cau, 1):
            n2 = f"{nhan} câu {j}"
            if k.get("loai") == "hoi_thoai":
                can(bool(c.get("nguoi", "").strip()), f"{n2}: thiếu người nói")
            if not can(bool(chu_han(c.get("zh", ""))), f"{n2}: thiếu câu tiếng Trung"):
                continue
            can(bool(c.get("pinyin", "").strip()), f"{n2}: thiếu pinyin")
            can(bool(c.get("vi", "").strip()), f"{n2}: thiếu bản dịch")
            canh += [f"{n2}: {x}" for x in so_pinyin(c["zh"], c.get("pinyin", ""))]
            so_cau += 1

    # ---- Ngữ pháp, chú thích, ngữ âm → Cấu trúc ngữ pháp
    so_vd = 0
    for g in d["ngu_phap"]:
        nhan = f"ngữ pháp “{g.get('ten', '?')}”"
        can(bool(g.get("ten", "").strip()), "ngữ pháp thiếu tên")
        can(g.get("nhom") in ("ngu_phap", "chu_thich", "ngu_am"), f"{nhan}: “nhom” phải là ngu_phap/chu_thich/ngu_am")
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

    # ---- Tập viết chữ Hán → Luyện viết
    gap = set()
    for c in d["viet_chu"]:
        h = c.get("hanzi", "")
        can(len(h) == 1 and la_han(h), f"viet_chu: “{h}” phải đúng một chữ Hán")
        can(bool(c.get("pinyin", "").strip()), f"viet_chu {h}: thiếu pinyin")
        if h in gap:
            canh.append(f"viet_chu {h}: lặp lại")
        gap.add(h)
        canh += [f"viet_chu {x}" for x in so_pinyin(h, c.get("pinyin", ""))]
    if not d["viet_chu"]:
        canh.append("không có chữ tập viết: sinh.py sẽ tách chữ từ Từ mới")

    for x in d["loi_sach"]:
        can(all(k in x for k in ("o_dau", "sach_in", "sua_thanh")), "loi_sach thiếu o_dau/sach_in/sua_thanh")

    print(f"{ten}: {len(d['tu_moi'])} từ mới, {len(d['ten_rieng'])} tên riêng, {len(d['bai_khoa'])} bài khoá ({so_cau} câu), "
          f"{len(d['ngu_phap'])} ngữ pháp ({so_vd} ví dụ), {len(d['viet_chu'])} chữ viết — {len(loi)} lỗi, {len(canh)} cảnh báo")
    for x in loi:
        print(f"   ✗ {x}")
    for x in canh:
        print(f"   ! {x}")
    return len(loi), len(canh)


if __name__ == "__main__":
    tong_loi = tong_canh = 0
    ds = chon_bai(sys.argv[1:])
    for q, b in ds:
        l, w = kiem(q, b)
        tong_loi += l
        tong_canh += w
    if len(ds) > 1:
        print(f"\nTổng: {tong_loi} lỗi, {tong_canh} cảnh báo")
    sys.exit(1 if tong_loi else 0)
