# Chép một bài Giáo trình Hán ngữ ra JSON

Sách là bản scan, chỉ đọc được qua ảnh. **Ảnh là chuẩn**: chép đúng như sách in, không suy đoán, không
tự sửa cho "hay hơn". Chỉ làm 4 phần dưới đây, phần Luyện tập khác của sách bỏ qua.

| Mục trong sách | Khoá JSON | Phần trên web |
|---|---|---|
| 生词 Từ mới, 专名 Tên riêng | `tu_moi`, `ten_rieng` | Lật thẻ |
| 课文 Bài khóa | `bai_khoa` | Đọc hội thoại |
| 语法 Ngữ pháp, 注释 Chú thích, 语音 Ngữ âm | `ngu_phap` (phân biệt bằng `nhom`) | Cấu trúc ngữ pháp |
| 写汉字 Tập viết chữ Hán (cuối phần Luyện tập) | `viet_chu` | Luyện viết |

## Cách làm

```bash
cd D:/zika-devtools/Project/zika/data/han-ngu
python xem_trang.py <quyển> <bài>        # ảnh trang: anh/q<quyển>/bai<số>/pNNN_tren.jpg, pNNN_duoi.jpg
python xem_trang.py <quyển> <bài> --cat <trang> 0 .3 1 .6   # phóng một vùng khi chữ nhỏ, nhất là pinyin
python kiem_tra.py <quyển> <bài>         # kiểm file vừa chép
```

Đọc lần lượt các ảnh, ghi `bai/q<quyển>/bai<số 2 chữ số>.json` (UTF-8). Không in lại nội dung sách vào
báo cáo. Chạy `kiem_tra.py`: `✗` bắt buộc sửa; `!` thì mở lại ảnh xem đúng chỗ đó, sách in vậy thì để
nguyên, chép nhầm thì sửa. Chép xong còn lỗi `✗` thì chưa được báo xong.

`kiem_tra.py` so pinyin với **mọi** cách đọc máy biết của từng chữ, nên pinyin sai kiểu máy sinh (学生 xuéshēng,
时候 shíhòu, 长 zhǎng trong 很长时间) vẫn lọt. Pinyin tự thêm phải theo cách bảng 生词 của sách in (thanh nhẹ,
viết hoa tên riêng); lần soát 08/11/2026 tìm ra hơn 500 chỗ lệch kiểu này.

## Phần tiếng Anh (thêm 08/11/2026)

Sách in tiếng Anh ở bảng từ và ngữ pháp; web hiện kèm để người học học cả tiếng Anh. Chép **y như in**, không tự dịch
chỗ sách không in, không có thì **không tạo khoá** (đừng ghi `""`):

| Khoá | Lấy từ |
|---|---|
| `tu_moi[].nghia_en`, `ten_rieng[].nghia_en` | cột English của bảng 生词 / 专名 |
| `ngu_phap[].ten_en` | phần tiếng Anh của tiêu đề mục (`unexpectedly; to one's surprise`, `How to say the dates`) |
| `ngu_phap[].giai_thich_en`, `luu_y_en` | đoạn giải thích / lưu ý tiếng Anh, nhiều đoạn nối bằng `\n\n` |
| `ngu_phap[].cong_thuc_en` | danh sách phần tiếng Anh của công thức, cùng thứ tự `cong_thuc` |
| `vi_du[].en`, `vi_du[].kieu_en` | bản dịch tiếng Anh của ví dụ, tiếng Anh của tiêu đề mục con (`kieu`) |
| `bai_khoa[].ten_en`, `ten_en` | tên bài khoá / tên bài tiếng Anh nếu in |

## Định dạng

```jsonc
{
  "quyen": 2, "bai": 16, "trang": [20, 33],      // "trang" lấy đúng dòng python xem_trang.py in ra
  "ten_zh": "你常去图书馆吗", "ten_pinyin": "Nǐ cháng qù túshūguǎn ma", "ten_vi": "Bạn có thường xuyên đến thư viện không",

  // 生词 theo đúng thứ tự in. "bai_khoa": số bài khóa mà bảng từ này đứng dưới; sách chỉ có một bảng thì để 0.
  "tu_moi": [{"hanzi": "常", "pinyin": "cháng", "loai": "副", "nghia": "thường, thường xuyên", "bai_khoa": 0}],
  // 专名 nếu có. "nghia" ghi như sách, ví dụ "Mary (tên người)". Không có thì [].
  "ten_rieng": [{"hanzi": "玛丽", "pinyin": "Mǎlì", "nghia": "Mary (tên người)"}],

  "bai_khoa": [{
    "so": 1,
    "ten_zh": "你常去图书馆吗", "ten_vi": "",   // tên riêng của bài khóa nếu sách in
    "loai": "hoi_thoai",                        // "hoi_thoai" hoặc "doan_van" (một người kể)
    "boi_canh_zh": "", "boi_canh_vi": "",       // câu dẫn cảnh trong ngoặc, nếu sách in
    "cau": [{
      "nguoi": "玛丽", "nguoi_vi": "Mary",      // người nói; đoạn văn thì để ""
      "zh": "你常去图书馆吗？",                  // nguyên văn, dấu câu Trung ，。？！：；“”
      "pinyin": "Nǐ cháng qù túshūguǎn ma?",
      "vi": "Bạn có thường đến thư viện không?",
      "vi_tu_dich": true                        // true nếu sách KHÔNG in bản dịch câu này và mình tự dịch
    }]
  }],

  "ngu_phap": [{
    "nhom": "ngu_phap",                         // "ngu_phap" · "chu_thich" (注释) · "ngu_am" (语音)
    "ten": "Trạng ngữ chỉ thời gian",           // tên mục như sách, giữ chữ Hán trong tên
    "mo_ta": "",
    "giai_thich": "…",                          // nguyên văn phần giải thích tiếng Việt
    "cong_thuc": ["Chủ ngữ + Trạng ngữ + Động từ"],  // khung công thức, ngăn thành phần bằng " + "
    "luu_y": "",
    "vi_du": [{"zh": "…", "pinyin": "…", "vi": "…", "kieu": "", "tu_dich": false}]
  }],

  // 写汉字: từng chữ đúng thứ tự in, kèm pinyin in trên ô. Sách không có mục này thì [].
  "viet_chu": [{"hanzi": "安", "pinyin": "ān"}],

  "loi_sach": [],   // [{"o_dau": "…", "sach_in": "…", "sua_thanh": "…", "ly_do": "…"}] khi sách in sai rõ ràng
  "ghi_chu": []
}
```

## Quy tắc

- Giữ đúng thứ tự sách: bảng từ, câu thoại, và mục trong `ngu_phap` (注释 thường đứng trước 语法, 语音 sau cùng).
- Mỗi lượt nói là một phần tử `cau`, giữ nguyên ký hiệu sách in như `……`. Người nói in "A"/"B" thì `nguoi` là "A"/"B", `nguoi_vi` để "".
- Câu dẫn cảnh nằm giữa bài khóa: thành một phần tử `cau` đúng vị trí, `nguoi` là "Bài đọc". Câu dẫn đầu bài thì để ở `boi_canh_zh`/`boi_canh_vi`.
- Từ dạng khung có dấu ba chấm thì `hanzi` giữ đúng một dấu `…` giữa các chữ (`太…了`, `除了…以外`), không xoá dấu. Chữ trong ngoặc ở bảng từ như 责（任）thì ghi đủ chữ `责任`.
- Câu sách đánh dấu * (câu sai): giữ làm ví dụ, bỏ dấu *, `kieu` là "Câu sai".
- 词语用法 (cách dùng từ, quyển 5–6) đưa vào `ngu_phap` với `nhom: "ngu_phap"`.
- Gộp mục nhỏ cùng loại: các mục cùng một đề mục sách (như 发音要领 của từng âm) phải dùng **cùng một `ten`** và đứng liền nhau, công cụ sẽ gộp thành một mục, không tách mỗi âm một mục. Một bài không nên có quá 12 mục khác tên.
- `ten` của mục ngữ pháp: chữ Hán, rồi " — ", rồi tên tiếng Việt nếu sách in cả hai. Hai mục trùng tên thì thêm chú thích ngắn trong ngoặc để phân biệt.
- Công thức in trong khung thì ghi vào `cong_thuc`, không bỏ sót. Công thức sách không in thì **không tự đặt**.
- `giai_thich` là nguyên văn đoạn tiếng Việt sách in, không tự viết thêm câu. Sách không in đoạn giải thích thì
  ghi phần tiếng Việt của tiêu đề mục (vd "Chỉ, chỉ là (phó từ)"). Ví dụ chỉ lấy đúng ví dụ sách in ở mục đó,
  không tự lấy câu bài khoá; giữ nhãn A：/B： và chú thích trong ngoặc như in.
- Bảng, danh sách sách in (一日 … 十日, 12 tháng) phải đủ từng mục, không nhảy cóc.
- Quyển 3–6 không in pinyin cho bài khóa và ví dụ: tự thêm, tự kiểm bằng `kiem_tra.py`; bản dịch tự dịch thì đánh dấu như trên. Quyển 5–6 không có 写汉字 thì `viet_chu` là `[]`.
- Từ có nghĩa rộng thì giữ nguyên toàn bộ nghĩa tiếng Việt in sau cột tiếng Anh.
- Pinyin giữ dấu thanh và cách viết liền/tách của sách. Bài khóa không in pinyin thì tự thêm theo cách viết ở bảng 生词
  của sách (bất kỳ quyển nào): thanh nhẹ như in (xuésheng, péngyou, shíhou, dìfang, dōngxi), tên riêng viết hoa
  (Běijīng, Xīngqīsān), 不 trong bổ ngữ khả năng là "bu" (tīng bu dǒng), đa âm tự theo nghĩa (很长时间 cháng, 待 dāi,
  一场雪 cháng). `kiem_tra.py` không bắt được các lỗi này (xem đầu file).
- Bản dịch: sách in thì chép nguyên văn, `vi_tu_dich: false`. Sách không in thì dịch tự nhiên, sát nghĩa, `vi_tu_dich: true`, ví dụ ngữ pháp thì `tu_dich: true`.
- Một chữ chỉ để hình minh hoạ, tranh hoặc tiêu đề trang thì không chép. Không chép phần 练习 ngoài mục 写汉字.
- Bài chỉ dạy ngữ âm (quyển 1 các bài đầu): chữ ở mục đọc nhận biết đưa vào `tu_moi` nếu sách in đủ chữ, pinyin, nghĩa; các quy tắc phát âm vào `ngu_phap` với `nhom: "ngu_am"`.
- Không chắc một chữ thì phóng to bằng `--cat`. Vẫn không đọc được thì ghi vào `ghi_chu`, không đoán.
