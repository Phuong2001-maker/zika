# Giọng đọc mẫu — Yunxi cho từ lật thẻ, Kokoro cho câu và các phần khác

Từ ở lật thẻ dùng **giọng nam Yunxi**, tạo sẵn qua dịch vụ đọc của Microsoft Edge.
`doc_tu_nam.py` cần mạng khi tạo file mới và `python -m pip install edge-tts av`.
Mỗi lượt tạo 10 từ, tách đúng mốc WordBoundary, kiểm nhãn khớp trước khi lưu; lưu cả file gốc và mốc
ở `ra/yunxi-batch/`. Sau đó kéo thời lượng bằng `atempo` giữ cao độ: đích 0,48 giây/chữ cho từ 1–2 chữ,
0,43 giây/chữ cho từ dài hơn. Trình duyệt chỉ phát mp3 có sẵn, không gọi dịch vụ lúc học.

Lật thẻ theo bài và tổng hợp đều chọn giọng nam; câu ví dụ cũng chọn nam. Từ có tên file
`sha1(chữ.trim() + "\n" + pinyin_bỏ_khoảng_trắng_viết_thường)`, pinyin chuẩn hoá Unicode NFC.
Nhờ đó `还 hái/huán`, `头 tou/tóu`, `道 dao/dào` không dùng nhầm file. Các trường hợp cần chọn âm
khác mặc định của dịch vụ được ghi trong `chu_doc()`; chữ/nhãn trên thẻ giữ nguyên.

Các file không chỉ định pinyin vẫn dùng `sha1(chữ.trim())`, giữ tương thích câu ví dụ, luyện viết,
hội thoại, ngữ pháp, câu hỏi nghe bằng **Kokoro** chạy trên CPU. File nằm ở
`uploads/tts/<giọng>/<2 ký tự>/<hash>.mp3`; web tự tính tên trong `speech.ts`.

## Chạy

1. Bật môi trường local: bấm đúp `dev\CHAY-TAT-CA.cmd` (MariaDB + API + web).
2. Mở PowerShell:

   ```powershell
   cd D:\zika-devtools\Project\zika\data\nap-sach
   powershell -ExecutionPolicy Bypass -File .\sinh-giong-doc.ps1
   ```

3. Chờ. Bộ hiện tại 10.274 file; lần sau chỉ sinh file mới hoặc file đổi tốc độ/cách đọc/bộ sinh.
   Máy chạy 100% CPU trong lúc đó là bình thường. Dừng giữa chừng thì chạy lại y lệnh cũ.
4. Đưa lên server: `python xuat_sql.py` → làm theo `HUONG-DAN-SERVER.md`. Nếu chỉ đổi âm thanh
   (không đổi nội dung chữ) thì chỉ cần chép `dong-goi-server/uploads/tts` đè lên server, không cần SQL.

Script làm 5 bước: lập danh sách (`viec_doc.py tu web`) → sinh (Kokoro `doc_mau.py` + Yunxi `doc_tu_nam.py`) →
tải lên web local (`tai_doc.py`) → gắn câu thoại + ghép bài khoá (`gan_thoai.py`) → quét lỗi (`quet.py`).

## Khi nào phải chạy

- Thêm/sửa từ, câu ví dụ, câu thoại, ngữ pháp, câu hỏi nghe (ở trang quản trị hoặc qua `nap.py`).
- Đổi tốc độ đọc (xem dưới).

## Chỉnh tốc độ đọc

Giọng nam từ lật thẻ: đích thời lượng nằm ở `doc_tu_nam.py::doc_cham()`; khi đổi cách xử lý,
tăng `revision` của mục Yunxi trong `tu_theo_pinyin()` để sinh lại từ bản gốc đã lưu.

Tốc độ **Kokoro** trong `viec_doc.py`:

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
