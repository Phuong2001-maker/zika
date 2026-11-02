"""Lập danh sách câu chữ cần đọc mẫu → ra/doc_edge.json (đầu vào của doc_edge.py).

  python viec_doc.py tu web  # từ, câu ví dụ, chữ luyện viết (file soạn sẵn) + câu thoại, ví dụ ngữ pháp,
                             # câu hỏi nghe (đọc từ web local)

Tên file = sha1(câu chữ) như web tính (web/src/lib/learn/speech.ts), mỗi câu đủ hai giọng nữ/nam
cho khớp lựa chọn giọng trong Cài đặt; từ lật thẻ thêm pinyin vào khoá. Câu thoại đọc bằng giọng
cố định của từng nhân vật (bảng VAI), thư mục theo tên giọng.
"""
from __future__ import annotations

import hashlib
import json
import re
import sys
import unicodedata

from chung import THU_MUC, chu_han, ghi_json, tach_am_tiet

def loai(text: str) -> str:
    """"tu" (từ/chữ đứng riêng, đọc rõ từng âm) hay "cau"."""
    return "tu" if 1 <= len(chu_han(text)) <= 4 and not re.search(r"[。！？，、；：!?,;:]", text) else "cau"


def khoa_doc(text: str, reading: str | None = None) -> str:
    py = re.sub(r"\s+", "", unicodedata.normalize("NFC", reading or "")).lower()
    return hashlib.sha1((text.strip() + ("\n" + py if py else "")).encode("utf-8")).hexdigest()


def duong(text: str, giong: str, reading: str | None = None) -> str:
    h = khoa_doc(text, reading)
    return f"ra/tts-edge/{giong}/{h[:2]}/{h}.mp3"


def tu_theo_pinyin() -> list[dict]:
    ra = []
    for cap in (1, 2, 3):
        for w in json.loads((THU_MUC / "ra" / f"tu_hsk{cap}.json").read_text(encoding="utf-8")):
            for g in ("nu", "nam"):
                ra.append({"text": w["hanzi"], "pinyin": w["pinyin"], "reading": w["pinyin"], "voice": g,
                           "kind": loai(w["hanzi"]), "out": duong(w["hanzi"], g, w["pinyin"])})
    return ra


def them(ds: dict, text: str, pinyin: str | None) -> None:
    text = text.strip()
    if not text or text in ds:
        return  # đã có (từ vựng đứng trước chữ đơn nên cách đọc của từ được giữ)
    ds[text] = {"pinyin": pinyin, "kind": loai(text)}


def tu_va_vi_du(ds: dict) -> None:
    for cap in (1, 2, 3):
        tu = json.loads((THU_MUC / "ra" / f"tu_hsk{cap}.json").read_text(encoding="utf-8"))
        vd: dict = {}
        for f in sorted((THU_MUC / "vi-du").glob(f"hsk{cap}*.json")):
            vd.update(json.loads(f.read_text(encoding="utf-8")))
        for w in tu:
            them(ds, w["hanzi"], w["pinyin"])
            for e in vd.get(w["hanzi"], []):
                them(ds, e["zh"], e["pinyin"])
        # Chữ đơn ở màn luyện viết: đọc theo âm của chữ trong từ chứa nó.
        for w in tu:
            chu, am = chu_han(w["hanzi"]), tach_am_tiet(w["pinyin"])
            # Nhi hoá: 面条儿 = miàntiáor (3 chữ, 2 âm tiết) → bỏ 儿, âm tiết trước bỏ r.
            if "儿" in chu and len(am) == len(chu) - chu.count("儿"):
                chu = [c for c in chu if c != "儿"]
                am = [a[:-1] if a.lower().endswith("r") and a.lower() != "er" else a for a in am]
            if len(chu) == len(am):
                for c, a in zip(chu, am):
                    them(ds, c, a)


# Giọng cố định cho từng nhân vật để một người nói cùng một giọng suốt các bài:
# xiaoxiao nữ chính/người lớn, xiaoyi nữ thứ hai/bé gái, yunxi nam trẻ, yunjian nam trưởng thành,
# yunyang thầy/người lớn tuổi/người có chức vụ, yunxia bé trai.
VAI = {
    **dict.fromkeys(["小丽", "王一雪", "李文", "刘小雪", "妈妈", "张阿姨", "老师", "张老师", "琳达", "Bài đọc",
                     "旁白", "A", "同事", "奶奶", "姐姐"], "xiaoxiao"),
    **dict.fromkeys(["白家月", "安妮", "小林", "小美", "小米", "安娜", "服务员", "售货员", "妹妹"], "xiaoyi"),
    **dict.fromkeys(["小刚", "王一飞", "刘明", "杨同乐", "小王", "小陈", "小张", "麦克", "同学", "同学们", "B",
                     "小明", "大卫", "哥哥", "弟弟"], "yunxi"),
    **dict.fromkeys(["陈天中", "小李", "爸爸", "王叔叔", "小李、小陈"], "yunjian"),
    **dict.fromkeys(["爷爷", "王经理", "张经理", "胡医生"], "yunyang"),
    **dict.fromkeys(["刘小明"], "yunxia"),
}
# Hai người trong một bài khoá trùng giọng thì người sau lấy giọng kế tiếp cùng giới.
# Chỉ có 2 giọng nữ: người nữ thứ ba trong một bài dùng xiaoxiao2 (Xiaoxiao hạ cao độ, doc_edge.CAO_DO).
THAY = {"xiaoxiao": ["xiaoyi", "xiaoxiao2"], "xiaoyi": ["xiaoxiao", "xiaoxiao2"], "yunxi": ["yunjian", "yunyang"],
        "yunjian": ["yunxi", "yunyang"], "yunyang": ["yunjian", "yunxi"], "yunxia": ["yunxi", "yunjian"]}
NU_LON = {"妈妈", "张阿姨", "奶奶", "老师", "张老师", "同事"}
# Nhân vật chính giữ giọng gốc khi trùng, theo thứ tự này (小丽 luôn trước 李文…).
CHINH = ["Bài đọc", "旁白", "小丽", "小刚", "王一雪", "王一飞", "刘明", "李文", "陈天中"]


def giong_rieng(sp: dict, thu_tu: int) -> str:
    ten, vai = sp["name"], sp.get("roleVi", "").lower()
    if ten in VAI:
        return VAI[ten]
    if any(x in ten for x in ("丽", "妈", "姐", "妹", "奶", "女", "娜", "雪", "阿姨")) or vai.split()[:1] in (["mẹ"], ["chị"], ["bà"], ["cô"]):
        return "xiaoxiao"
    if any(x in ten for x in ("爷", "经理", "医生", "先生")) or vai.split()[:1] in (["ông"], ["bác"]):
        return "yunyang"
    if any(x in ten for x in ("爸", "叔")) or vai.split()[:1] in (["bố"], ["chú"]):
        return "yunjian"
    if any(x in ten for x in ("哥", "弟")) or vai.split()[:1] in (["anh"],):
        return "yunxi"
    return "xiaoxiao" if thu_tu % 2 == 0 else "yunxi"


def giong_bai(ds_khoa: list[dict]) -> dict[str, str]:
    """Tên nhân vật → giọng cho mọi bài khoá của một bài: một người giữ một giọng suốt bài, hai người
    cùng nói trong một bài khoá không trùng giọng (người khác khoá thì được trùng — chỉ có 3 giọng nữ).
    Lời nói chung ("小李、小陈") lấy giọng người đầu."""
    speakers = [sp for k in ds_khoa for sp in k["speakers"]]
    ten = list(dict.fromkeys(sp["name"] for sp in speakers))
    rieng = {sp["name"]: sp for sp in speakers}
    cung: dict[str, set[str]] = {t: set() for t in ten}
    for k in ds_khoa:
        co = {sp["name"] for sp in k["speakers"] if "、" not in sp["name"]}
        for t in co:
            cung[t] |= co - {t}
    ra: dict[str, str] = {}
    for t in sorted((t for t in ten if "、" not in t), key=lambda t: CHINH.index(t) if t in CHINH else len(CHINH)):
        g = giong_rieng(rieng[t], ten.index(t))
        cam = {ra[u] for u in cung[t] if u in ra}
        # Người nữ lớn tuổi trùng giọng thì sang Xiaoxiao hạ cao độ (già dặn), để Xiaoyi cho nhân vật trẻ.
        thu_tu = ["xiaoxiao2", "xiaoyi"] if g == "xiaoxiao" and t in NU_LON else THAY[g]
        ra[t] = next((x for x in [g, *thu_tu] if x not in cam), g)
    for t in ten:
        if "、" in t:
            ra[t] = ra.get(t.split("、")[0]) or giong_rieng(rieng[t], ten.index(t))
    return ra


def tu_web(ds: dict, thoai: list) -> None:
    from nap import Phien
    s = Phien()
    s.dang_nhap()
    for cap in (1, 2, 3):
        for b in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]:
            for p in s.goi("GET", f"/learn/lessons/{b['id']}/grammar")["points"]:
                for e in p["examples"]:
                    them(ds, e["zh"], e.get("pinyin"))
            for phan in ("bt", "kt"):
                for q in s.goi("GET", f"/admin/lessons/{b['id']}/questions?part={phan}")["questions"]:
                    t = (q.get("payload") or {}).get("audioText")
                    if t:
                        them(ds, t, None)
            ds_khoa = s.goi("GET", f"/learn/lessons/{b['id']}/dialogue")["dialogues"]
            theo_ten = giong_bai(ds_khoa)
            for k in ds_khoa:
                giong = {sp["id"]: theo_ten[sp["name"]] for sp in k["speakers"]}
                thoai.append({"cap": cap, "bai": b["no"], "lessonId": b["id"], "khoa": k["no"], "coAudio": bool(k["audioUrl"]),
                              "giong": {sp["name"]: giong[sp["id"]] for sp in k["speakers"]},
                              "cau": [{"zh": l["zh"], "pinyin": l["pinyin"], "voice": giong[l["speakerId"]]} for l in k["lines"]]})


def gop(viec: list[dict]) -> None:
    """Một danh sách chung ra/doc_edge.json cho doc_edge.py (bỏ trùng theo file ra)."""
    tat, gap = [], set()
    for v in viec:
        if v["out"] not in gap:
            gap.add(v["out"])
            tat.append(v)
    ghi_json(THU_MUC / "ra" / "doc_edge.json", tat)
    dem: dict[str, int] = {}
    for v in tat:
        dem[v["voice"]] = dem.get(v["voice"], 0) + 1
    print(f"{len(tat)} file → ra/doc_edge.json · theo giọng: {dem}")


if __name__ == "__main__":
    if not ("tu" in sys.argv and "web" in sys.argv):
        sys.exit("Chạy: python viec_doc.py tu web")
    ds: dict = {}
    thoai: list = []
    tu_va_vi_du(ds)
    tu_web(ds, thoai)
    ghi_json(THU_MUC / "ra" / "hoi_thoai_giong.json", thoai)
    viec = tu_theo_pinyin()
    # Câu thoại: một giọng đúng nhân vật (thư mục theo tên giọng, nên câu trùng với ví dụ không đè nhau).
    viec += [{"text": c["zh"], "pinyin": c["pinyin"], "voice": c["voice"], "kind": loai(c["zh"]),
              "out": duong(c["zh"], c["voice"])} for k in thoai for c in k["cau"]]
    viec += [{"text": t, **v, "voice": g, "out": duong(t, g)} for t, v in ds.items() for g in ("nu", "nam")]
    gop(viec)
