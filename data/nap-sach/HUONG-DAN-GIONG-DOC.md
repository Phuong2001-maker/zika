# Giọng đọc mẫu — giọng Microsoft (edge-tts), tự kiểm từng file

Mọi file đọc mẫu (từ lật thẻ, câu ví dụ, chữ luyện viết, ví dụ ngữ pháp, câu hỏi nghe, câu thoại,
bài khoá ghép) sinh bằng giọng Microsoft qua `edge-tts`, cùng một chuẩn: không kéo dài âm bằng phần
mềm, cùng độ to -16 LUFS (đỉnh ≤ -1 dBFS, chặn đỉnh cục bộ chứ không hạ nhỏ cả file). Trình duyệt chỉ
phát mp3 có sẵn, không gọi dịch vụ lúc học. Bộ Kokoro cũ đã bỏ (03/11/2026).

| Dùng cho | Giọng | Thư mục |
|---|---|---|
| Giọng chung theo Cài đặt (từ, ví dụ, chữ, ngữ pháp, câu hỏi nghe) | nữ Xiaoxiao / nam Yunxi | `tts/nu`, `tts/nam` |
| Lật thẻ (cố định giọng nam, khoá cả pinyin) | nam Yunxi | `tts/nam` |
| Câu thoại — giọng riêng từng nhân vật (bảng `VAI` trong `viec_doc.py`) | xiaoxiao, xiaoyi, xiaoxiao2, yunxi, yunjian, yunyang, yunxia | `tts/<tên giọng>` |

Phân vai: xiaoxiao nữ chính/người lớn, xiaoyi nữ thứ hai/bé gái, xiaoxiao2 (Xiaoxiao hạ cao độ
`-14Hz`, chỉ có 2 giọng nữ) cho người nữ lớn tuổi khi trùng, yunxi nam trẻ, yunjian nam trưởng thành,
yunyang thầy/người lớn tuổi/người có chức vụ, yunxia bé trai. Phân **theo cả bài** (`giong_bai`): một
người giữ một giọng suốt bài; hai người cùng nói trong một bài khoá không trùng giọng (người sau lấy
giọng kế tiếp cùng giới, `THAY`); lời nói chung "小李、小陈" lấy giọng người đầu. Nhân vật mới chưa có
trong bảng thì đoán theo tên/vai.

Tên file: `sha1(chữ.trim())`, từ lật thẻ thêm `"\n" + pinyin_bỏ_khoảng_trắng_viết_thường` (NFC) để
`还 hái/huán`, `头 tou/tóu` không dùng nhầm file. Web tự tính đường dẫn trong `speech.ts`; câu thoại
và bài khoá thì đường dẫn lưu trong database.

## Chạy

1. Bật môi trường local: bấm đúp `dev\CHAY-TAT-CA.cmd` (MariaDB + API + web).
2. Lần đầu: `python -m pip install edge-tts av pyloudnorm praat-parselmouth funasr modelscope pypinyin scipy`
   (FunASR tự tải mô hình nhận dạng ~1 GB vào `Project/kiem-giong-doc/models/modelscope` lần đầu).
3. Một lệnh:

   ```powershell
   cd D:\zika-devtools\Project\zika\data\nap-sach
   powershell -ExecutionPolicy Bypass -File .\sinh-giong-doc.ps1
   ```

   Làm 5 bước: lập danh sách (`viec_doc.py tu web`) → sinh + kiểm (`doc_edge.py`) → tải lên web local
   (`tai_doc.py`) → gắn câu thoại và bài khoá (`gan_thoai.py`) → quét qua web (`quet.py`).
   Dừng giữa chừng thì chạy lại y lệnh cũ: file đã sinh và đã kiểm được bỏ qua.
4. Đổi xong thì tăng `TTS_VERSION` trong `web/src/lib/learn/speech.ts`, build web, rồi
   `python dong_goi_giong.py` → `release/cap-nhat-giong-moi-<ngày giờ>.zip` (xem `HUONG-DAN-SERVER.md`).

## Tự kiểm và tự sinh lại (`doc_edge.py`)

Chạy theo đợt 200 file: sinh → kiểm ngay → file lỗi sinh lại tối đa 3 lần → ghi `ra/doc_edge_kq.json`.

| Kiểm | Ngưỡng | Khi không đạt |
|---|---|---|
| Giải mã, câm | có tiếng | sinh lại |
| Độ to | -19 … -14,5 LUFS | sinh lại |
| Từ đơn: thời lượng | 0,15–0,8 giây/âm tiết (儿 nhi hoá không tính, thanh nhẹ tính nửa) | đổi tốc độ, sinh lại |
| Câu: tốc độ | 1,8–5,5 âm tiết/giây | chậm/nhanh hơn 10%, sinh lại |
| Khoảng lặng giữa chừng | ≤ 1,2 giây | sinh lại |
| FunASR nghe lại, so pinyin không dấu | câu ≤ 10 âm tiết: khớp hết; ≤ 40: lệch ≤ 1; bài đọc dài: lệch ≤ 15% | câu: ép chữ đồng âm ở chỗ sai (chỉ chữ đa âm), rồi đổi tốc độ |
| Từ một chữ thanh 1/2/4: đường cao độ (60% giữa) | không trái hẳn thanh | không sinh lại — vào danh sách người nghe |

Trạng thái mỗi file: `dat` · `nghi` (chữ/từ đứng riêng mà máy nghe nhầm hoặc nghi thanh — nhận dạng
thiếu ngữ cảnh, sinh lại không đổi được kết quả; cần người nghe) · `loi` (còn lỗi sau 4 lần, không tải lên).
Khi so âm (máy nhận dạng hay lẫn, giọng đọc luôn đúng): bỏ 儿 nhi hoá, trợ từ cuối câu (啊 呀 呢 吧);
coi 您 = 你, 的/地/得 trợ từ = de, 外边 = 外面. Hết 4 lần mà chỉ còn máy nghe chưa khớp (không lỗi kỹ
thuật) thì vẫn dùng, xếp `nghi` để người nghe xác nhận.

`python doc_edge.py --xet-lai` sinh lại từ đầu mọi file chưa `dat` và file mà chữ gửi đi đọc đã đổi.
`python doc_edge.py --kiem-lai` kiểm lại mọi file đã có (đổi ngưỡng kiểm thì chạy cái này).
`python doc_edge.py --kiem-chua-dat` chỉ kiểm lại file chưa đạt (đổi luật so).
`python doc_cham_lai.py 5.8` sinh lại chậm hơn 15% những câu đọc nhanh hơn 5,8 âm tiết/giây (phần có tiếng).
`python xuat_nghe_tay.py` xuất `ra/nghe-tay-<ngày>.zip` cho người biết tiếng Trung nghe xác nhận.
`python test_doc_edge.py` kiểm các hàm chuẩn bị chữ và đo.

Tốc độ: đo 03/11/2026 bỏ khoảng nghỉ, băng giáo trình thật trung vị 6,22 âm tiết/giây; giọng mới
nữ 5,39, nam 5,74 — không nhanh hơn băng thật. `quet.py` báo câu nhanh hơn 6,5.

## Đọc đúng

- **Chữ đa âm**: chỗ cách đọc mặc định (pypinyin theo ngữ cảnh) khác pinyin sách thì gửi đi đọc một
  chữ đồng âm đúng thanh — chỉ chọn trong 3.755 chữ thông dụng (GB2312 cấp 1), ưu tiên chữ có trong
  giáo trình (这只 → 这织, 我明天还你钱 → 环). Chữ trên web không đổi. Từ đứng riêng cố định trong
  `TU_RIENG` (还 hái/huán, 长 cháng, 只 zhī, 地 de, 便 pián…). Chỗ không có chữ đồng âm (得 děi,
  不得了 liǎo) nằm trong `NGHE_TAY`, để người nghe kiểm.
- **Biến điệu** 3-3, 一, 不 và thanh nhẹ để giọng đọc tự xử lý như người thật.
- **Số**: số điện thoại (≥ 7 chữ số) đọc từng số, 1 = yāo; ngày, giờ, giá giọng đọc tự đọc đúng.
- **Chữ cái**: "A：… B：…" bỏ nhãn, B đọc giọng khác giới; "HSK" đọc 汉语水平考试.
- **Hội thoại đề HSK 3.0** "男：… 女：… 问：…" (10/11): bỏ nhãn, lời 男 luôn giọng nam, lời 女 luôn giọng nữ; câu hỏi
  "问：…" vẫn đọc chữ 问 như đề thật, bằng giọng của file (giọng người học chọn). Nhãn chỉ tính khi đứng đầu câu, nên
  "我想问：" trong lời kể không bị cắt. Máy kiểm so với chữ đã bỏ nhãn (`bo_nhan`). File có nhãn mang thêm dấu "nam-nu"
  trong chữ ký (`chu_ky`), nên chạy `doc_edge.py` bình thường là tự sinh lại đúng những file đó.
- **Ngoặc**: phần trong ngoặc sau câu (`（日）`, lời giải thích `（妈妈还在看电视。）`) không đọc; câu dẫn
  cả câu trong ngoặc thì đọc phần trong.
- **Hai cách nói "A/B"** (星期日/星期天, 在/正在): đọc cả hai, nghỉ ngắn ở giữa; riêng "日/号" chỉ đọc 日.
- **Ép cách đọc** chỉ khi ghép chắc được pinyin vào từng chữ: không ghép khi có chữ số, và mỗi âm tiết
  phải là một cách đọc của đúng chữ đó (tránh lệch cả câu khi đếm số + nhi hoá bù nhau).
- **Khoảng nghỉ** giữa câu dài hơn 0,6 giây rút còn 0,45 giây.

## Bài khoá

`gan_thoai.py`: khoá có băng thu thật (HSK 1–2) khớp sách thì giữ băng, cân về -16 LUFS; khoá băng
đọc lệch sách (`bang_lech_sach.json`, lập bằng `kiem_bang_that.py` rồi xét tay) hoặc chưa có băng thì
ghép các câu thoại, nghỉ 0,6 giây giữa hai lượt nói.

## Lỗi hay gặp

| Triệu chứng | Cách xử lý |
|---|---|
| `edge-tts lỗi … thử lại sau …s` trong `ra/doc_edge.log` | mạng chập chờn hoặc dịch vụ chặn tạm; script tự chờ và thử lại |
| `HTTP 422 Giọng` khi tải lên | API cũ chưa nhận tên giọng nhân vật; cập nhật `MiscController::tts` |
| Quét báo "khác bản đã kiểm" | file trên web không phải bản `doc_edge.py` đã kiểm — chạy lại `tai_doc.py` |
| Quét báo "tốc độ lạ" | nghe thử file đó; chạy `doc_edge.py --xet-lai` |
