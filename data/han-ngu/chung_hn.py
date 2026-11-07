"""Phần dùng chung cho bộ nạp Giáo trình Hán ngữ 6 quyển (xem README.md cùng thư mục).

Dùng lại hàm của data/nap-sach (chữ Hán, pinyin, Hán Việt, cấu tạo chữ) để hai bộ nạp đi cùng một chuẩn.
Quyển n nằm ở cấp n của giáo trình "Hán ngữ 6 tập"; số bài giữ đúng số sách in (quyển 2 bắt đầu từ Bài 16).
"""
from __future__ import annotations

import json
import os
import sys
from pathlib import Path

THU_MUC = Path(__file__).resolve().parent
NAP_SACH = THU_MUC.parent / "nap-sach"
sys.path.insert(0, str(NAP_SACH))

from chung import REPO, ghi_json  # noqa: E402

SACH_DIR = Path(os.environ.get("ZIKA_HANNGU_DIR", THU_MUC.parents[2] / "HanNgu"))
TEN_GIAO_TRINH = "Hán ngữ 6 tập"
# Số bài sách in của từng quyển: quyển chẵn nối tiếp số bài của quyển lẻ trước nó.
BAI = {1: range(1, 16), 2: range(16, 26), 3: range(1, 13), 4: range(13, 26), 5: range(1, 14), 6: range(14, 27)}


def pdf(q: int) -> Path:
    p = SACH_DIR / f"0 Giáo trình Hán {q} - New.pdf"
    if not p.exists():
        raise SystemExit(f"Không thấy {p}. Đặt biến ZIKA_HANNGU_DIR trỏ tới thư mục sách.")
    return p


def bang_trang() -> dict:
    return json.loads((THU_MUC / "trang.json").read_text(encoding="utf-8"))


def trang_cua_bai(q: int, bai: int) -> tuple[int, int]:
    """Trang PDF (đếm từ 1) đầu và cuối của một bài, theo trang.json đã đối chiếu với ảnh."""
    t = bang_trang()[str(q)]
    ds = t["trang_dau"]
    i = list(BAI[q]).index(bai)
    return ds[i], (ds[i + 1] - 1 if i + 1 < len(ds) else t["trang_cuoi"])


def file_bai(q: int, bai: int) -> Path:
    # ZIKA_HN_BAI: thư mục khác khi cần so hai bản chép của cùng một bài.
    return Path(os.environ.get("ZIKA_HN_BAI", THU_MUC / "bai")) / f"q{q}" / f"bai{bai:02d}.json"


def file_ra(q: int, bai: int) -> Path:
    return THU_MUC / "ra" / f"q{q}" / f"bai{bai:02d}.json"


def doc_bai(q: int, bai: int) -> dict:
    return json.loads(file_bai(q, bai).read_text(encoding="utf-8"))


def chon_bai(args: list[str]) -> list[tuple[int, int]]:
    """'all' · '2' (cả quyển) · '2 16 17' (vài bài) → danh sách (quyển, bài)."""
    if not args or args[0] == "all":
        return [(q, b) for q in BAI for b in BAI[q]]
    q = int(args[0])
    if len(args) == 1:
        return [(q, b) for b in BAI[q]]
    return [(q, int(b)) for b in args[1:]]


__all__ = ["BAI", "NAP_SACH", "REPO", "SACH_DIR", "TEN_GIAO_TRINH", "THU_MUC", "bang_trang", "chon_bai", "doc_bai",
           "file_bai", "file_ra", "ghi_json", "pdf", "trang_cua_bai"]
