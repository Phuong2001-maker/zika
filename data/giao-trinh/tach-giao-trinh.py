# -*- coding: utf-8 -*-
"""
Render 3 quyển giáo trình HSK 1–3 (PDF) thành ảnh WebP từng trang cho phần Giáo trình.

    python tach-giao-trinh.py "<thư mục uploads>"

Trên máy dev thư mục uploads là `api/dev-uploads`, trên hosting là `public_html/uploads`.
Kết quả: `<uploads>/giao-trinh/hsk1/bia.webp` + `001.webp`, `002.webp`…

Vì sao là ảnh chứ không phải chữ: chỉ HSK 1 là PDF chữ thật. HSK 2 và HSK 3 là bản scan —
mỗi trang đúng một ảnh JPEG, 0 ký tự text — nên không tách chữ ra được nếu không OCR.
Để cả ba quyển đọc giống hệt nhau thì render hết thành ảnh.

Vì sao rộng 1200px: đo thử ở 900 / 1200 / 1600px. 1200px đọc rõ cả chữ Hán cỡ nhỏ lẫn
pinyin mà cả bộ 469 trang chỉ ~40MB, trong khi 3 file PDF gốc là 111MB.

Vì sao bìa lấy từ trang 1 của PDF: đó là ảnh scan gốc 2480×3508, nét hơn hẳn ảnh chụp
bằng điện thoại và không dính chữ viết tay trên sách giấy.
"""

import sys
from pathlib import Path

import pymupdf

sys.stdout.reconfigure(encoding="utf-8")

# Rộng 1200px cho trang đọc, 800px cho ảnh bìa ở màn chọn sách (thẻ bìa không bao giờ to hơn thế).
RONG_TRANG = 1200
RONG_BIA = 800
CHAT_LUONG = 72

# Ba quyển, theo đúng thứ tự hiện trên màn chọn sách.
SACH = [
    ("hsk1", r"D:\Tiếng trung\HSK1 3.0 Hanu 24.6.26.pdf"),
    ("hsk2", r"D:\Tiếng trung\HSK2 3.0 Hanu 29.6.26.pdf"),
    ("hsk3", r"D:\Tiếng trung\HSK3 3.0 Hanu 29.6.26.pdf"),
]


def render(trang: pymupdf.Page, rong: int, dich: Path) -> int:
    """Render một trang PDF ra WebP rộng `rong` px, trả về số byte đã ghi."""
    zoom = rong / trang.rect.width
    pix = trang.get_pixmap(matrix=pymupdf.Matrix(zoom, zoom))
    data = pix.pil_tobytes(format="WEBP", quality=CHAT_LUONG, method=4)
    dich.write_bytes(data)
    return len(data)


def main() -> int:
    if len(sys.argv) < 2:
        print(__doc__)
        return 2

    goc = Path(sys.argv[1]).resolve() / "giao-trinh"
    tong = 0

    for ma, pdf in SACH:
        nguon = Path(pdf)
        if not nguon.is_file():
            print(f"THIEU: khong thay {nguon}")
            return 1

        thu_muc = goc / ma
        thu_muc.mkdir(parents=True, exist_ok=True)

        doc = pymupdf.open(nguon)
        cuon = 0

        # Bìa = trang 1, render riêng ở cỡ nhỏ để màn chọn sách tải nhẹ.
        cuon += render(doc[0], RONG_BIA, thu_muc / "bia.webp")

        for i, trang in enumerate(doc, start=1):
            cuon += render(trang, RONG_TRANG, thu_muc / f"{i:03d}.webp")
            if i % 25 == 0 or i == len(doc):
                print(f"  {ma}: {i}/{len(doc)} trang", flush=True)

        print(f"{ma}: {len(doc)} trang · {cuon / 1048576:.1f} MB · {thu_muc}")
        tong += cuon
        doc.close()

    print(f"Xong. Tong cong {tong / 1048576:.1f} MB tai {goc}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
