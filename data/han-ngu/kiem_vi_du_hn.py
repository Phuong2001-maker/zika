"""Câu ví dụ lật thẻ của Hán ngữ: từ nào lấy câu ở đâu, từ nào còn thiếu, và kiểm câu tự soạn (vi-du/q{n}.json).

  python kiem_vi_du_hn.py            # thống kê nguồn ví dụ + kiểm câu tự soạn; thoát mã 1 nếu còn lỗi hoặc còn từ thiếu
  python kiem_vi_du_hn.py --thieu    # in từ chưa có câu nào (cần soạn), kèm nghĩa để soạn
  python kiem_vi_du_hn.py --quyen 4  # chỉ thống kê và kiểm quyển 4 (soạn từng quyển cho nhanh)
  python kiem_vi_du_hn.py --xuat     # ghi ra/can-soan/q{n}.json: từ cần soạn + chữ đã gặp tới từng bài

Luật câu tự soạn (giống kiem_vi_du.py của bộ HSK, tính theo tiến độ sách này):
mỗi từ 1–2 câu; câu chứa đúng từ đó (đứng riêng, không phải mảnh của từ khác); ngắn theo quyển; chỉ dùng chữ người
học đã gặp tới bài đó (từ mới và bài khóa của các bài ≤ bài đang soạn); pinyin khớp từng chữ; có nghĩa tiếng Việt.
Ngoại lệ ghi ngay trong câu kèm lý do: "cho_phep" (bài đầu quá ít chữ, phải dùng chữ ngoài phạm vi),
"pinyin_sach" (đọc theo sách in mà máy không biết).
"""
from __future__ import annotations

import json
import sys
from collections import Counter

from chung_hn import BAI, NAP_SACH, THU_MUC, doc_bai, file_bai, ghi_json
from chung import chu_han

import importlib.util  # noqa: E402

if str(NAP_SACH) not in sys.path:
    sys.path.append(str(NAP_SACH))
from kiem_tra import so_pinyin  # noqa: E402

# Nạp đúng han-ngu/sinh.py: nap-sach cũng có sinh.py và nằm trên sys.path.
_spec = importlib.util.spec_from_file_location("sinh_han_ngu", THU_MUC / "sinh.py")
sinh = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(sinh)

sys.stdout.reconfigure(encoding="utf-8")

DAI_TOI_DA = {1: 12, 2: 14, 3: 16, 4: 18, 5: 20, 6: 22}  # số chữ Hán của một câu tự soạn


def dung_bai() -> dict[tuple[int, int], sinh.BaiHanNgu]:
    tu_dien = sinh.tu_dien_chung()
    cac_bai = {(q, so): sinh.BaiHanNgu(doc_bai(q, so), tu_dien, set()) for q in BAI for so in BAI[q] if file_bai(q, so).exists()}
    sinh.BaiHanNgu.KHO = [(k, zh, py, vi) for k, b in sorted(cac_bai.items()) for zh, py, vi in b.cau_sach()]
    return cac_bai


def nguon_vi_du(b: sinh.BaiHanNgu, han: str) -> str:
    """'bai' (chính bài) · 'truoc' (bài trước) · 'soan' (câu tự soạn) · 'thieu'."""
    vd = b.vi_du_cho(han)
    if not vd:
        return "thieu"
    zh = vd[0]["zh"]
    if any(zh == x[0] for x in b.cau_sach()):
        return "bai"
    if any(zh == x[1] for x in sinh.BaiHanNgu.KHO):
        return "truoc"
    return "soan"


def chu_da_gap(cac_bai: dict, den: tuple[int, int]) -> set[str]:
    s: set[str] = set()
    for k, b in cac_bai.items():
        if k <= den:
            for w in b.d["tu_moi"] + b.d["ten_rieng"]:
                s.update(chu_han(w.get("hanzi", "")))
            for c in b.cau:
                s.update(chu_han(c["zh"]))
    return s


def xuat(cac_bai: dict, thieu_ds: list[dict]) -> None:
    """ra/can-soan/q{n}.json: từ cần soạn câu và chữ người học đã gặp tới từng bài (để soạn trong phạm vi đó)."""
    for q in sorted({x["quyen"] for x in thieu_ds}):
        tu = [x for x in thieu_ds if x["quyen"] == q]
        chu = {str(b): "".join(sorted(chu_da_gap(cac_bai, (q, b)))) for b in sorted({x["bai"] for x in tu})}
        ghi_json(THU_MUC / "ra" / "can-soan" / f"q{q}.json", {"tu": tu, "chu_da_gap": chu})
        print(f"Q{q}: {len(tu)} từ cần soạn → ra/can-soan/q{q}.json")


def main() -> int:
    cac_bai = dung_bai()
    chi_quyen = None
    if "--quyen" in sys.argv:
        chi_quyen = int(sys.argv[sys.argv.index("--quyen") + 1])
        cac_bai_kiem = {k: b for k, b in cac_bai.items() if k[0] == chi_quyen}
    else:
        cac_bai_kiem = cac_bai
    thieu_ds: list[dict] = []
    dem: Counter = Counter()
    thieu: list[str] = []
    dung_soan: dict[int, set[str]] = {}
    noi_dung: dict[tuple[int, str], tuple[int, int]] = {}  # (quyển, chữ) → bài đầu tiên có từ đó
    for (q, so), b in sorted(cac_bai_kiem.items()):
        for w in b.words():
            nguon = nguon_vi_du(b, w["hanzi"])
            dem[nguon] += 1
            if nguon == "thieu":
                thieu.append(f"Q{q} B{so} | {w['hanzi']} | {w['pinyin']} | {w['meaning'][:60]}")
                thieu_ds.append({"quyen": q, "bai": so, "hanzi": w["hanzi"], "pinyin": w["pinyin"], "nghia": w["meaning"]})
            if nguon == "soan":
                dung_soan.setdefault(q, set()).add(w["hanzi"])
            noi_dung.setdefault((q, w["hanzi"]), (q, so))
    print(f"Nguồn ví dụ: chính bài {dem['bai']} · bài trước {dem['truoc']} · tự soạn {dem['soan']} · còn thiếu {dem['thieu']}")
    if "--thieu" in sys.argv:
        print("\n".join(thieu))
    if "--xuat" in sys.argv:
        xuat(cac_bai, thieu_ds)

    loi = 0
    for q in sorted(BAI):
        if chi_quyen is not None and q != chi_quyen:
            continue
        vd = sinh.vi_du_soan(q)
        for han, cau in vd.items():
            if (q, han) not in noi_dung:
                print(f"[thừa] Q{q} {han}: quyển này không có từ đó")
                loi += 1
                continue
            if han not in dung_soan.get(q, set()):
                print(f"[không dùng] Q{q} {han}: đã có câu sách, câu soạn bị bỏ qua")
            duoc = chu_da_gap(cac_bai, noi_dung[(q, han)])
            if not 1 <= len(cau) <= 2:
                print(f"[số câu] Q{q} {han}: {len(cau)}")
                loi += 1
            for e in cau:
                zh, py, vi = e.get("zh", ""), e.get("pinyin", ""), e.get("vi", "")
                ghi = []
                if not sinh.co_tu(zh, han, cac_bai[noi_dung[(q, han)]].tu_dien):
                    ghi.append("không chứa từ (đứng riêng)")
                n = len(chu_han(zh))
                if n > DAI_TOI_DA[q]:
                    ghi.append(f"dài {n} chữ > {DAI_TOI_DA[q]}")
                if n <= len(chu_han(han)):
                    ghi.append("chỉ có chính từ đó")
                ngoai = sorted({c for c in chu_han(zh) if c not in duoc})
                if ngoai and not e.get("cho_phep"):
                    ghi.append("chữ chưa học: " + "".join(ngoai))
                if not vi.strip():
                    ghi.append("thiếu nghĩa")
                if not e.get("pinyin_sach"):  # cách đọc khẩu ngữ như sách in mà máy không biết (马马虎虎 hūhū)
                    ghi += so_pinyin(zh, py)
                if ghi:
                    print(f"[Q{q} {han}] {zh} · {py} → " + "; ".join(ghi))
                    loi += 1
    print(f"Lỗi câu tự soạn: {loi}")
    return 1 if loi or dem["thieu"] else 0


if __name__ == "__main__":
    sys.exit(main())
