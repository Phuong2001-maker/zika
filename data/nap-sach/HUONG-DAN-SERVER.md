# Đưa giáo trình HSK 1–3 lên server

## Chỉ cập nhật tốc độ giọng đọc — 02/11/2026

Nội dung HSK 1–3 đã được nạp lên server ngày 02/11 (xem nhật ký). Lần này chỉ đổi file giọng đọc
và số cache `TTS_VERSION = 2`: **không import lại SQL**.

Gói riêng `release/cap-nhat-giong-doc-20261102.zip` gồm web đã build ngay ở gốc zip và
`uploads/tts/` (đủ 8.486 mp3), tên bên trong dùng dấu `/` để giải nén được trên Linux.
Upload và giải nén **gộp vào** DOCROOT `domains/tiengtrunghanu.io.vn`, cho phép ghi đè file trùng.
Zip này không có `api/`, mã PHP hay `config.php`; không cần chép thư mục `app/` của gói phát hành.

Sau khi chép xong, kiểm tra trên web thật: lật thẻ HSK 1, thử cả nữ/nam, từ đơn đọc chậm hơn,
URL âm thanh có `?v=2`, file trả `audio/mpeg`; làm tiếp kịch bản trong `KICH-BAN-TEST.md`.
Mục đang dở trong `CLAUDE.md` chỉ được xoá sau khi xác minh web thật.

## Nạp toàn bộ giáo trình

Gói đóng ngày 02/11/2026 từ máy local, đã nạp thử vào một database trống: số dòng khớp 100% với local,
chạy lại lần 2 không lỗi, 9.327 file mà database trỏ tới đều có trong `uploads/`.

```
dong-goi-server/
├─ 01-cau-truc.sql   cập nhật cấu trúc (migration 004 + 005)
├─ 02-noi-dung.sql   toàn bộ nội dung HSK 1–3 (5,6 MB)
└─ uploads/
   ├─ tts/           8.486 file giọng đọc mẫu (từ, ví dụ, chữ, câu thoại, ngữ pháp, câu hỏi nghe)
   ├─ audio/         144 file bài khoá (81 file thu thật HSK 1–2 + 63 file ghép HSK 3 / HSK 1 bài 2)
   ├─ hanzi/         nét chữ cho màn Luyện viết
   └─ giao-trinh/    ảnh trang sách cho màn Đọc giáo trình
```

## Thứ tự làm

**0. Sao lưu database server** (phpMyAdmin → Export) trước khi làm gì.

**1. Code mới:** kéo code từ GitHub (commit `24fed86` trở về sau) và build web như mọi lần —
xem README mục "Đưa lên hosting" (đừng để Paste của 1Panel thay cả thư mục mất `config.php`).

**2. Cấu trúc database**, chọn MỘT trong hai:
- có dòng lệnh: `cd api && php app/tools/install.php --migrate`
- chỉ có phpMyAdmin: Import `01-cau-truc.sql`

**3. Nội dung:** phpMyAdmin → chọn database của web → Import `02-noi-dung.sql`
(file 5,6 MB; hosting giới hạn upload nhỏ hơn thì nén .zip rồi import, phpMyAdmin nhận .zip).

**4. File:** chép 4 thư mục trong `uploads/` vào thư mục uploads của API trên server
(đường dẫn ghi ở `config.php` → `paths.uploads_dir`). **Chép gộp vào**, đừng xoá/thay cả thư mục
uploads: trong đó còn ảnh đại diện, ảnh góp ý… của người dùng.

**5. Cache dài cho file tĩnh** (khuyên làm, chống mất âm thanh/nét chữ khi mạng chập chờn).
Thêm vào cấu hình nginx của site (1Panel → Website → Cấu hình):

```nginx
location /uploads/ {
    expires 365d;
    add_header Cache-Control "public, max-age=31536000, immutable";
}
```

File âm thanh và nét chữ đều có `?v=` trong đường dẫn, sinh lại thì đổi số là trình duyệt tải bản mới.

**6. Kiểm tra** trên web thật theo `KICH-BAN-TEST.md` trong repo (mở một bài lật thẻ, lật thẻ phải
nghe giọng người; Luyện viết có bộ thủ; Hội thoại HSK 3 có nút Nghe).

## Lưu ý

- `02-noi-dung.sql` **xoá nội dung học cũ** (bài, từ, câu hỏi, hội thoại, ngữ pháp, chữ) rồi nạp lại.
  Tài khoản, cài đặt, thông báo, góp ý **giữ nguyên**. Tiến độ học và bài làm gắn với bài học cũ
  (HSK 1 bản cũ trên server) bị xoá theo — vì nội dung bài đã khác hẳn nên giữ lại cũng sai.
- Không có trong gói: tài khoản local (admin/biên tập/học viên mẫu), 2 thông báo thử ở local.
- Lần sau cập nhật nội dung: ở máy local chạy `cd data/nap-sach && python xuat_sql.py` là có gói mới
  ở thư mục này (gói cũ bị thay).
