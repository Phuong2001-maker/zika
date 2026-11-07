"""Dựng payload API từ bai/q<quyển>/baiNN.json → ra/q<quyển>/baiNN.json (chưa gọi API).

  python sinh.py all | python sinh.py 2 | python sinh.py 2 16

Chỉ dựng 4 phần: Lật thẻ, Luyện viết, Đọc hội thoại, Ngữ pháp. Giáo trình này không có Bài tập và
Kiểm tra (quyết định của người dùng), nên không sinh câu hỏi.
"""
from __future__ import annotations

import json
import sys
from collections import Counter

from chung_hn import BAI, chon_bai, doc_bai, file_bai, file_ra, ghi_json
from chung import cau_tao, chu_han, han_viet_chu, kho_chu, nghia_ngan, tach_am_tiet
import importlib.util

from chung_hn import NAP_SACH

_spec = importlib.util.spec_from_file_location("sinh_nap_sach", NAP_SACH / "sinh.py")
_goc = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_goc)

TIEN_TO = {"chu_thich": "Chú thích: ", "ngu_am": "Ngữ âm: ", "ngu_phap": ""}


def la_cau_sai(v: dict) -> bool:
    """Câu sách đánh dấu *. Mỗi bài do một agent chép nên nhãn không đồng nhất ("sai", "Không thể nói (*)"…)."""
    k = (v.get("kieu") or "").strip().lower()
    return k in ("sai", "câu sai") or "không thể nói" in k or "đánh dấu *" in k


class BaiHanNgu(_goc.Bai):
    def __init__(self, d: dict, tu_dien: set[str], tu_cap_truoc: set[str]):
        # Bộ nạp HSK dùng khoá "cap"; quyển n nằm ở cấp n.
        d = {**d, "cap": d["quyen"]}
        d["ngu_phap"] = [{**g, "ten": TIEN_TO.get(g.get("nhom", ""), "") + g["ten"],
                          "vi_du": [{**v, "kieu": "Câu sai"} if la_cau_sai(v) else v for v in g.get("vi_du", [])]}
                         for g in d["ngu_phap"]]
        super().__init__(d, tu_dien, tu_cap_truoc)
        # Câu sách đánh dấu sai chỉ được hiện ở ngữ pháp kèm nhãn; lấy làm "Thử xếp câu" là dạy người học câu sai.
        self.cau_sai = {_goc.tron(v["zh"]) for g in d["ngu_phap"] for v in g["vi_du"] if v["kieu"] == "Câu sai"}
        self.loai |= self.cau_sai

    def vi_du_cho(self, han: str) -> list[dict]:
        # Không dùng câu soạn riêng của HSK (vi-du/): câu đó tính theo từ vựng HSK, không theo tiến độ sách này.
        # Ưu tiên câu ngắn trong chính bài; lấy tối đa 2 câu như lật thẻ HSK. Lật thẻ không có chỗ ghi nhãn nên bỏ câu sai.
        nguon = [(c["zh"], c.get("pinyin", ""), c.get("vi", "")) for c in self.cau]
        nguon += [(v["zh"], v.get("pinyin", ""), v.get("vi", "")) for g in self.d["ngu_phap"] for v in g.get("vi_du", [])
                  if _goc.tron(v["zh"]) not in self.cau_sai]
        ra: list[dict] = []
        for zh, py, vi in sorted(nguon, key=lambda x: len(x[0])):
            if han in zh and vi and py and len(zh) <= 60 and zh not in [x["zh"] for x in ra]:
                ra.append({"zh": zh, "pinyin": py, "vi": vi, "highlight": han})
            if len(ra) == 2:
                break
        return ra

    def words(self) -> list[dict]:
        ra = super().words()
        # Sách in một chữ hai lần với hai cách đọc (好 hào/hǎo): web chỉ giữ một thẻ, nên ghi rõ cách đọc thứ hai
        # vào nghĩa thay vì để nghĩa đó đứng dưới pinyin của cách đọc đầu.
        goc: dict[str, list[dict]] = {}
        for w in self.d["tu_moi"] + self.d["ten_rieng"]:
            goc.setdefault(w["hanzi"], []).append(w)
        khong_loai = {w["hanzi"] for w in self.d["tu_moi"] if not (w.get("loai") or "").strip()}
        for x in ra:
            ban = goc.get(x["hanzi"], [])
            if len({w["pinyin"] for w in ban}) > 1:
                x["meaning"] = "; ".join(f"({w['pinyin']}) {w['nghia']}" for w in ban)[:255]
            # Sách không ghi từ loại cho cụm và cấu trúc (没关系, 又…又…); để trống thì bộ HSK coi là danh từ, sai.
            if x["hanzi"] in khong_loai:
                x["pos"] = "other"
        return ra

    def grammar(self) -> list[dict]:
        ra = super().grammar()
        for g in ra:
            p = g.get("practice")
            # Máy chấm giữ lại — và … khi so ô chữ với câu đúng, còn tách ô thì bỏ: câu kiểu này làm API bỏ cả điểm ngữ pháp.
            if p and any(c in p["answer"] for c in "—…"):
                g["practice"] = None
        return ra

    def writing(self) -> list[dict]:
        # Sách có mục 写汉字 thì lấy đúng danh sách đó; bài không có thì tách chữ từ Từ mới như HSK.
        if not self.d.get("viet_chu"):
            return super().writing()
        nghia_don = {w["hanzi"]: w["nghia"] for w in self.d["tu_moi"] if len(w["hanzi"]) == 1}
        chua = {}
        for w in self.d["tu_moi"] + self.d["ten_rieng"]:
            am = tach_am_tiet(w["pinyin"])
            for c, a in zip(chu_han(w["hanzi"]), am if len(am) == len(chu_han(w["hanzi"])) else [""] * 99):
                chua.setdefault(c, (w, a))
        ra: list[dict] = []
        for x in self.d["viet_chu"]:
            c = x["hanzi"]
            if c in [y["hanzi"] for y in ra]:
                continue
            a = x.get("pinyin") or (chua[c][1] if c in chua else "") or kho_chu().get(c, {}).get("pinyin", "")
            nghia = nghia_don.get(c) or nghia_ngan(c) or (f"(trong {chua[c][0]['hanzi']}) {chua[c][0]['nghia']}" if c in chua else "")
            ra.append({"hanzi": c, "pinyin": a.lower(), "hanViet": han_viet_chu(c, a).upper(),
                       "meaning": nghia[:255], "structure": cau_tao(c), "visible": True})
        return ra

    def payload(self) -> dict:
        return {
            "quyen": self.cap, "bai": self.so,
            "lesson": {"title": self.d["ten_vi"][:120], "sampleText": self.d["ten_zh"][:60]},
            "words": self.words(),
            "writing": self.writing(),
            "dialogues": self.dialogues(),
            "grammar": self.grammar(),
            "bao_cao": self.bao_cao,
        }


def ten_nhan_vat() -> dict[str, str]:
    """Tên tiếng Việt thống nhất của từng nhân vật: mỗi bài do một agent chép nên 罗兰 có lúc Roland, có lúc La Lan.
    Lấy cách ghi nhiều bài dùng nhất, viết hoa đầu câu."""
    dem: dict[str, Counter] = {}
    for q in BAI:
        for b in BAI[q]:
            f = file_bai(q, b)
            if f.exists():
                for k in json.loads(f.read_text(encoding="utf-8"))["bai_khoa"]:
                    for c in k["cau"]:
                        if c.get("nguoi") and c.get("nguoi_vi"):
                            dem.setdefault(c["nguoi"], Counter())[c["nguoi_vi"]] += 1
    return {t: (v := d.most_common(1)[0][0])[:1].upper() + v[1:] for t, d in dem.items()}


def tu_dien_chung() -> set[str]:
    s = set(_goc.TU_HU)
    for q in BAI:
        for b in BAI[q]:
            f = file_bai(q, b)
            if f.exists():
                d = json.loads(f.read_text(encoding="utf-8"))
                s |= {w["hanzi"] for w in d["tu_moi"] + d["ten_rieng"] if w.get("hanzi")}
    return {w for w in s if len(w) > 1}


if __name__ == "__main__":
    tu_dien = tu_dien_chung()
    ten = ten_nhan_vat()
    chon = chon_bai(sys.argv[1:])
    for q in sorted({x for x, _ in chon}):
        # Từ trùng trong cùng cấp: bài trước giữ, bài sau bỏ (bảng words duy nhất theo giáo trình + cấp + chữ Hán).
        da_co: set[str] = set()
        for so in BAI[q]:
            if not file_bai(q, so).exists():
                continue
            d = doc_bai(q, so)
            for k in d["bai_khoa"]:
                for c in k["cau"]:
                    if c.get("nguoi") in ten:
                        c["nguoi_vi"] = ten[c["nguoi"]]
            if (q, so) in chon:
                p = BaiHanNgu(d, tu_dien, set(da_co)).payload()
                ghi_json(file_ra(q, so), p)
                print(f"Quyển {q} · Bài {so}: {len(p['words'])} từ, {len(p['writing'])} chữ viết, "
                      f"{len(p['dialogues'])} bài khoá, {len(p['grammar'])} ngữ pháp"
                      + (f" — {len(p['bao_cao'])} ghi chú" if p["bao_cao"] else ""))
            da_co |= {w["hanzi"] for w in d["tu_moi"] + d["ten_rieng"] if w.get("hanzi")}
