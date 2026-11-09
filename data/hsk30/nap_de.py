"""Nạp file đề Thi thử HSK 3.0 vào web qua API quản trị.

  python data/hsk30/nap_de.py data/hsk30/de/*.json

Đi qua API (không ghi thẳng DB) nên server vẫn chạy đủ QuestionPayload::clean như khi soạn tay. Đề đã có (cùng cấp,
cùng tên) thì thay toàn bộ câu; đề mới tạo ở trạng thái Nháp. Câu nào server từ chối thì in ra và thoát mã 1.

Mặc định nạp vào máy dev (http://127.0.0.1:8787/api, tài khoản admin mẫu). Đổi bằng biến môi trường
ZIKA_API, ZIKA_EMAIL, ZIKA_MAT_KHAU.
"""
from __future__ import annotations

import http.cookiejar
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path

API = os.environ.get("ZIKA_API", "http://127.0.0.1:8787/api").rstrip("/")


class Phien:
    def __init__(self) -> None:
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.goi("GET", "/public/config")
        email = os.environ.get("ZIKA_EMAIL", "admin@zika.local")
        st, _ = self.goi("POST", "/auth/login", {"email": email, "password": os.environ.get("ZIKA_MAT_KHAU", "Zika@2026")})
        if st != 200:
            raise SystemExit(f"Không đăng nhập được {email} (HTTP {st}).")

    def goi(self, m: str, u: str, body: object = None) -> tuple[int, object]:
        t = next((c.value for c in self.jar if c.name == "zk_csrf"), "")
        rq = urllib.request.Request(API + u, method=m, data=None if body is None else json.dumps(body).encode(),
                                    headers={"Content-Type": "application/json", "X-CSRF-Token": t})
        try:
            with self.op.open(rq) as r:
                raw = r.read()
                j = json.loads(raw or b"null")
                return r.status, j.get("data", j) if isinstance(j, dict) else j
        except urllib.error.HTTPError as e:
            try:
                return e.code, json.loads(e.read() or b"null")
            except ValueError:
                return e.code, None


def main(paths: list[str]) -> int:
    s = Phien()
    st, co = s.goi("GET", "/admin/hsk30")
    if st != 200:
        print("Không đọc được danh sách đề:", st, co)
        return 1
    da_co = {(d["levelId"], d["title"]): d for d in co}
    loi = 0
    for p in paths:
        de = json.loads(Path(p).read_text(encoding="utf-8"))
        khoa = (de["level"], de["title"])
        if khoa in da_co:
            mid = da_co[khoa]["id"]
            s.goi("PATCH", f"/admin/mock-tests/{mid}", {"durationSec": de["durationSec"]})
        else:
            st, r = s.goi("POST", "/admin/hsk30", {"levelId": de["level"], "title": de["title"], "durationSec": de["durationSec"]})
            if st != 201:
                print(f"{p}: không tạo được đề — {st} {r}")
                loi += 1
                continue
            mid = r["id"]
        items = [{"type": q["type"], "payload": q["payload"], "explain": q.get("explain", ""), "score": 1, "section": q["section"]}
                 for q in de["questions"]]
        st, r = s.goi("POST", f"/admin/mock-tests/{mid}/questions/bulk", {"mode": "replace", "items": items})
        if st != 200:
            print(f"{p}: nạp câu lỗi — {st} {r}")
            loi += 1
            continue
        bo = r.get("skipped") or []
        print(f"{de['title']}: {r.get('created')} câu{', BỎ ' + str(len(bo)) + ' câu' if bo else ''} (đề #{mid})")
        for b in bo:
            print(f"   dòng {b.get('row')}: {b.get('reason')}")
        loi += len(bo)
    return 1 if loi else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
