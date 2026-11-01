"""Đóng gói nội dung giáo trình ở máy local để đưa lên server: SQL + thư mục uploads + hướng dẫn.

  python xuat_sql.py              # → <Project>/dong-goi-server/  (không nằm trong repo)

Ra 2 file SQL chạy theo thứ tự trong phpMyAdmin:
  01-cau-truc.sql   migration 004, 005 (cột âm thanh bài khoá, bộ thủ) — bỏ qua nếu đã chạy --migrate
  02-noi-dung.sql   xoá sạch nội dung học cũ rồi nạp lại toàn bộ HSK 1–3 như máy local
Chỉ gồm bảng nội dung. Không có tài khoản, phiên, tiến độ, thông báo, góp ý — dữ liệu người dùng trên
server giữ nguyên (riêng tiến độ/bài làm gắn với bài học cũ bị xoá theo bài học, xem HUONG-DAN).
"""
from __future__ import annotations

import os
import shutil
import subprocess
import tempfile
from datetime import datetime
from pathlib import Path

from chung import REPO, THU_MUC

PHP = r"D:/zika-devtools/php/php.exe"
DUMP = r"D:/zika-devtools/mariadb/bin/mariadb-dump.exe"
RA = THU_MUC.parents[2] / "dong-goi-server"

# Thứ tự cha → con (nạp); xoá thì đi ngược lại.
BANG = [
    "levels", "lessons", "words", "word_examples", "characters", "lesson_chars", "writing_configs",
    "mock_tests", "questions", "test_configs", "dialogues", "dialogue_speakers", "dialogue_lines",
    "dialogue_questions", "grammar_points", "audio_assets",
]
THU_MUC_UPLOADS = ["tts", "audio", "hanzi", "giao-trinh"]


def cau_hinh_db() -> dict:
    code = "$c = require 'app/config.php'; echo json_encode($c['db']);"
    out = subprocess.run([PHP, "-r", code], cwd=REPO / "api", capture_output=True, text=True, check=True).stdout
    import json
    return json.loads(out)


def main() -> None:
    db = cau_hinh_db()
    if RA.exists():
        shutil.rmtree(RA)
    (RA / "uploads").mkdir(parents=True)

    # 01 — cấu trúc: đúng nội dung migration, kèm dòng ghi đã chạy để install.php --migrate không chạy lại.
    phan = ["-- Zìkǎ · cập nhật cấu trúc database (migration 004, 005).",
            "-- Đã chạy `php app/tools/install.php --migrate` trên server thì BỎ QUA file này.", ""]
    for ten in ("004_dialogue_audio.sql", "005_character_analysis.sql"):
        phan.append((REPO / "api" / "app" / "migrations" / ten).read_text(encoding="utf-8"))
        phan.append(f"INSERT IGNORE INTO schema_migrations (filename) VALUES ('{ten}');\n")
    (RA / "01-cau-truc.sql").write_text("\n".join(phan), encoding="utf-8")

    # 02 — nội dung. Mật khẩu DB đưa qua file tạm, không lộ trên dòng lệnh.
    with tempfile.NamedTemporaryFile("w", suffix=".cnf", delete=False, encoding="utf-8") as f:
        f.write(f"[client]\nuser={db['user']}\npassword={db['pass']}\nhost={db['host']}\nport={db['port']}\n")
        cnf = f.name
    try:
        du_lieu = subprocess.run(
            [DUMP, f"--defaults-extra-file={cnf}", "--no-create-info", "--skip-triggers", "--complete-insert",
             "--skip-extended-insert", "--default-character-set=utf8mb4", "--skip-comments", "--no-tablespaces",
             db["name"], *BANG],
            capture_output=True, check=True,
        ).stdout.decode("utf-8")
    finally:
        os.unlink(cnf)
    dau = [
        f"-- Zìkǎ · nội dung giáo trình HSK 1–3, xuất từ máy local {datetime.now():%d/%m/%Y %H:%M}.",
        "-- Chạy SAU 01-cau-truc.sql (hoặc sau install.php --migrate).",
        "-- Xoá nội dung học cũ: khoá ngoại bật nên tiến độ/bài làm gắn với bài cũ bị xoá theo.",
        "SET NAMES utf8mb4;",
        "SET FOREIGN_KEY_CHECKS = 1;",
        "START TRANSACTION;",
        *[f"DELETE FROM `{b}`;" for b in reversed(BANG) if b != "levels"],
        "COMMIT;",
        "-- levels: giữ 6 dòng, cập nhật bằng REPLACE bên dưới.",
        "",
    ]
    # levels đã có sẵn trên server (khoá ngoại từ nhiều bảng) → REPLACE thay vì INSERT trùng khoá.
    du_lieu = du_lieu.replace("INSERT INTO `levels`", "REPLACE INTO `levels`")
    (RA / "02-noi-dung.sql").write_text("\n".join(dau) + du_lieu, encoding="utf-8")

    for d in THU_MUC_UPLOADS:
        nguon = REPO / "api" / "dev-uploads" / d
        if nguon.exists():
            shutil.copytree(nguon, RA / "uploads" / d)
    print(f"Đã đóng gói vào {RA}")


if __name__ == "__main__":
    main()
