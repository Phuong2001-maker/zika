# Đặc tả chép đề mô phỏng HSK 3.0 của sách vào Thi thử (15/11/2026)

Người dùng yêu cầu thêm 4 đề: `D:\zika-devtools\Project\Thi hsk3.0\HSK1\` và `\HSK2\`, mỗi cấp 2 đề (试卷1, 试卷2).
Mọi file là PDF scan (không có lớp chữ): đề `HSK3.0模拟（一）N级.pdf` (cả 2 đề trong một file), lời nghe `…听力文本…pdf`,
đáp án `…答案…pdf`, file ghi âm cả đề `…录音.mp3` (không dùng: web đọc lời nghe bằng giọng tự tạo).
Đọc trước `README.md` cùng thư mục (định dạng đề, dạng câu từng phần) — đặc tả này chỉ ghi phần **khác**.

## Nguyên tắc
- Chép **đúng như đề**: câu, phương án, thứ tự câu, số câu từng phần, thời gian. Bỏ câu ví dụ (例如).
- Đáp án theo file 答案; tự kiểm lại, đáp án sai thì sửa và ghi vào `explain`.
- `explain`: tiếng Việt ngắn (≤ 200 ký tự), vì sao đúng / nghĩa chữ khoá. Bắt buộc mọi câu.

## File ra
`de-sach/hsk{N}-mp{K}.json` (không lên git):
```json
{ "level": 2, "title": "HSK 2 · Đề mô phỏng 1", "nguon": "sach", "durationSec": 3300, "questions": [ ... ] }
```
`durationSec` = tổng thời gian làm bài in trong bảng 考试结构 của đề (không tính 5 phút điền thông tin cá nhân).

## Tranh
Cắt từng tranh của đề thành ảnh JPEG (rộng tối đa 480 px, chất lượng 85) bằng pymupdf
(`page.get_pixmap(dpi=200, clip=Rect)` rồi lưu, hoặc Pillow để thu nhỏ). Lưu vào
`D:\zika-devtools\Project\zika\api\dev-uploads\hinh-de\hsk{N}-mp{K}\` tên `c{số câu}-{a|b|c}.jpg` (tranh riêng của câu)
hoặc `n{câu đầu}-{a…f}.jpg` (tranh dùng chung cả nhóm câu). Trong payload ghi `"/uploads/hinh-de/hsk2-mp1/c01-a.jpg"`.
Cắt sát khung tranh, không lấy chữ cái A/B/C và dấu ✓ của câu ví dụ. Mở lại ảnh cắt để soát.

## Dạng câu theo kiểu bài trên đề
| Kiểu bài trên đề | `type` | payload |
|---|---|---|
| Nghe, chọn 1 trong 3 tranh của câu | `pic` | `audioText`, `options` 3 ảnh, `answer` |
| Nghe, chọn tranh trong nhóm A–E/A–F dùng chung | `pic` | `audioText`, `options` = **cả nhóm ảnh** đúng thứ tự in (kể cả tranh của câu ví dụ), `answer` |
| Nghe, xem tranh đúng hay sai (✓ ✗) | `listen` | `audioText`, `imageUrl` ảnh tranh, `options` `["Đúng","Sai"]` |
| Nghe, chọn A/B/C chữ in | `listen` | `audioText`, `options` chữ Hán đúng như in (bỏ pinyin), `answer` |
| Đọc câu, chọn tranh trong nhóm | `pic` | `ask` câu chữ Hán, `options` cả nhóm ảnh |
| Đọc, chọn từ trong khung A–F điền chỗ trống | `blank` | `sentence` một `___`, `options` = **cả khung từ**, `answer` |
| Đọc, chọn câu trả lời / câu phù hợp trong nhóm A–F | `pick` | `ask` câu của đề, `options` = **cả nhóm câu** (2–6), `answer` |
| Đọc đoạn, câu ★ hỏi, chọn A/B/C | `pick` | `ask` = đoạn văn + xuống dòng + `★` câu hỏi (≤ 600 ký tự), `options` |
| Đọc đoạn, câu ★ đúng hay sai | `tf` | `passage`, `statement`, `answer` true/false |
| Viết: chọn bộ phận A–F ghép thành chữ | `pick` | `ask` = pinyin + mô tả ("nán shēng — chữ đầu còn thiếu một bộ phận"), `imageUrl` = ảnh cắt ô chữ thiếu trên đề, `options` các bộ phận A–F như in |
| Viết: nhìn pinyin viết chữ vào ô | `go` | `sentence` câu có `___` ở ô viết, `pinyin` in trên ô, `answer` chữ đúng, `vi` "" |
| Kiểu khác | gần nhất với các dạng trên; không được thì ghi vào báo cáo, không tự chế |

`audioText`: lấy **nguyên văn** lời nghe của câu trong 听力文本 (bỏ số câu, bỏ phần đọc lại lần hai). Hội thoại ghi
`男：…女：…`, câu hỏi đọc kèm ghi `问：…` như in. ≤ 200 ký tự. Bỏ pinyin in trên chữ.
`section`: `listening` / `reading` / `writing` theo phần của đề.

## Tự kiểm
`cd D:/zika-devtools/Project/zika && PYTHONIOENCODING=utf-8 python data/hsk30/kiem_de.py data/hsk30/de-sach/hsk{N}-*.json`
đến 0 lỗi. Không nạp vào web (người giao việc nạp).
