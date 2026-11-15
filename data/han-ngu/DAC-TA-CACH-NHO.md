# Đặc tả soạn "Cách nhớ" + "Từ ghép" cho màn Luyện viết (Hán ngữ, 1.381 chữ)

Giao diện đã chốt 15/11/2026: ảnh `ra/mau-luyen-viet.png` (今) và `ra/mau-luyen-viet-2.png` (校). Kế hoạch:
`ra/KE-HOACH-BAI-TAP-LUYEN-VIET.md` mục 2.

- Đầu vào: `ra/cach-nho-vao/q{n}.json` (sinh bằng `python cach_nho_vao.py`).
- Đầu ra: `cach-nho/q{n}.json`, **cùng thứ tự** với đầu vào, mỗi chữ một object:

```json
{
  "hanzi": "今",
  "phan": [
    {"mau": "a", "vi_tri": "trên", "chu": "人", "ten": "Bộ Nhân", "nghia": "người"},
    {"mau": "b", "vi_tri": "dưới", "chu": "丶㇇", "ten": "một chấm + một nét gập", "nghia": "trông như cây kim đồng hồ"}
  ],
  "lien_tuong": "Âm Hán Việt là KIM → nghĩ đến cây kim đồng hồ. Người 人 đứng nhìn cây kim 丶㇇ chỉ giờ: giờ kim đang chỉ chính là bây giờ → “nay, bây giờ”.",
  "tu_ghep": [
    {"hanzi": "今天", "pinyin": "jīntiān", "nghia": "Hôm nay"},
    {"hanzi": "今年", "pinyin": "jīnnián", "nghia": "Năm nay"}
  ]
}
```

## `phan`: các phần của chữ (thẻ màu trên màn)
Màn tô chữ hai màu theo dữ liệu nét: nét trong `net_bo` (nét của bộ thủ) màu son = phần **a**, các nét còn lại màu xanh = phần **b**.
Thẻ phải khớp đúng màu đó:
- `net_bo` rỗng hoặc `net_bo_phu_ca_chu` = true → **một phần** `mau: "a"`, `vi_tri: "cả chữ"`. `ten` là "Chữ tượng hình", "Chữ chỉ sự"…
  hoặc tên bộ nếu chính chữ là một bộ ("Bộ Nhân"); `nghia` tả ngắn hình/nghĩa ("hình người đứng nghiêng").
- Còn lại → **đúng hai phần**: `a` = bộ thủ (đúng các nét `net_bo`), `b` = mọi nét còn lại gộp làm một.
  - `vi_tri` theo IDS: ⿰ trái/phải, ⿱ trên/dưới, ⿲ trái/giữa/phải gộp, ⿳ trên/giữa/dưới gộp, ⿴ ngoài/trong, ⿵ bao trên → ngoài/trong,
    ⿶ ⿷ ⿸ ⿹ ⿺ tương tự (ghi "bao ngoài"/"bên trong" hay "trên trái"… cho dễ hiểu), ⿻ "đan xen". Bộ nằm ở đâu thì ghi đúng chỗ đó.
  - Phần b là một chữ/bộ phận có tên → `chu` là chữ đó, `ten` kiểu "chữ Giao (jiāo)" hoặc "Bộ Khẩu"; chữ hình thanh (`loai` = s) mà phần b
    gợi âm thì thêm vào `nghia` "· gợi âm đọc".
  - Phần b chỉ là nét lẻ → `chu` viết bằng ký tự nét (丶 丿 一 乛 ㇇ 亅…), `ten` tả hình dáng ("một chấm + một nét gập").
- Tên bộ dùng tên Hán Việt quen: 亻 "Bộ Nhân đứng", 氵 "Bộ Thuỷ (ba chấm thuỷ)", 扌 "Bộ Thủ (tay)", 讠 "Bộ Ngôn", 艹 "Bộ Thảo",
  忄 "Bộ Tâm đứng", 纟 "Bộ Mịch", 钅 "Bộ Kim", 饣 "Bộ Thực", 辶 "Bộ Sước", 阝 trái "Bộ Phụ" / phải "Bộ Ấp", 口 "Bộ Khẩu"…
- **Phải đúng sự thật**: thành phần lấy theo `ids`, `thanh_phan_soan_tay`, `bo`; nghĩa bộ/chữ thành phần là nghĩa thật. Không bịa.

## `lien_tuong`: câu liên tưởng
- 1–2 câu, ≤ 200 ký tự, tiếng Việt đơn giản, dễ nhớ. Ghép ý các phần thành nghĩa chữ; gắn âm Hán Việt khi tự nhiên (今 KIM → cây kim).
- Chữ thành phần nhắc trong câu thì viết đúng ký tự như trong `phan[].chu` (màn sẽ tô màu chúng).
- Là mẹo nhớ, **không** nói thành nguồn gốc chữ ("chữ này vốn là…") trừ khi đúng là chữ tượng hình/chỉ sự được biết rõ.
- `phan_tich_cu` có câu "Mẹo nhớ" soạn tay hay thì dùng lại/viết gọn lại. Bỏ các câu khuôn sai kiểu "vẽ hình ảnh phía nam".

## `tu_ghep`: 3–5 từ
- Ưu tiên `tu_ung_vien` theo thứ tự (Hán ngữ đã học trước), giữ đúng chữ và pinyin; bỏ tên riêng, bỏ từ trùng nghĩa.
- `nghia`: viết hoa chữ cái đầu, gọn ≤ 40 ký tự, chọn nghĩa chính của từ.
- Ứng viên dưới 3 từ thì thêm từ thông dụng (ưu tiên HSK 1–4) với pinyin dấu thanh và nghĩa **chính xác**, thêm `"them": true`.
- Từ phải chứa chữ đang học, không lấy chính chữ đó đứng một mình.

## Tự kiểm trước khi xong
`python kiem_cach_nho.py q{n}` (số phần khớp `net_bo`, đủ trường, độ dài, từ ghép chứa chữ, pinyin có dấu).
