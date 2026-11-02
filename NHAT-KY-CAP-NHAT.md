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

### 02/11/2026 — Kiểm tra lại phát âm, chuyển từ lật thẻ sang giọng nam Yunxi

Người dùng báo cả nhóm từ đơn đọc không đúng và yêu cầu lật thẻ giọng nam. Lượt quét trước chỉ
kiểm file thiếu/hỏng/câm/tốc độ, không đủ để kết luận phát âm đúng. Đối chiếu pinyin phát hiện
`头 tóu` dùng file `tou`, `道 dào` dùng file `dao`, `还 huán` dùng file `hái` do chỉ băm chữ Hán.

Đã đổi từ ở cả màn học theo bài và tổng hợp sang file theo **chữ + pinyin**, cố định giọng nam;
nút nghe câu ví dụ cũng dùng nam. Backend nhận `reading` tuỳ chọn khi tải TTS; web/PHP/Python dùng
cùng quy tắc băm, chuẩn hoá NFC, bỏ khoảng trắng và viết thường pinyin. Giữ nguyên đường dẫn cũ
cho những nơi không truyền pinyin.

Đã tạo 894 file nam Yunxi cho 932 thẻ (các từ trùng dùng chung), trong tổng 10.274 file TTS.
Dịch vụ tạo từng nhóm 10 từ, chỉ tách khi mốc WordBoundary khớp đúng chữ. Các chữ dễ đọc sai
được chọn âm trong `doc_tu_nam.py::chu_doc()` (có phân biệt `还 hái/huán`, `只 zhī`, `长 cháng`,
`地 de`, `粘人 niánrén`). File gốc/mốc cắt lưu tại `ra/yunxi-batch/`; tạo mới cần mạng và
`pip install edge-tts av`, phát trên web chỉ dùng mp3 đã có.

Giọng Yunxi sinh ở -20%, sau đó dùng atempo giữ cao độ để đọc chậm. Đo đoạn có tiếng trên đủ
894 file: trung vị 1 chữ **0,491**, 2 chữ **0,486**, 3 chữ **0,435**, 4 chữ **0,434**, 5 chữ **0,433**
giây/chữ (`ra/do_toc_do_yunxi.json`). `TTS_VERSION = 5` để bỏ cache file nam cũ.

Đã rà nhận dạng độc lập 40 mục đầu; kết quả mới rõ hơn bộ Kokoro chậm. Nhận dạng chỉ dùng để
sàng lọc, không phải chứng nhận toàn bộ thanh điệu đúng. Báo cáo trong `ra/kiem_nhan_dang_nam.json`
và `ra/kiem_nhan_dang_yunxi.json`.

Kiểm tra: web 0 lỗi/0 cảnh báo, lint sạch, 52/52 test; API 49/49 test; 3 test xử lý âm thanh
(giữ cao độ khi kéo dài, không tăng tốc file đã chậm, từ chối file câm) đều đạt.
Đã tải đủ bộ nam mới. Quét qua web lúc 14:46: **8.537 file âm thanh + 697 file nét chữ, 0 lỗi**;
pinyin thực tế của đủ 932 thẻ đều có file tương ứng. Chưa cập nhật hosting.

### 02/11/2026 — Từ và chữ đơn đọc chậm lại

Giữ `TOC_TU = 0.58`, `TOC_TU_DAI = 0.52` và `TTS_VERSION = 2` từ commit `4286d0d`.
Đã kiểm đủ 8.486 mp3 trên đĩa: không file thiếu, hỏng hoặc câm. Đo 2.648 file từ/chữ 1–4 chữ
(cả nữ và nam), lấy đoạn từ mẫu đầu đến mẫu cuối có `abs(a) > 0.02`, chia số chữ Hán:

| Số chữ | File | Trung vị giây/âm tiết | Phân vị 10–90% |
|---|---|---|---|
| 1 | 1.394 | 0,585 | 0,511–0,657 |
| 2 | 1.084 | 0,454 | 0,413–0,486 |
| 3 | 138 | 0,519 | 0,385–0,693 |
| 4 | 32 | 0,619 | 0,542–0,684 |

Chi tiết phép đo: `data/nap-sach/ra/do_toc_do.json` (chỉ lưu tại máy).
Bộ quét `quet.py` bỏ áp ngưỡng tốc độ câu cho từ 1–4 chữ vì nhóm này được sinh chậm riêng;
trước đó 30 file 4 chữ bị báo chậm do tính cả khoảng lặng đầu/cuối. Vẫn kiểm thiếu/hỏng/câm cho
mọi file và kiểm tốc độ câu từ 5 chữ. Đã thử 7 trường hợp âm thanh: bình thường, nhanh, chậm,
từ ngắn đọc chậm, câm, hỏng, thiếu — kết quả đúng.
Bộ quét dùng `127.0.0.1:5173` khớp Vite chỉ nghe IPv4. Đo 3 lượt tải cùng một mp3 trên Windows:
`localhost` mất 6,188 giây, `127.0.0.1` mất 0,008 giây; tránh thử kết nối IPv6 thất bại cho từng file.

Kiểm tra code: Svelte 0 lỗi/0 cảnh báo, lint sạch, web 47/47 test, API 46/46 test (611 assertions).
`python kiem_vi_du.py 1 2 3`: 320 + 172 + 440 từ, 0 lỗi.

Đã tải lại 2.648 file vào local, tổng đủ 8.486; đối chiếu SHA-256 với nguồn: 0 sai lệch.
Gắn âm thanh cho 40 bài, ghép 63 bài khoá, 0 câu thiếu file. Quét qua web lúc 14:14:
7.949 file âm thanh + 697 file nét chữ, **0 lỗi** (chi tiết ở cuối nhật ký).

**Đã đóng gói:** build thành công `release/zika-release-20261102-1414.zip`; chạy lại `xuat_sql.py`
ra `D:/zika-devtools/Project/dong-goi-server/`. Gói riêng `release/cap-nhat-giong-doc-20261102.zip`
gồm 166 file web + 8.486 mp3, 129.462.282 byte (123,46 MiB), đường dẫn `/`, không có API/PHP/config.
Đã kiểm CRC và đối chiếu SHA-256 từng file trong zip với nguồn. Manifest và file `.zip.sha256`
nằm cạnh zip; SHA-256 của zip: `fa14050e602f7b378541c2cb2482ec3e04a45235ace987d75b61bcce4718fc36`.

**Còn tiếp:** triển khai và xác minh web thật; chưa có kết nối hosting trong phiên này.
Từ môi trường hiện tại, tải thử mp3 trên web thật trả HTTP 403; chưa xác minh được bản mới trên server.

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

## Lần quét gần nhất — 02/11/2026 14:46

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
| file âm thanh | 8.537 |
| file nét chữ | 697 |

| Lỗi | Số chỗ | Ví dụ |
|---|---|---|
| Không có lỗi | 0 | |
