# Nạp giáo trình Hanu HSK 1–3 vào Zìkǎ

Đường đi: **trang sách → `bai/hsk<cấp>/baiNN.json` (chép tay từ ảnh) → `kiem_tra.py` → `sinh.py`
(dựng payload API) → `nap.py` (gọi API quản trị như nút Nhập Excel) → `doi_chieu.py`**.

Nạp qua API chứ không chạy SQL thẳng, để giữ đủ 3 lớp kiểm tra của web (`QuestionPayload::clean`,
đếm lại `cnt_*` + xoá cache, tải nét chữ) — xem `data/giao-trinh/TIEN-DO.md` Phần E.

Nội dung sách có bản quyền (Trung tâm Ngôn ngữ & Văn hoá Trung Quốc – ĐH Hà Nội), nên `bai/`,
`ra/`, `anh/` **không đưa lên git** (đã có trong `.gitignore`). Repo chỉ giữ code.

| Quyển | Số bài | Trang PDF | Lớp chữ |
|---|---|---|---|
| HSK 1 | 15 | 3–149 | chữ thật, tin được |
| HSK 2 | 10 | 4–121 | OCR, sai ~6–9% — **ảnh là chuẩn** |
| HSK 3 | 15 | 6–199 | OCR, sai ~6–9% — **ảnh là chuẩn** |

Trang mở đầu từng bài nằm ở `chung.py` (`TRANG_DAU`).

## Công cụ

```bash
cd data/nap-sach
set PYTHONUTF8=1                       # PowerShell: $env:PYTHONUTF8 = "1"
python xem_trang.py 2 5                # ảnh + lớp chữ của HSK 2 bài 5 vào anh/hsk2/bai05/
python xem_trang.py 2 5 --cat 52 0 .3 1 .6   # phóng một vùng trang 52 để đọc pinyin nhỏ
python kiem_tra.py 2 5                 # kiểm một bài · python kiem_tra.py all
python sinh.py all                     # dựng payload vào ra/
python nap.py all                      # nạp vào web local (cần MariaDB + API đang chạy)
python doi_chieu.py all                # đọc lại từ API, so với bai/*.json
```

Python: bản cài sẵn trên máy (có `pymupdf`, `openpyxl`); `pypinyin` mượn từ venv của `kokoro-tts`.

## Bản đồ mục sách → phần học (đã chốt, đừng tự đổi)

| Phần học | Lấy từ |
|---|---|
| Lật thẻ | 生词 Từ mới + 专名 Tên riêng |
| Luyện viết | Từ mới + Tên riêng, tách ra từng chữ (không theo bảng 会写字) |
| Bài tập | Câu hỏi quanh 课文 (判断对错, nghe chọn) + 练习 (选词填空, 排列成句, 判断…) + câu sinh từ từ vựng/bài khoá |
| Đọc hội thoại | Nguyên văn các 课文 (một bài nhiều bài khoá) |
| Ngữ pháp | 语法, gần như lấy nguyên |
| Kiểm tra | Sách không có → sinh từ 句式 Mẫu câu, bài khoá, từ vựng (`sinh.py`) |

Bỏ: 目标 Mục tiêu, 热身 Khởi động (chọn ảnh — web chưa có dạng ảnh), 文化 Văn hoá (đọc ở mục Giáo
trình), bài miêu tả tranh / tự đặt câu / đọc to (không chấm được).

Nguyên tắc câu hỏi: **suy ra, đừng bịa** — ưu tiên bài tập có sẵn trong sách; câu tự sinh chỉ dùng
dạng mà đáp án đúng do cấu tạo (xáo câu nguyên văn, khoét một từ, lấy bảng từ vựng); không tự dịch,
dùng đúng câu tiếng Việt sách in; nhiễu chỉ lấy trong cùng bài.

## Định dạng `bai/hsk<cấp>/baiNN.json`

Chép **đúng như sách in**: chữ Hán, pinyin (giữ dấu thanh và cách viết liền/tách của sách), tiếng
Việt (đủ dấu). Lớp chữ chỉ để gợi ý — chỗ nào lệch với ảnh thì theo ảnh. Mọi chuỗi là UTF-8; dùng
dấu câu Trung `，。？！：；“”` trong câu tiếng Trung.

```jsonc
{
  "cap": 2, "bai": 5, "trang": [49, 59],
  "ten_zh": "他打羽毛球打得很厉害", "ten_pinyin": "Tā dǎ yǔmáoqiú dǎ de hěn lìhai",
  "ten_vi": "Anh ấy chơi cầu lông rất giỏi",

  // 生词 theo đúng thứ tự in. "bai_khoa": số 课文 mà bảng từ này đứng dưới (sách chia nhiều bảng).
  "tu_moi": [
    {"hanzi": "羽毛球", "pinyin": "yǔmáoqiú", "loai": "danh từ", "nghia": "cầu lông", "bai_khoa": 1}
  ],
  // 专名 (chỉ khi bài có). "nghia" ghi như sách, ví dụ "Lý Văn (tên người)".
  "ten_rieng": [{"hanzi": "李文", "pinyin": "Lǐ Wén", "nghia": "Lý Văn"}],

  "bai_khoa": [
    {
      "so": 1,
      "loai": "hoi_thoai",                 // "hoi_thoai" · "doan_van" (một người kể, HSK 3)
      "boi_canh_zh": "在校园里，李文向白家月要手机号。",   // câu dẫn cảnh nếu sách in, không có để ""
      "boi_canh_vi": "",                   // bản dịch câu dẫn nếu sách in
      "cau": [
        {"nguoi": "李文", "nguoi_vi": "Lý Văn", "zh": "家月，你的手机号是多少？",
         "pinyin": "Jiāyuè, nǐ de shǒujī hào shì duōshao?", "vi": "Gia Nguyệt, số điện thoại của bạn là bao nhiêu?"}
      ],
      // Bài tập in ngay quanh bài khoá này (nghe rồi chọn / đúng sai). Bỏ trống nếu không có.
      "cau_hoi": [
        {"dang": "dung_sai", "cau": "李文要白家月的手机号。", "dap_an": true},
        {"dang": "chon", "hoi": "白家月的手机号是多少？", "phuong_an": ["13801234567", "13801234576", "13901234567"], "dap_an": 0}
      ]
    }
  ],

  "ngu_phap": [
    {
      "ten": "Câu hỏi dùng 吗",            // tên điểm ngữ pháp như sách (giữ chữ Hán trong tên)
      "mo_ta": "",                          // dòng phụ ngắn nếu sách có
      "giai_thich": "…",                    // nguyên văn phần giải thích tiếng Việt
      "cong_thuc": ["Chủ ngữ + Động từ + Tân ngữ + 吗？"],  // từng khung công thức, ngăn thành phần bằng " + "
      "luu_y": "",
      "vi_du": [
        {"zh": "你是老师吗？", "pinyin": "Nǐ shì lǎoshī ma?", "vi": "Bạn là giáo viên phải không?",
         "kieu": "Câu hỏi", "tu_dich": false}   // kieu: Khẳng định · Phủ định · Câu hỏi · "" ; tu_dich=true nếu sách KHÔNG in bản dịch và mình tự dịch
      ]
    }
  ],

  // 练习 — chỉ các dạng chuyển được thành câu hỏi chấm tự động.
  "luyen_tap": [
    {"dang": "chon_tu_dien", "tu": ["是", "叫", "在", "有"],
     "cau": [{"cau": "我___越南人。", "dap_an": "是", "vi": ""}]},            // đúng một chỗ trống ___
    {"dang": "sap_xep", "cau": [{"o": ["我", "是", "越南人"], "dap_an": "我是越南人。", "vi": ""}]},
    {"dang": "dung_sai", "doan": "…đoạn văn/hội thoại sách in…",
     "cau": [{"cau": "…", "dap_an": false}]},
    {"dang": "chon", "doan": "", "cau": [{"hoi": "…", "phuong_an": ["…", "…", "…"], "dap_an": 1}]},
    {"dang": "dich", "cau": [{"zh": "…", "vi": "…"}]}    // CHỈ khi sách in sẵn cả hai câu
  ],

  // 句式 MẪU CÂU ở Tổng kết: mỗi dòng một mẫu và các ví dụ (từng câu riêng).
  "mau_cau": [{"mau": "……是……", "vi_du": ["我是越南人。", "她是我妈妈。"]}],

  "nhan_biet": ["手", "机"],     // 会认字 (HSK 1, từng chữ) hoặc 会认词 (HSK 2–3, từng từ), đúng thứ tự in
  "biet_viet": ["手"],          // 会写字 nếu sách in

  // Chỗ sách in sai mà mình đã sửa khi chép. Không sửa gì thì để [].
  "loi_sach": [{"o_dau": "Tổng kết · 会认字", "sach_in": "钱", "sua_thanh": "前", "ly_do": "前 là 生词 số 14, cả trang không có 钱"}],
  "ghi_chu": [],

  // Tuỳ chọn, người soát điền: câu (chữ Hán, hoặc câu tiếng Việt / câu xét đúng sai) mà sinh.py
  // KHÔNG được dùng làm câu hỏi vì có hơn một đáp án đúng hoặc xếp được nhiều thứ tự.
  "loai_tru": ["下午你___来吗？"]
}
```

Quy ước thêm:

- `dap_an` của `dung_sai`/`chon`/`chon_tu_dien`/`sap_xep`: sách thường không in đáp án — tự giải
  dựa trên chính bài khoá/câu sách in; chỗ nào không chắc thì **bỏ câu đó** và ghi vào `ghi_chu`.
- `chon` ở `cau_hoi`/`luyen_tap`: 2–4 phương án, `dap_an` là số thứ tự (0 = A).
- `sap_xep`: 2–8 ô, ghép các ô (bỏ dấu câu) phải ra đúng `dap_an`; `dap_an` ≤ 60 ký tự.
- `dung_sai`.`doan` ≤ 400 ký tự (cắt đúng đoạn sách dùng để xét); câu đúng/sai quanh bài khoá thì
  `sinh.py` tự lấy bài khoá làm đoạn văn.
- Bài 1–3 HSK 1 dạy phát âm: không có 生词 thì để `tu_moi: []`; chữ ở mục 认读 đưa vào `tu_moi`
  (ghi `"bai_khoa": 0`) nếu sách in đủ chữ, pinyin, nghĩa.
- Sách in sai rõ ràng (chữ trong bảng không khớp từ mới, ô trống, lỗi gõ) thì chép bản đã sửa và
  ghi vào `loi_sach`. Không tự thêm dấu câu sách không in.
- HSK 3 không in pinyin và bản dịch cho 课文. Pinyin được thêm theo cách viết của bảng 生词
  (`kiem_tra.py` so từng âm tiết với máy); bản dịch tham khảo ghi `"vi_tu_dich": true` ở từng câu —
  web hiện câu ghi chú "Bản dịch tiếng Việt là bản tham khảo…" dưới bài khoá đó, và `sinh.py` không
  dùng các câu này làm đáp án được chấm (nghe chọn nghĩa, chọn câu tiếng Trung).

## Nguồn dữ liệu phụ (không có trong sách)

- Âm Hán Việt của từ, nghĩa/âm Hán Việt/cấu tạo của từng chữ ở Luyện viết: `tu-dien/` — chép từ dự
  án Vở tập viết HSK (`curated.js` soạn tay; `hanzi-1/2.js` tự sinh từ Unicode Unihan, Make Me a
  Hanzi, CVDICT © Phong Phan **CC BY-SA 4.0**, hanviet-pinyin-wordlist © Phong Phan MIT).
- Câu ví dụ của thẻ từ: lấy câu trong chính bài khoá có chứa từ đó (kèm pinyin, nghĩa sách in).

## File nghe bài khoá (HSK 1, HSK 2)

Dùng đúng bộ mp3 người dùng gửi (`Project/hsk1/`, `Project/hsk2/Bài N/`). HSK 3 chưa có file nghe nên
**không gắn gì** — trang hội thoại ẩn nút "Nghe" và bấm câu không phát gì, không thay bằng giọng máy.

1. `python nghe_gan.py 1` / `2` — giải mã mp3, nhận dạng giọng nói tiếng Trung của Windows (`nghe_chu.ps1`),
   chấm trùng chữ với từng phần của bài → `ra/nghe.json`. Lâu (vài phút một bài), nên chạy nền.
2. `python gan_nghe.py 1` / `2` — tải mp3 lên `/admin/media/audio` rồi gắn vào bài khoá.
   Gắn theo thứ tự file cố định (HSK 1: bài khoá k = file 2k−1, file chẵn là đọc từ mới;
   HSK 2: bài khoá k = file k, file 5 là bài tập), chữ nhận dạng được chỉ để kiểm chéo (≥ 0.2).
   Chỗ máy nghe kém đã xét tay ghi ở `TAY` trong `gan_nghe.py`.

Kết quả ở máy local 02/11/2026: HSK 1 gắn 41/44 bài khoá, HSK 2 40/40. Ba bài khoá còn trống là
HSK 1 bài 2 khoá 1–3: bộ nghe HSK 1 là bản FLTRP, bài 2 chỉ có 3 đoạn và tên nhân vật khác sách Hanu,
gắn vào sẽ nghe khác chữ đang đọc. File đọc bảng từ mới chưa gắn (web chỉ có ô nghe cho từng từ).

## Ví dụ lật thẻ, bộ thủ, giọng đọc mẫu

Các bước và lệnh nằm ở bảng "Quy trình nạp và kiểm tra nội dung" trong
[NHAT-KY-CAP-NHAT.md](../../NHAT-KY-CAP-NHAT.md). Tóm tắt công cụ:

| File | Việc |
|---|---|
| `vi-du/hsk*.json` | câu ví dụ lật thẻ tự soạn (2 câu/từ) — `sinh.py` ưu tiên dùng |
| `kiem_vi_du.py`, `nap_vi_du.py` | kiểm (độ dài theo cấp, chữ ngoài cấp, pinyin) và nạp ví dụ |
| `phan_tich_chu.py` | bộ thủ, giải thích cấu tạo, chữ cùng bộ cho màn Luyện viết |
| `viec_doc.py` → `doc_edge.py` → `tai_doc.py` → `gan_thoai.py` | giọng đọc mẫu Microsoft (edge-tts): lập danh sách + phân vai, sinh và tự kiểm từng file (FunASR, độ to, tốc độ), tải lên, gắn câu thoại + bài khoá — xem [HUONG-DAN-GIONG-DOC.md](HUONG-DAN-GIONG-DOC.md) |
| `kiem_bang_that.py`, `bang_lech_sach.json` | băng thu thật nào đọc lệch sách Hanu (thay bằng bản ghép) |
| `dong_goi_giong.py` | đóng gói web + mp3 + SQL nhỏ để đưa lên server |
| `quet.py` | quét lỗi toàn bộ (âm thanh, nét chữ, bộ thủ, ví dụ) → cuối NHAT-KY-CAP-NHAT.md |

