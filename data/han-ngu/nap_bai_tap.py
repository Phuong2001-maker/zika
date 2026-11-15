"""Nạp phần Bài tập của Hán ngữ (chép từ 练习 của sách, bai-tap/q{n}/baiNN.json) qua API quản trị.

  python nap_bai_tap.py all        python nap_bai_tap.py 3        python nap_bai_tap.py 3 5

Mỗi bài nạp kiểu "replace" vào POST /admin/lessons/{id}/questions/bulk part=bt, nên giữ đủ lớp kiểm của server
(QuestionPayload::clean). Câu sắp xếp: file chép giữ thứ tự mảnh như sách in; server cần mảnh theo thứ tự đúng
(màn học tự xáo khi hiện), nên xếp lại theo đáp án trước khi gửi. Cần MariaDB + API đang chạy.
"""
from __future__ import annotations

import json
import re
import sys

from chung_hn import TEN_GIAO_TRINH, THU_MUC, ghi_json
from chung import THU_MUC as _  # noqa: F401
from nap import Loi, Phien, bo_qua  # type: ignore  # data/nap-sach/nap.py

DAU = re.compile(r"[\s，。！？、；：“”‘’（）《》,.!?;:'\"()\-…—]")


def xep_manh(tiles: list[str], dap_an: str) -> list[str] | None:
    """Xếp các mảnh theo đúng thứ tự trong đáp án (bỏ dấu câu khi so). Không xếp được thì None."""
    goc = DAU.sub("", dap_an)
    con = list(range(len(tiles)))
    ra: list[str] = []

    def thu(vt: int) -> bool:
        if vt == len(goc):
            return not con
        for i in list(con):
            m = DAU.sub("", tiles[i])
            if m and goc.startswith(m, vt):
                con.remove(i)
                ra.append(tiles[i])
                if thu(vt + len(m)):
                    return True
                ra.pop()
                con.append(i)
        return False

    return ra if thu(0) else None


def cau_gui(c: dict) -> dict:
    p = dict(c["payload"])
    if c["type"] == "order":
        xep = xep_manh(p["tiles"], p["answer"])
        if xep is None:
            raise ValueError(f"không xếp được mảnh theo đáp án: {c['muc']}")
        p["tiles"] = xep
    out = {"type": c["type"], "payload": p}
    if str(c.get("giai_thich", "")).strip():
        out["explain"] = c["giai_thich"].strip()
    return out


def main(args: list[str]) -> int:
    s = Phien()
    s.dang_nhap()
    ds = s.goi("GET", "/admin/curricula")
    ds = ds["curricula"] if isinstance(ds, dict) and "curricula" in ds else ds
    cid = next(int(c["id"]) for c in ds if c["name"] == TEN_GIAO_TRINH)
    quyen = range(1, 7) if not args or args == ["all"] else [int(args[0])]
    chi_bai = int(args[1]) if len(args) > 1 else None
    bao_cao: dict = {}
    loi = 0
    for q in quyen:
        bai_co = {x["no"]: x["id"] for x in s.goi("GET", f"/admin/levels/{q}/lessons?curriculum={cid}")["lessons"]}
        for f in sorted((THU_MUC / "bai-tap" / f"q{q}").glob("bai*.json")):
            d = json.loads(f.read_text(encoding="utf-8"))
            so = int(d["bai"])
            if chi_bai is not None and so != chi_bai:
                continue
            if so not in bai_co:
                print(f"Quyển {q} · Bài {so}: không thấy bài trong giáo trình")
                loi += 1
                continue
            try:
                items = [cau_gui(c) for c in d.get("cau", [])]
                if not items:
                    continue
                kq = s.goi("POST", f"/admin/lessons/{bai_co[so]}/questions/bulk", {"part": "bt", "mode": "replace", "items": items}, timeout=300)
            except (Loi, ValueError) as e:
                print(f"Quyển {q} · Bài {so}: ✗ {e}")
                loi += 1
                continue
            bo = bo_qua(kq)
            bao_cao[f"q{q}-bai{so:02d}"] = {"gui": len(items), "nap": kq.get("created", 0), "bo": bo}
            print(f"Quyển {q} · Bài {so}: {kq.get('created', 0)}/{len(items)} câu" + (f" — bỏ: {'; '.join(bo[:5])}" if bo else ""))
            loi += 1 if bo else 0
    ghi_json(THU_MUC / "ra" / "nap-bai-tap.json", bao_cao)
    print(f"Tổng: {sum(x['nap'] for x in bao_cao.values())} câu ở {len(bao_cao)} bài" + (f", {loi} chỗ lỗi" if loi else ""))
    return 1 if loi else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
