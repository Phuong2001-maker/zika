"""In mọi câu hỏi đã dựng (ra/hsk<cấp>/baiNN.json) thành dòng ngắn để người soát đọc nhanh.

  python xuat_cau_hoi.py 1        cả quyển · python xuat_cau_hoi.py 1 3   một bài

Mỗi dòng kèm KHOÁ — chuỗi chép nguyên vào "loai_tru" của bai/*.json nếu muốn bỏ câu đó.
"""
import json
import sys

from chung import THU_MUC, chon_bai

TEN = {"meaning": "chọn nghĩa", "pinyin": "chọn pinyin", "tone": "thanh điệu", "audio": "nghe chọn chữ",
       "listen": "nghe chọn nghĩa", "blank": "điền chỗ trống", "pick": "chọn đáp án", "toZh": "chọn câu tiếng Trung",
       "order": "xếp câu", "trans": "dịch", "tf": "đúng/sai", "match": "nối"}


def dong(q: dict) -> tuple[str, str]:
    p, t = q["payload"], q["type"]
    pa = " · ".join(f"{chr(65 + i)} {o}" for i, o in enumerate(p.get("options", [])))
    dung = chr(65 + p["answer"]) if isinstance(p.get("answer"), int) and p.get("options") else ""
    if t in ("meaning", "pinyin", "tone"):
        return f"{p['word']} {p.get('ask', '')} | {pa} | đúng {dung}", p["word"]
    if t in ("audio", "listen"):
        return f"🔊 {p['audioText']} | {pa} | đúng {dung}", p["audioText"]
    if t == "blank":
        return f"{p['sentence']} ({p.get('vi', '')}) | {pa} | đúng {dung}", p["sentence"]
    if t == "pick":
        return f"{p['ask']} | {pa} | đúng {dung}", p["ask"]
    if t == "toZh":
        return f"{p['vi']} | {pa} | đúng {dung}", p["vi"]
    if t == "order":
        return f"{' / '.join(p['tiles'])} → {p['answer']} ({p.get('vi', '')})", p["answer"]
    if t == "tf":
        return f"[{p['passage'][:80]}…] {p['statement']} → {'ĐÚNG' if p['answer'] else 'SAI'}", p["statement"]
    if t == "match":
        return " · ".join(f"{a}={b}" for a, b in p["pairs"]), ""
    return json.dumps(p, ensure_ascii=False)[:200], ""


for c, b in chon_bai(sys.argv[1:]):
    f = THU_MUC / "ra" / f"hsk{c}" / f"bai{b:02d}.json"
    if not f.exists():
        continue
    p = json.loads(f.read_text(encoding="utf-8"))
    print(f"\n===== HSK {c} · Bài {b}")
    for phan in ("bt", "kt"):
        for i, q in enumerate(p[phan], 1):
            noi, khoa = dong(q)
            print(f"[{phan.upper()} {i}] {TEN.get(q['type'], q['type'])}: {noi}" + (f"   KHOÁ={khoa}" if khoa else ""))
