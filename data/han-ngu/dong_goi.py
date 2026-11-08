"""Đóng gói giáo trình Hán ngữ cho server, ra ../../../dong-goi-han-ngu/ (ngoài repo).

  python dong_goi.py       # sau quet_hn.py: cần ra/quet_hn.json để biết những file nào được dùng

Gói gồm cấu trúc DB, nội dung, file âm thanh + nét chữ và hướng dẫn. Chép nguyên zip đè lên server là đủ:
tên file âm thanh theo nội dung chữ nên trùng thì giống hệt, không làm hỏng giáo trình HSK.
"""
from __future__ import annotations

import hashlib
import json
import re
import subprocess
import zipfile
from urllib.parse import unquote

from chung_hn import REPO, THU_MUC

PHP = "D:/zika-devtools/php/php.exe"
RA = REPO.parent / "dong-goi-han-ngu"
UPLOADS = REPO / "api" / "dev-uploads"
MIGRATION = REPO / "api" / "app" / "migrations"
DOCROOT = "domains/tiengtrunghanu.io.vn"
# 004, 005 đã lên server từ gói 02/11; 006 vào gói 04/11 nhưng không chắc đã chạy, cũng an toàn vì có kiểm tra.
TU_SO, DEN_SO = 6, 18


def cau_truc() -> str:
    """Mỗi migration chỉ chạy nếu schema_migrations chưa ghi: lệnh ALTER không chạy lại được, mà người dùng chỉ có
    phpMyAdmin nên không có install.php --migrate để tự xét. Dùng PREPARE chứ không dùng thủ tục tạm vì thủ tục
    cần quyền CREATE ROUTINE (user DB local đã không có, hosting chưa chắc có) và lệnh DELIMITER."""
    phan = ["-- Zìkǎ · cấu trúc DB tới migration 018. Migration đã chạy thì tự bỏ qua, chạy lại file này không sao.",
            "SET NAMES utf8mb4;"]
    for f in sorted(MIGRATION.glob("*.sql")):
        so = int(f.name[:3])
        if not TU_SO <= so <= DEN_SO:
            continue
        than = "\n".join(d for d in f.read_text(encoding="utf-8").splitlines()
                         if d.strip() and not d.lstrip().startswith("--") and not d.upper().startswith("SET NAMES"))
        phan += ["", f"-- {f.name}", f"SET @zika_chay = NOT EXISTS (SELECT 1 FROM schema_migrations WHERE filename = '{f.name}');"]
        for lenh in re.split(r";\s*(?:\n|$)", than):
            if lenh.strip():
                sql = lenh.replace("\\", "\\\\").replace("'", "''")
                phan += [f"SET @zika_lenh = IF(@zika_chay, '{sql}', 'DO 0');", "PREPARE zika_st FROM @zika_lenh;", "EXECUTE zika_st;", "DEALLOCATE PREPARE zika_st;"]
        phan.append(f"INSERT IGNORE INTO schema_migrations (filename) VALUES ('{f.name}');")
    return "\n".join(phan) + "\n"


def huong_dan(dem: dict, so_file: int, zip_mb: int, sql_mb: float) -> str:
    return f"""ĐƯA GIÁO TRÌNH "HÁN NGỮ 6 TẬP" LÊN SERVER

Gói có: {dem['bai']} bài · {dem['tu']:,} từ · {dem['khoa']} bài khóa ({dem['cau']:,} câu) · {dem['np']} mục ngữ pháp · {dem['chu']:,} chữ luyện viết · {so_file:,} file âm thanh + nét chữ.

CẦN TRƯỚC: code mới trên server (gói web + PHP đóng từ commit có giáo trình này). Code cũ không có màn Giáo trình
và không nhận từ dạng "又…又…". Chưa có gói code thì đừng chạy file SQL.

1. Sao lưu: phpMyAdmin → database buwsofujhosting_zika → Export (Xuất). Giữ file ở máy.
2. phpMyAdmin → Import (Nhập) → 01-cau-truc.sql. Migration đã chạy tự bỏ qua, nên không phải xem trước đã chạy tới đâu.
3. phpMyAdmin → Import → 02-han-ngu.sql ({sql_mb:.1f} MB; quá giới hạn upload thì chọn bản 02-han-ngu.sql.gz).
   Chạy lại lần nữa cũng không sao: file chỉ xoá và nạp lại đúng giáo trình này.
   CHÚ Ý: chạy lại sẽ xoá tiến độ học của học viên ở giáo trình này. Chỉ chạy lại khi có bản nội dung mới.
4. 1Panel → /home/buwsofujhosting → upload uploads-han-ngu.zip ({zip_mb} MB) → giải nén vào CHÍNH thư mục đó, cho phép
   gộp thư mục và ghi đè file trùng. Đừng xoá gì trong uploads.
5. Xoá các FILE trong /home/buwsofujhosting/zika-app/storage/cache/content, giữ thư mục. Không thì API trả dữ liệu cũ.
6. Vào web bằng tài khoản quản trị → Giáo trình: thấy "Giáo trình HSK 3.0" (tên mới của giáo trình HSK) và "Hán ngữ 6 tập"
   có nhãn "Mặc định". Từ giờ MỌI học viên chưa được phân quyền (tài khoản cũ lẫn mới đăng ký) học Hán ngữ;
   ai cần học HSK thì gán "Giáo trình HSK 3.0" ở Giáo trình → Phân quyền học. Tiến độ HSK cũ vẫn còn, gán lại là học tiếp.
   Gán "Giáo trình HSK 3.0" cho một học viên thử rồi kiểm:
   - Học viên chưa phân quyền thấy "Quyển 1…6"; học viên được gán HSK 3.0 thấy "HSK 1…6".
   - Quyển 1 bài 1: Lật thẻ nghe được, Luyện viết có nét, Hội thoại bấm câu nào đọc câu đó, Ngữ pháp có ví dụ.
   - Quyển 6 bài 22: nút "Nghe" cả bài khóa phát đủ 8 phút.
   - Học viên được gán HSK 3.0: thấy đúng bài HSK và tiến độ cũ.

Không có trong gói: tài khoản, tiến độ, ảnh trang sách. File SQL không đụng bài và từ của giáo trình HSK. Ảnh hưởng chung:
- Hán ngữ thành giáo trình mặc định: học viên chưa phân quyền chuyển sang Hán ngữ, lượt ôn tập dở của họ bị bỏ
  (tiến độ từng bài HSK vẫn giữ).
- Cấp 4–6 được bật hiển thị vì Hán ngữ dùng cấp 1–6 làm 6 quyển; học viên HSK sẽ thấy các cấp này ở trạng thái chưa có bài.
"""


if __name__ == "__main__":
    RA.mkdir(exist_ok=True)
    (RA / "01-cau-truc.sql").write_text(cau_truc(), encoding="utf-8")
    ra = subprocess.run([PHP, str(THU_MUC / "xuat_sql.php"), str(RA / "02-han-ngu.sql")], capture_output=True, encoding="utf-8", check=True).stdout
    dem = json.loads(ra)["dem"]
    subprocess.run([PHP, "-r", f"$a=file_get_contents('{(RA / '02-han-ngu.sql').as_posix()}');file_put_contents('{(RA / '02-han-ngu.sql.gz').as_posix()}',gzencode($a,9));"], check=True)

    q = json.loads((THU_MUC / "ra" / "quet_hn.json").read_text(encoding="utf-8"))
    if q["loi"]:
        raise SystemExit(f"Bản quét còn lỗi, chưa đóng gói: {list(q['loi'])}")
    duong = sorted({unquote(u.split("?")[0]).removeprefix("/uploads/") for u in q["am_thanh"] + q["net_chu"]})
    # Đường dẫn trong DB phải nằm trọn trong gói, kể cả file bản quét không gọi tới (âm thanh từng từ của từ trùng).
    sql = (RA / "02-han-ngu.sql").read_text(encoding="utf-8")
    trong_sql = {m.split("?")[0].removeprefix("/uploads/") for m in re.findall(r"/uploads/[\w/.\-]+", sql)}
    duong = sorted(set(duong) | trong_sql)
    thieu = [p for p in duong if not (UPLOADS / p).is_file()]
    if thieu:
        raise SystemExit(f"Thiếu {len(thieu)} file trong uploads, ví dụ {thieu[:3]}")
    goi = RA / "uploads-han-ngu.zip"
    with zipfile.ZipFile(goi, "w", zipfile.ZIP_STORED) as z:
        for p in duong:
            z.write(UPLOADS / p, f"{DOCROOT}/uploads/{p}")
    with zipfile.ZipFile(goi) as z:
        if z.testzip() is not None or len(z.namelist()) != len(duong):
            raise SystemExit("Zip hỏng")
    (RA / "HUONG-DAN.txt").write_text(huong_dan(dem, len(duong), goi.stat().st_size // 1048576, (RA / "02-han-ngu.sql").stat().st_size / 1048576), encoding="utf-8-sig")
    h = hashlib.sha256(goi.read_bytes()).hexdigest()
    (RA / "uploads-han-ngu.zip.sha256").write_text(f"{h}  {goi.name}\n", encoding="ascii")
    print(json.dumps({"thu_muc": str(RA), "file": len(duong), "zip_mb": goi.stat().st_size // 1048576, **dem}, ensure_ascii=False))
