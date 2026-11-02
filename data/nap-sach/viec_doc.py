"""Lập danh sách câu chữ cần đọc mẫu → ra/doc_mau.json (đầu vào của doc_mau.py).

  python viec_doc.py tu      # từ, câu ví dụ, chữ luyện viết (lấy từ file soạn sẵn, không cần web)
  python viec_doc.py web     # câu thoại (giọng theo nhân vật), ví dụ ngữ pháp, câu hỏi nghe (đọc từ web)

Tên file = sha1(câu chữ) như web tính (web/src/lib/learn/speech.ts), mỗi câu đủ hai giọng nữ/nam
cho khớp lựa chọn giọng trong Cài đặt. Từ và chữ đơn đọc chậm hơn câu một chút cho nghe rõ.
"""
from __future__ import annotations

import hashlib
import json
import sys

from chung import THU_MUC, chu_han, ghi_json, tach_am_tiet

TOC_TU = 0.58   # từ/chữ 1–2 chữ: ~0,5 giây mỗi âm tiết, như giáo viên đọc từ mới (0,9 nghe quá nhanh)
TOC_TU_DAI = 0.52  # từ 3–4 chữ: đọc chậm riêng; số đo thực tế xem HUONG-DAN-GIONG-DOC.md
TOC_CAU = 1.0


def toc_tu(text: str) -> float:
    return TOC_TU if len(chu_han(text)) <= 2 else TOC_TU_DAI


def duong(text: str, giong: str) -> str:
    h = hashlib.sha1(text.strip().encode("utf-8")).hexdigest()
    return f"ra/tts/{giong}/{h[:2]}/{h}.mp3"


def them(ds: dict, text: str, pinyin: str | None, toc: float) -> None:
    text = text.strip()
    if not text or text in ds:
        return  # đã có (từ vựng đứng trước chữ đơn nên cách đọc của từ được giữ)
    ds[text] = {"pinyin": pinyin, "speed": toc}


def tu_va_vi_du(ds: dict) -> None:
    for cap in (1, 2, 3):
        tu = json.loads((THU_MUC / "ra" / f"tu_hsk{cap}.json").read_text(encoding="utf-8"))
        vd: dict = {}
        for f in sorted((THU_MUC / "vi-du").glob(f"hsk{cap}*.json")):
            vd.update(json.loads(f.read_text(encoding="utf-8")))
        for w in tu:
            them(ds, w["hanzi"], w["pinyin"], toc_tu(w["hanzi"]))
            for e in vd.get(w["hanzi"], []):
                them(ds, e["zh"], e["pinyin"], TOC_CAU)
        # Chữ đơn ở màn luyện viết: đọc theo âm của chữ trong từ chứa nó.
        for w in tu:
            chu, am = chu_han(w["hanzi"]), tach_am_tiet(w["pinyin"])
            # Nhi hoá: 面条儿 = miàntiáor (3 chữ, 2 âm tiết) → bỏ 儿, âm tiết trước bỏ r.
            if "儿" in chu and len(am) == len(chu) - chu.count("儿"):
                chu = [c for c in chu if c != "儿"]
                am = [a[:-1] if a.lower().endswith("r") and a.lower() != "er" else a for a in am]
            if len(chu) == len(am):
                for c, a in zip(chu, am):
                    them(ds, c, a, TOC_TU)


# Giọng cố định cho từng nhân vật để một người nói cùng một giọng suốt các bài.
NU = {"小丽", "王一雪", "李文", "白家月", "安妮", "妈妈", "小美", "小米", "刘小雪", "安娜", "琳达", "张阿姨",
      "售货员", "服务员", "老师", "张老师", "小林", "Bài đọc", "旁白", "A", "同事", "奶奶", "姐姐", "妹妹"}
NAM = {"小刚", "刘明", "陈天中", "王一飞", "小明", "杨同乐", "爸爸", "爷爷", "麦克", "胡医生", "张经理",
       "王经理", "刘小明", "小李", "小王", "小陈", "同学们", "同学", "B", "大卫", "哥哥", "弟弟"}
# Nhân vật chính giữ giọng gốc; người phụ cùng giới trong một bài khoá mới đổi sang giọng thứ hai.
CHINH = {"小丽", "小刚", "王一雪", "王一飞", "刘明", "李文", "陈天中", "Bài đọc", "旁白"}


def gioi(sp: dict, thu_tu: int) -> str:
    ten, vai = sp["name"], sp.get("roleVi", "").lower()
    if ten in NU:
        return "nu"
    if ten in NAM:
        return "nam"
    if any(x in ten for x in ("丽", "妈", "姐", "妹", "奶", "女", "娜", "雪", "阿姨")) or vai.split()[:1] in (["mẹ"], ["chị"], ["bà"], ["cô"]):
        return "nu"
    if any(x in ten for x in ("爸", "哥", "弟", "爷", "先生", "叔")) or vai.split()[:1] in (["bố"], ["anh"], ["ông"], ["chú"]):
        return "nam"
    return "nu" if thu_tu % 2 == 0 else "nam"


def giong_khoa(speakers: list[dict]) -> dict[int, str]:
    goc = {sp["id"]: gioi(sp, n) for n, sp in enumerate(speakers)}
    ra: dict[int, str] = {}
    # Nhân vật chính chọn trước để luôn giữ giọng gốc.
    for sp in sorted(speakers, key=lambda x: x["name"] not in CHINH):
        g = goc[sp["id"]]
        ra[sp["id"]] = g + "2" if g in ra.values() else g
    return ra


def tu_web(ds: dict, thoai: list) -> None:
    from nap import Phien
    s = Phien()
    s.dang_nhap()
    for cap in (1, 2, 3):
        for b in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]:
            for p in s.goi("GET", f"/learn/lessons/{b['id']}/grammar")["points"]:
                for e in p["examples"]:
                    them(ds, e["zh"], e.get("pinyin"), TOC_CAU)
            for phan in ("bt", "kt"):
                for q in s.goi("GET", f"/admin/lessons/{b['id']}/questions?part={phan}")["questions"]:
                    t = (q.get("payload") or {}).get("audioText")
                    if t:
                        them(ds, t, None, toc_tu(t) if len(chu_han(t)) <= 4 else TOC_CAU)
            for k in s.goi("GET", f"/learn/lessons/{b['id']}/dialogue")["dialogues"]:
                giong = giong_khoa(k["speakers"])
                thoai.append({"cap": cap, "bai": b["no"], "lessonId": b["id"], "khoa": k["no"], "coAudio": bool(k["audioUrl"]),
                              "giong": {sp["name"]: giong[sp["id"]] for sp in k["speakers"]},
                              "cau": [{"zh": l["zh"], "pinyin": l["pinyin"], "voice": giong[l["speakerId"]]} for l in k["lines"]]})


def gop(viec: list[dict]) -> None:
    """`viec_doc.py tu web`: một danh sách chung ra/doc_mau_tat.json cho doc_mau.py, và dọn file cũ.

    - Thứ tự: giọng nữ (mặc định trong Cài đặt) → câu thoại → giọng nam, để dừng giữa chừng vẫn dùng được.
    - ra/toc_rieng.json {out: tốc độ}: câu đã chỉnh tay (câu thoại dài đọc chậm lại) — giữ qua các lần lập.
    - File đã có mà tốc độ hoặc pinyin khác lần lập trước → xoá file + bỏ khỏi ra/da_tai.json, để
      doc_mau.py sinh lại và tai_doc.py tải lại đúng những file đó (file còn nguyên thì bỏ qua).
    """
    f_tat, f_rieng, f_da = (THU_MUC / "ra" / t for t in ("doc_mau_tat.json", "toc_rieng.json", "da_tai.json"))
    rieng = json.loads(f_rieng.read_text(encoding="utf-8")) if f_rieng.exists() else {}
    cu = {v["out"]: v for v in json.loads(f_tat.read_text(encoding="utf-8"))} if f_tat.exists() else {}
    da = json.loads(f_da.read_text(encoding="utf-8")) if f_da.exists() else {}
    thu_tu = {"nu": 0, "nu2": 1, "nam2": 1, "nam": 2}
    tat, gap = [], set()
    for v in sorted(viec, key=lambda v: thu_tu[v["voice"]]):
        if v["out"] in gap:
            continue
        gap.add(v["out"])
        v["speed"] = rieng.get(v["out"], v["speed"])
        tat.append(v)
    xoa = 0
    for v in tat:
        c = cu.get(v["out"])
        if c and (c.get("speed") != v["speed"] or c.get("pinyin") != v.get("pinyin")):
            (THU_MUC / v["out"]).unlink(missing_ok=True)
            da.pop(v["out"], None)
            xoa += 1
    ghi_json(f_tat, tat)
    ghi_json(f_da, da)
    con = sum(1 for v in tat if not (THU_MUC / v["out"]).exists())
    print(f"{len(tat)} file trong ra/doc_mau_tat.json · {xoa} file đổi tốc độ/pinyin đã xoá · {con} file cần sinh")


if __name__ == "__main__":
    ds: dict = {}
    thoai: list = []
    if "tu" in sys.argv:
        tu_va_vi_du(ds)
    if "web" in sys.argv:
        tu_web(ds, thoai)
        ghi_json(THU_MUC / "ra" / "hoi_thoai_giong.json", thoai)
    viec = [{"text": t, **v, "voice": g, "out": duong(t, g)} for t, v in ds.items() for g in ("nu", "nam")]
    # Câu thoại: một giọng đúng nhân vật (file riêng theo giọng, nên câu trùng với ví dụ không đè nhau).
    viec += [{"text": c["zh"], "pinyin": c["pinyin"], "speed": TOC_CAU, "voice": c["voice"], "out": duong(c["zh"], c["voice"])}
             for k in thoai for c in k["cau"]]
    if "tu" in sys.argv and "web" in sys.argv:
        gop(viec)
    else:
        ten = "doc_mau.json" if "tu" in sys.argv else "doc_mau_web.json"
        ghi_json(THU_MUC / "ra" / ten, viec)
        print(f"{len(ds)} câu chữ × 2 giọng + {sum(len(k['cau']) for k in thoai)} câu thoại = {len(viec)} file → ra/{ten}")
