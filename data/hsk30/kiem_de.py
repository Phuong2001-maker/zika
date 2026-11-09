"""Kiểm tự động file đề Thi thử HSK 3.0 (đặc tả ở README.md cùng thư mục).

  python data/hsk30/kiem_de.py data/hsk30/de/*.json

Luật giống QuestionPayload.php ở server (độ dài, số phương án, ô chữ ghép đúng câu…) cộng thêm luật của bộ đề:
đúng số câu từng phần theo cấp, đúng dạng câu từng phần, mã hình có trong kho, có giải thích, ba đề cùng cấp
không trùng câu, đáp án đúng không hay là phương án dài nhất. In lỗi theo "file · câu n", thoát mã 1 nếu còn lỗi.
"""
from __future__ import annotations

import json
import re
import sys
import unicodedata
from collections import defaultdict
from pathlib import Path

GOC = Path(__file__).resolve().parents[2]

CAU_TRUC = {  # cấp: (giây, nghe, đọc, viết, nói)
    1: (1920, 20, 20, 0, 0),
    2: (3120, 25, 25, 10, 0),
    3: (4380, 20, 20, 15, 15),
    4: (4500, 25, 25, 15, 5),
    5: (6000, 25, 27, 15, 5),
    6: (6900, 30, 32, 15, 5),
}
PHAN = ["listening", "reading", "writing", "speaking"]
DANG_THEO_PHAN = {
    "listening": {"listen", "pic"},
    "reading": {"pic", "blank", "pick", "tf", "match"},
    "writing": {"order", "toZh", "blank"},
    "speaking": {"speak"},
}
CHOICE = {"listen", "pic", "blank", "pick", "toZh"}
NHIEM_VU = {"repeat", "read", "answer", "topic", "picture"}
HAN = re.compile(r"[㐀-鿿]")


def kho_hinh() -> set[str]:
    ts = (GOC / "web/src/lib/learn/thi-hsk/hinh.ts").read_text(encoding="utf-8")
    return set(re.findall(r"\['([a-z0-9-]+)', '", ts))


def norm_zh(s: str) -> str:
    s = unicodedata.normalize("NFC", s)
    return re.sub(r"[\s，。！？、；：“”‘’（）《》,.!?;:'\"()\-]", "", s)


def kiem_cau(q: dict, hinh: set[str]) -> list[str]:
    loi: list[str] = []
    t = q.get("type")
    p = q.get("payload")
    if not isinstance(p, dict):
        return ["thiếu payload"]
    s = lambda k: str(p.get(k) or "").strip()  # noqa: E731

    def phuong_an(can_han: bool) -> None:
        opts = p.get("options")
        if not isinstance(opts, list) or not 2 <= len(opts) <= 4:
            loi.append("cần 2–4 phương án")
            return
        opts = [str(o).strip() for o in opts]
        if any(o == "" for o in opts):
            loi.append("có phương án trống")
        if any(len(o) > 200 for o in opts):
            loi.append("phương án dài quá 200 ký tự")
        if len(set(opts)) != len(opts):
            loi.append("phương án trùng nhau")
        a = p.get("answer")
        if not isinstance(a, int) or isinstance(a, bool) or not 0 <= a < len(opts):
            loi.append("answer phải là chỉ số phương án đúng")
        if can_han and not all(HAN.search(o) for o in opts):
            loi.append("phương án phải là chữ Hán")

    if t == "listen":
        if not 1 <= len(s("audioText")) <= 200 or not HAN.search(s("audioText")):
            loi.append("audioText 1–200 ký tự chữ Hán")
        opts = [str(o) for o in p.get("options") or []]
        phuong_an(can_han=opts not in (["Đúng", "Sai"], ["Sai", "Đúng"]))
    elif t == "pic":
        if not s("audioText") and not s("ask"):
            loi.append("pic cần audioText hoặc ask")
        if len(s("audioText")) > 200 or len(s("ask")) > 200:
            loi.append("audioText/ask dài quá 200")
        phuong_an(can_han=False)
        for o in p.get("options") or []:
            if o not in hinh:
                loi.append(f"mã hình không có trong kho: {o}")
    elif t == "blank":
        if s("sentence").count("___") != 1:
            loi.append("sentence phải có đúng một ___")
        if len(s("sentence")) > 255:
            loi.append("sentence dài quá 255")
        phuong_an(can_han=True)
    elif t == "pick":
        if not 1 <= len(s("ask")) <= 200:
            loi.append("ask 1–200 ký tự")
        phuong_an(can_han=False)
    elif t == "toZh":
        if not 1 <= len(s("vi")) <= 200:
            loi.append("vi 1–200 ký tự")
        phuong_an(can_han=True)
    elif t == "tf":
        if not 1 <= len(s("passage")) <= 400:
            loi.append("passage 1–400 ký tự")
        if not 1 <= len(s("statement")) <= 200:
            loi.append("statement 1–200 ký tự")
        if not isinstance(p.get("answer"), bool):
            loi.append("answer của tf phải là true/false")
    elif t == "match":
        pairs = p.get("pairs")
        if not isinstance(pairs, list) or not 2 <= len(pairs) <= 6:
            loi.append("cần 2–6 cặp")
        else:
            a = [str(x[0]).strip() for x in pairs]
            b = [str(x[1]).strip() for x in pairs]
            if any(not x or len(x) > 32 for x in a) or any(not x or len(x) > 200 for x in b):
                loi.append("cặp trống hoặc quá dài")
            if len(set(a)) != len(a) or len(set(b)) != len(b):
                loi.append("hai cột không được trùng")
    elif t == "order":
        ans = s("answer")
        tiles = [str(x).strip() for x in p.get("tiles") or [] if str(x).strip()]
        if not ans or len(ans) > 60:
            loi.append("answer 1–60 ký tự")
        if not 2 <= len(tiles) <= 8 or any(len(x) > 20 for x in tiles):
            loi.append("cần 2–8 ô, mỗi ô ≤20 ký tự")
        elif norm_zh("".join(tiles)) != norm_zh(ans):
            loi.append("các ô ghép lại không ra câu đúng")
    elif t == "speak":
        task = p.get("task")
        if task not in NHIEM_VU:
            loi.append("task không hợp lệ")
        if task == "repeat" and not s("audioText"):
            loi.append("repeat cần audioText")
        if task in ("read", "topic") and not s("prompt"):
            loi.append(f"{task} cần prompt")
        if task == "picture" and not str(p.get("imageUrl") or ""):
            loi.append("picture cần imageUrl")
        if len(s("prompt")) > 300 or len(s("audioText")) > 300:
            loi.append("prompt/audioText dài quá 300")
        if not isinstance(p.get("prepSec"), int) or not 0 <= p["prepSec"] <= 300:
            loi.append("prepSec 0–300")
        if not isinstance(p.get("answerSec"), int) or not 5 <= p["answerSec"] <= 300:
            loi.append("answerSec 5–300")
        if not s("sample") or len(s("sample")) > 500 or len(s("sampleVi")) > 500:
            loi.append("cần sample (≤500), sampleVi ≤500")
    else:
        loi.append(f"dạng câu không dùng được: {t}")

    url = p.get("imageUrl")
    if url:
        m = re.fullmatch(r"/hinh-hsk/([a-z0-9-]+)\.svg", str(url))
        if not m or m.group(1) not in hinh:
            loi.append(f"imageUrl không có trong kho: {url}")
    if t != "speak":
        ex = str(q.get("explain") or "").strip()
        if not ex or len(ex) > 1000:
            loi.append("cần explain tiếng Việt (≤1000 ký tự)")
    return loi


def khoa_trung(q: dict) -> str:
    """Chữ đặc trưng của câu để bắt trùng giữa các đề cùng cấp."""
    p = q.get("payload") or {}
    for k in ("audioText", "sentence", "ask", "statement", "vi", "prompt"):
        if p.get(k):
            return f"{q.get('type')}:{norm_zh(str(p[k]))}"
    if q.get("type") == "order":
        return f"order:{norm_zh(str(p.get('answer', '')))}"
    if q.get("type") == "match":
        return "match:" + "|".join(str(x[0]) for x in p.get("pairs") or [])
    return ""


def kiem_file(f: Path, hinh: set[str]) -> tuple[list[str], int, list[tuple[str, str]]]:
    loi: list[str] = []
    try:
        de = json.loads(f.read_text(encoding="utf-8"))
    except Exception as e:  # noqa: BLE001
        return [f"{f.name}: JSON hỏng — {e}"], 0, []
    cap = de.get("level")
    if cap not in CAU_TRUC:
        return [f"{f.name}: level phải 1–6"], 0, []
    giay, *so = CAU_TRUC[cap]
    if de.get("durationSec") != giay:
        loi.append(f"{f.name}: durationSec phải là {giay}")
    if not str(de.get("title") or "").strip() or len(str(de["title"])) > 80:
        loi.append(f"{f.name}: title 1–80 ký tự")
    qs = de.get("questions") or []
    dem = defaultdict(int)
    thu_tu = []
    khoa: list[tuple[str, str]] = []
    for i, q in enumerate(qs, 1):
        sec = q.get("section")
        if sec not in PHAN:
            loi.append(f"{f.name} · câu {i}: section sai ({sec})")
            continue
        dem[sec] += 1
        thu_tu.append(PHAN.index(sec))
        if q.get("type") not in DANG_THEO_PHAN[sec]:
            loi.append(f"{f.name} · câu {i}: dạng {q.get('type')} không dùng ở phần {sec}")
        if sec == "listening" and q.get("type") == "pic" and not (q.get("payload") or {}).get("audioText"):
            loi.append(f"{f.name} · câu {i}: chọn tranh ở phần nghe cần audioText")
        for e in kiem_cau(q, hinh):
            loi.append(f"{f.name} · câu {i}: {e}")
        k = khoa_trung(q)
        if k:
            khoa.append((k, f"{f.name} · câu {i}"))
    for sec, n in zip(PHAN, so):
        if dem[sec] != n:
            loi.append(f"{f.name}: phần {sec} cần {n} câu, đang có {dem[sec]}")
    if thu_tu != sorted(thu_tu):
        loi.append(f"{f.name}: câu phải xếp Nghe → Đọc → Viết → Nói")
    dai, mong, n = do_dai_dap_an(qs)
    if n and dai / n > mong + 0.05:
        loi.append(f"{f.name}: đáp án đúng là phương án dài nhất ở {dai}/{n} câu ({dai / n:.0%}), chỉ nên"
                   f" ≤ {mong + 0.05:.0%} — người thi đoán theo độ dài được")
    return loi, cap, khoa


def do_dai_dap_an(qs: list[dict]) -> tuple[int, float, int]:
    """Số câu chọn mà đáp án đúng dài hẳn nhất, tỉ lệ nếu ngẫu nhiên (1/số phương án), số câu xét.
    Bỏ chọn tranh: phương án là mã hình, không ai nhìn thấy độ dài."""
    dai, mong, n = 0, 0.0, 0
    for q in qs:
        p = q.get("payload") or {}
        o, a = p.get("options"), p.get("answer")
        if q.get("type") not in CHOICE or q.get("type") == "pic" or not isinstance(o, list) or len(o) < 2                 or not isinstance(a, int) or not 0 <= a < len(o):
            continue
        do = [len(norm_zh(str(x))) for x in o]
        n += 1
        mong += 1 / len(o)
        if do[a] == max(do) and do.count(do[a]) == 1:
            dai += 1
    return dai, (mong / n if n else 0.0), n


def main(paths: list[str]) -> int:
    hinh = kho_hinh()
    tat_ca: list[str] = []
    theo_cap: dict[int, list[tuple[str, str]]] = defaultdict(list)
    for p in paths:
        loi, cap, khoa = kiem_file(Path(p), hinh)
        tat_ca += loi
        if cap:
            theo_cap[cap] += khoa
    for cap, ds in theo_cap.items():
        gap: dict[str, str] = {}
        for k, noi in ds:
            if k in gap:
                tat_ca.append(f"HSK {cap}: trùng câu — {gap[k]} và {noi}")
            else:
                gap[k] = noi
    for dong in tat_ca:
        print(dong)
    print(f"{len(paths)} file, {len(tat_ca)} lỗi.")
    return 1 if tat_ca else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
