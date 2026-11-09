# Đề Thi thử HSK 3.0 — soạn, kiểm, nạp

Đề do Zìkǎ tự soạn (10/11/2026), **không chép** đề của trang luyện thi nào. Chỉ mượn **cấu trúc** đề HSK 3.0
(4 phần, số câu, thời gian). Câu hỏi dùng từ vựng đúng cấp HSK 3.0, giải thích bằng tiếng Việt.

```
data/hsk30/
  README.md        đặc tả này (người soạn đề và người soát đọc trước)
  kiem_de.py       kiểm tự động một hoặc nhiều file đề — phải sạch lỗi mới được nạp
  nap_de.py        nạp đề vào web qua API quản trị (giữ đủ lớp kiểm của server)
  hinh/ve.py       vẽ kho hình SVG → web/static/hinh-hsk/
  de/hsk<N>-de<K>.json   mỗi file một đề
```

Chạy: `python data/hsk30/kiem_de.py data/hsk30/de/*.json` rồi `python data/hsk30/nap_de.py data/hsk30/de/*.json`.
Đề nạp vào luôn ở trạng thái **Nháp**; admin soát rồi bấm Công khai ở Quản trị → Đề HSK 3.0.
Nạp lại cùng tên đề thì đề cũ (cùng cấp, cùng tên) được thay câu, không tạo đề trùng.

## Cấu trúc đề theo cấp (bắt buộc đúng số câu từng phần)

| Cấp | Thời gian | Nghe | Đọc | Viết | Nói | Tổng |
|---|---|---|---|---|---|---|
| 1 | 32 phút (1920 giây) | 20 | 20 | 0 | 0 | 40 |
| 2 | 52 phút (3120) | 25 | 25 | 10 | 0 | 60 |
| 3 | 73 phút (4380) | 20 | 20 | 15 | 15 | 70 |
| 4 | 75 phút (4500) | 25 | 25 | 15 | 5 | 70 |
| 5 | 100 phút (6000) | 25 | 27 | 15 | 5 | 72 |
| 6 | 115 phút (6900) | 30 | 32 | 15 | 5 | 82 |

Thứ tự câu trong file: hết phần Nghe → Đọc → Viết → Nói (màn làm bài chia nhóm theo thứ tự này).

## Dạng câu được dùng ở từng phần

| Phần (`section`) | Dạng (`type`) |
|---|---|
| `listening` | `listen` (nghe chọn đáp án), `pic` có `audioText` (nghe chọn tranh) |
| `reading` | `pic` có `ask` (đọc chọn tranh), `blank`, `pick`, `tf`, `match` |
| `writing` | `order` (sắp xếp thành câu), `toZh` (chọn câu tiếng Trung đúng nghĩa), `blank` |
| `speaking` | `speak` |

Gợi ý theo cấp: HSK 1–2 dùng nhiều `pic` (khoảng 1/3 phần nghe và vài câu phần đọc). HSK 3 có thể có 1–2 câu
`speak` kiểu `picture`. HSK 4–6 không cần tranh. Độ dài và độ khó tăng dần theo cấp; HSK 5–6 dùng đoạn văn dài
gần giới hạn ký tự.

## Định dạng file

```json
{
  "level": 1,
  "title": "HSK 1 · Đề 1",
  "durationSec": 1920,
  "questions": [
    { "section": "listening", "type": "pic", "payload": { "audioText": "我喝茶。", "options": ["cha", "ca-phe", "sua"], "answer": 0 }, "explain": "喝茶 = uống trà." }
  ]
}
```

Mỗi câu: `section`, `type`, `payload`, `explain` (tiếng Việt, bắt buộc với mọi câu trừ `speak`, ngắn gọn: vì sao đúng,
nghĩa từ khoá). `answer` của trắc nghiệm là **chỉ số** (0 = phương án đầu). Thứ tự phương án không quan trọng —
web tự xáo khi làm bài — nhưng **đừng để đáp án luôn ở cùng một vị trí**.

### Payload từng dạng (giới hạn giống kiểm tra ở server)

- `listen`: `audioText` (chữ Hán, 1–200 ký tự, web đọc bằng giọng máy, người học không thấy chữ), `options` 2–4
  phương án **khác nhau** (chữ Hán; riêng câu nghe-xem-tranh đúng/sai dùng `["Đúng", "Sai"]`), `answer`.
  Có thể kèm `imageUrl` (`/hinh-hsk/<mã>.svg`) cho câu "nghe rồi xem tranh có đúng không".
- `pic`: `audioText` (phần nghe) **hoặc** `ask` (phần đọc, chữ Hán), `options` 2–4 **mã hình** trong kho, `answer`.
- `blank`: `sentence` có đúng một `___`, tối đa 255 ký tự; `vi` nghĩa câu; `showMeaning`: false;
  `options` 2–4 chữ Hán; `answer`.
- `pick`: `ask` (đoạn/câu hỏi chữ Hán, tối đa 200 ký tự), `options` 2–4, `answer`.
- `tf`: `passage` (1–400 ký tự), `statement` (1–200), `answer` true/false.
- `match`: `pairs` 2–6 cặp `[chữ Hán ≤32 ký tự, nghĩa tiếng Việt]`, hai cột không trùng.
- `order`: `answer` câu đúng (≤60 ký tự), `tiles` 2–8 ô (mỗi ô ≤20), ghép các ô **đúng thứ tự** phải ra `answer`
  (bỏ dấu câu, khoảng trắng); `vi` nghĩa câu.
- `toZh`: `vi` câu tiếng Việt (≤200), `options` 2–4 câu tiếng Trung, `answer`.
- `speak`: `task` một trong `repeat` (nghe rồi nhắc lại — bắt buộc `audioText`), `read` (đọc to — bắt buộc `prompt`),
  `answer` (trả lời câu hỏi — `audioText` và/hoặc `prompt`), `topic` (nói theo chủ đề — bắt buộc `prompt`),
  `picture` (nhìn tranh nói — bắt buộc `imageUrl`); `prompt`/`audioText` ≤300; `vi` gợi ý tiếng Việt;
  `prepSec` 0–300, `answerSec` 5–300; `sample` bài nói mẫu (bắt buộc), `sampleVi` nghĩa bài mẫu.

## Kho hình (mã dùng trong `pic` và `imageUrl`)

Danh sách đầy đủ ở `web/src/lib/learn/thi-hsk/hinh.ts`: đồ ăn uống (tao, cha, ca-phe, com, mi, nuoc, sua, trung, ca,
dua-hau, banh-ngot), đồ vật (sach, but, ban, ghe, dien-thoai, may-tinh, ti-vi, o, coc, ao, giay, mu, tui, ve, tien),
xe (xe-buyt, tau-hoa, may-bay, taxi, xe-dap), nơi (truong-hoc, benh-vien, cua-hang, nha-hang, nha, san-bay),
thời tiết (nang, mua, tuyet, am-u, gio), con vật (meo, cho, chim), người (bac-si, giao-vien, hoc-sinh, em-be),
hoạt động (an-com, uong-tra, doc-sach, xem-tivi, ngu, goi-dien, viet-chu, chay-bo, boi, da-bong, hat, nau-an,
mua-do, lam-viec, nghe-nhac, chup-anh, di-bo, lai-xe, om, cuoi), giờ (gio-7, gio-8, gio-9-30, gio-12, gio-3, gio-6-30).
Chỉ dùng mã có trong danh sách; câu chọn tranh phải phân biệt được bằng tranh (đừng cho hai tranh cùng nghĩa).

## Chất lượng (người soát kiểm theo đây)

1. Đáp án đúng **duy nhất**, các phương án sai hợp lý nhưng chắc chắn sai.
2. Tiếng Trung tự nhiên, đúng ngữ pháp, dấu câu Trung (，。？！).
3. Từ vựng đúng cấp HSK 3.0 (cấp 1 rất cơ bản; không dùng từ của cấp cao hơn trong câu hỏi).
4. Ba đề cùng cấp không trùng câu; mỗi đề phủ nhiều chủ đề (gia đình, ăn uống, mua sắm, thời gian, thời tiết,
   trường học, công việc, du lịch, sức khoẻ…).
5. Giải thích tiếng Việt đúng, ngắn, có nghĩa các từ khoá.
