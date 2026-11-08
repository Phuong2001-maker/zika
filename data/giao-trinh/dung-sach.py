# -*- coding: utf-8 -*-
"""
Dựng thư mục đọc giáo trình cho 9 quyển: HSK 1–3 (Hanu, bản 3.0) và Hán ngữ 1–6.

    python dung-sach.py [<thư mục uploads>] [--chi hn1,hn2] [--ve-lai]

Mặc định thư mục uploads là `api/dev-uploads`. Mỗi quyển ra một thư mục `<uploads>/giao-trinh/<mã>/`:

    bia.webp, 001.webp…      ảnh trang sách (rộng 1200px, bìa 800px)
    nghe/01.mp3…             file nghe đi kèm sách, đổi tên theo số thứ tự (tên gốc có dấu tiếng Việt)
    dap-an/001.webp…         ảnh trang đáp án (chỉ Hán ngữ 3–6 có)
    sach.json                file mô tả: tên, chiều cao từng trang, mục lục bài, danh sách file nghe, đáp án

API chỉ đọc sach.json, nên quyển nào không có file nghe hay đáp án thì web tự ẩn mục đó — không viết cứng ở đâu cả.
Ảnh trang đã có thì không render lại (thêm --ve-lai để render lại); file nghe đã chép thì không chép lại.
File MP3 bitrate thay đổi mà thiếu header Xing (bộ Hán ngữ 6) được ffmpeg đóng gói lại để trình duyệt tua đúng.

Vì sao đáp án là ảnh chứ không phải chữ: file Word đáp án có bảng và danh sách đánh số lồng nhau, đổi sang HTML thì
vỡ bố cục. Word in ra PDF (giữ đúng trình bày) rồi render như trang sách, đọc cùng một khung với sách.
"""

import argparse
import hashlib
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

import pymupdf
from PIL import Image

sys.stdout.reconfigure(encoding="utf-8")

GOC_REPO = Path(__file__).resolve().parents[2]
NGUON = GOC_REPO.parent  # D:\zika-devtools\Project
HAN_NGU = NGUON / "HanNgu"
RA_DAP_AN = Path(__file__).resolve().parent / "ra" / "dap-an"

RONG_TRANG = 1200
RONG_BIA = 800
CHAT_LUONG = 72
TRINH_DO = {1: "Sơ cấp", 2: "Sơ cấp", 3: "Trung cấp", 4: "Trung cấp", 5: "Cao cấp", 6: "Cao cấp"}


def sach_hsk(cap: int) -> dict:
    pdf = {1: "HSK1 3.0 Hanu 24.6.26.pdf", 2: "HSK2 3.0 Hanu 29.6.26.pdf", 3: "HSK3 3.0 Hanu 29.6.26.pdf"}[cap]
    return {
        "ma": f"hsk{cap}", "bo": "hsk", "cap": cap,
        "ten": f"Giáo trình HSK {cap}", "phu": "Đại học Hà Nội · bản 3.0",
        "pdf": NGUON / pdf,
        "bai": [(b["bai"], b["ten_zh"], b["ten_vi"], b["trang"][0]) for b in doc_bai(GOC_REPO / f"data/nap-sach/bai/hsk{cap}")],
        "nghe": nghe_hsk(cap),
        "dap_an": None,
    }


def sach_han_ngu(q: int) -> dict:
    trang = json.loads((GOC_REPO / "data/han-ngu/trang.json").read_text(encoding="utf-8"))[str(q)]
    bai = []
    for b in doc_bai(GOC_REPO / f"data/han-ngu/bai/q{q}"):
        dau = trang["trang_dau"][b["bai"] - trang["bai_dau"]]
        bai.append((b["bai"], b["ten_zh"], b["ten_vi"], dau))
    dap_an = RA_DAP_AN / f"hn{q}.pdf"
    return {
        "ma": f"hn{q}", "bo": "hanngu", "cap": q,
        "ten": f"Giáo trình Hán ngữ {q}", "phu": f"Quyển {q} · {TRINH_DO[q]}",
        "pdf": HAN_NGU / "New folder" / f"0 Giáo trình Hán {q} - New.pdf",
        "bai": bai,
        "nghe": nghe_han_ngu(q),
        "dap_an": dap_an if dap_an.is_file() else None,
    }


def doc_bai(thu_muc: Path) -> list[dict]:
    return sorted((json.loads(f.read_text(encoding="utf-8")) for f in thu_muc.glob("bai*.json")), key=lambda b: b["bai"])


# ---------- file nghe ----------

# Thứ tự các phần trong một bài, đúng thứ tự in trong sách.
THU_TU_PHAN = {"Từ mới": 0, "Bài khoá": 1, "Bài khoá 1": 1, "Bài khoá 2": 2, "Bài khoá 3": 3, "Luyện tập": 9}


def nghe_hsk(cap: int) -> list[tuple]:
    """HSK 1: `1-3.mp3`, HSK 2: `Bài 1/Bài 1-3.mp3` — số in cạnh biểu tượng tai nghe trong sách. HSK 3 không có file."""
    goc = NGUON / f"hsk{cap}"
    if not goc.is_dir():
        return []
    ra = []
    for f in goc.rglob("*.mp3"):
        m = re.search(r"(\d+)-(\d+)\.mp3$", f.name)
        if m:
            b, k = int(m[1]), int(m[2])
            ra.append(((b, k), b, f"Bài nghe {b}-{k}", f))
    return sorted(ra)


def nghe_han_ngu(q: int) -> list[tuple]:
    """Quyển 1, 5, 6: mỗi bài một file `Quyển 1 Bài 3.mp3`. Quyển 2–4: tách phần `B16 - Từ mới.mp3`."""
    goc = next((d for d in HAN_NGU.glob("File nghe*") if re.search(rf"\D{q}$", d.name)), None)
    if goc is None:
        return []
    ra = []
    for f in goc.glob("*.mp3"):
        if m := re.match(r"Quyển \d+ Bài (\d+)\.mp3$", f.name):
            b = int(m[1])
            ra.append(((b, 0), b, "Ghi âm cả bài", f))
        elif m := re.match(r"B(\d+)\s*-\s*(.+?)\.mp3$", f.name):
            b, phan = int(m[1]), m[2].strip().replace("Bài khóa", "Bài khoá")
            if phan not in THU_TU_PHAN:
                raise SystemExit(f"Không biết phần '{phan}' trong {f}")
            ra.append(((b, THU_TU_PHAN[phan]), b, phan, f))
        else:
            raise SystemExit(f"Tên file nghe lạ: {f}")
    # Bài có cả "Bài khoá" lẫn "Bài khoá 2" thì file thứ nhất là bài khoá 1 — gọi đúng tên cho người nghe khỏi đoán.
    co_hai = {b for _, b, ten, _ in ra if ten == "Bài khoá 2"}
    return sorted((k, b, "Bài khoá 1" if ten == "Bài khoá" and b in co_hai else ten, f) for k, b, ten, f in ra)


BITRATE = {1: [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
           2: [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160]}
TAN_SO = {3: [44100, 48000, 32000], 2: [22050, 24000, 16000], 0: [11025, 12000, 8000]}


def do_dai_mp3(f: Path) -> int:
    """Số giây của file MP3 (layer III), đếm từng frame nên đúng cả với file bitrate thay đổi."""
    return round(quet_mp3(f)[0])


def quet_mp3(f: Path) -> tuple[float, bool]:
    """(số giây, có cần thêm header Xing không). Cần khi bitrate thay đổi mà frame đầu không có Xing/Info/VBRI:
    trình duyệt khi đó đoán thời lượng theo bitrate trung bình, sai tới 15% và không tua quá được mốc đoán sai."""
    d = f.read_bytes()
    i = 0
    if d[:3] == b"ID3":
        i = 10 + ((d[6] & 0x7F) << 21 | (d[7] & 0x7F) << 14 | (d[8] & 0x7F) << 7 | (d[9] & 0x7F))
    giay = 0.0
    n = len(d)
    cac_bitrate = set()
    co_xing = None
    while i + 4 <= n:
        if d[i] != 0xFF or (d[i + 1] & 0xE0) != 0xE0:
            i += 1
            continue
        ban = (d[i + 1] >> 3) & 3          # 3 = MPEG1, 2 = MPEG2, 0 = MPEG2.5
        lop = (d[i + 1] >> 1) & 3          # 1 = layer III
        br_i = d[i + 2] >> 4
        sr_i = (d[i + 2] >> 2) & 3
        if ban == 1 or lop != 1 or br_i in (0, 15) or sr_i == 3:
            i += 1
            continue
        br = BITRATE[1 if ban == 3 else 2][br_i] * 1000
        sr = TAN_SO[ban][sr_i]
        dem = (d[i + 2] >> 1) & 1
        dai = (144 if ban == 3 else 72) * br // sr + dem
        giay += (1152 if ban == 3 else 576) / sr
        if co_xing is None:
            co_xing = any(m in d[i:i + 200] for m in (b"Xing", b"Info", b"VBRI"))
        cac_bitrate.add(br)
        i += dai
    return giay, len(cac_bitrate) > 1 and not co_xing


FFMPEG = None


def chep_nghe(nguon: Path, dich: Path) -> None:
    """Chép file nghe; file thiếu header Xing thì cho ffmpeg đóng gói lại (chép nguyên luồng âm thanh, không nén
    lại nên không giảm chất lượng) để có header. Đã chép rồi, mới hơn nguồn và không thiếu header thì bỏ qua."""
    global FFMPEG
    if dich.is_file() and dich.stat().st_mtime >= nguon.stat().st_mtime and not quet_mp3(dich)[1]:
        return
    if not quet_mp3(nguon)[1]:
        shutil.copyfile(nguon, dich)
        return
    if FFMPEG is None:
        import imageio_ffmpeg  # ffmpeg đóng sẵn trong gói pip, máy không cần cài riêng
        FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()
    tam = dich.with_suffix(".tam.mp3")
    subprocess.run([FFMPEG, "-v", "error", "-y", "-i", str(nguon), "-map", "0:a", "-c:a", "copy", "-write_xing", "1", str(tam)], check=True)
    if quet_mp3(tam)[1]:
        raise SystemExit(f"ffmpeg vẫn không ghi được header Xing cho {nguon}")
    tam.replace(dich)


# ---------- ảnh trang ----------

def render(trang: pymupdf.Page, rong: int, dich: Path) -> None:
    zoom = rong / trang.rect.width
    pix = trang.get_pixmap(matrix=pymupdf.Matrix(zoom, zoom))
    dich.write_bytes(pix.pil_tobytes(format="WEBP", quality=CHAT_LUONG, method=4))


def dung_trang(pdf: Path, thu_muc: Path, ve_lai: bool, ten: str, co_bia: bool) -> list[int]:
    """Render PDF ra NNN.webp (bỏ qua trang đã có), trả về chiều cao từng trang theo đúng ảnh đã ghi."""
    thu_muc.mkdir(parents=True, exist_ok=True)
    doc = pymupdf.open(pdf)
    if co_bia and (ve_lai or not (thu_muc / "bia.webp").is_file()):
        render(doc[0], RONG_BIA, thu_muc / "bia.webp")
    cao = []
    for i, trang in enumerate(doc, start=1):
        dich = thu_muc / f"{i:03d}.webp"
        if ve_lai or not dich.is_file():
            render(trang, RONG_TRANG, dich)
        with Image.open(dich) as anh:
            if anh.width != RONG_TRANG:
                raise SystemExit(f"{dich} rộng {anh.width}px, cần {RONG_TRANG}px")
            cao.append(anh.height)
        if i % 25 == 0 or i == len(doc):
            print(f"  {ten}: {i}/{len(doc)} trang", flush=True)
    doc.close()
    return cao


def bai_dap_an(pdf: Path) -> list[dict]:
    """Trang bắt đầu của từng bài trong file đáp án: dòng 'Bài N' đứng riêng (đầu mỗi bài in '第N课' rồi 'Bài N')."""
    ra = []
    doc = pymupdf.open(pdf)
    for i, trang in enumerate(doc, start=1):
        for dong in trang.get_text().splitlines():
            if m := re.fullmatch(r"Bài\s+(\d+)", dong.strip()):
                if not any(x["so"] == int(m[1]) for x in ra):
                    ra.append({"so": int(m[1]), "trang": i})
    doc.close()
    return ra


def main() -> int:
    ap = argparse.ArgumentParser(description="Dựng thư mục đọc giáo trình")
    ap.add_argument("uploads", nargs="?", default=str(GOC_REPO / "api" / "dev-uploads"))
    ap.add_argument("--chi", help="chỉ dựng các mã này, cách nhau dấu phẩy (hsk1,hn3…)")
    ap.add_argument("--ve-lai", action="store_true", help="render lại cả trang đã có")
    th = ap.parse_args()
    uploads = Path(th.uploads).resolve()
    ve_lai = th.ve_lai
    chi = set(th.chi.split(",")) if th.chi else None

    ds = [sach_hsk(c) for c in (1, 2, 3)] + [sach_han_ngu(q) for q in range(1, 7)]
    goc = uploads / "giao-trinh"
    for s in ds:
        if chi and s["ma"] not in chi:
            continue
        if not s["pdf"].is_file():
            print(f"THIẾU: {s['pdf']}")
            return 1
        thu_muc = goc / s["ma"]
        cao = dung_trang(s["pdf"], thu_muc, ve_lai, s["ma"], co_bia=True)

        nghe = []
        (thu_muc / "nghe").mkdir(exist_ok=True)
        for so, (_, b, ten, f) in enumerate(s["nghe"], start=1):
            dich = thu_muc / "nghe" / f"{so:02d}.mp3"
            chep_nghe(f, dich)
            nghe.append({"so": so, "bai": b, "ten": ten, "giay": do_dai_mp3(dich), "kb": round(dich.stat().st_size / 1024)})
        # File thừa của lần chạy trước (nguồn bớt file) thì xoá, kẻo API phục vụ file không còn trong mục lục.
        for f in (thu_muc / "nghe").glob("*.mp3"):
            if int(f.stem) > len(nghe):
                f.unlink()
        if not nghe:
            (thu_muc / "nghe").rmdir()

        dap_an = None
        if s["dap_an"]:
            cao_da = dung_trang(s["dap_an"], thu_muc / "dap-an", ve_lai, f"{s['ma']} đáp án", co_bia=False)
            dap_an = {"soTrang": len(cao_da), "cao": cao_da, "bai": bai_dap_an(s["dap_an"])}
        elif (thu_muc / "dap-an").is_dir():
            shutil.rmtree(thu_muc / "dap-an")

        # Phiên bản theo tên, cỡ, giờ sửa của mọi file: web gắn vào đường dẫn (?v=) nên ảnh/file nghe dựng lại thì
        # trình duyệt tải bản mới thay vì giữ bản cũ 30 ngày; không đổi gì thì không làm mất cache.
        dau_vet = hashlib.sha1()
        for f in sorted(thu_muc.rglob("*")):
            if f.is_file() and f.name != "sach.json":
                st = f.stat()
                dau_vet.update(f"{f.relative_to(thu_muc).as_posix()}|{st.st_size}|{int(st.st_mtime)};".encode())
        mo_ta = {
            "ma": s["ma"], "bo": s["bo"], "cap": s["cap"], "ten": s["ten"], "phu": s["phu"], "phien": dau_vet.hexdigest()[:10],
            "soTrang": len(cao), "rong": RONG_TRANG, "cao": cao,
            "bai": [{"so": b, "ten": zh, "tenVi": vi, "trang": t} for b, zh, vi, t in s["bai"]],
            "nghe": nghe,
            "dapAn": dap_an,
        }
        (thu_muc / "sach.json").write_text(json.dumps(mo_ta, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
        mb = sum(f.stat().st_size for f in thu_muc.rglob("*") if f.is_file()) / 1048576
        print(f"{s['ma']}: {len(cao)} trang · {len(nghe)} file nghe ({sum(x['giay'] for x in nghe) // 60} phút)"
              f" · {'đáp án ' + str(dap_an['soTrang']) + ' trang, ' + str(len(dap_an['bai'])) + ' bài' if dap_an else 'không có đáp án'}"
              f" · {mb:.0f} MB", flush=True)
    print("Xong.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
