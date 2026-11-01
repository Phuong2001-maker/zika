"""Nạp ra/hsk<cấp>/baiNN.json vào web qua API quản trị — đi đúng đường của nút Nhập Excel.

  python nap.py all | python nap.py 2 | python nap.py 2 5

Cần MariaDB + API đang chạy. Biến môi trường:
  ZIKA_API             mặc định http://127.0.0.1:8787/api
  ZIKA_ADMIN_EMAIL     mặc định admin@zika.local (tài khoản mẫu, chỉ máy dev)
  ZIKA_ADMIN_PASSWORD  mặc định đọc Installer::DEMO_PASSWORD
Chạy lại bao nhiêu lần cũng được: mọi phần ghi theo kiểu "xoá hết rồi nhập mới" của từng bài.
"""
from __future__ import annotations

import collections
import http.cookiejar
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request

from chung import REPO, THU_MUC, chon_bai, ghi_json

API = os.environ.get("ZIKA_API", "http://127.0.0.1:8787/api").rstrip("/")
ORIGIN = "http://localhost:5173"


class Loi(Exception):
    pass


class Phien:
    # API chặn 300 yêu cầu / 60 giây cho mỗi tài khoản (Core/App.php) — tự giữ dưới mức đó.
    TRAN = 270

    def __init__(self) -> None:
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.moc: collections.deque[float] = collections.deque()

    def csrf(self) -> str:
        return next((c.value for c in self.jar if c.name == "zk_csrf"), "")

    def cho_luot(self) -> None:
        while self.moc and time.monotonic() - self.moc[0] > 60:
            self.moc.popleft()
        if len(self.moc) >= self.TRAN:
            time.sleep(60.5 - (time.monotonic() - self.moc[0]))
            self.moc.popleft()
        self.moc.append(time.monotonic())

    def goi(self, method: str, path: str, body=None, timeout: int = 180):
        for lan in range(6):
            self.cho_luot()
            try:
                return self._goi(method, path, body, timeout)
            except Loi as e:
                if " → 429:" not in str(e) or lan == 5:
                    raise
                time.sleep(15)
        return None

    def _goi(self, method: str, path: str, body=None, timeout: int = 180):
        data = None if body is None else json.dumps(body, ensure_ascii=False).encode("utf-8")
        req = urllib.request.Request(API + path, data=data, method=method)
        req.add_header("Accept", "application/json")
        req.add_header("Origin", ORIGIN)
        if data is not None:
            req.add_header("Content-Type", "application/json")
        if method != "GET":
            req.add_header("X-CSRF-Token", self.csrf())
        try:
            with self.op.open(req, timeout=timeout) as r:
                raw = r.read().decode("utf-8")
        except urllib.error.HTTPError as e:
            raise Loi(f"{method} {path} → {e.code}: {e.read().decode('utf-8', 'replace')[:400]}") from None
        out = json.loads(raw) if raw.strip() else {}
        return out.get("data", out) if isinstance(out, dict) else out

    def dang_nhap(self) -> None:
        email = os.environ.get("ZIKA_ADMIN_EMAIL", "admin@zika.local")
        mk = os.environ.get("ZIKA_ADMIN_PASSWORD") or re.search(
            r"DEMO_PASSWORD\s*=\s*'([^']+)'",
            (REPO / "api/app/src/Services/Installer.php").read_text(encoding="utf-8"),
        ).group(1)
        self.goi("GET", "/public/config")
        self.goi("POST", "/auth/login", {"email": email, "password": mk, "remember": False})


def bo_qua(kq) -> list[str]:
    return [f"{x.get('hanzi') or ('dòng ' + str(x.get('row')))}: {x.get('reason')}" for x in (kq or {}).get("skipped", [])]


def nap_bai(s: Phien, p: dict, bai_co: dict[int, int]) -> dict:
    cap, so = p["cap"], p["bai"]
    if so in bai_co:
        lid = bai_co[so]
    else:
        if so != (max(bai_co) if bai_co else 0) + 1:
            raise Loi(f"HSK {cap} bài {so}: phải nạp bài {(max(bai_co) if bai_co else 0) + 1} trước (số bài cấp tự tăng)")
        lid = s.goi("POST", f"/admin/levels/{cap}/lessons", {"title": p["lesson"]["title"]})["id"]
        bai_co[so] = lid
    s.goi("PATCH", f"/admin/lessons/{lid}", {**p["lesson"], "status": "draft"})
    bc: dict[str, list[str] | int] = {"id": lid}

    kq = s.goi("POST", "/admin/words/bulk", {"levelId": cap, "lessonId": lid, "mode": "replace", "items": p["words"]})
    bc["words"], bc["words_bo"] = kq.get("created", 0), bo_qua(kq)

    # Nhập hàng loạt để điền pinyin/Hán Việt/nghĩa/cấu tạo cho bảng characters, rồi dựng lại danh sách
    # bằng from-words để mỗi chữ nối với từ nguồn (màn luyện viết hiện "Có trong từ: …").
    kq = s.goi("POST", f"/admin/lessons/{lid}/writing/bulk", {"mode": "replace", "items": p["writing"]}, timeout=300)
    bc["writing_bo"] = bo_qua(kq)
    for c in kq.get("chars", []):
        s.goi("DELETE", f"/admin/lesson-chars/{c['lessonCharId']}")
    kq = s.goi("POST", f"/admin/lessons/{lid}/writing/from-words", {}, timeout=300)
    co = {c["hanzi"] for c in kq.get("chars", [])}
    con = [x for x in p["writing"] if x["hanzi"] not in co]  # chữ của từ trùng cấp (không nằm trong bảng từ của bài)
    if con:
        kq = s.goi("POST", f"/admin/lessons/{lid}/writing/bulk", {"mode": "append", "items": con}, timeout=300)
    bc["writing"] = len(kq.get("chars", []))
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
    for phan in ("bt", "kt"):
        kq = s.goi("POST", f"/admin/lessons/{lid}/questions/bulk", {"part": phan, "mode": "replace", "items": p[phan]})
        bc[phan], bc[phan + "_bo"] = kq.get("created", 0), bo_qua(kq)
    if p["kt"]:
        s.goi("PUT", f"/admin/lessons/{lid}/test-config", p["testConfig"])
    s.goi("PATCH", f"/admin/lessons/{lid}", {"status": "published"})
    return bc


if __name__ == "__main__":
    s = Phien()
    s.dang_nhap()
    chon = chon_bai(sys.argv[1:])
    bao_cao = {}
    for cap in sorted({c for c, _ in chon}):
        bai_co = {x["no"]: x["id"] for x in s.goi("GET", f"/admin/levels/{cap}/lessons")["lessons"]}
        for c, so in chon:
            if c != cap:
                continue
            f = THU_MUC / "ra" / f"hsk{cap}" / f"bai{so:02d}.json"
            if not f.exists():
                print(f"HSK {cap} · Bài {so}: chưa có {f.name} (chạy sinh.py trước)")
                continue
            p = json.loads(f.read_text(encoding="utf-8"))
            try:
                bc = nap_bai(s, p, bai_co)
            except Loi as e:
                print(f"HSK {cap} · Bài {so}: ✗ {e}")
                continue
            bao_cao[f"hsk{cap}-bai{so:02d}"] = bc
            bo = sum(len(v) for k, v in bc.items() if k.endswith("_bo") or k == "thieu_net")
            print(f"HSK {cap} · Bài {so}: {bc['words']} từ, {bc['writing']} chữ viết, {bc.get('dialogues', 0)} bài khoá, "
                  f"{bc['grammar']} ngữ pháp, {bc['bt']} bài tập, {bc['kt']} kiểm tra" + (f" — {bo} mục bị bỏ" if bo else ""))
            for k, v in bc.items():
                if (k.endswith("_bo") or k == "thieu_net") and v:
                    print(f"   {k}: " + "; ".join(v[:8]) + (" …" if len(v) > 8 else ""))
        s.goi("PATCH", f"/admin/levels/{cap}", {"visible": True})
    cu = THU_MUC / "ra" / "nap.json"
    gop = json.loads(cu.read_text(encoding="utf-8")) if cu.exists() else {}
    gop.update(bao_cao)
    ghi_json(cu, gop)
