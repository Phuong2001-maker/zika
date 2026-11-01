"""Dựng payload API từ bai/hsk<cấp>/baiNN.json → ra/hsk<cấp>/baiNN.json (chưa gọi API).

  python sinh.py all | python sinh.py 2 | python sinh.py 2 5

Mỗi phần học lấy đúng như README (bản đồ mục sách → phần học). Câu hỏi tự sinh theo nguyên tắc
"suy ra, đừng bịa": chỉ dùng câu nguyên văn trong bài, bảng từ đã soát, nhiễu lấy trong cùng bài.
Kết quả cố định (hạt giống ngẫu nhiên theo cấp/bài) nên chạy lại ra đúng như cũ.
"""
from __future__ import annotations

import json
import random
import re
import sys

from chung import (THU_MUC, cau_tao, chon_bai, chu_han, file_bai, ghi_json, han_viet_chu, han_viet_tu, kho_chu,
                   la_han, nghia_ngan, so_bai, tach_am_tiet)

MAU = ["#DCEBFF", "#CFF5E1", "#FFE0DC", "#FFF0B3", "#E8E0FF", "#FFE9C7"]
DAU = set("，。！？、；：“”‘’（）《》,.!?;:'\"()-—… \t\n")
# Từ hư hay gặp không nằm trong bảng từ của bài — chỉ để cắt ô khi xếp câu cho tự nhiên.
TU_HU = ("我们 你们 他们 她们 它们 咱们 什么 怎么 怎么样 这儿 那儿 哪儿 这里 那里 哪里 这个 那个 哪个 这些 那些 "
         "一个 一下 一点儿 一起 一些 因为 所以 但是 可是 如果 虽然 已经 现在 今天 明天 昨天 时候 没有 可以 "
         "喜欢 知道 觉得 认识 谢谢 再见 对不起 没关系 不客气 老师 学生 朋友 中国 越南 汉语 中文 北京 河内").split()
LOAI = [
    ("danh từ riêng", "n"), ("tên riêng", "n"), ("danh từ", "n"), ("động từ", "v"), ("tính từ", "adj"),
    ("hình dung từ", "adj"), ("phó từ", "adv"), ("trạng từ", "adv"), ("lượng từ", "mw"), ("số từ", "num"),
    ("đại từ", "pron"), ("giới từ", "prep"), ("liên từ", "conj"), ("trợ từ", "part"), ("cụm", "phrase"),
    ("thán từ", "other"), ("名", "n"), ("动", "v"), ("形", "adj"), ("副", "adv"), ("量", "mw"), ("数", "num"),
    ("代", "pron"), ("介", "prep"), ("连", "conj"), ("助", "part"), ("叹", "other"),
]


def ma_loai(loai: str) -> str:
    s = (loai or "").strip().lower()
    for khoa, ma in LOAI:
        if s.startswith(khoa) or s.startswith(f"({khoa}"):
            return ma
    return "other" if s else "n"


def viet_thuong(s: str) -> str:
    """Tiêu đề sách in HOA ("CÁCH DÙNG TỪ 是") → "Cách dùng từ 是" cho dễ đọc trên web; chữ thường sẵn thì giữ."""
    chu = [c for c in s if c.isalpha() and not la_han(c)]
    if not chu or sum(c.isupper() for c in chu) < 0.8 * len(chu):
        return s
    s = s.lower()
    return s[:1].upper() + s[1:]


def tron(s: str) -> str:
    return "".join(c for c in s if c not in DAU)


def tach_o(cau: str, tu_dien: set[str]) -> list[str] | None:
    """Cắt câu thành ô (theo từ, tham lam dài nhất), bỏ dấu câu; 2–8 ô, không được thì None."""
    s = tron(cau)
    o: list[str] = []
    i = 0
    while i < len(s):
        if s[i].isdigit():
            j = i
            while j < len(s) and s[j].isdigit():
                j += 1
            o.append(s[i:j])
            i = j
            continue
        for n in range(min(6, len(s) - i), 0, -1):
            if n == 1 or s[i:i + n] in tu_dien:
                # 们, 儿 luôn bám vào chữ trước (同学们, 一点儿), đứng riêng thành ô thì vô nghĩa
                if n == 1 and s[i] in "们儿" and o:
                    o[-1] += s[i]
                else:
                    o.append(s[i:i + n])
                i += n
                break
    while len(o) > 8:  # gộp hai ô ngắn nhất kề nhau
        k = min(range(len(o) - 1), key=lambda j: len(o[j]) + len(o[j + 1]))
        o[k:k + 2] = [o[k] + o[k + 1]]
    if not 2 <= len(o) <= 8 or "".join(o) != s:
        return None
    return o


def sap_lai(o: list[str], dap_an: str) -> list[str] | None:
    """Xếp các ô sách in (đã xáo) về đúng thứ tự câu đáp án — web bắt lưu ô theo thứ tự đúng."""
    dich = tron(dap_an)

    def thu(con: list[str], da: str) -> list[str] | None:
        if not con:
            return [] if da == dich else None
        for i, x in enumerate(con):
            t = tron(x)
            if dich.startswith(da + t):
                r = thu(con[:i] + con[i + 1:], da + t)
                if r is not None:
                    return [x] + r
        return None

    return thu(list(o), "")


def chon_dung(dung: str, nhieu: list[str], rng: random.Random, n: int = 4) -> tuple[list[str], int] | None:
    """Ghép đáp án đúng với tối đa n-1 nhiễu khác nhau, trộn vị trí. Không đủ 2 phương án → None."""
    pa = [dung]
    for x in nhieu:
        if x and x not in pa and len(pa) < n:
            pa.append(x)
    if len(pa) < 2:
        return None
    rng.shuffle(pa)
    return pa, pa.index(dung)


class Bai:
    def __init__(self, d: dict, tu_dien: set[str], tu_cap_truoc: set[str]):
        self.d = d
        self.cap, self.so = d["cap"], d["bai"]
        self.rng = random.Random(f"zika-{self.cap}-{self.so}")
        self.tu_dien = tu_dien
        self.tu_cap_truoc = tu_cap_truoc  # từ đã có ở bài trước cùng cấp (API bỏ qua từ trùng trong cấp)
        self.bao_cao: list[str] = []
        self.cau = [c for k in d["bai_khoa"] for c in k["cau"] if chu_han(c.get("zh", ""))]
        self.tu = [w for w in d["tu_moi"] if w.get("hanzi") and w.get("nghia")]
        self.loai = {tron(x) for x in d.get("loai_tru", [])}

    # ---------------- Lật thẻ
    def vi_du_cho(self, han: str) -> list[dict]:
        ra = []
        nguon = [(c["zh"], c.get("pinyin", ""), c.get("vi", "")) for c in self.cau]
        nguon += [(v["zh"], v.get("pinyin", ""), v.get("vi", "")) for g in self.d["ngu_phap"] for v in g.get("vi_du", [])]
        for zh, py, vi in sorted(nguon, key=lambda x: len(x[0])):
            if han in zh and vi and len(zh) <= 60 and zh not in [x["zh"] for x in ra]:
                ra.append({"zh": zh, "pinyin": py, "vi": vi, "highlight": han})
            if len(ra) == 2:
                break
        return ra

    def words(self) -> list[dict]:
        ra = []
        for w in self.d["tu_moi"] + self.d["ten_rieng"]:
            h = w["hanzi"]
            if h in self.tu_cap_truoc:
                self.bao_cao.append(f"Lật thẻ: bỏ {h} — đã có ở bài trước cùng cấp")
                continue
            cu = next((x for x in ra if x["hanzi"] == h), None)
            if cu is not None:
                # Sách in một chữ hai lần với hai nghĩa (天: trời/thời tiết · ngày): web chỉ giữ một thẻ nên gộp nghĩa
                if w["nghia"] not in cu["meaning"]:
                    cu["meaning"] = f"{cu['meaning']}; {w['nghia']}"[:255]
                continue
            ra.append({
                "hanzi": h, "pinyin": w["pinyin"], "meaning": w["nghia"],
                "hanViet": han_viet_tu(h, w["pinyin"]), "pos": ma_loai(w.get("loai", "danh từ riêng")),
                "examples": self.vi_du_cho(h),
            })
        return ra

    # ---------------- Luyện viết
    def writing(self) -> list[dict]:
        nghia_don = {w["hanzi"]: w["nghia"] for w in self.d["tu_moi"] if len(w["hanzi"]) == 1}
        ra: list[dict] = []
        for w in self.d["tu_moi"] + self.d["ten_rieng"]:
            chu = chu_han(w["hanzi"])
            am = tach_am_tiet(w["pinyin"])
            if len(am) != len(chu):
                am = [kho_chu().get(c, {}).get("pinyin", "") for c in chu]
            for c, a in zip(chu, am):
                if c in [x["hanzi"] for x in ra]:
                    continue
                # Chữ chỉ đứng trong từ (馒 của 馒头) không có nghĩa riêng: ghi nghĩa của từ chứa nó
                nghia = nghia_don.get(c) or nghia_ngan(c) or f"(trong {w['hanzi']}) {w['nghia']}"
                ra.append({
                    "hanzi": c, "pinyin": a.lower(), "hanViet": han_viet_chu(c, a).upper(),
                    "meaning": nghia[:255], "structure": cau_tao(c), "visible": True,
                })
        return ra

    # ---------------- Hội thoại
    def dialogues(self) -> list[dict]:
        tu_dai = sorted({w["hanzi"] for w in self.d["tu_moi"]}, key=len, reverse=True)
        ra = []
        for i, k in enumerate(self.d["bai_khoa"][:10], 1):
            cau = [c for c in k["cau"] if chu_han(c.get("zh", ""))][:60]
            if not cau:
                continue
            nguoi: list[str] = []
            for c in cau:
                ten = c.get("nguoi", "").strip() or "Bài đọc"
                c["_ten"] = ten
                if ten not in nguoi:
                    nguoi.append(ten)
            vi_ten = {c["_ten"]: c.get("nguoi_vi", "") for c in cau}
            speakers = [{
                "key": f"s{j}", "name": ten[:30], "avatarChar": "文" if ten == "Bài đọc" else ten[-1],
                "color": MAU[j % len(MAU)], "roleVi": (vi_ten.get(ten) or "")[:40],
            } for j, ten in enumerate(nguoi[:6])]
            key = {s["name"]: s["key"] for s in speakers}
            lines = []
            for c in cau:
                if c["_ten"][:30] not in key:
                    self.bao_cao.append(f"Hội thoại: bỏ câu của nhân vật thứ 7 trở đi ({c['zh'][:12]}…)")
                    continue
                nhan = next((t for t in tu_dai if t in c["zh"]), None)
                lines.append({"speakerKey": key[c["_ten"][:30]], "zh": c["zh"], "pinyin": c.get("pinyin", ""),
                              "vi": c.get("vi", ""), "highlight": nhan})
            canh = "\n".join(x for x in (k.get("boi_canh_zh", ""), k.get("boi_canh_vi", "")) if x).strip()
            if any(c.get("vi_tu_dich") for c in cau):
                canh = (canh + "\n" if canh else "") + "Bản dịch tiếng Việt là bản tham khảo của Zìkǎ — sách không in."
            ra.append({"no": i, "title": f"Bài khoá {i}", "scene": canh, "speakers": speakers, "lines": lines})
        return ra

    # ---------------- Ngữ pháp
    @staticmethod
    def khoi(cong_thuc: str) -> list[dict]:
        ra = []
        for j, phan in enumerate(x.strip() for x in re.split(r"\s+\+\s+|＋", cong_thuc) if x.strip()):
            hz = "".join(c for c in phan if la_han(c) or c in "……？！。，～~/")
            nhan = "".join(c for c in phan if not (la_han(c) or c in "……？！。，～~")).strip(" /")
            if hz or nhan:
                ra.append({"label": nhan[:40], "hz": hz[:40], "color": MAU[j % len(MAU)]})
        return ra if 1 < len(ra) <= 6 else []

    def gop_ngu_phap(self) -> list[dict]:
        """Gộp các điểm liền nhau cùng tên (người chép tách mỗi ô lưu ý một điểm) — 11 tab trùng tên thì khó học."""
        ra: list[dict] = []
        for g in self.d["ngu_phap"]:
            if ra and ra[-1]["ten"] == g["ten"]:
                cu = ra[-1]
                cu["giai_thich"] = "\n\n".join(x for x in (cu["giai_thich"], g.get("giai_thich", "")) if x.strip())
                cu["cong_thuc"] = cu.get("cong_thuc", []) + [x for x in g.get("cong_thuc", []) if x not in cu.get("cong_thuc", [])]
                cu["vi_du"] = cu.get("vi_du", []) + g.get("vi_du", [])
                cu["luu_y"] = "\n".join(x for x in (cu.get("luu_y", ""), g.get("luu_y", "")) if x)
            else:
                ra.append({**g, "cong_thuc": list(g.get("cong_thuc", [])), "vi_du": list(g.get("vi_du", []))})
        return ra

    def grammar(self) -> list[dict]:
        ra = []
        for g in self.gop_ngu_phap():
            ct = [x for x in g.get("cong_thuc", []) if x.strip()]
            then_chot = "".join(c for c in g["ten"] if la_han(c))
            ex = []
            for v in g.get("vi_du", []):
                nhan = next((t for t in (then_chot, *chu_han(then_chot)) if t and t in v["zh"]), None)
                ex.append({"kind": v.get("kieu", ""), "formula": "", "zh": v["zh"], "highlight": nhan,
                           "pinyin": v.get("pinyin", ""), "vi": v.get("vi", "")})
            practice = None
            for v in g.get("vi_du", []):
                o = tach_o(v["zh"], self.tu_dien)
                # câu người soát đã loại (xếp được nhiều cách) thì không lấy làm bài "Thử xếp câu"
                if o and len(v["zh"]) <= 120 and len(o) >= 3 and v.get("vi") and tron(v["zh"]) not in self.loai:
                    practice = {"answer": v["zh"], "vi": v["vi"], "tiles": o}
                    break
            ten = viet_thuong(g["ten"])
            # Nhiều khung công thức (khẳng định / phủ định / nghi vấn) thì ghi đủ ở dòng phụ, khung đầu vẽ thành khối
            phu = g.get("mo_ta") or " · ".join(ct)
            # Web giữ tối đa 6 ví dụ mỗi điểm: ví dụ thứ 7 trở đi sang điểm "(tiếp)" để không mất câu nào của sách
            for k in range(0, max(len(ex), 1), 6):
                ra.append({
                    "title": (ten if k == 0 else f"{ten} (tiếp)")[:120],
                    "subtitle": phu[:160] if k == 0 else "",
                    "explain": g["giai_thich"][:4000] if k == 0 else f"Ví dụ thêm cho “{ten}”.",
                    "note": (g.get("luu_y") or None) if k == 0 else None,
                    "blocks": self.khoi(ct[0]) if ct and k == 0 else [],
                    "examples": ex[k:k + 6], "practice": practice if k == 0 else None,
                })
        return ra

    # ---------------- Câu hỏi
    def doan_cho(self, cau_xet: str, k: dict) -> str:
        """Đoạn hội thoại ≤ 400 ký tự chứa nhiều chữ của câu cần xét nhất."""
        dong = [f"{c.get('nguoi', '')}：{c['zh']}" if c.get("nguoi") else c["zh"] for c in k["cau"]]
        toan = "\n".join(dong)
        if len(toan) <= 400:
            return toan
        chu = set(chu_han(cau_xet))
        tot, diem = "", -1
        for a in range(len(dong)):
            for b in range(a + 1, len(dong) + 1):
                s = "\n".join(dong[a:b])
                if len(s) > 400:
                    break
                d = len(chu & set(s))
                if d > diem:
                    tot, diem = s, d
        return tot

    def cau_hoi_sach(self) -> tuple[list[dict], list[dict]]:
        bt, kt = [], []
        for k in self.d["bai_khoa"]:
            for q in k.get("cau_hoi", []):
                if q["dang"] == "dung_sai":
                    bt.append({"type": "tf", "payload": {"passage": self.doan_cho(q["cau"], k), "statement": q["cau"],
                                                          "answer": q["dap_an"]}})
                else:
                    kt.append({"type": "pick", "payload": {"ask": q["hoi"][:200], "options": q["phuong_an"], "answer": q["dap_an"]}})
        for b in self.d["luyen_tap"]:
            for i, c in enumerate(b.get("cau", [])):
                if b["dang"] == "chon_tu_dien":
                    pa = chon_dung(c["dap_an"], b["tu"][i:] + b["tu"][:i], self.rng)
                    if pa:
                        bt.append({"type": "blank", "payload": {"sentence": c["cau"], "vi": c.get("vi", ""),
                                                                "showMeaning": bool(c.get("vi")), "options": pa[0], "answer": pa[1]}})
                elif b["dang"] == "sap_xep":
                    o = sap_lai(c["o"], c["dap_an"])
                    if o and len(c["dap_an"]) <= 60:
                        bt.append({"type": "order", "payload": {"vi": c.get("vi", ""), "answer": c["dap_an"], "tiles": o}})
                    else:
                        self.bao_cao.append(f"Bài tập: bỏ câu xếp “{c['dap_an'][:15]}…” dài quá 60 ký tự")
                elif b["dang"] == "dung_sai":
                    doan = b.get("doan", "")
                    if doan and len(doan) <= 400:
                        bt.append({"type": "tf", "payload": {"passage": doan, "statement": c["cau"], "answer": c["dap_an"]}})
                elif b["dang"] == "chon":
                    kt.append({"type": "pick", "payload": {"ask": c["hoi"][:200], "options": c["phuong_an"], "answer": c["dap_an"]}})
                elif b["dang"] == "dich":
                    self.bao_cao.append("Bài tập: bỏ câu dịch (chấm theo ý bắt buộc, sách không cho đủ để chấm)")
        return bt, kt

    def cau_hoi_sinh(self) -> tuple[list[dict], list[dict]]:
        rng, tu = self.rng, self.tu[:]
        rng.shuffle(tu)
        nghia = [w["nghia"] for w in self.tu]
        bt: list[dict] = []
        kt: list[dict] = []

        def nhieu(truong: str, tru: dict) -> list[str]:
            ds = [w[truong] for w in self.tu if w is not tru and w[truong] != tru[truong]]
            rng.shuffle(ds)
            return ds

        # Nối từ – nghĩa (bt 1 câu, kt 1 câu), nghĩa không trùng
        nhom: list[list[dict]] = [[]]
        for w in tu:
            if len(nhom[-1]) == 5:
                nhom.append([])
            # nghĩa lồng nhau (没关系 "không sao đâu" · 没事 "không sao đâu, không việc gì") thì nối được nhiều cách
            if not any(w["nghia"] in x["nghia"] or x["nghia"] in w["nghia"] or w["hanzi"] == x["hanzi"] for x in nhom[-1]):
                nhom[-1].append(w)
        nhom = [g for g in nhom if len(g) >= 3]
        for g, dich in zip(nhom[:2], (bt, kt)):
            dich.append({"type": "match", "payload": {"pairs": [[w["hanzi"], w["nghia"][:200]] for w in g]}})

        def trac_nghiem(dich, kieu, w, truong, them=None):
            pa = chon_dung(w[truong], nhieu(truong, w), rng)
            if pa:
                dich.append({"type": kieu, "payload": {"word": w["hanzi"], "ask": "", "options": pa[0], "answer": pa[1], **(them or {})}})

        hang = iter(tu)
        for w in [next(hang, None) for _ in range(3)]:
            if w and len(w["hanzi"]) <= 16 and len(set(nghia)) >= 2:
                trac_nghiem(bt, "meaning", w, "nghia")
        for w in [next(hang, None) for _ in range(2)]:
            if w and len(w["hanzi"]) <= 16:
                trac_nghiem(bt, "pinyin", w, "pinyin")
        for w in [next(hang, None) for _ in range(2)]:
            if w and len(w["hanzi"]) <= 16:
                pa = chon_dung(w["hanzi"], [x for x in nhieu("hanzi", w) if len(x) <= 8], rng)
                if pa:
                    bt.append({"type": "audio", "payload": {"audioText": w["hanzi"], "options": pa[0], "answer": pa[1]}})
        for w in [next(hang, None) for _ in range(2)]:
            if w and len(w["hanzi"]) <= 16:
                trac_nghiem(kt, "meaning", w, "nghia")
        don = [w for w in self.tu if len(w["hanzi"]) == 1 and re.search(r"[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ]", w["pinyin"])]
        rng.shuffle(don)
        for w in don[:2]:
            from kiem_tra import thanh  # noqa: PLC0415
            t = thanh(w["pinyin"])
            kt.append({"type": "tone", "payload": {"word": w["hanzi"], "ask": "Chữ này mang thanh mấy?",
                                                   "options": ["Thanh 1", "Thanh 2", "Thanh 3", "Thanh 4"], "answer": t - 1}})

        # Câu trong bài khoá: xếp câu, điền từ (bt); nghe chọn nghĩa, chọn câu tiếng Trung (kt)
        cau = [c for c in self.cau if c.get("vi") and 4 <= len(tron(c["zh"])) <= 40]
        rng.shuffle(cau)
        da_dung: set[str] = set()

        def lay(n, dk):
            ra = []
            for c in cau:
                if len(ra) < n and c["zh"] not in da_dung and dk(c):
                    da_dung.add(c["zh"])
                    ra.append(c)
            return ra

        # chỉ xếp câu đơn: câu có dấu phẩy/dấu chấm giữa câu cho ra ô cắt ngang câu, xếp vô nghĩa
        for c in lay(2, lambda c: not re.search(r"[，。！？；、,.!?;…—]", c["zh"].rstrip("。！？!?."))
                     and tach_o(c["zh"], self.tu_dien) is not None and len(tach_o(c["zh"], self.tu_dien)) >= 3):
            bt.append({"type": "order", "payload": {"vi": c["vi"], "answer": c["zh"], "tiles": tach_o(c["zh"], self.tu_dien)}})
        tu_dai = sorted(self.tu, key=lambda w: len(w["hanzi"]), reverse=True)
        for c in lay(2, lambda c: any(w["hanzi"] in c["zh"] for w in tu_dai)):
            w = next(w for w in tu_dai if w["hanzi"] in c["zh"])
            pa = chon_dung(w["hanzi"], [x["hanzi"] for x in self.tu if x["hanzi"] not in c["zh"]], rng)
            if pa:
                bt.append({"type": "blank", "payload": {"sentence": c["zh"].replace(w["hanzi"], "___", 1), "vi": c["vi"],
                                                        "showMeaning": True, "options": pa[0], "answer": pa[1]}})
        # Câu dịch tham khảo (sách không in, vi_tu_dich) chỉ để đọc hiểu, không làm đáp án được chấm
        vi_khac = [c["vi"] for c in self.cau if c.get("vi") and not c.get("vi_tu_dich")]
        for c in lay(3, lambda c: len(c["zh"]) <= 200 and not c.get("vi_tu_dich")):
            pa = chon_dung(c["vi"], [v for v in vi_khac if v != c["vi"]][::-1], rng)
            if pa:
                kt.append({"type": "listen", "payload": {"audioText": c["zh"], "options": pa[0], "answer": pa[1]}})
        zh_khac = [c["zh"] for c in self.cau]
        for c in lay(2, lambda c: not c.get("vi_tu_dich")):
            pa = chon_dung(c["zh"], [z for z in zh_khac if z != c["zh"]], rng)
            if pa:
                kt.append({"type": "toZh", "payload": {"vi": c["vi"][:200], "options": pa[0], "answer": pa[1]}})

        # Mẫu câu (句式): xếp câu cho phần kiểm tra; nghĩa lấy từ bài khoá nếu câu có trong đó
        nghia_cau = {tron(c["zh"]): c.get("vi", "") for c in self.cau}
        for m in self.d["mau_cau"]:
            for v in m.get("vi_du", []):
                o = tach_o(v, self.tu_dien)
                if o and len(o) >= 3 and len(v) <= 60 and sum(1 for q in kt if q["type"] == "order") < 3:
                    kt.append({"type": "order", "payload": {"vi": nghia_cau.get(tron(v), ""), "answer": v, "tiles": o}})
        return bt, kt

    def questions(self) -> tuple[list[dict], list[dict]]:
        bt1, kt1 = self.cau_hoi_sach()
        bt2, kt2 = self.cau_hoi_sinh()
        # Người soát ghi vào "loai_tru" những câu có hơn một đáp án đúng / xếp được nhiều cách.
        loai = self.loai

        def giu(q: dict) -> bool:
            p = q["payload"]
            khoa = [p.get(k, "") for k in ("sentence", "answer", "audioText", "word", "statement", "ask", "vi")]
            bo = any(isinstance(x, str) and x and tron(x) in loai for x in khoa)
            if bo:
                self.bao_cao.append(f"Bỏ câu {q['type']} theo danh sách loại trừ của người soát")
            return not bo

        return [q for q in bt1 + bt2 if giu(q)], [q for q in kt1 + kt2 if giu(q)]

    def payload(self) -> dict:
        bt, kt = self.questions()
        so_kt = max(len(kt), 1)
        return {
            "cap": self.cap, "bai": self.so,
            "lesson": {"title": self.d["ten_vi"][:120], "sampleText": self.d["ten_zh"][:60]},
            "words": self.words(),
            "writing": self.writing(),
            "dialogues": self.dialogues(),
            "grammar": self.grammar(),
            "bt": bt, "kt": kt,
            "testConfig": {"durationSec": max(300, min(1800, 60 * so_kt)), "passScore": round(so_kt * 0.6, 1),
                           "listenLimit": 2, "attemptsLimit": None, "shuffleQuestions": True, "shuffleOptions": True,
                           "autoSubmit": True, "showAnswersAfter": True},
            "bao_cao": self.bao_cao,
        }


def tu_dien_chung() -> set[str]:
    """Từ của cả 3 quyển + từ hư hay gặp, để cắt ô khi xếp câu."""
    s = set(TU_HU)
    for c in (1, 2, 3):
        for b in range(1, so_bai(c) + 1):
            f = file_bai(c, b)
            if f.exists():
                d = json.loads(f.read_text(encoding="utf-8"))
                s |= {w["hanzi"] for w in d["tu_moi"] + d["ten_rieng"] if w.get("hanzi")}
    return {w for w in s if len(w) > 1}


if __name__ == "__main__":
    tu_dien = tu_dien_chung()
    da_co: dict[int, set[str]] = {}
    chon = chon_bai(sys.argv[1:])
    for cap in sorted({c for c, _ in chon}):
        # Từ trùng trong cùng cấp: bài trước giữ, bài sau bỏ (bảng words là duy nhất theo cấp + chữ Hán).
        da_co[cap] = set()
        for so in range(1, so_bai(cap) + 1):
            f = file_bai(cap, so)
            if not f.exists():
                continue
            d = json.loads(f.read_text(encoding="utf-8"))
            if (cap, so) in chon:
                p = Bai(d, tu_dien, set(da_co[cap])).payload()
                ghi_json(THU_MUC / "ra" / f"hsk{cap}" / f"bai{so:02d}.json", p)
                print(f"HSK {cap} · Bài {so}: {len(p['words'])} từ, {len(p['writing'])} chữ viết, "
                      f"{len(p['dialogues'])} bài khoá, {len(p['grammar'])} ngữ pháp, {len(p['bt'])} bài tập, "
                      f"{len(p['kt'])} câu kiểm tra" + (f" — {len(p['bao_cao'])} ghi chú" if p["bao_cao"] else ""))
            da_co[cap] |= {w["hanzi"] for w in d["tu_moi"] + d["ten_rieng"] if w.get("hanzi")}
