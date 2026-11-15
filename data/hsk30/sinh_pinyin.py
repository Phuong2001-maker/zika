"""Sinh pinyin in trên đầu chữ cho đề Thi thử HSK 1–2 rồi nạp qua PUT /admin/pinyin (migration 030).

  python data/hsk30/sinh_pinyin.py            (mọi đề cấp 1–2 đang có trong DB)
  python data/hsk30/sinh_pinyin.py --xem 46   (in thử pinyin của các chuỗi trong đề có id 46, không nạp)

Lấy đúng các chuỗi màn thi hiện (giống PinyinDe::chuoi ở API), tách từ bằng jieba, đọc bằng pypinyin có biến điệu
一/不 tính trên cả cụm chữ liền nhau, ghép pinyin theo từ ("wǒmen") như đề giấy. Chữ 儿 cuối từ đọc nhập vào âm
trước (这儿 → zhèr). Đọc DB chỉ để lấy đề; ghi qua API quản trị. Chạy lại bất cứ lúc nào (ghi đè theo chuỗi).
"""
from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

import jieba
from pypinyin import Style, lazy_pinyin

GOC = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(GOC / "data" / "nap-sach"))
from nap import Phien  # type: ignore  # noqa: E402

jieba.setLogLevel(60)
PHP = "D:/zika-devtools/php/php.exe"
HAN = re.compile(r"[\u3400-\u9fff]+")


def truy_van(sql: str) -> list[dict]:
    code = (
        "$c=require 'app/config.php';$d=$c['db'];"
        "$p=new PDO(\"mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4\",$d['user'],$d['pass']);"
        "echo json_encode($p->query(stream_get_contents(STDIN))->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE);"
    )
    kq = subprocess.run([PHP, "-r", code], cwd=GOC / "api", input=sql.encode("utf-8"), capture_output=True, check=True)
    return json.loads(kq.stdout.decode("utf-8"))


def chuoi(type_: str, p: dict) -> list[str]:
    """Giống PinyinDe::chuoi: các chuỗi chữ Hán màn thi hiện ra (đáp án không ảnh hưởng)."""
    if type_ in ("listen", "speak"):
        ds = [str(x) for x in p.get("options") or []]
    else:
        ds = [str(p[k]) for k in ("ask", "statement", "passage", "word") if isinstance(p.get(k), str)]
        if isinstance(p.get("sentence"), str):
            ds += p["sentence"].split("___")
        for k in ("options", "tiles"):
            ds += [str(x) for x in p.get(k) or []]
        ds += [str(x[0]) for x in p.get("pairs") or []]
    return [s for s in ds if HAN.search(s)]


TU_KHO: dict[str, str] = {}


def nap_tu_kho() -> None:
    """Pinyin chuẩn của từ trong kho từ vựng web (có thanh nhẹ: 衣服 yīfu, 东西 dōngxi — pypinyin hay đọc sai chỗ này)."""
    for w in truy_van("SELECT hanzi, pinyin FROM words WHERE pinyin <> ''"):
        h, py = w["hanzi"], re.sub(r"\s+", "", w["pinyin"]).lower()
        if HAN.fullmatch(h) and len(h) >= 2 and h not in TU_KHO:
            TU_KHO[h] = py


def doc_cum(cum: str) -> list[tuple[str, str]]:
    """Một cụm chữ Hán liền nhau → [(từ, pinyin từ)]. Đọc cả cụm một lần để biến điệu 一/不 theo chữ đứng sau;
    từ có trong kho từ vựng thì lấy pinyin của kho."""
    am = lazy_pinyin(cum, style=Style.TONE, tone_sandhi=True)
    if len(am) != len(cum):  # phòng hờ: đọc từng chữ
        am = [lazy_pinyin(c, style=Style.TONE, tone_sandhi=True)[0] for c in cum]
    out, i = [], 0
    for tu in jieba.lcut(cum, HMM=False):
        py = am[i:i + len(tu)]
        i += len(tu)
        if tu in TU_KHO:
            out.append((tu, TU_KHO[tu]))
            continue
        if len(tu) > 1 and tu.endswith("儿") and py[-1] in ("ér", "er", "r"):
            py = py[:-1]
            py[-1] += "r"
        out.append((tu, "".join(py)))
    return out


def tach(s: str) -> list[list[str]]:
    out: list[list[str]] = []
    vt = 0
    for m in HAN.finditer(s):
        if m.start() > vt:
            out.append([s[vt:m.start()], ""])
        out += [[tu, py] for tu, py in doc_cum(m.group())]
        vt = m.end()
    if vt < len(s):
        out.append([s[vt:], ""])
    # Câu trọn (có dấu kết câu) viết hoa chữ cái đầu như đề giấy; phương án là cụm từ thì để thường.
    if re.search(r"[。？！?!]\s*$", s):
        for t in out:
            if t[1]:
                t[1] = t[1][0].upper() + t[1][1:]
                break
    return out


def main(args: list[str]) -> int:
    xem = args[:1] == ["--xem"]
    dk = f"m.id = {int(args[1])}" if xem else "m.level_id <= 2"
    nap_tu_kho()
    rows = truy_van(
        f"SELECT q.type, q.payload FROM questions q JOIN mock_tests m ON m.id = q.mock_test_id WHERE {dk}"
    )
    ds: dict[str, list[list[str]]] = {}
    for r in rows:
        for s in chuoi(r["type"], json.loads(r["payload"] or "{}")):
            if s not in ds:
                ds[s] = tach(s)
    if xem:
        for s, tok in list(ds.items())[:40]:
            print(s, "→", " ".join(py or tu for tu, py in tok))
        return 0
    phien = Phien()
    phien.dang_nhap()
    items = [{"text": s, "tokens": tok} for s, tok in ds.items()]
    luu, bo = 0, []
    for i in range(0, len(items), 500):
        kq = phien.goi("PUT", "/admin/pinyin", {"items": items[i:i + 500]}, timeout=300)
        luu += kq.get("saved", 0)
        bo += kq.get("skipped", [])
    print(f"{len(rows)} câu đề cấp 1–2 · {len(items)} chuỗi · đã lưu {luu}" + (f" · bỏ {len(bo)}: {bo[:3]}" if bo else ""))
    return 1 if bo else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
