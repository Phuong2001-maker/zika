"""Lập danh sách giọng đọc cần có cho giáo trình Hán ngữ → ra/doc_edge_hn.json, ra/hoi_thoai_giong.json.

  python viec_hn.py

Dùng chung thư mục mp3 và sổ kết quả với bộ HSK (data/nap-sach/ra), nên câu chữ đã có giọng thì không
sinh lại. Chỉ lập đúng giọng màn học dùng để đỡ thời gian máy: lật thẻ luôn giọng nam (speech.ts), luyện
viết và ví dụ ngữ pháp theo Cài đặt nên đủ nữ/nam, câu thoại theo giọng nhân vật.
"""
from __future__ import annotations

import json
from collections import Counter

from chung_hn import BAI, NAP_SACH, THU_MUC, file_ra, ghi_json
from viec_doc import VAI, duong, giong_bai, loai

# Dàn nhân vật của bộ Hán ngữ, chia giọng theo giới tính và tuổi như bảng VAI của HSK.
VAI.update({
    **dict.fromkeys(["玛丽", "田芳", "罗兰", "林老师", "海伦", "丹尼丝", "珍妮", "李美英", "高老师", "妻子", "田芳妈", "护士", "空姐", "秘书"], "xiaoxiao"),
    **dict.fromkeys(["麦克", "爱德华", "张东", "山本", "保罗", "夏雨", "大山", "李昌浩", "谢大力", "小张", "留学生"], "yunxi"),
    **dict.fromkeys(["田中", "关建平", "病人", "路人", "业务员"], "yunjian"),
    **dict.fromkeys(["王老师", "关经理", "王教授", "马校长", "大夫"], "yunyang"),
})


def viec() -> tuple[list[dict], list[dict]]:
    ds: dict[str, dict] = {}
    thoai: list[dict] = []

    def them(text: str, pinyin: str | None, voice: str, reading: str | None = None) -> None:
        text = text.strip()
        if not text:
            return
        out = duong(text, voice, reading)
        if out not in ds:
            v = {"text": text, "pinyin": pinyin, "voice": voice, "kind": loai(text), "out": out}
            if reading:
                v["reading"] = reading
            ds[out] = v

    for q in BAI:
        for b in BAI[q]:
            f = file_ra(q, b)
            if not f.exists():
                continue
            p = json.loads(f.read_text(encoding="utf-8"))
            for w in p["words"]:
                them(w["hanzi"], w["pinyin"], "nam", w["pinyin"])
                for e in w.get("examples", []):
                    them(e["zh"], e.get("pinyin"), "nam")
            for c in p["writing"]:
                for g in ("nu", "nam"):
                    them(c["hanzi"], c.get("pinyin"), g)
            for g in p["grammar"]:
                for e in g.get("examples", []):
                    for v in ("nu", "nam"):
                        them(e["zh"], e.get("pinyin"), v)
            theo_ten = giong_bai(p["dialogues"])
            for k in p["dialogues"]:
                ten = {s["key"]: s["name"] for s in k["speakers"]}
                cau = []
                for l in k["lines"]:
                    giong = theo_ten[ten[l["speakerKey"]]]
                    them(l["zh"], l.get("pinyin"), giong)
                    cau.append({"zh": l["zh"], "pinyin": l.get("pinyin"), "voice": giong})
                thoai.append({"quyen": q, "bai": b, "khoa": k["no"], "cau": cau})
    return list(ds.values()), thoai


if __name__ == "__main__":
    ds, thoai = viec()
    ghi_json(THU_MUC / "ra" / "doc_edge_hn.json", ds)
    ghi_json(THU_MUC / "ra" / "hoi_thoai_giong.json", thoai)
    co = sum((NAP_SACH / v["out"]).exists() for v in ds)
    print(f"{len(ds)} file cần có · đã có sẵn {co} · phải sinh {len(ds) - co} · theo giọng: {dict(Counter(v['voice'] for v in ds))}")
    ten = Counter()
    for k in thoai:
        ten.update({c["voice"] for c in k["cau"]})
    print("bài khóa theo giọng:", dict(ten))
