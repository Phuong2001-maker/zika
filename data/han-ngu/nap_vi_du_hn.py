"""Cập nhật câu ví dụ lật thẻ của Hán ngữ theo ra/q*/bai*.json (sau sinh.py), chỉ đụng từ có ví dụ đổi.

  python nap_vi_du_hn.py --thu   # chỉ so và ghi ra/vi_du_doi.json + ra/cap-nhat-vi-du-hn.sql, không sửa DB
  python nap_vi_du_hn.py         # như trên rồi sửa từng từ qua PUT /admin/words/{id} (giữ id từ, tiến độ học viên)

Không dùng nap.py: nạp lại cả bài thì đổi id từ, mất âm thanh đã gắn, và trên server phải chạy lại cả 02-han-ngu.sql (xoá
bài là xoá luôn tiến độ học của học viên). File SQL tìm từ theo giáo trình + quyển + số bài + chữ + pinyin, vì id trên server
khác local; so chữ và pinyin theo utf8mb4_bin để hǎo và hào là hai từ.
"""
from __future__ import annotations

import json
import subprocess
import sys

from chung_hn import BAI, NAP_SACH, REPO, TEN_GIAO_TRINH, THU_MUC, file_ra, ghi_json

if str(NAP_SACH) not in sys.path:
    sys.path.append(str(NAP_SACH))

sys.stdout.reconfigure(encoding="utf-8")
PHP = "D:/zika-devtools/php/php.exe"


def vi_du_db() -> dict[tuple[int, int, str, str], dict]:
    """Ví dụ đang có trong DB local, theo (quyển, bài, chữ, pinyin)."""
    code = r"""
$c = require 'app/config.php'; $d = $c['db'];
$p = new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$ten = $argv[1];
$ra = [];
foreach ($p->query("SELECT w.id, l.level_id, l.lesson_no, w.hanzi, w.pinyin FROM words w JOIN lessons l ON l.id = w.lesson_id
                     JOIN curricula c ON c.id = l.curriculum_id WHERE c.name = " . $p->quote($ten)) as $w) {
    $w['examples'] = $p->query('SELECT zh, pinyin, vi, highlight FROM word_examples WHERE word_id = ' . (int) $w['id'] . ' ORDER BY sort_order, id')->fetchAll();
    $ra[] = $w;
}
echo json_encode($ra, JSON_UNESCAPED_UNICODE);
"""
    out = subprocess.run([PHP, "-r", code, "--", TEN_GIAO_TRINH], cwd=REPO / "api", check=True, capture_output=True,
                         encoding="utf-8").stdout
    return {(int(w["level_id"]), int(w["lesson_no"]), w["hanzi"], w["pinyin"]): w for w in json.loads(out)}


def chuan(ex: list[dict], han: str) -> list[tuple[str, str, str, str | None]]:
    """Dạng so sánh: API lưu highlight NULL khi trùng chữ của từ."""
    return [(e["zh"].strip(), (e.get("pinyin") or "").strip(), (e.get("vi") or "").strip(),
             None if (e.get("highlight") or han) == han else e["highlight"]) for e in ex]


def sql_chuoi(s: str | None) -> str:
    if s is None:
        return "NULL"
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def viet_sql(doi: list[dict]) -> str:
    ten = sql_chuoi(TEN_GIAO_TRINH)
    dong = [f"-- Zìkǎ · câu ví dụ lật thẻ Hán ngữ: {len(doi)} từ. Chỉ thay ví dụ của đúng các từ này, không đụng bài, từ hay tiến độ.",
            "-- Chạy lại nhiều lần vẫn ra cùng kết quả. Xong thì xoá file trong storage/cache/content.",
            "SET NAMES utf8mb4;"]
    for x in doi:
        dong.append(
            f"SET @w = (SELECT w.id FROM words w JOIN lessons l ON l.id = w.lesson_id JOIN curricula c ON c.id = l.curriculum_id"
            f" WHERE c.name = {ten} AND l.level_id = {x['quyen']} AND l.lesson_no = {x['bai']}"
            f" AND w.hanzi COLLATE utf8mb4_bin = {sql_chuoi(x['hanzi'])} AND w.pinyin COLLATE utf8mb4_bin = {sql_chuoi(x['pinyin'])}"
            f" ORDER BY w.id LIMIT 1);")
        dong.append("DELETE FROM word_examples WHERE word_id = @w;")
        for i, (zh, py, vi, hl) in enumerate(x["moi"], 1):
            dong.append(f"INSERT INTO word_examples (word_id, sort_order, zh, pinyin, vi, highlight) SELECT @w, {i}, "
                        f"{sql_chuoi(zh)}, {sql_chuoi(py)}, {sql_chuoi(vi)}, {sql_chuoi(hl)} FROM DUAL WHERE @w IS NOT NULL;")
    return "\n".join(dong) + "\n"


def main() -> None:
    db = vi_du_db()
    doi: list[dict] = []
    khong_thay = []
    for q in BAI:
        for b in BAI[q]:
            f = file_ra(q, b)
            if not f.exists():
                continue
            for w in json.loads(f.read_text(encoding="utf-8"))["words"]:
                k = (q, b, w["hanzi"], w["pinyin"])
                if k not in db:
                    khong_thay.append(k)
                    continue
                moi, cu = chuan(w.get("examples", []), w["hanzi"]), chuan(db[k]["examples"], w["hanzi"])
                if moi != cu:
                    doi.append({"quyen": q, "bai": b, "hanzi": w["hanzi"], "pinyin": w["pinyin"], "id": int(db[k]["id"]),
                                "cu": cu, "moi": moi})
    # Chỉ ghi khi có thay đổi: chạy lại sau khi đã nạp thì không còn gì khác, ghi đè là mất file SQL cho server.
    if doi:
        ghi_json(THU_MUC / "ra" / "vi_du_doi.json", doi)
        (THU_MUC / "ra" / "cap-nhat-vi-du-hn.sql").write_text(viet_sql(doi), encoding="utf-8")
    them = sum(1 for x in doi if not x["cu"])
    print(f"{len(doi)} từ đổi ví dụ ({them} từ trước chưa có, {len(doi) - them} từ thay ví dụ) · {len(khong_thay)} từ không thấy trong DB")
    if khong_thay:
        print("  không thấy:", khong_thay[:10])
    if "--thu" in sys.argv or not doi:
        return

    from nap import Phien  # data/nap-sach/nap.py
    s = Phien()
    s.dang_nhap()
    for i, x in enumerate(doi, 1):
        cu = s.goi("GET", f"/admin/words/{x['id']}")
        cu["examples"] = [{"zh": zh, "pinyin": py, "vi": vi, "highlight": hl or x["hanzi"]} for zh, py, vi, hl in x["moi"]]
        s.goi("PUT", f"/admin/words/{x['id']}", cu)
        if i % 50 == 0:
            print(f"  {i}/{len(doi)}", flush=True)
    sau = vi_du_db()
    con = [x for x in doi if chuan(sau[(x["quyen"], x["bai"], x["hanzi"], x["pinyin"])]["examples"], x["hanzi"]) != x["moi"]]
    print(f"Đã sửa {len(doi)} từ · kiểm lại trong DB: {len(doi) - len(con)} khớp, {len(con)} lệch")
    if con:
        print("  lệch:", [(x["quyen"], x["bai"], x["hanzi"]) for x in con[:10]])
        sys.exit(1)


if __name__ == "__main__":
    main()
