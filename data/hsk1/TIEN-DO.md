# Nạp giáo trình HSK 1 vào Zìkǎ — đang làm dở

Ghi lúc dừng việc ngày **14/10/2026**. File này để mở ra là làm tiếp được ngay, không cần đọc lại
lịch sử chat.

Nguồn: `C:\Users\TNDESKTOP0001\Downloads\HSK1 3.0 Hanu 24.6.26.pdf`
(HSK 1 bản 3.0 — Trung tâm Ngôn ngữ & Văn hoá Trung Quốc, ĐH Hà Nội · 149 trang · 15 bài).

---

## Phần A — Nhập nội dung từ Excel ✅ XONG, ĐÃ COMMIT

Hai commit đã vào `main`:

| Commit | Nội dung |
|---|---|
| `5272982` | Sửa lỗi dạng câu “Chọn từ điền câu” ở phần Kiểm tra không lưu được (web gọi `fill`, API chỉ biết `blank`) |
| `8184c70` | Nhập nội dung từ Excel cho 6 phần soạn bài |

Cả 6 trang soạn bài đã có nút **Nhập Excel**. Đã thử thật trên trình duyệt với file `.xlsx` sinh
tại chỗ: đọc đúng, báo lỗi đúng dòng, ghi đúng vào database. Kiểm thử: web 500 file 0 lỗi 0 cảnh
báo, lint sạch, 43/43; API 44/44.

Chi tiết cách dùng xem [README.md](../../README.md) mục “Nhập nội dung từ Excel”, chi tiết kỹ
thuật xem [CLAUDE.md](../../CLAUDE.md) bẫy số 6.

---

## Phần B — Nạp dữ liệu HSK 1 ⏳ ĐANG DỞ

### Hai quyết định đã chốt với chủ dự án

1. **Bài tập và Kiểm tra**: giáo trình gần như không có dữ liệu cho hai phần này (chỉ vài bài
   「选词填空」, còn lại là miêu tả tranh / phân vai đọc / luyện phát âm — không đưa lên web được).
   → **Tự sinh thêm** cho đủ **10 câu Bài tập + 10 câu Kiểm tra mỗi bài**, lấy nguyên liệu từ
   từ vựng, bài khoá và ngữ pháp của chính bài đó.

2. **Phạm vi xoá**: **xoá sạch** bài học, từ vựng, câu hỏi, hội thoại, ngữ pháp của **mọi cấp**
   (kể cả dữ liệu mẫu HSK 2 · Bài 6), rồi nạp 15 bài mới vào **HSK 1**.
   **Giữ nguyên** tài khoản, cài đặt, và tiến độ học.

### Đã làm

- `tach-pdf.py` — bóc PDF ra `hsk1.json`. Chạy:

```bash
cd data/hsk1 && python tach-pdf.py "C:/Users/TNDESKTOP0001/Downloads/HSK1 3.0 Hanu 24.6.26.pdf" hsk1.json
```

- `hsk1.json` — kết quả bóc hiện tại:

| | Số lượng | Tình trạng |
|---|---|---|
| Từ vựng | **326 từ** | ✅ Tốt — chữ Hán, pinyin, từ loại, nghĩa Việt đầy đủ |
| Hội thoại | **41 đoạn · 221 câu** | ✅ Chữ Hán + pinyin tốt — ⚠️ bản dịch mới ghép được 48/221 |
| Chữ luyện viết | **336 chữ** | ✅ Tốt |
| Tên bài | 13/15 đúng | ⚠️ Bài 8 và 15 lấy nhầm thành “Hãy chọn hình ảnh tương ứng với…” |
| Ngữ pháp | **6/27** | ❌ Kém — xem bên dưới |
| Mẫu câu (句式) | **0** | ❌ Chưa bóc được |

### Việc còn lại, theo thứ tự

1. **Ngữ pháp** — regex hiện chỉ bắt được khuôn A (bài 3, 4):
   `N.` → tên VIẾT HOA → giải thích → ví dụ gạch đầu dòng `•`.
   Từ bài 5 trở đi giáo trình đổi sang khuôn B: `1. 时间的表达   Biểu đạt thời gian` (tên chữ Hán
   và tiếng Việt **cùng một dòng**), nội dung là bảng chứ không có bullet.
   → Chỉ có **~27 điểm ngữ pháp** cho cả cuốn. Nhanh và chắc hơn cả là **đọc PDF rồi soạn tay**
   vào một file `ngu-phap.json` thay vì cố thêm regex. Nguồn tốt nhất là mục
   **句式 MẪU CÂU** ở phần 学习小结 cuối mỗi bài (bảng “mẫu câu | ví dụ”, ngắn gọn, đúng dạng web cần).

2. **Bản dịch hội thoại (48/221)** — hàm `cau_tieng_viet()` chỉ ghép khi *tổng số câu Việt của cả
   bài khớp đúng tổng số câu Trung*; lệch một câu là bỏ hết bài đó (cố ý: thà thiếu còn hơn gán
   nhầm câu). Hiện chỉ bài 6, 12, 14 khớp. Cần nới: ghép theo **từng đoạn 课文** thay vì cả bài,
   và xử lý câu tiếng Việt bị ngắt làm 2–3 dòng trong PDF.

3. **Hai từ chưa có nghĩa**: `的` và `了` — giáo trình cố ý không ghi nghĩa (trợ từ). Điền tay.

4. **Tên bài 8 và 15** — sửa tay trong `hsk1.json`.

5. **Sinh câu Bài tập + Kiểm tra** (chưa bắt đầu). Viết `sinh-cau-hoi.py` đọc `hsk1.json`, mỗi bài
   sinh 10 + 10 câu. Dạng dùng được, theo đúng `QuestionGrader` của API:
   - Bài tập (`BT_TYPES`): `meaning` `blank` `audio` `pinyin` `order` `trans` `tf` `match`
   - Kiểm tra (`KT_TYPES`): `blank` `listen` `meaning` `pick` `order` `trans` `tf` `match` `tone` `toZh`
   Nguyên liệu: `meaning`/`pinyin` từ bảng từ vựng (phương án nhiễu lấy từ từ khác **cùng bài**),
   `order` từ câu trong bài khoá, `match` từ 2–6 cặp từ–nghĩa, `blank` từ câu bài khoá khoét một từ.
   **Khuôn payload từng dạng xem `api/app/src/Services/QuestionPayload.php`** — viết sai là API
   trả 422.

6. **Xuất Excel** (chưa bắt đầu) — `xuat-excel.py`, mỗi bài một file `excel/bai-01.xlsx` gồm đủ
   các sheet đặt **đúng tên spec** để bộ nhập tự nhận: `Từ vựng`, `Bài tập`, `Kiểm tra`,
   `Luyện viết`, `Lời thoại`, `Câu hỏi`, `Ngữ pháp`, `Ví dụ`.
   Tên cột lấy đúng từ `web/src/lib/admin/import/*.ts` (mục `columns[].header`).
   Bộ nhập đã được sửa để file gộp nhiều sheet vẫn lấy đúng sheet phụ (`pickSheet(..., 1)`).

7. **Xoá dữ liệu cũ + nạp mới** (chưa bắt đầu) — `nap-du-lieu.php` dùng `Zika\Core\Db`, chạy bằng
   `D:/zika-devtools/php/php.exe`. Thứ tự xoá phải theo khoá ngoại:
   `questions` → `dialogue_lines` → `dialogue_speakers` → `dialogue_questions` → `dialogues` →
   `grammar_points` → `lesson_chars` → `word_examples` → `words` → `test_configs` →
   `writing_configs` → `mock_tests` → `lessons`.
   **Giữ** `users`, `site_settings`, `lesson_progress`, `visits`, `stats_daily`.
   Nạp xong **bắt buộc** gọi `ContentWriter::touch($lessonId)` cho từng bài để đếm lại `cnt_*` và
   xoá cache nội dung, nếu không giao diện vẫn hiện số cũ.

8. **Chạy lại kiểm thử rồi commit**:

```bash
cd web && npm run check && npm run lint && npm test
```

```bash
cd api && "D:/zika-devtools/php/php.exe" app/vendor/bin/phpunit -c phpunit.xml
```

### Ba cái bẫy đã sa vào, đừng sa lại

1. **Chữ Hán trong PDF là ký tự Kangxi Radicals**, không phải chữ Hán thường — `⽂` (U+2F02) chứ
   không phải `文` (U+6587). Nhìn giống hệt nhau. `Pinyin::isHanzi` ở API sẽ từ chối, API báo
   “Không phải chữ Hán”. Hàm `chuan_hoa()` đã xử lý.

2. **Đừng NFKC cả chuỗi** để sửa bẫy số 1: NFKC đổi luôn dấu câu toàn hình `，。？！` thành dấu
   ASCII `,.?!`, làm câu tiếng Trung sai chính tả. `chuan_hoa()` chỉ chuẩn hoá đúng dải
   `U+2E80–U+2FD5`, giữ nguyên mọi ký tự khác.

3. **Nhãn các mục (`生词` `语法` `课文` `练习`) nằm ở lề trang**, nên PyMuPDF trả chúng ra lẫn giữa
   nội dung, không đúng thứ tự nhìn thấy. **Không dùng nhãn làm mốc cắt.** Vì vậy `cat_theo_bai()`
   cắt theo **trang**, còn `tach_ngu_phap()` nhận theo **khuôn nội dung**. Bản dịch tiếng Việt của
   bài khoá cũng nằm ở khung riêng nên thường ra **trước** cả câu tiếng Trung.

### Lưu ý ngoài kỹ thuật

Mỗi trang PDF đều ghi *“Bản quyền thuộc Trung tâm Ngôn ngữ Văn hoá Trung Quốc - ĐH Hà Nội. Không
sao in dưới mọi hình thức”*, trong khi README mô tả Zìkǎ là web học **miễn phí** (công khai). Đã
báo chủ dự án và làm tiếp theo giả định chủ dự án có quyền dùng nội dung này. Nếu web mở công khai
thì nên cân nhắc lại phần bài khoá và phần giải thích ngữ pháp.
