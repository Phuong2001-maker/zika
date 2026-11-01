"""Xuất ảnh + lớp chữ các trang của một bài để đọc và chép dữ liệu.

  python xem_trang.py 2 5                       ảnh cả trang, ảnh nửa trên/dưới (nét hơn) và lớp chữ của HSK 2 bài 5
  python xem_trang.py 2 5 --cat 52 0 .3 1 .6    phóng to một vùng của trang PDF 52 (toạ độ 0–1: x0 y0 x1 y1)

Kết quả nằm ở anh/hsk<cấp>/bai<số>/ (không đưa lên git).
"""
import argparse

import pymupdf

from chung import THU_MUC, pdf, trang_cua_bai

ap = argparse.ArgumentParser()
ap.add_argument("cap", type=int)
ap.add_argument("bai", type=int)
ap.add_argument("--cat", nargs=5, type=float, metavar=("TRANG", "X0", "Y0", "X1", "Y1"))
a = ap.parse_args()

ra = THU_MUC / "anh" / f"hsk{a.cap}" / f"bai{a.bai:02d}"
ra.mkdir(parents=True, exist_ok=True)
doc_anh = pymupdf.open(pdf(a.cap, "anh"))
doc_chu = pymupdf.open(pdf(a.cap, "chu"))

if a.cat:
    so, x0, y0, x1, y1 = a.cat
    trang = doc_anh[int(so) - 1]
    r = trang.rect
    vung = pymupdf.Rect(r.x0 + x0 * r.width, r.y0 + y0 * r.height, r.x0 + x1 * r.width, r.y0 + y1 * r.height)
    f = ra / f"p{int(so):03d}_cat_{x0:g}_{y0:g}_{x1:g}_{y1:g}.jpg"
    trang.get_pixmap(dpi=260, clip=vung).save(f, jpg_quality=85)
    print(f)
    raise SystemExit

dau, cuoi = trang_cua_bai(a.cap, a.bai)
print(f"HSK {a.cap} bài {a.bai}: trang PDF {dau}–{cuoi}  → {ra}")
for so in range(dau, cuoi + 1):
    trang = doc_anh[so - 1]
    r = trang.rect
    trang.get_pixmap(dpi=120).save(ra / f"p{so:03d}.jpg", jpg_quality=82)
    tren = pymupdf.Rect(r.x0, r.y0, r.x1, r.y0 + r.height * 0.55)
    duoi = pymupdf.Rect(r.x0, r.y0 + r.height * 0.45, r.x1, r.y1)
    trang.get_pixmap(dpi=180, clip=tren).save(ra / f"p{so:03d}_tren.jpg", jpg_quality=82)
    trang.get_pixmap(dpi=180, clip=duoi).save(ra / f"p{so:03d}_duoi.jpg", jpg_quality=82)
    (ra / f"p{so:03d}.txt").write_text(doc_chu[so - 1].get_text(), encoding="utf-8")
    print(f"  p{so:03d}.jpg  p{so:03d}_tren.jpg  p{so:03d}_duoi.jpg  p{so:03d}.txt")
