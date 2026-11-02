# Đưa giáo trình HSK 1–3 lên server

## Cập nhật giọng đọc mới + sửa giao diện — 03/11/2026

Thay **cả** gói `cap-nhat-lat-the-nam-20261102.zip` (không cần upload gói đó nữa). Mọi giọng đọc mẫu
chuyển sang giọng Microsoft, câu thoại đọc giọng riêng từng nhân vật, `TTS_VERSION = 6`; kèm web đã
sửa giao diện (bảng thông báo, chuyển nhanh, tương phản, chế độ tối…). Nội dung bài **không đổi**:
không import lại `02-noi-dung.sql`.

Gói `release/cap-nhat-giong-moi-<ngày giờ>.zip` (tên bên trong dùng `/`):

| Trong zip | Là gì |
|---|---|
| gốc zip (`index.html`, `_app/`…) | web đã build |
| `uploads/tts/…` | toàn bộ file đọc mẫu mới: `nu`, `nam` và giọng nhân vật `xiaoxiao`, `xiaoyi`, `yunxi`, `yunjian`, `yunyang`, `yunxia` |
| `uploads/audio/…` | file nghe cả bài khoá (băng thật đã cân độ to, hoặc bản ghép) |
| `cap-nhat-am-thanh.sql` | chỉ `UPDATE` cột `audio_path` của bài khoá và câu thoại, tìm theo (cấp, số bài, thứ tự khoá, thứ tự câu) |

Không có `api/`, mã PHP hay `config.php`.

**0.** Sao lưu database (phpMyAdmin → Export).
**1.** Upload zip, giải nén **gộp vào** DOCROOT `domains/tiengtrunghanu.io.vn`, cho phép ghi đè file trùng.
**2.** phpMyAdmin → database của web → Import `cap-nhat-am-thanh.sql`. Chạy lại lần nữa cũng không sao.
   Import xong **xoá file `cap-nhat-am-thanh.sql` khỏi DOCROOT** (để đó ai cũng tải được).
**3.** Xoá sạch `<APP_DIR>/storage/cache/content/` — không thì API vẫn trả đường dẫn âm thanh cũ.
**4.** Kiểm (hoặc báo để kiểm qua mạng như lần trước):
   - `https://tiengtrunghanu.io.vn/_app/version.json` ra số mới của gói;
   - lật thẻ: URL âm thanh có `?v=6`, trả `audio/mpeg`, giọng nam đọc rõ, không kéo dài;
   - hội thoại HSK 1 bài 3: bấm từng câu nghe đúng giọng nhân vật; "Nghe" cả bài khoá đọc đúng chữ trên màn hình;
   - trình duyệt đã từng mở web: Ctrl+Shift+R một lần.
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
