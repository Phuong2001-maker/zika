# Giọng đọc mẫu Kokoro — tự sinh trên máy

Mọi chỗ web đọc chữ Hán (lật thẻ, câu ví dụ, luyện viết, hội thoại, ngữ pháp, câu hỏi nghe) phát file
mp3 sinh sẵn bằng **Kokoro** — phần mềm đọc tiếng Trung chạy ngay trên máy (CPU), không gọi dịch vụ
nào, không tốn tiền/token. File nằm ở `uploads/tts/<giọng>/<2 ký tự>/<sha1 câu chữ>.mp3`; web tự tính
tên file từ chữ cần đọc (`web/src/lib/learn/speech.ts`), chữ nào chưa có file thì rơi về giọng trình duyệt.

## Chạy

1. Bật môi trường local: bấm đúp `dev\CHAY-TAT-CA.cmd` (MariaDB + API + web).
2. Mở PowerShell:

   ```powershell
   cd D:\zika-devtools\Project\zika\data\nap-sach
   powershell -ExecutionPolicy Bypass -File .\sinh-giong-doc.ps1
   ```

3. Chờ. Lần đầu (~8.500 file) khoảng 1,5 giờ; lần sau chỉ sinh file mới hoặc file đổi tốc độ.
   Máy chạy 100% CPU trong lúc đó là bình thường. Dừng giữa chừng thì chạy lại y lệnh cũ.
4. Đưa lên server: `python xuat_sql.py` → làm theo `HUONG-DAN-SERVER.md`. Nếu chỉ đổi âm thanh
   (không đổi nội dung chữ) thì chỉ cần chép `dong-goi-server/uploads/tts` đè lên server, không cần SQL.

Script làm 5 bước: lập danh sách (`viec_doc.py tu web`) → sinh (`doc_mau.py`, chạy song song) →
tải lên web local (`tai_doc.py`) → gắn câu thoại + ghép bài khoá (`gan_thoai.py`) → quét lỗi (`quet.py`).

## Khi nào phải chạy

- Thêm/sửa từ, câu ví dụ, câu thoại, ngữ pháp, câu hỏi nghe (ở trang quản trị hoặc qua `nap.py`).
- Đổi tốc độ đọc (xem dưới).

## Chỉnh tốc độ đọc

Trong `viec_doc.py`:

| Hằng | Dùng cho | Hiện tại | Đo được |
|---|---|---|---|
| `TOC_TU` | từ/chữ 1–2 chữ | 0,58 | trung vị 0,585 (1 chữ), 0,454 (2 chữ) giây/âm tiết |
| `TOC_TU_DAI` | từ 3–4 chữ | 0,52 | trung vị 0,519 (3 chữ), 0,619 (4 chữ) giây/âm tiết |
| `TOC_CAU` | câu ví dụ, câu thoại, ngữ pháp | 1,0 | trung vị ~2,9 âm tiết/giây |

Đo lại ngày 02/11/2026 trên đủ 2.648 file từ/chữ, cả giọng nữ và nam: 1.394 file 1 chữ,
1.084 file 2 chữ, 138 file 3 chữ, 32 file 4 chữ. Lấy đoạn từ mẫu đầu đến mẫu cuối có
`abs(a) > 0.02`, tính thời lượng đoạn đó rồi chia số chữ Hán; giữ các khoảng nghỉ giữa âm tiết.
Không cộng riêng số mẫu vượt ngưỡng vì cách đó loại cả phần âm nhỏ bên trong lời nói.
Chi tiết lần đo nằm ở `ra/do_toc_do.json` (file sinh tại máy, không đưa lên git).

Kokoro: 1,0 là tốc độ nói thường, số nhỏ hơn là chậm hơn. Sửa số rồi chạy lại script — bước lập danh
sách tự xoá đúng những file có tốc độ khác để sinh lại. Câu riêng lẻ cần tốc độ khác (vd câu thoại dài
đọc nhanh quá) ghi vào `ra/toc_rieng.json` dạng `{"ra/tts/nu/ab/abc….mp3": 0.85}`.

**Sau khi sinh lại file đã có**, tăng `TTS_VERSION` trong `web/src/lib/learn/speech.ts` (1 → 2 → 3…) rồi
build + đưa code lên: trình duyệt học viên giữ file cũ trong cache theo đường dẫn, đổi số thì tải bản mới.

## Đọc đúng

- **Chữ đa âm** (还 hái/huán, 得 de/děi…): Kokoro tự đoán bằng pypinyin nên hay sai; `doc_mau.py` ép
  đọc đúng pinyin của sách cho từng chữ, rồi mới để Kokoro biến điệu (3-3, 一, 不).
- **Số**: số điện thoại đọc từng số (1 = yāo); năm, giá, giờ Kokoro tự đọc đúng.
- **Chữ cái**: giọng tiếng Trung không đọc được chữ Latin — "A：… B：…" bỏ nhãn, A/B hai giọng;
  "HSK" đọc 汉语水平考试. Gặp chữ Latin khác thì thêm vào `doc_so()` trong `doc_mau.py`.
- **Câu thoại**: giọng theo nhân vật (bảng `NU`/`NAM`/`CHINH` trong `viec_doc.py`), nhân vật mới
  chưa có trong bảng thì đoán theo tên/vai, không đoán được thì xen kẽ nữ/nam.

## Lỗi hay gặp

| Triệu chứng | Cách xử lý |
|---|---|
| `uv trampoline failed` | đừng chạy `.venv\Scripts\python.exe` của kokoro-tts — script đã dùng Python đi kèm |
| `No module named numpy._core…` | đang để `PYTHONPATH` trỏ vào Kokoro khi chạy Python thường — mở cửa sổ mới |
| `HTTP 415` khi tải lên | API cũ chưa có `Upload::isMp3`; cập nhật code API |
| Trình duyệt bị đăng xuất khi đang chạy | bình thường: script đăng nhập admin nhiều lần, đăng nhập lại là được |
| Quét báo "tốc độ lạ" | nghe thử file đó; nhanh quá thì thêm tốc độ riêng vào `ra/toc_rieng.json` |
