# Nhật ký cập nhật Zìkǎ

Mỗi lần cập nhật nội dung hoặc tính năng thì ghi thêm một mục ở đầu phần "Các lần cập nhật":
đã làm gì, đã test gì, kết quả, việc còn treo. Sau mỗi lần nạp/sửa nội dung chạy lại

```bash
cd data/nap-sach && python quet.py
```

để mục "Lần quét gần nhất" ở cuối file tự cập nhật (âm thanh, nét chữ, bộ thủ, ví dụ, hội thoại).
Kịch bản test giao diện: [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

## Quy trình nạp và kiểm tra nội dung (máy local)

| Bước | Lệnh (trong `data/nap-sach/`) | Ghi chú |
|---|---|---|
| 1. Nạp giáo trình | `python sinh.py 1 2 3` → `python nap.py 1 2 3` | xem [README](data/nap-sach/README.md) |
| 2. Ví dụ lật thẻ | soạn `vi-du/hsk*.json` → `python kiem_vi_du.py 1 2 3` (phải 0 lỗi) → `python nap_vi_du.py 1 2 3` | 2 câu/từ, HSK1 ≤ 12 chữ, HSK2 ≤ 16, HSK3 ≤ 22, chỉ dùng chữ trong từ vựng các cấp ≤ cấp đó |
| 3. Phân tích chữ (bộ thủ) | `python phan_tich_chu.py` | từ `tu-dien/` (soạn tay + Unihan/Make Me a Hanzi) |
| 4. File nghe thật | `python nghe_gan.py 1 2` → `python gan_nghe.py 1 2` | mp3 giáo trình gửi kèm, HSK 1–2 |
| 5. Giọng đọc mẫu | **một lệnh:** `powershell -ExecutionPolicy Bypass -File .\sinh-giong-doc.ps1` | xem [HUONG-DAN-GIONG-DOC.md](data/nap-sach/HUONG-DAN-GIONG-DOC.md) |
| 6. Quét lỗi | `python quet.py` | cập nhật cuối file này |

## Các lần cập nhật

### 02/11/2026 — Ví dụ lật thẻ, giọng đọc Kokoro, bộ thủ, chuông thông báo

**Nội dung**
- 1.864 câu ví dụ mới cho 932 từ HSK 1–3 (2 câu/từ, ngắn theo cấp, pinyin và nghĩa đầy đủ),
  thay ví dụ cũ lấy từ đoạn hội thoại dài. Kiểm tự động 0 lỗi.
- Phân tích 697 chữ ở màn Luyện viết: bộ thủ (tên, nghĩa, số bộ), câu giải thích cấu tạo
  (314 chữ có giải thích soạn tay kèm mẹo nhớ, còn lại sinh từ dữ liệu hình thanh/hội ý),
  3–4 chữ khác cùng bộ ưu tiên chữ đã học.
- Giọng đọc mẫu Kokoro (giọng người, chạy trên máy) cho mọi từ, câu ví dụ, chữ luyện viết,
  ví dụ ngữ pháp, câu hỏi nghe — đủ giọng nữ và nam theo Cài đặt; câu thoại đọc đúng giọng
  nhân vật. Ép đọc đúng pinyin của sách cho chữ đa âm (还 huán/hái, 得 děi/de…).
- Bài khoá chưa có file thu thật (HSK 3, HSK 1 bài 2 khoá 1–3) có file ghép từ giọng Kokoro.
  HSK 1–2 còn lại giữ file thu thật của giáo trình.

**Tính năng / sửa lỗi**
- Chuông thông báo cho học viên (cạnh ảnh đại diện, mọi màn học): admin đăng ở Quản trị →
  Thông báo là học viên thấy số đỏ trong ≤ 2 phút; bảng "Tất cả (n)", "Đánh dấu đã xem tất cả",
  nhãn Mới/Đã đọc, trang `/notifications` đọc đủ nội dung. Trước đây thông báo có tải về nhưng
  không chỗ nào hiển thị. Hai tab "Tất cả (n)" / "Chưa đọc (m)"; chưa đọc = nền hồng, vạch và chấm đỏ,
  tiêu đề đậm, nhãn "Mới"; đã đọc = nền trắng, tiêu đề xám, "✓ Đã đọc"; đọc hết thì nút đổi thành
  "✓ Đã xem tất cả" (trước đó nút bị ẩn nên không biết đã đọc hết hay chưa).
- Lật thẻ: thẻ cao theo màn hình điện thoại, chữ "Chạm để lật", ví dụ có pinyin tô màu thanh,
  nút nghe to cho ngón tay.
- Sửa lỗi trắng trang ở lật thẻ khi pinyin có âm tiết lặp (常常, 不…不): trùng khoá vòng lặp Svelte.
- Luyện viết trên điện thoại: dải chữ cuộn ngang, ô viết to (≤ 340px) nằm ngay màn đầu; đã thử
  viết bằng chạm ngón tay, trang không bị cuộn theo.
- Quản trị → Từ vựng: chỉ hiện số từ thật ("320 từ"), bỏ dạng "320/150".
- Service worker không còn chặn file mp3 (thẻ audio xin từng đoạn, phản hồi 206 không cất được
  vào cache nên file câm, nhất là Safari/iPhone).
- Lưu hội thoại ở trang quản trị không còn xoá âm thanh của từng câu thoại.

**Quét lỗi và test (02/11/2026)** — `quet.py` tải thử từng file qua web như học viên:
- Lần 1: thiếu âm thanh chữ 条 (chỉ nằm trong từ nhi hoá 面条儿, bị bỏ sót khi tách chữ) → đã sinh.
- Đo tốc độ toàn bộ 5.706 câu ≥ 4 chữ: trung vị 2,9 âm tiết/giây (vừa cho người học); 79 câu
  nhanh hơn 3,8 (câu thoại dài) → sinh lại chậm hơn, về ~3,4. Đoạn nhiều câu đọc từng câu, nghỉ 0,3 giây.
- Lần cuối: còn 2 câu số điện thoại (HSK 1 bài 6) — Kokoro đọc 0932463321 thành "九亿三千…" →
  sửa đọc từng số (1 = yāo); cùng lúc sửa 13 ví dụ dạng "A：… B：…" (bỏ nhãn, A/B hai giọng) và
  "HSK" (đọc 汉语水平考试 vì giọng tiếng Trung không đọc chữ cái). 54 file sinh lại, kiểm riêng: 0 lỗi.
- Không file âm thanh nào thiếu/hỏng/câm; 697 file nét chữ đủ, số nét khớp; 697 chữ đủ bộ thủ + phân tích.
- Test giao diện theo [KICH-BAN-TEST.md](KICH-BAN-TEST.md) ở 375px / 768px / 1366px: lật thẻ (tự đọc
  khi lật, 2 ví dụ, nghe từng câu), luyện viết (viết bằng chạm ngón tay, bộ thủ, chữ cùng bộ),
  hội thoại (file thật HSK 1–2, file ghép HSK 3, từng câu đúng giọng nhân vật), ngữ pháp, chuông
  thông báo (admin đăng → số đỏ → đọc → đánh dấu đã xem tất cả). Lỗi tìm ra khi test và đã sửa:
  bấm nghe liên tiếp làm câu trước bị đánh dấu "thiếu file" và rơi sang giọng khác; bảng chuông tự
  đóng khi bấm "Đánh dấu đã xem tất cả".
- Kiểm tra code: web 514 file 0 lỗi 0 cảnh báo, lint sạch, 47/47 test; API 46/46.

**Gói đưa lên server:** `python xuat_sql.py` → `Project/dong-goi-server/` (ngoài repo): 2 file SQL +
`uploads/` (tts, audio, hanzi, giao-trinh) + HUONG-DAN.md (bản gốc: `data/nap-sach/HUONG-DAN-SERVER.md`).
Đã nạp thử vào MariaDB trống: số dòng khớp 100% local, chạy lại 2 lần không lỗi, người dùng giữ nguyên,
9.327 file database trỏ tới đều có trong gói.

**Đã đưa lên web thật (02/11/2026):** xoá nội dung cũ, import 2 file SQL, chép code (`af65846`) và
`uploads`, xoá `storage/cache/content`. Kiểm tra: dữ liệu web thật **giống hệt local** (dấu vân tay
40 bài × 5 phần trùng), 8.551 file âm thanh + 697 nét chữ tải được, kịch bản giao diện đạt —
chi tiết cuối [KICH-BAN-TEST.md](KICH-BAN-TEST.md).

**Database:** migration `004_dialogue_audio.sql` (file nghe cả bài khoá),
`005_character_analysis.sql` (bộ thủ, phân tích chữ). Server thật cần chạy
`php app/tools/install.php --migrate`.

## Lần quét gần nhất — 02/11/2026 06:34

Sinh tự động bởi `data/nap-sach/quet.py`, đừng sửa tay mục này.

| Hạng mục | Số lượng |
|---|---|
| từ | 932 |
| câu ví dụ | 1.864 |
| chữ luyện viết | 1.567 |
| bài khoá | 144 |
| câu thoại | 822 |
| câu hỏi nghe | 149 |
| ví dụ ngữ pháp | 653 |
| file âm thanh | 7.949 |
| file nét chữ | 697 |

| Lỗi | Số chỗ | Ví dụ |
|---|---|---|
| Âm thanh: tốc độ lạ | 2 | HSK 1 bài 6 · câu thoại 我的手机号是0932463321 · /uploads/tts/nu2/b5/b533d05aab59783ba517b41cfb2daab70dd0cf23.mp3 |