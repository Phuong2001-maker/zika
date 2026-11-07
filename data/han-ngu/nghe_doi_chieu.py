"""Đối chiếu file ghi âm với chữ bài khóa đã chép: file nào dùng được, file nào phải tạo giọng mới.

  python nghe_doi_chieu.py            # cần nghe/ (nghe_kiem.py) và bai/ → ra/nghe_gan.json + in báo cáo

Quyết định cho từng bài khóa:
  - "file": file riêng đúng tên của bài khóa (quyển 2–4), cắt bỏ phần đọc tiêu đề ở đầu.
  - "cat":  đoạn khớp sách trong file chung của cả bài (quyển 1, 5, 6).
  - "tao":  không có đoạn nào đọc khớp → ghép giọng đọc mẫu cho đồng bộ.

Chấm theo chữ nghe được chứ không theo tên file, vì có file chép nhầm (quyển 4: B14 Bài khóa thật ra là
B13 Luyện tập) và file của bản sách khác (quyển 1, 5, 6 là băng 修订本, chữ khác sách phiên bản 3).
Hai ngưỡng khác nhau vì độ tin khác nhau: file riêng đúng tên thì chữ nghe lệch chủ yếu do máy nghe
(羽绒 → 羽羽绒), còn đoạn trong file chung có thể là bản sách khác nên phải khớp chặt.
"""
from __future__ import annotations

import hashlib
import json
import re
from difflib import SequenceMatcher

from chung_hn import BAI, SACH_DIR, THU_MUC, doc_bai, file_bai, ghi_json
from chung import chu_han

PHU_FILE_RIENG = 0.75
PHU_FILE_CHUNG = 0.90
THUA_TOI_DA = 0.25
# Câu hụt hẳn (dưới 40% chữ khớp) nhiều quá nghĩa là băng đọc khác sách, không phải máy nghe nhầm.
CAU_HUT = 0.4
CAU_HUT_TOI_DA = 0.20
# File riêng đúng tên: đã xem tay B18 khoá 2 (30%) và B22 (25%), chữ đúng sách, chỉ là máy nghe kém đoạn có nhạc.
CAU_HUT_FILE_RIENG = 0.35
DEM_GIAY = 0.25
THU_MUC_NGHE = {1: "File nghe Hán ngữ 1", 2: "File nghe Hán 2", 3: "File nghe Hán 3", 4: "File nghe Hán 4",
                5: "File nghe Hán ngữ 5", 6: "File nghe Hán ngữ 6"}


def doc_nghe(q: int) -> dict[str, dict]:
    return {f.stem: json.loads(f.read_text(encoding="utf-8")) for f in sorted((THU_MUC / "nghe" / f"q{q}").glob("*.json"))}


def cau_khoa(k: dict) -> list[str]:
    # Câu dẫn cảnh ("Bài đọc") nhiều băng không đọc, không tính khi chấm.
    return ["".join(chu_han(c.get("zh", ""))) for c in k["cau"] if c.get("nguoi") != "Bài đọc"]


def do(cau: list[str], nghe: str) -> dict:
    """Mức khớp của các câu sách với chữ nghe được, kèm vị trí đoạn khớp trong chữ nghe được."""
    sach = "".join(cau)
    rong = {"phu": 0.0, "thua": 1.0, "hut": 1.0, "dau": 0, "cuoi": 0}
    if not sach or not nghe:
        return rong
    khoi = [b for b in SequenceMatcher(None, sach, nghe, autojunk=False).get_matching_blocks() if b.size >= 2]
    if not khoi:
        return rong
    # Giữ cụm dày nhất: trong file dài, vài chữ rải rác ở xa không được tính vào đoạn.
    tot, cum = [], []
    for b in khoi:
        if cum and b.b - (cum[-1].b + cum[-1].size) > max(40, len(sach) // 2):
            if sum(x.size for x in cum) > sum(x.size for x in tot):
                tot = cum
            cum = []
        cum.append(b)
    if sum(x.size for x in cum) > sum(x.size for x in tot):
        tot = cum
    co = [False] * len(sach)
    for b in tot:
        for i in range(b.a, b.a + b.size):
            co[i] = True
    trung, hut, vt = sum(co), 0, 0
    for c in cau:
        if c and sum(co[vt:vt + len(c)]) / len(c) < CAU_HUT:
            hut += 1
        vt += len(c)
    dau, cuoi = tot[0].b, tot[-1].b + tot[-1].size
    return {"phu": round(trung / len(sach), 3), "thua": round(max(0, (cuoi - dau) - trung) / len(sach), 3),
            "hut": round(hut / max(1, len(cau)), 3), "dau": dau, "cuoi": cuoi}


def so_khoa(ten: str) -> int | None:
    """'B16 - Bài khóa 2' → 2, 'B16 - Bài khóa' → 1, không phải file bài khóa → None."""
    m = re.search(r"Bài khóa\s*(\d+)?$", ten)
    return (int(m.group(1)) if m.group(1) else 1) if m else None


def so_bai_ten(ten: str) -> int | None:
    m = re.search(r"(?:^B|Bài\s*)(\d+)", ten)
    return int(m.group(1)) if m else None


def giay(n: dict, r: dict) -> tuple[float, float] | None:
    """Đổi vị trí chữ ra giây. Máy gộp một cụm chữ Latinh thành một mốc nên phải đếm mốc theo cụm."""
    chi_so, moc = [], 0
    chu = n["chu"]
    for i, c in enumerate(chu):
        if i and c.isascii() and c.isalnum() and chu[i - 1].isascii() and chu[i - 1].isalnum():
            chi_so.append(moc - 1)
        else:
            chi_so.append(moc)
            moc += 1
    if r["cuoi"] > len(chi_so) or r["cuoi"] <= r["dau"]:
        return None
    if moc != len(n["moc"]):
        # Phần đọc tiêu đề tiếng Anh ở đầu file bị máy chia mốc khác; đoạn sau toàn chữ Hán nên đếm ngược từ cuối file.
        if any(c.isascii() for c in chu[r["dau"]:]):
            return None
        lech = len(n["moc"]) - len(chu)
        return max(0.0, n["moc"][r["dau"] + lech][0] / 1000 - DEM_GIAY), n["moc"][r["cuoi"] - 1 + lech][1] / 1000 + DEM_GIAY
    return max(0.0, n["moc"][chi_so[r["dau"]]][0] / 1000 - DEM_GIAY), n["moc"][chi_so[r["cuoi"] - 1]][1] / 1000 + DEM_GIAY


def trung_noi_dung(q: int) -> dict[str, str]:
    """File trùng từng byte với file khác trong quyển: tên → file đứng trước nó."""
    gap: dict[str, str] = {}
    ra: dict[str, str] = {}
    for f in sorted((SACH_DIR / THU_MUC_NGHE[q]).glob("*.mp3")):
        h = hashlib.md5(f.read_bytes()).hexdigest()
        if h in gap:
            ra[f.stem] = gap[h]
        else:
            gap[h] = f.stem
    return ra


def chon_file(q: int, b: int, k: dict, nghe: dict[str, dict], trung: dict[str, str]) -> dict:
    cau = cau_khoa(k)
    # File riêng đúng tên (bỏ file trùng byte với file khác, vì ít nhất một trong hai là chép nhầm tên).
    for ten, n in nghe.items():
        if so_bai_ten(ten) == b and so_khoa(ten) == k["so"]:
            r = do(cau, n["chu"])
            if r["phu"] >= PHU_FILE_RIENG and r["hut"] <= CAU_HUT_FILE_RIENG:
                g = giay(n, r)
                return {"cach": "file", "ten": ten, **r, "tu_giay": g[0] if g else None, "den_giay": g[1] if g else None,
                        **({"trung_voi": trung[ten]} if ten in trung else {})}
    # Đoạn trong file chung của đúng bài, rồi của bài khác trong quyển (băng có thể xếp bài khác sách).
    for ten, n in sorted(nghe.items(), key=lambda x: so_bai_ten(x[0]) != b):
        if so_khoa(ten) is not None or "Từ mới" in ten or "Luyện tập" in ten:
            continue
        r = do(cau, n["chu"])
        if r["phu"] >= PHU_FILE_CHUNG and r["thua"] <= THUA_TOI_DA and r["hut"] <= CAU_HUT_TOI_DA:
            g = giay(n, r)
            if g:
                return {"cach": "cat", "ten": ten, **r, "tu_giay": g[0], "den_giay": g[1]}
    return {"cach": "tao", "ly_do": "không có file đọc khớp sách"}


if __name__ == "__main__":
    ket = {}
    dem: dict[int, dict[str, int]] = {q: {"file": 0, "cat": 0, "tao": 0} for q in BAI}
    for q in BAI:
        nghe = doc_nghe(q)
        trung = trung_noi_dung(q)
        for b in BAI[q]:
            if not file_bai(q, b).exists():
                continue
            for k in doc_bai(q, b)["bai_khoa"]:
                chon = chon_file(q, b, k, nghe, trung)
                ket[f"q{q}-bai{b:02d}-khoa{k['so']}"] = chon
                dem[q][chon["cach"]] += 1
    ghi_json(THU_MUC / "ra" / "nghe_gan.json", ket)
    for q, d in dem.items():
        print(f"Quyển {q}: {d['file']} dùng file riêng · {d['cat']} cắt từ file chung · {d['tao']} tạo giọng mới")
    print(f"Tổng {len(ket)} bài khóa: " + ", ".join(f"{sum(d[c] for d in dem.values())} {c}" for c in ("file", "cat", "tao")))
    for ten, v in ket.items():
        if v["cach"] == "tao" and ten[:2] in ("q2", "q3", "q4"):
            print(f"  {ten}: tạo giọng mới")
        if v.get("trung_voi") or (v["cach"] == "file" and v["tu_giay"] is None):
            print(f"  {ten}: {v}")
