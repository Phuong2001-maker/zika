# Đặc tả chép Bài tập Hán ngữ từ phần 练习 của sách (chốt 15/11/2026)

Đọc trước: `ra/KE-HOACH-BAI-TAP-LUYEN-VIET.md` mục 1 (lấy dạng nào, bỏ dạng nào) và bảng khảo sát `ra/khao-sat-luyen-tap/q{n}.md`
(trang PDF, số câu, đáp án lệch của từng bài). Sách là bản scan: ảnh `anh/q{n}/baiNN/p###_tren.jpg|_duoi.jpg`, phóng to bằng
`PYTHONUTF8=1 ZIKA_HANNGU_DIR="D:/zika-devtools/Project/HanNgu/New folder" python xem_trang.py <quyển> <bài> --cat <trang> x0 y0 x1 y1`.
Đáp án Lixin (Q3–6) dạng chữ: thư mục scratchpad `dap-an/q{n}.txt` (đường dẫn trong lời giao việc).

## Nguyên tắc người dùng chốt
- Lấy **đúng 100% như sách**: chữ, câu, phương án, khung từ, thứ tự in. Không thêm phương án nhiễu tự nghĩ, không bớt.
- Đáp án: sách/Lixin có thì đối chiếu; Lixin lệch sách hoặc sai thì **tự giải cho đúng**; không có thì tự giải. Ghi nguồn ở `dap_an`.
- Câu có 2 chỗ trống: **tách làm 2 câu**, mỗi câu một `___`, chỗ trống còn lại **điền sẵn đáp án** của nó.
- Lỗi in của sách: dùng bản đúng, ghi chữ sách in vào `ghi_chu`.
- Câu có hai đáp án đều đúng mà phương án sách in có cả hai → bỏ câu đó, ghi vào `bo` kèm lý do.

## Đầu ra
`bai-tap/q{n}/baiNN.json` (NN = số bài in trong sách, 2 chữ số; thư mục không lên git):

```json
{
  "quyen": 3, "bai": 1, "trang": [37, 38],
  "cau": [
    {"muc": "四 选择正确答案 (1)", "type": "blank", "payload": {...}, "dap_an": "sach|lixin|tu_giai|lixin_sai_da_sua",
     "giai_thich": "", "ghi_chu": ""}
  ],
  "bo": [{"muc": "…", "ly_do": "…"}]
}
```
`muc` = số mục + tiêu đề in + số câu trong sách, để tra ngược. `giai_thich` để trống hoặc một câu tiếng Việt ngắn khi cần nói vì sao.
Câu theo đúng thứ tự in.

## Payload theo dạng (web đã nới giới hạn 15/11)
- `blank` (điền chỗ trống): `{"sentence": "今天太冷了，你___多穿一件衣服。", "vi": "", "showMeaning": false, "options": [...], "answer": 1}`
  - đúng một `___`; `options` = **đúng các lựa chọn sách in** cho câu đó: 2–4 từ in cạnh câu, A–D in sẵn, nhóm từ cố định
    (不/没, 了/着/过…), hoặc **cả khung từ** của mục (tối đa 16 từ, giữ thứ tự in). `answer` = chỉ số trong `options`.
- `order` (组句, 连句成段): `{"vi": "", "answer": "câu/đoạn đúng có dấu câu", "tiles": ["mảnh", "theo", "thứ tự in"]}`
  - 2–12 mảnh, mỗi mảnh ≤ 120 ký tự, đáp án ≤ 400 ký tự. Ghép các mảnh (bỏ dấu câu, khoảng trắng) phải đúng bằng đáp án.
  - 连句成段: mỗi mảnh là một câu A/B/C… sách in (bỏ chữ cái đánh dấu).
  - Câu xếp được hơn một thứ tự đúng → bỏ.
- `match` (nối): `{"pairs": [["vế trái", "vế phải"], ...]}` 2–6 cặp, vế trái ≤ 32 ký tự; quá 6 cặp thì tách nhóm.
- `tf` (đúng/sai): `{"passage": "đoạn văn sách in (≤ 2000 ký tự)", "statement": "câu ★ cần xét", "answer": true}`.
- `pick` (chọn đáp án; Bài tập đã nhận dạng này): `{"ask": "…", "options": [...], "answer": 0}`, 2–4 phương án, ask ≤ 200 ký tự.
  - Chọn vị trí: `ask` = `"（了）我A已经B学C两年D汉语。"` (từ trong ngoặc đứng đầu, giữ chữ cái vị trí như sách), options `["A","B","C","D"]`.
- `pinyin` (Q1 声调): `{"word": "马", "ask": "", "options": ["mā","má","mǎ","mà"], "answer": 2}` — options đúng 4 thanh in trên dòng;
  dòng là từ hai âm tiết thì bỏ.
- `audio` (Q5–6 辨音辨调): `{"audioText": "终于", "audioUrl": null, "options": ["终于", "充裕"], "answer": 0}` — mỗi cặp một câu,
  options là đúng hai từ sách in (chữ Hán); cặp lẻ (1, 3, 5…) đọc từ thứ nhất, cặp chẵn đọc từ thứ hai. Giọng đọc web tự sinh.

## Tự kiểm trước khi xong
`python kiem_bai_tap.py q{n}` (đúng schema, đúng giới hạn, đáp án trỏ đúng, ghép mảnh khớp). Lỗi thì sửa đến khi 0 lỗi.
