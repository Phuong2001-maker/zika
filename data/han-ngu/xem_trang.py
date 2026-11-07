"""Xuất ảnh các trang của một bài để đọc và chép dữ liệu (sách là bản scan, không có lớp chữ dùng được).

  python xem_trang.py 2 16                       ảnh cả trang và ảnh nửa trên/dưới (nét hơn) của quyển 2 bài 16
  python xem_trang.py 2 16 --cat 22 0 .3 1 .6    phóng to một vùng của trang PDF 22 (toạ độ 0–1: x0 y0 x1 y1)

Kết quả nằm ở anh/q<quyển>/bai<số>/ (không đưa lên git).
"""
import argparse

import pymupdf

from chung_hn import THU_MUC, pdf, trang_cua_bai

ap = argparse.ArgumentParser()
ap.add_argument("quyen", type=int)
ap.add_argument("bai", type=int)
ap.add_argument("--cat", nargs=5, type=float, metavar=("TRANG", "X0", "Y0", "X1", "Y1"))
a = ap.parse_args()

ra = THU_MUC / "anh" / f"q{a.quyen}" / f"bai{a.bai:02d}"
ra.mkdir(parents=True, exist_ok=True)
doc = pymupdf.open(pdf(a.quyen))

if a.cat:
    so, x0, y0, x1, y1 = a.cat
    trang = doc[int(so) - 1]
    r = trang.rect
    vung = pymupdf.Rect(r.x0 + x0 * r.width, r.y0 + y0 * r.height, r.x0 + x1 * r.width, r.y0 + y1 * r.height)
    f = ra / f"p{int(so):03d}_cat_{x0:g}_{y0:g}_{x1:g}_{y1:g}.jpg"
    trang.get_pixmap(dpi=260, clip=vung).save(f, jpg_quality=85)
    print(f)
    raise SystemExit

dau, cuoi = trang_cua_bai(a.quyen, a.bai)
print(f"Quyển {a.quyen} bài {a.bai}: trang PDF {dau}–{cuoi}  → {ra}")
for so in range(dau, cuoi + 1):
    trang = doc[so - 1]
    r = trang.rect
    tren = pymupdf.Rect(r.x0, r.y0, r.x1, r.y0 + r.height * 0.55)
    duoi = pymupdf.Rect(r.x0, r.y0 + r.height * 0.45, r.x1, r.y1)
    trang.get_pixmap(dpi=170, clip=tren).save(ra / f"p{so:03d}_tren.jpg", jpg_quality=80)
    trang.get_pixmap(dpi=170, clip=duoi).save(ra / f"p{so:03d}_duoi.jpg", jpg_quality=80)
    print(f"  p{so:03d}_tren.jpg  p{so:03d}_duoi.jpg")
