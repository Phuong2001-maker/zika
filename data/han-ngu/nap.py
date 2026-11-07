"""Nạp ra/q<quyển>/baiNN.json vào giáo trình "Hán ngữ 6 tập" qua API quản trị.

  python nap.py all | python nap.py 2 | python nap.py 2 16

Cần MariaDB + API đang chạy (ZIKA_API, mặc định http://127.0.0.1:8787/api). Đi qua API nên giữ đủ lớp
kiểm tra như khi soạn tay. Chạy lại bao nhiêu lần cũng được: mọi phần ghi theo kiểu "thay toàn bộ".

API tự đánh số bài từ 1, nhưng sách đánh số nối tiếp (quyển 2 từ Bài 16). Số bài được đặt thẳng trong
DB ngay khi tạo, nên không phải sửa code web và file SQL cho server mang đúng số này.
"""
from __future__ import annotations

import json
import subprocess
import sys

from chung_hn import REPO, TEN_GIAO_TRINH, THU_MUC, chon_bai, file_ra, ghi_json
from chung import THU_MUC as _  # noqa: F401
from nap import Loi, Phien, bo_qua  # type: ignore  # data/nap-sach/nap.py

PHP = "D:/zika-devtools/php/php.exe"


def dat_so_bai(lid: int, so: int) -> None:
    """Đặt lesson_no = số sách in. Giá trị là số nguyên đã ép kiểu, không có đầu vào ngoài."""
    code = (
        "$c=require 'app/config.php';$d=$c['db'];"
        "$p=new PDO(\"mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4\",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);"
        f"$p->exec('UPDATE lessons SET lesson_no={int(so)} WHERE id={int(lid)}');"
    )
    subprocess.run([PHP, "-r", code], cwd=REPO / "api", check=True, capture_output=True)


MO_TA = "Giáo trình Hán ngữ phiên bản 3, 6 quyển: Từ mới, Bài khóa, Ngữ pháp và Tập viết chữ Hán."


def giao_trinh(s: Phien) -> int:
    ds = s.goi("GET", "/admin/curricula")
    ds = ds["curricula"] if isinstance(ds, dict) and "curricula" in ds else ds
    for c in ds:
        if c["name"] == TEN_GIAO_TRINH:
            s.goi("PUT", f"/admin/curricula/{c['id']}", {"name": c["name"], "description": MO_TA, "color": c["color"], "levelLabel": "Quyển"})
            return int(c["id"])
    return int(s.goi("POST", "/admin/curricula", {"name": TEN_GIAO_TRINH, "description": MO_TA, "color": "#E4EEDF", "levelLabel": "Quyển"})["id"])


def nap_bai(s: Phien, p: dict, cid: int, bai_co: dict[int, int]) -> dict:
    q, so = p["quyen"], p["bai"]
    if so in bai_co:
        lid = bai_co[so]
    else:
        lid = s.goi("POST", f"/admin/levels/{q}/lessons", {"title": p["lesson"]["title"], "curriculumId": cid})["id"]
        dat_so_bai(lid, so)
        bai_co[so] = lid
    s.goi("PATCH", f"/admin/lessons/{lid}", {**p["lesson"], "status": "draft"})
    bc: dict = {"id": lid}

    kq = s.goi("POST", "/admin/words/bulk", {"levelId": q, "lessonId": lid, "curriculumId": cid, "mode": "replace", "items": p["words"]})
    bc["words"], bc["words_bo"] = kq.get("created", 0), bo_qua(kq)

    kq = s.goi("POST", f"/admin/lessons/{lid}/writing/bulk", {"mode": "replace", "items": p["writing"]}, timeout=300)
    bc["writing"], bc["writing_bo"] = len(kq.get("chars", [])), bo_qua(kq)
    thieu_net = []
    for c in kq.get("chars", []):
        if c.get("strokeStatus") == "missing":
            try:
                s.goi("POST", f"/admin/characters/{c['charId']}/stroke-data/fetch", {})
            except Loi:
                thieu_net.append(c["hanzi"])
    bc["thieu_net"] = thieu_net

    if p["dialogues"]:
        kq = s.goi("PUT", f"/admin/lessons/{lid}/dialogue", {"dialogues": p["dialogues"]})
        bc["dialogues"] = len(kq.get("dialogues", []))
    kq = s.goi("POST", f"/admin/lessons/{lid}/grammar/bulk", {"mode": "replace", "items": p["grammar"]})
    bc["grammar"], bc["grammar_bo"] = kq.get("created", 0), bo_qua(kq)
    s.goi("PATCH", f"/admin/lessons/{lid}", {"status": "published"})
    return bc


if __name__ == "__main__":
    s = Phien()
    s.dang_nhap()
    cid = giao_trinh(s)
    print(f"Giáo trình “{TEN_GIAO_TRINH}” = {cid}")
    chon = chon_bai(sys.argv[1:])
    bao_cao = {}
    co_loi = False
    for q in sorted({x for x, _ in chon}):
        bai_co = {x["no"]: x["id"] for x in s.goi("GET", f"/admin/levels/{q}/lessons?curriculum={cid}")["lessons"]}
        for x, so in chon:
            if x != q:
                continue
            f = file_ra(q, so)
            if not f.exists():
                print(f"Quyển {q} · Bài {so}: chưa có {f.name} (chạy sinh.py trước)")
                continue
            p = json.loads(f.read_text(encoding="utf-8"))
            try:
                bc = nap_bai(s, p, cid, bai_co)
            except Loi as e:
                print(f"Quyển {q} · Bài {so}: ✗ {e}")
                co_loi = True
                continue
            bao_cao[f"q{q}-bai{so:02d}"] = bc
            bo = sum(len(v) for k, v in bc.items() if k.endswith("_bo") or k == "thieu_net")
            print(f"Quyển {q} · Bài {so}: {bc['words']} từ, {bc['writing']} chữ viết, {bc.get('dialogues', 0)} bài khoá, "
                  f"{bc['grammar']} ngữ pháp" + (f" — {bo} mục bị bỏ" if bo else ""))
            for k, v in bc.items():
                if (k.endswith("_bo") or k == "thieu_net") and v:
                    print(f"   {k}: " + "; ".join(v[:8]) + (" …" if len(v) > 8 else ""))
        # Cấp 4–6 trước đây ẩn vì chưa có bài; quyển 4–6 nằm ở các cấp này nên phải mở.
        s.goi("PATCH", f"/admin/levels/{q}", {"visible": True})
    cu = THU_MUC / "ra" / "nap.json"
    gop = json.loads(cu.read_text(encoding="utf-8")) if cu.exists() else {}
    gop.update(bao_cao)
    ghi_json(cu, gop)
    sys.exit(1 if co_loi else 0)
