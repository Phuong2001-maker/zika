"""Dựng payload API từ bai/q<quyển>/baiNN.json → ra/q<quyển>/baiNN.json (chưa gọi API).

  python sinh.py all | python sinh.py 2 | python sinh.py 2 16

Chỉ dựng 4 phần: Lật thẻ, Luyện viết, Đọc hội thoại, Ngữ pháp. Giáo trình này không có Bài tập và
Kiểm tra (quyết định của người dùng), nên không sinh câu hỏi.
"""
from __future__ import annotations

import json
import sys
from collections import Counter

from chung_hn import BAI, THU_MUC, chon_bai, doc_bai, file_bai, file_ra, ghi_json
from chung import cau_tao, chu_han, han_viet_chu, kho_chu, la_han, nghia_ngan, tach_am_tiet
import importlib.util

from chung_hn import NAP_SACH

_spec = importlib.util.spec_from_file_location("sinh_nap_sach", NAP_SACH / "sinh.py")
_goc = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_goc)

TIEN_TO = {"chu_thich": "Chú thích: ", "ngu_am": "Ngữ âm: ", "ngu_phap": ""}

# Câu ví dụ lật thẻ lấy từ bài trước thì không dài quá chừng này (số chữ Hán): thẻ là để nhớ từ, không phải đọc đoạn văn.
VI_DU_DAI_TOI_DA = 30


def vi_du_soan(q: int) -> dict:
    """Câu tự soạn cho từ mà cả bài này lẫn các bài trước của sách không có câu nào chứa nó (đã qua kiem_vi_du_hn.py)."""
    vd: dict = {}
    for f in sorted((THU_MUC / "vi-du").glob(f"q{q}*.json")):  # soạn tách nhiều file (q6-a, q6-b) cũng được
        vd.update(json.loads(f.read_text(encoding="utf-8")))
    return vd


_DA_TACH: dict[tuple[int, str], list[str]] = {}


def tach_tu(cau: str, tu_dien: set[str]) -> list[str]:
    """Cắt câu theo từ (tham lam dài nhất), bỏ dấu câu. Không giới hạn số ô như tach_o của bộ HSK.
    Nhớ kết quả theo (bộ từ điển, câu): mỗi từ thiếu ví dụ đều dò lại cả kho câu của bộ sách."""
    khoa = (id(tu_dien), cau)
    if khoa not in _DA_TACH:
        _DA_TACH[khoa] = _tach(cau, tu_dien)
    return _DA_TACH[khoa]


def _tach(cau: str, tu_dien: set[str]) -> list[str]:
    s = _goc.tron(cau)
    dai = max((len(x) for x in tu_dien), default=1)
    ra: list[str] = []
    i = 0
    while i < len(s):
        for n in range(min(dai, len(s) - i), 0, -1):
            if n == 1 or s[i:i + n] in tu_dien:
                ra.append(s[i:i + n])
                i += n
                break
    return ra


def nhan_vd(han: str) -> str:
    """Chữ tô đậm trong câu ví dụ. API đòi câu chứa đúng chuỗi này, mà 太…了 không bao giờ nằm nguyên trong câu
    (API bỏ cả câu, nên trước 08/11 mọi từ dạng cấu trúc đều không có ví dụ): tô mảnh đầu, web tô mọi chỗ có mảnh đó."""
    return next((m for m in han.split("…") if m), han) if "…" in han else han


def co_tu(cau: str, han: str, tu_dien: set[str]) -> bool:
    """Từ đứng riêng trong câu chứ không phải mảnh của từ khác: 大 không khớp 大学, 学生 không khớp 大学生.
    Cấu trúc có dấu … (又…又…) thì các mảnh phải xuất hiện đúng thứ tự."""
    if "…" in han:
        s, vt = _goc.tron(cau), 0
        for m in (x for x in han.split("…") if x):
            j = s.find(m, vt)
            if j < 0:
                return False
            vt = j + len(m)
        return True
    # Từ nhiều chữ của sách đã nằm trong tu_dien (tu_dien_chung), từ một chữ thì luôn tách được: khỏi ghép thêm han,
    # ghép là mỗi từ một bộ từ điển mới và mất bộ nhớ tách câu.
    return han in tach_tu(cau, tu_dien) or (len(han) > 1 and han not in tu_dien and han in tach_tu(cau, tu_dien | {han}))


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

    # Kho câu sách của cả bộ theo thứ tự bài, dựng một lần ở __main__: [((quyển, bài), zh, pinyin, vi)].
    KHO: list[tuple[tuple[int, int], str, str, str]] = []

    def cau_sach(self) -> list[tuple[str, str, str]]:
        """Câu bài khóa và ví dụ ngữ pháp của bài này, trừ câu sách đánh dấu sai."""
        nguon = [(c["zh"], c.get("pinyin", ""), c.get("vi", "")) for c in self.cau]
        return nguon + [(v["zh"], v.get("pinyin", ""), v.get("vi", "")) for g in self.d["ngu_phap"] for v in g.get("vi_du", [])
                        if _goc.tron(v["zh"]) not in self.cau_sai]

    @staticmethod
    def dung_duoc(zh: str, py: str, vi: str, han: str, bai_khac: bool = False) -> bool:
        # Câu ví dụ ngữ âm giữ dấu trọng âm như sách (我'吃。): đúng ở mục ngữ âm, nhưng lạ khi làm câu ví dụ của thẻ từ.
        # Câu chỉ có đúng từ đó (马 → "马" ở mục luyện phát âm) không giúp gì cho người học.
        # Đếm cả chữ số: "1840年" là ví dụ tốt cho 年. Câu lấy từ bài khác thì bỏ câu có ngoặc (怎么（为什么）没来？ là
        # ghi chú ngữ pháp, đặt lên thẻ thì rối); câu chính bài giữ như trước để không đổi ví dụ đang dùng tốt.
        return bool(vi and py and len(zh) <= 60 and "'" not in zh and not (bai_khac and "（" in zh)
                    and len(_goc.tron(zh)) > len(_goc.tron(han)))

    def vi_du_cho(self, han: str) -> list[dict]:
        # Không dùng câu soạn riêng của HSK (data/nap-sach/vi-du/): câu đó tính theo từ vựng HSK, không theo tiến độ sách này.
        # Thứ tự: câu ngắn trong chính bài → câu sách ở bài trước (người học đã gặp) → câu soạn thêm. Tối đa 2 câu như
        # lật thẻ HSK. Lật thẻ không có chỗ ghi nhãn nên bỏ câu sai.
        ra: list[dict] = []
        for zh, py, vi in sorted(self.cau_sach(), key=lambda x: len(x[0])):
            # Từ dạng cấu trúc (太…了) không bao giờ nằm nguyên trong câu: xét từng mảnh theo thứ tự.
            co = co_tu(zh, han, self.tu_dien) if "…" in han else han in zh
            if co and self.dung_duoc(zh, py, vi, han) and zh not in [x["zh"] for x in ra]:
                ra.append({"zh": zh, "pinyin": py, "vi": vi, "highlight": nhan_vd(han)})
            if len(ra) == 2:
                return ra
        if ra:
            return ra
        # Bài trước: bắt buộc từ đứng riêng trong câu, vì câu bài khác không do chính từ này mà có.
        truoc = [(zh, py, vi) for k, zh, py, vi in self.KHO
                 if k < (self.cap, self.so) and len(chu_han(zh)) <= VI_DU_DAI_TOI_DA
                 and self.dung_duoc(zh, py, vi, han, bai_khac=True) and co_tu(zh, han, self.tu_dien)]
        for zh, py, vi in sorted(truoc, key=lambda x: len(x[0])):
            if zh not in [x["zh"] for x in ra]:
                ra.append({"zh": zh, "pinyin": py, "vi": vi, "highlight": nhan_vd(han)})
            if len(ra) == 2:
                return ra
        if ra:
            return ra
        return [{"zh": v["zh"], "pinyin": v["pinyin"], "vi": v["vi"], "highlight": nhan_vd(han)}
                for v in vi_du_soan(self.cap).get(han, [])][:2]

    def words(self) -> list[dict]:
        # Mỗi dòng 生词/专名 sách in là một thẻ, kể cả từ đã có ở bài trước (sách in lại thường với nghĩa khác)
        # và cùng chữ hai cách đọc trong một bài (好 hào/hǎo). Chỉ gộp khi sách in lặp đúng chữ và cách đọc.
        ra: list[dict] = []
        for w in self.d["tu_moi"] + self.d["ten_rieng"]:
            h, py = w["hanzi"], w["pinyin"]
            en = (w.get("nghia_en") or "").strip()
            cu = next((x for x in ra if x["hanzi"] == h and x["pinyin"] == py), None)
            if cu is not None:
                if w["nghia"] not in cu["meaning"]:
                    cu["meaning"] = f"{cu['meaning']}; {w['nghia']}"[:255]
                if en and en not in (cu.get("meaningEn") or ""):
                    cu["meaningEn"] = f"{cu['meaningEn']}; {en}"[:255] if cu.get("meaningEn") else en[:255]
                continue
            # Sách không ghi từ loại cho cụm và cấu trúc (没关系, 又…又…); để trống thì bộ HSK coi là danh từ, sai.
            co_loai = (w.get("loai") or "").strip()
            x = {"hanzi": h, "pinyin": py, "meaning": w["nghia"][:255],
                 "hanViet": _goc.han_viet_tu(h, py),
                 "pos": _goc.ma_loai(w.get("loai", "danh từ riêng")) if (co_loai or w in self.d["ten_rieng"]) else "other",
                 "examples": self.vi_du_cho(h)}
            if en:
                x["meaningEn"] = en[:255]
            ra.append(x)
        return ra

    def dialogues(self) -> list[dict]:
        ra = super().dialogues()
        # Tên bài khoá như sách in (我学习汉语); bộ HSK chỉ đặt "Bài khoá n". Không ghép ten_vi: tên tiếng Việt
        # của bài khoá là người chép tự dịch, sách không in.
        co_cau = [k for k in self.d["bai_khoa"][:10] if any(chu_han(c.get("zh", "")) for c in k["cau"])]
        for x, k in zip(ra, co_cau):
            ten = (k.get("ten_zh") or "").strip()
            if ten:
                x["title"] = ten[:120]
        return ra

    def gop_ngu_phap(self) -> list[dict]:
        """Như bộ HSK (gộp mục liền nhau cùng tên) nhưng mang theo phần tiếng Anh của từng mục."""
        ra: list[dict] = []
        for g in self.d["ngu_phap"]:
            if ra and ra[-1]["ten"] == g["ten"]:
                cu = ra[-1]
                noi = lambda a, b, sep: sep.join(x for x in (a, b) if x and x.strip())  # noqa: E731
                cu["giai_thich"] = noi(cu["giai_thich"], g.get("giai_thich", ""), "\n\n")
                cu["giai_thich_en"] = noi(cu.get("giai_thich_en", ""), g.get("giai_thich_en", ""), "\n\n")
                cu["luu_y"] = noi(cu.get("luu_y", ""), g.get("luu_y", ""), "\n")
                cu["luu_y_en"] = noi(cu.get("luu_y_en", ""), g.get("luu_y_en", ""), "\n")
                cu["ten_en"] = cu.get("ten_en") or g.get("ten_en", "")
                for k in ("cong_thuc", "cong_thuc_en"):
                    cu[k] = cu.get(k, []) + [x for x in g.get(k, []) if x not in cu.get(k, [])]
                cu["vi_du"] = cu.get("vi_du", []) + g.get("vi_du", [])
            else:
                ra.append({**g, "cong_thuc": list(g.get("cong_thuc", [])), "cong_thuc_en": list(g.get("cong_thuc_en", [])),
                           "vi_du": list(g.get("vi_du", []))})
        return ra

    def grammar(self) -> list[dict]:
        # Viết lại thay vì dùng bộ HSK: bộ đó tách mục quá 6 ví dụ thành "(tiếp)" và cắt tên/công thức dài,
        # còn sách là chuẩn — API nay giữ tới 40 ví dụ và tên 255, công thức 500 ký tự.
        ra = []
        for g in self.gop_ngu_phap():
            ct = [x for x in g.get("cong_thuc", []) if x.strip()]
            then_chot = "".join(c for c in g["ten"] if la_han(c))
            ex = []
            for v in g.get("vi_du", []):
                nhan = next((t for t in (then_chot, *chu_han(then_chot)) if t and t in v["zh"]), None)
                e = {"kind": v.get("kieu", ""), "formula": "", "zh": v["zh"], "highlight": nhan,
                     "pinyin": v.get("pinyin", ""), "vi": v.get("vi", "")}
                if (v.get("en") or "").strip():
                    e["en"] = v["en"].strip()
                if (v.get("kieu_en") or "").strip():
                    e["kindEn"] = v["kieu_en"].strip()
                ex.append(e)
            practice = None
            for v in g.get("vi_du", []):
                o = _goc.tach_o(v["zh"], self.tu_dien)
                # Máy chấm giữ — và … khi so ô chữ với câu đúng, còn tách ô thì bỏ: câu kiểu này làm API bỏ cả điểm.
                if (o and len(v["zh"]) <= 120 and len(o) >= 3 and v.get("vi") and _goc.tron(v["zh"]) not in self.loai
                        and not any(c in v["zh"] for c in "—…")):
                    practice = {"answer": v["zh"], "vi": v["vi"], "tiles": o}
                    break
            ten = _goc.viet_thuong(g["ten"])
            # Dòng phụ gồm cả mô tả sách in lẫn mọi công thức: bộ HSK chỉ lấy một trong hai nên mục có mô tả
            # (拼音 Initial-final combinations) mất hết bảng phiên âm. mo_ta trùng tên tiếng Anh thì đã hiện ở titleEn.
            mo_ta = (g.get("mo_ta") or "").strip()
            if mo_ta == (g.get("ten_en") or "").strip():
                mo_ta = ""
            item = {
                "title": ten[:255],
                "subtitle": " · ".join(x for x in [mo_ta, *ct] if x)[:2000],
                "explain": (g.get("giai_thich") or "").strip()[:4000] or ten[:4000],
                "note": (g.get("luu_y") or None),
                # Công thức chép "中文 — Tiếng Việt" như sách in: ô công thức chỉ dựng từ phần chữ Hán, phần tiếng Việt
                # đã hiện nguyên văn ở dòng phụ; tách cả chuỗi theo " + " thì ô lẫn chữ Việt.
                "blocks": self.khoi(ct[0].split(" — ")[0]) if ct else [],
                "examples": ex[:40],
                "practice": practice,
            }
            phu_en = g.get("mo_ta_en") or " · ".join(x for x in g.get("cong_thuc_en", []) if x.strip())
            for khoa, gt in (("titleEn", g.get("ten_en")), ("subtitleEn", phu_en),
                             ("explainEn", g.get("giai_thich_en")), ("noteEn", g.get("luu_y_en"))):
                if (gt or "").strip():
                    item[khoa] = gt.strip()
            if len(ex) > 40:
                self.bao_cao.append(f"Ngữ pháp: «{ten[:30]}» có {len(ex)} ví dụ, web chỉ giữ 40")
            ra.append(item)
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
    # Dựng mọi bài trước (kể cả bài không chọn) để có kho câu sách cho ví dụ lấy từ bài trước.
    cac_bai: dict[tuple[int, int], BaiHanNgu] = {}
    for q in BAI:
        for so in BAI[q]:
            if file_bai(q, so).exists():
                d = doc_bai(q, so)
                for k in d["bai_khoa"]:
                    for c in k["cau"]:
                        if c.get("nguoi") in ten:
                            c["nguoi_vi"] = ten[c["nguoi"]]
                cac_bai[(q, so)] = BaiHanNgu(d, tu_dien, set())
    BaiHanNgu.KHO = [(k, zh, py, vi) for k, b in sorted(cac_bai.items()) for zh, py, vi in b.cau_sach()]
    for q in sorted({x for x, _ in chon}):
        for so in BAI[q]:
            if (q, so) not in chon or not file_bai(q, so).exists():
                continue
            # Sách in lại từ của bài trước thì bài này vẫn có thẻ đó (migration 017 bỏ khoá duy nhất theo cấp).
            p = cac_bai[(q, so)].payload()
            ghi_json(file_ra(q, so), p)
            print(f"Quyển {q} · Bài {so}: {len(p['words'])} từ, {len(p['writing'])} chữ viết, "
                  f"{len(p['dialogues'])} bài khoá, {len(p['grammar'])} ngữ pháp"
                  + (f" — {len(p['bao_cao'])} ghi chú" if p["bao_cao"] else ""))
