# Giáo trình HSK 1–3 — đã làm gì, còn phải làm gì

> **Cũ (08/11/2026):** `tach-giao-trinh.py` đã thay bằng `dung-sach.py` (9 quyển Hán ngữ + HSK, kèm file nghe, đáp án,
> `sach.json`); kệ sách lấy danh sách từ API, service worker không cache sách. Cách làm hiện tại ở mục "Giáo trình" của
> [README.md](../../README.md). Phần dưới giữ làm lịch sử.

Ghi lúc dừng việc ngày **19/10/2026**. Mở file này ra là làm tiếp được ngay, không cần đọc lại
lịch sử chat.

Nguồn (nằm ngoài repo, trên máy chủ dự án):

| Quyển | Đường dẫn | Trang | Dạng |
|---|---|---|---|
| HSK 1 | `D:\Tiếng trung\HSK1 3.0 Hanu 24.6.26.pdf` | 149 | **PDF chữ thật** — bóc được bằng máy |
| HSK 2 | `D:\Tiếng trung\HSK2 3.0 Hanu 29.6.26.pdf` | 121 | **Scan** — mỗi trang 1 ảnh JPEG, 0 ký tự text |
| HSK 3 | `D:\Tiếng trung\HSK3 3.0 Hanu 29.6.26.pdf` | 199 | **Scan** — như trên |

Cả ba của Trung tâm Ngôn ngữ & Văn hoá Trung Quốc, Đại học Hà Nội, bản 3.0.

---

## Phần A — Màn đọc giáo trình ✅ XONG, ĐÃ COMMIT

Nhánh `giao-trinh-hsk1-3`. Người học bấm **Giáo trình** ở bảng Chuyển nhanh → kệ 3 quyển
(`/learn/books`) → màn đọc từng trang (`/learn/books/<mã>`).

### Vì sao là ảnh từng trang chứ không phải chữ

Chỉ HSK 1 bóc được chữ. HSK 2 và HSK 3 là bản scan, không có text nào. Để cả ba quyển đọc
giống hệt nhau thì render hết thành ảnh.

Đã đo thử 900 / 1200 / 1600px: **1200px** đọc rõ cả chữ Hán cỡ nhỏ lẫn pinyin mà cả bộ 469
trang chỉ ~44MB, trong khi 3 file PDF gốc là 111MB. Ảnh bìa lấy từ chính trang 1 của PDF
(scan gốc 2480×3508) nên nét hơn hẳn ảnh chụp điện thoại và không dính chữ viết tay.

### File liên quan

| File | Việc |
|---|---|
| `data/giao-trinh/tach-giao-trinh.py` | Render 469 trang + 3 bìa ra WebP vào thư mục uploads |
| `web/src/lib/learn/sach.ts` | Danh sách 3 quyển, URL ảnh, nhớ trang đang đọc (localStorage) |
| `web/src/routes/learn/books/+page.svelte` | Kệ sách |
| `web/src/routes/learn/books/[ma]/+page.svelte` | Màn đọc, lật trang quanh gáy sách |
| `web/src/lib/components/Switcher.svelte` | Ô Giáo trình cuối lưới bảng Chuyển nhanh |
| `web/src/service-worker.ts` | Cache riêng `zika-book`, trần 80 trang |
| `api/app/src/Services/VisitService.php` | Nhãn "Đang đọc giáo trình HSK n" |

### Những chỗ đã chốt với chủ dự án

- Nhớ chỗ đọc dở bằng **localStorage**, không đẩy lên server.
- Ô Giáo trình nằm **trong lưới**, cùng hàng với Cấu trúc ngữ pháp, không có dòng mô tả phụ.
- Kệ sách **không có đoạn giới thiệu**, chỉ tiêu đề và 3 bìa.
- Màn đọc **không có thanh nút trên/dưới** — bấm hai nửa trang, vuốt ngang, phím mũi tên.

### Chạy lại script khi cần

```bash
cd data/giao-trinh && python tach-giao-trinh.py "D:/zika-devtools/update/zika/api/dev-uploads"
```

Trên hosting đổi đích thành `public_html/uploads`. **Ảnh nằm ngoài git** (`api/dev-uploads/`
đã bị gitignore) nên đẩy code lên server là chưa đủ — phải tải thư mục `giao-trinh/` lên
`public_html/uploads/`, không thì màn đọc báo "Chưa có ảnh trang sách".

---

## Phần B — Cấu trúc một bài, đã khảo sát cả 3 quyển

### 8 mục bài nào của quyển nào cũng có

Theo đúng thứ tự in trong sách:

1. **目标 · Mục tiêu bài học**
2. **热身 · Khởi động**
3. **课文 · Bài khoá** — câu hỏi nghe chọn đáp án + đoạn hội thoại có pinyin
4. **生词 · Từ mới** — STT, chữ Hán, pinyin, từ loại, nghĩa Việt
5. **语法 · Ngữ pháp** — công thức khung nét đứt + ví dụ
6. **练习 · Luyện tập**
7. **学习小结 · Tổng kết bài học** — 会认字/会认词 + 会写字
8. **句式 · Mẫu câu** — bảng MẪU CÂU | VÍ DỤ, nằm trong Tổng kết

Mục 3–4–5 là **một cụm lặp lại**, không phải xuất hiện một lần: Bài khoá 1 → Từ mới → Ngữ
pháp 1 → Bài khoá 2 → Từ mới → Ngữ pháp 2 → … **Ít nhất 2 vòng, nhiều nhất 4 vòng**
(HSK 3 bài 1 có tới 课文 4 và 语法 3).

### Ngoại lệ và mục riêng

| Mục | HSK 1 | HSK 2 | HSK 3 |
|---|---|---|---|
| 文化知识 · Kiến thức văn hoá | **chỉ bài 1–2** | mọi bài | mọi bài |
| 语音 · Ngữ âm + 声调 · Thanh điệu | **chỉ bài 1–3** | không | không |
| Chữ cần nhận biết | 会认**字** | 会认**词** | 会认**词** |
| Phụ lục chữ Hán cuối sách | có | không | không |

**Ba bài đầu HSK 1 chưa đủ 8 mục** vì đó là giai đoạn dạy phát âm: bài 1 thiếu Khởi động,
Ngữ pháp, Tổng kết, Mẫu câu; bài 2 thiếu Khởi động, Ngữ pháp; bài 3 thiếu Khởi động.
Từ bài 4 trở đi cả ba quyển đủ 8 mục.

**专名 · Tên riêng** nằm ngay dưới Từ mới nhưng chỉ có khi bài đó thật sự có tên riêng
(HSK 1: 5/15 bài).

---

## Phần C — Bản đồ mục sách → 6 phần học, ĐÃ CHỐT

| Phần học | Lấy từ mục nào của sách |
|---|---|
| **Lật thẻ** (`lt`) | 生词 Từ mới **+ 专名 Tên riêng** |
| **Luyện viết** (`lv`) | 生词 Từ mới **+ 专名 Tên riêng** (tách ra cấp chữ) |
| **Bài tập** (`bt`) | Bài tập quanh 课文 (判断对错 → `tf`, nghe chọn đáp án → `listen`, điền đoạn văn → `blank`) **+ 练习 Luyện tập** (选词填空 → `blank`, 排列成句 → `order`, 翻译 → `trans`) |
| **Đọc hội thoại** (`ht`) | Đúng đoạn hội thoại của 课文 |
| **Kiểm tra** (`kt`) | **Không có trong sách — phải tự sinh.** Nguồn tốt nhất là bảng 句式 Mẫu câu ở Tổng kết bài học |
| **Ngữ pháp** (`np`) | 语法 — khớp 1:1, gần như bê thẳng |

### Quyết định của chủ dự án, đừng tự đổi

- **Luyện viết lấy từ 生词 Từ mới**, KHÔNG lấy theo danh sách 会写字 của sách. (Đã đề xuất
  dùng 会写字 vì sách chia sẵn và vì Từ mới là *từ* còn luyện viết là *chữ* — chủ dự án
  chọn lấy Từ mới.)
- **专名 Tên riêng vào cả Lật thẻ lẫn Luyện viết.**
- **Bỏ 目标 Mục tiêu bài học** — không đưa vào phần nào.
- **Bỏ 文化知识 Kiến thức văn hoá** — người học đọc ở phần Giáo trình.
- **Bỏ 热身 Khởi động** — xem lý do kỹ thuật ở Phần D.

---

## Phần D — Vấn đề đã biết

Xếp theo mức nặng.

### 1. HSK 2 và HSK 3 không bóc được chữ ❌ chưa có lời giải

Hai quyển là scan hoàn toàn. `data/hsk1/tach-pdf.py` chỉ chạy với HSK 1. Muốn nạp 6 phần cho
HSK 2–3 phải OCR 320 trang rồi soát tay, hoặc gõ tay.

**Khuyến nghị:** làm xong HSK 1 trước. HSK 2–3 cứ để đọc ở phần Giáo trình, nạp 6 phần sau,
từng bài một. Nếu buộc phải OCR thì bóc **2 lượt độc lập rồi so lệch** — chỗ hai lượt khác
nhau là chỗ phải nhìn mắt.

### 2. `dialogues` chỉ chứa được 1 hội thoại mỗi bài ✅ ĐÃ SỬA

~~`UNIQUE KEY uq_dlg_lesson (lesson_id)`~~ — migration `003_dialogue_nhieu_bai_khoa.sql`
(commit `957fbe6`) đã bỏ khoá này, thêm cột `sort_order`, và bỏ hẳn bảng `dialogue_questions`.
Mỗi bài giờ chứa được nhiều bài khoá. Đã chạy xong trên server ngày 23/10/2026.

Kèm theo: một số 课文 của HSK 3 là **đoạn văn một người kể**, không phải đối thoại hai người,
trong khi mô hình hiện tại giả định có nhân vật nói qua lại.

### 3. Khởi động không nhập được

11 dạng câu hỏi hiện có (`meaning, pinyin, tone, audio, listen, blank, pick, toZh, order,
trans, tf, match`) **đều là chữ và âm thanh, không có dạng nào dùng ảnh**. Mà 热身 trong sách
là "chọn ảnh tương ứng với từ". Vì thế đã bỏ Khởi động khỏi Bài tập.

### 4. Luyện viết đòi dữ liệu cấp chữ mà sách chỉ cho cấp từ

Nét chữ thì ổn — `HanziDataService` tự tải từ `hanzi-writer-data` về `uploads/hanzi/`. Nhưng
bảng `characters` còn đòi `han_viet` và `meaning_vi` **cho từng chữ**, trong khi sách chỉ ghi
nghĩa của cả từ (服务员 = nhân viên phục vụ, không ghi nghĩa riêng 服 / 务 / 员). Phải lấy từ
nguồn từ điển khác hoặc điền tay. 专名 là chỗ khó nhất: tách 颐和园 ra từng chữ thì nghĩa gần
như vô nghĩa.

### 5. Có file âm thanh nhưng chưa gắn được ⚠️ sửa lại 23/10/2026

Chủ dự án có **143 file mp3** ở `D:\Tiếng trung\New folder` (ngoài git): `hsk1/` 93 file
(15 bài, bài 1–3 có 7 file, bài 4–15 có 6 file, ~28,6 phút) và `hsk2/Bài N/` 50 file
(10 bài × 5 file, ~24 phút). HSK 3 không có.

Ba chỗ vướng:

1. **Cột `audio_path` nằm ở `dialogue_lines`, tức một file cho MỘT CÂU**, còn mp3 là một file
   cho **cả đoạn** (10–34 giây). Gắn nguyên vào từng dòng thì bấm câu nào cũng phát lại cả đoạn.
   Phải chọn: cắt mp3 thành từng câu, hoặc thêm cột `audio_path` vào bảng `dialogues` rồi sửa
   màn đọc thêm nút “Nghe cả đoạn”.
2. **Chưa biết 6 file mỗi bài ứng với mục nào** (khởi động / từ mới / bài khoá 1,2,3 / luyện tập).
   Sách không in số track, phải nghe mới biết.
3. **Thẻ ID3 của HSK 1 ghi album `新HSK教程1` của NXB `外研社` (FLTRP)** — một bộ giáo trình khác,
   soạn cho HSK 2.0. Cấu trúc thì khớp (15 bài, bài 1–3 có thêm 1 file đúng với mục 语音·声调 chỉ
   có ở 3 bài đầu), nhưng phải nghe thử một file mới chắc là đúng sách. HSK 2 không có thẻ, và
   một file còn dấu vết `Lavf60` nên đã bị cắt lại bằng ffmpeg.

Trước khi gắn được thì Đọc hội thoại và hai dạng `listen` / `audio` vẫn dựa vào giọng đọc máy
(`web/src/lib/learn/speech.ts`).

### 6. Chất lượng bóc HSK 1 hiện tại

| | Số lượng | Tình trạng |
|---|---|---|
| Từ vựng | 326 từ | ✅ nhưng **có sót**, xem Phần E mục 1 |
| Hội thoại | 41 đoạn · 221 câu | ⚠️ bản dịch mới ghép được **48/221** |
| Chữ luyện viết | 336 chữ | ✅ |
| Tên bài | 13/15 đúng | ⚠️ bài 8 và 15 lấy nhầm thành "Hãy chọn hình ảnh tương ứng với…" |
| Ngữ pháp | 6/27 | ❌ regex chỉ bắt được khuôn A (bài 3, 4) |
| Mẫu câu (句式) | 0 | ❌ chưa bóc |

Hai từ `的` và `了` sách cố ý không ghi nghĩa (trợ từ) — phải điền tay.

---

## Phần E — Cách giảm sai

### 1. Lấy sách kiểm sách ⭐ đã thử, bắt được lỗi thật

Mỗi bài có mục **会认字** ở Tổng kết liệt kê **đủ chữ của bài đó**. So tập chữ trong từ vựng
đã bóc với danh sách ấy — thiếu chữ nào là biết sót từ đó.

Đã chạy thử bài 6: báo thiếu **5 chữ 出 多 少 租 车**, tức bộ tách sót **出租车 và 多少**.
`data/hsk1/TIEN-DO.md` mới ghi nhận mỗi 出租车 — vậy phép kiểm này vừa tìm thêm một lỗi
chưa ai biết.

Tương tự, bảng **句式 MẪU CÂU** đếm được số điểm ngữ pháp mỗi bài → bắt ngay lỗi "mới bóc
được 6/27".

**Việc cần làm: viết script chạy phép kiểm này cho cả 15 bài trước khi xuất Excel.**

### 2. Câu hỏi: suy ra, đừng bịa

- **Ưu tiên bài tập sách có sẵn** (选词填空, 判断对错, 排列成句) — đáp án của nhà xuất bản.
- **Dạng sinh an toàn vì đáp án đúng do cấu tạo:** `order` (xáo một câu lấy nguyên văn từ
  bài khoá), `blank` (khoét một từ khỏi câu bài khoá), `meaning` / `pinyin` / `tone` (lấy
  thẳng bảng Từ vựng đã soát).
- **Tránh tự dịch** — dùng đúng câu tiếng Việt sách in.
- **Nhiễu chỉ lấy từ cùng bài**, tự loại nếu nghĩa trùng nhau.

### 3. Đường ống: Excel để nạp, SQL chỉ để chuyển lên server

**Không viết `INSERT` tay.** Chạy SQL thẳng mất ba lớp bảo vệ:

1. Mất `QuestionPayload::clean()` / `Validator` — payload đúng cú pháp JSON nhưng thiếu khoá
   vẫn lọt, đến lúc học viên mở bài mới vỡ.
2. Không gọi được `ContentWriter::touch($lessonId)` — app hiện số `cnt_*` cũ và cache nội
   dung cũ dù database đã đổi.
3. Không tải được nét chữ về `uploads/hanzi/`.

MariaDB đang bật `STRICT_TRANS_TABLES` nên nó chặn được JSON hỏng cú pháp, ENUM sai, vượt
độ dài cột, sai khoá ngoại — **nhưng không chặn được sai nghĩa**, mà đó mới là loại lỗi nguy
hiểm trong một app dạy học.

Thứ tự đúng:

1. Sinh **một `.xlsx` cho mỗi bài**, đủ 8 sheet đúng tên spec (`Từ vựng`, `Bài tập`,
   `Kiểm tra`, `Luyện viết`, `Lời thoại`, `Câu hỏi`, `Ngữ pháp`, `Ví dụ`). Tên cột lấy đúng
   từ `columns[].header` trong `web/src/lib/admin/import/*.ts`.
2. Chạy bộ đối chiếu 会认字 / 句式, sửa hết rồi mới đưa file cho người soát.
3. Nhập qua nút **Nhập Excel** ở máy local.
4. Soát từng bài đối chiếu PDF; sai thì sửa trong Excel, nhập lại bằng "Xoá hết rồi nhập mới".
5. Xong hết mới `mysqldump` các bảng nội dung ra một `.sql` → dán vào phpMyAdmin trên hosting.

**`mysqldump` không mang theo file nét chữ** ở `uploads/hanzi/` — phải tải kèm, giống ảnh
giáo trình.

### 4. Soát bằng mắt cho nhanh

Đã có sẵn 469 ảnh trang. Dựng một trang đối chiếu **ảnh trang sách bên trái, dữ liệu đã nạp
bên phải** thì soát một bài mất vài phút, thay vì dò SQL.

---

## Phần F — Việc tiếp theo, theo thứ tự

Cập nhật 23/10/2026. **Chi tiết đầy đủ nằm ở [data/hsk1/TIEN-DO.md](../hsk1/TIEN-DO.md) mục
"Việc còn lại" — mở file đó trước, file này chỉ là bản đồ tổng.**

1. ~~Sửa `dialogues` UNIQUE~~ ✅ xong (migration 003, đã chạy cả local lẫn server).
2. ~~Viết script đối chiếu 会认字 / 句式~~ ✅ xong — `data/hsk1/kiem-tra.py`, và đã cho 5 agent
   đọc PDF đối chiếu cả 15 bài. Kết quả: 8 nhóm lỗi, xem `data/hsk1/TIEN-DO.md` Phần D.
3. **Sửa bộ tách HSK 1** — việc lớn nhất còn lại, 18 đầu việc đã liệt kê theo thứ tự ở
   `data/hsk1/TIEN-DO.md`. Lỗi gốc: bóc nhầm bảng 会写字 thay 会认字, dính 14 bài.
4. **Viết `sinh-cau-hoi.py`** theo nguyên tắc "suy ra, đừng bịa" ở Phần E mục 2. Khuôn payload
   từng dạng xem `api/app/src/Services/QuestionPayload.php` — sai là API trả 422.
5. **Nạp lại HSK 1** bằng `xuat-sql.py` (đường SQL, đã dùng được) hoặc nút Nhập Excel.
6. **Bài nghe** — quyết cách gắn, xem Phần D mục 5.
7. HSK 2–3: để sau. HSK 2 giờ đã có file nghe, nhưng sách vẫn là bản scan không bóc được chữ.

Chi tiết các bước bóc PDF HSK 1 xem thêm [data/hsk1/TIEN-DO.md](../hsk1/TIEN-DO.md).
