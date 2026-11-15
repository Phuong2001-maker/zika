"""Gom dữ liệu đầu vào để soạn "Cách nhớ" + "Từ ghép" cho màn Luyện viết Hán ngữ (1.381 chữ).

  python cach_nho_vao.py        → ra/cach-nho-vao/q1.json … q6.json (mỗi chữ xếp vào quyển nó xuất hiện đầu tiên)

Mỗi chữ có: pinyin, Hán Việt, nghĩa, bộ thủ, cấu tạo IDS + loại chữ (kho tu-dien), số nét và các nét thuộc bộ
(`radStrokes` trong file nét — là phần tô đỏ trên màn), phân tích cũ, và từ ghép ứng viên lấy từ bảng `words`
(Hán ngữ trước, đến quyển đang học; rồi các giáo trình khác). Chỉ đọc DB, không ghi.
"""
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

from chung_hn import THU_MUC

sys.path.append(str(THU_MUC.parent / "nap-sach"))
from chung import kho_chu  # noqa: E402

REPO = THU_MUC.parent.parent
PHP = "D:/zika-devtools/php/php.exe"
HAN_NGU = 3

SQL_CHU = f"""
SELECT c.id, c.hanzi, c.pinyin, c.han_viet, c.meaning_vi, c.radical, c.structure, c.analysis, c.stroke_data_path,
       MIN(l.level_id) AS quyen
FROM lesson_chars lc JOIN lessons l ON l.id = lc.lesson_id AND l.curriculum_id = {HAN_NGU}
JOIN characters c ON c.id = lc.char_id
GROUP BY c.id ORDER BY quyen, MIN(l.lesson_no), c.id
"""
SQL_TU = """
SELECT w.hanzi, w.pinyin, w.meaning_vi, w.curriculum_id, w.level_id, l.lesson_no
FROM words w JOIN lessons l ON l.id = w.lesson_id
"""


def truy_van(sql: str) -> list[dict]:
    code = (
        "$c=require 'app/config.php';$d=$c['db'];"
        "$p=new PDO(\"mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4\",$d['user'],$d['pass']);"
        "echo json_encode($p->query(stream_get_contents(STDIN))->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE);"
    )
    kq = subprocess.run([PHP, "-r", code], cwd=REPO / "api", input=sql.encode("utf-8"), capture_output=True, check=True)
    return json.loads(kq.stdout.decode("utf-8"))


def main() -> None:
    kho = kho_chu()
    chu = truy_van(SQL_CHU)
    tu = truy_van(SQL_TU)
    ra = THU_MUC / "ra" / "cach-nho-vao"
    ra.mkdir(parents=True, exist_ok=True)
    theo_quyen: dict[int, list[dict]] = {}
    for c in chu:
        q = int(c["quyen"])
        h = c["hanzi"]
        net = {}
        if c["stroke_data_path"]:
            f = REPO / "api" / "dev-uploads" / c["stroke_data_path"].removeprefix("/uploads/")
            if f.exists():
                net = json.loads(f.read_text(encoding="utf-8"))
        so_net = len(net.get("strokes", []))
        net_bo = net.get("radStrokes") or []
        # Từ ghép ứng viên: Hán ngữ đến quyển này trước (người học đã gặp), rồi giáo trình khác; bỏ trùng.
        ung = []
        thay = set()
        for w in sorted(
            (w for w in tu if h in w["hanzi"] and w["hanzi"] != h),
            key=lambda w: (int(w["curriculum_id"]) != HAN_NGU, int(w["level_id"]) > q, int(w["level_id"]), int(w["lesson_no"] or 0)),
        ):
            if w["hanzi"] in thay:
                continue
            thay.add(w["hanzi"])
            ung.append({
                "hanzi": w["hanzi"], "pinyin": w["pinyin"], "nghia": w["meaning_vi"],
                "nguon": ("Hán ngữ" if int(w["curriculum_id"]) == HAN_NGU else "HSK") + f" Q{w['level_id']} b{w['lesson_no']}",
            })
        k = kho.get(h, {})
        theo_quyen.setdefault(q, []).append({
            "hanzi": h, "pinyin": c["pinyin"], "hanViet": c["han_viet"], "nghia": c["meaning_vi"],
            "bo": c["radical"], "cau_tao": c["structure"], "ids": k.get("ids", ""), "loai": k.get("loai", ""),
            "thanh_phan_soan_tay": k.get("thanh_phan", []),
            "so_net": so_net, "net_bo": net_bo,
            "net_bo_phu_ca_chu": bool(so_net) and len(net_bo) == so_net,
            "phan_tich_cu": c["analysis"],
            "tu_ung_vien": ung[:12],
        })
    for q, ds in sorted(theo_quyen.items()):
        (ra / f"q{q}.json").write_text(json.dumps(ds, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"q{q}: {len(ds)} chữ, {sum(1 for x in ds if len(x['tu_ung_vien']) < 3)} chữ có dưới 3 từ ghép ứng viên")


if __name__ == "__main__":
    main()
