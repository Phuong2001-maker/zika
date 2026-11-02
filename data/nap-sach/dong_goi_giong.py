"""Đóng gói cập nhật giọng đọc để đưa lên server: web đã build + file âm thanh mới + SQL nhỏ.

  cd web && npm run build          # trước tiên, để có web/build với TTS_VERSION mới
  python dong_goi_giong.py         # → release/cap-nhat-giong-moi-<ngày giờ>.zip (+ .manifest.json, .sha256)

Trong zip (đường dẫn dùng "/", giải nén gộp vào DOCROOT, cho phép ghi đè):
  - web đã build ở gốc zip;
  - uploads/tts/...   mọi file đọc mẫu đã kiểm (giọng chung nu/nam + giọng riêng nhân vật);
  - uploads/audio/... file nghe cả bài khoá (băng thật đã cân độ to, hoặc bản ghép);
  - cap-nhat-am-thanh.sql  chỉ UPDATE cột audio_path của bài khoá và câu thoại, tìm dòng theo
    (cấp, số bài, thứ tự khoá, thứ tự câu) nên không phụ thuộc id; không xoá dòng nào.
Không có api/, mã PHP hay config.php.
"""
from __future__ import annotations

import hashlib
import json
import subprocess
import time
import zipfile

from chung import REPO, THU_MUC, ghi_json

PHP = "D:/zika-devtools/php/php.exe"
UPLOADS = REPO / "api" / "dev-uploads"
BUILD = REPO / "web" / "build"
RELEASE = REPO / "release"

TRUY_VAN = r'''
require "app/bootstrap.php";
$k = \Zika\Core\Db::all("SELECT l.level_id cap, l.lesson_no bai, d.sort_order khoa, d.audio_path FROM dialogues d
  JOIN lessons l ON l.id = d.lesson_id WHERE l.level_id <= 3 ORDER BY 1, 2, 3");
$c = \Zika\Core\Db::all("SELECT l.level_id cap, l.lesson_no bai, d.sort_order khoa, dl.sort_order cau, dl.audio_path
  FROM dialogue_lines dl JOIN dialogues d ON d.id = dl.dialogue_id JOIN lessons l ON l.id = d.lesson_id
  WHERE l.level_id <= 3 ORDER BY 1, 2, 3, 4");
echo json_encode(["khoa" => $k, "cau" => $c]);
'''


def q(s: str | None) -> str:
    return "NULL" if s is None else "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def sql(dl: dict) -> str:
    for nhom, khoa in (("khoa", ("cap", "bai", "khoa")), ("cau", ("cap", "bai", "khoa", "cau"))):
        gap = {tuple(r[x] for x in khoa) for r in dl[nhom]}
        if len(gap) != len(dl[nhom]):
            raise SystemExit(f"Khoá tự nhiên của {nhom} bị trùng, không xuất SQL theo cách này được")
    dong = ["-- Cập nhật đường dẫn âm thanh hội thoại HSK 1–3 (giọng đọc mới). Chỉ sửa cột audio_path.",
            "-- Tìm dòng theo cấp, số bài, thứ tự khoá, thứ tự câu; chạy lại nhiều lần vẫn ra cùng kết quả.",
            "SET NAMES utf8mb4;", "START TRANSACTION;"]
    for r in dl["khoa"]:
        dong.append(f"UPDATE dialogues d JOIN lessons l ON l.id = d.lesson_id SET d.audio_path = {q(r['audio_path'])}"
                    f" WHERE l.level_id = {r['cap']} AND l.lesson_no = {r['bai']} AND d.sort_order = {r['khoa']};")
    for r in dl["cau"]:
        dong.append(f"UPDATE dialogue_lines dl JOIN dialogues d ON d.id = dl.dialogue_id JOIN lessons l ON l.id = d.lesson_id"
                    f" SET dl.audio_path = {q(r['audio_path'])} WHERE l.level_id = {r['cap']} AND l.lesson_no = {r['bai']}"
                    f" AND d.sort_order = {r['khoa']} AND dl.sort_order = {r['cau']};")
    dong.append("COMMIT;")
    return "\n".join(dong) + "\n"


def sha(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()


if __name__ == "__main__":
    if not (BUILD / "index.html").exists():
        raise SystemExit("Chưa có web/build — chạy: cd web && npm run build")
    dl = json.loads(subprocess.run([PHP, "-r", TRUY_VAN], cwd=REPO / "api", capture_output=True, text=True,
                                   encoding="utf-8", check=True).stdout)
    da = json.loads((THU_MUC / "ra" / "da_tai_edge.json").read_text(encoding="utf-8"))
    # Chỉ file thuộc danh sách hiện tại (file của lần phân vai cũ đã tải lên local thì bỏ).
    hien = {v["out"] for v in json.loads((THU_MUC / "ra" / "doc_edge.json").read_text(encoding="utf-8"))}
    duong = {v["path"] for o, v in da.items() if o in hien}
    duong |= {r["audio_path"] for nhom in ("khoa", "cau") for r in dl[nhom] if r["audio_path"]}
    thieu = [p for p in duong if not (UPLOADS / p.removeprefix("/uploads/")).exists()]
    if thieu:
        raise SystemExit(f"{len(thieu)} file database trỏ tới nhưng không có trên máy, vd {thieu[:3]}")
    ten = f"cap-nhat-giong-moi-{time.strftime('%Y%m%d-%H%M')}"
    zf = RELEASE / f"{ten}.zip"
    RELEASE.mkdir(exist_ok=True)
    muc = []
    with zipfile.ZipFile(zf, "w", zipfile.ZIP_DEFLATED) as z:
        for f in sorted(BUILD.rglob("*")):
            if f.is_file():
                ten_zip = f.relative_to(BUILD).as_posix()
                z.write(f, ten_zip)
                muc.append({"path": ten_zip, "sha256": sha(f.read_bytes())})
        for p in sorted(duong):
            f = UPLOADS / p.removeprefix("/uploads/")
            ten_zip = "uploads/" + p.removeprefix("/uploads/")
            z.write(f, ten_zip, compress_type=zipfile.ZIP_STORED)  # mp3 đã nén sẵn
            muc.append({"path": ten_zip, "sha256": sha(f.read_bytes())})
        noi = sql(dl).encode("utf-8")
        z.writestr("cap-nhat-am-thanh.sql", noi)
        muc.append({"path": "cap-nhat-am-thanh.sql", "sha256": sha(noi)})
    # Kiểm lại: CRC từng file và SHA-256 trong zip khớp nguồn
    with zipfile.ZipFile(zf) as z:
        hong = z.testzip()
        if hong:
            raise SystemExit(f"CRC hỏng: {hong}")
        sai = [m["path"] for m in muc if sha(z.read(m["path"])) != m["sha256"]]
        if sai:
            raise SystemExit(f"SHA-256 lệch: {sai[:3]}")
    tong = sha(zf.read_bytes())
    ghi_json(RELEASE / f"{ten}.manifest.json", {"archive": zf.name, "sha256": tong, "entries": muc})
    (RELEASE / f"{ten}.zip.sha256").write_text(f"{tong}  {zf.name}\n", encoding="utf-8")
    mp3 = sum(1 for m in muc if m["path"].startswith("uploads/"))
    print(f"{zf.name}: {zf.stat().st_size:,} byte · {len(muc) - mp3 - 1} file web · {mp3} mp3 · SQL"
          f" {len(dl['khoa'])} khoá + {len(dl['cau'])} câu · CRC/SHA-256 khớp · {tong}")
