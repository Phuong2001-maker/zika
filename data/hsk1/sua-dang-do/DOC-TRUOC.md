# Sửa `tach-pdf.py` — code đã viết xong, CHƯA ghép vào

Ghi lúc dừng việc **23/10/2026 07:45**. Người tiếp theo đọc file này là làm tiếp được ngay.

**Trạng thái: 5 mảnh code đã viết và đã đo xong, nằm cạnh file này. Chưa mảnh nào được ghép vào
`data/hsk1/tach-pdf.py` — file đó vẫn nguyên bản cũ.** Việc còn lại là ghép 5 mảnh, chạy lại,
đo bằng `../kiem-tra.py`, rồi sinh lại SQL bằng `../xuat-sql.py`.

Nền của việc này: [../TIEN-DO.md](../TIEN-DO.md) Phần D (8 nhóm lỗi) và mục "Việc còn lại".

---

## 1. Năm mảnh code

Mỗi mảnh do một agent viết riêng, **mỗi con số dưới đây là đo thật sau khi chạy**, không phải ước lượng.

| File | Thay hàm | Kết quả đo |
|---|---|---|
| [1-mau-cau.py](1-mau-cau.py) | `tach_mau_cau()` | 15/15 bài khớp · **0 → 60 dòng** |
| [2-chu-hoi-nhan.py](2-chu-hoi-nhan.py) | `tach_chu_viet()` | 15/15 bài khớp · **336 → 389 chữ** |
| [3-loc-cau-rac.py](3-loc-cau-rac.py) | `tach_hoi_thoai()` + 4 hằng + 3 hàm phụ | 15/15 bài khớp · **221 → 198 câu** |
| [4-cau-cat-cut.py](4-cau-cat-cut.py) | `tach_hoi_thoai()` + 3 hằng + 4 hàm | 16/16 khớp · vá 7/7 câu cụt |
| [5-tu-vung-sot.py](5-tu-vung-sot.py) | `tach_tu_vung()` + 6 hằng + 4 hàm | 16/16 khớp · **326 → 346 mục** |

---

## 2. Chỗ đụng nhau — PHẢI xử trước khi dán

Agent soát chéo chưa kịp chạy (bị dừng vì hết thời gian), nên đây là phần tôi tự rà. Dán chồng
mà không đổi tên là file sẽ chạy sai **im lặng**, không báo lỗi.

### 2.1 `la_pinyin` bị định nghĩa BA lần, ba nghĩa khác nhau

| Mảnh | Luật | Dùng ở đâu |
|---|---|---|
| 3 | chặt nhất — đòi `PY_SACH`, loại dòng tiếng Việt bằng cách đòi chỉ chữ Latin + dấu pinyin | `tim_pinyin()` |
| 4 | loại tiếng Việt bằng `CHU_VIET_RIENG`, đòi có chữ Latin | `ghep_cau_tran_dong()` |
| 5 | trả về **tuple** `(pinyin, phần sau dấu "(")` chứ không phải bool | `doc_muc()` |

Mảnh 5 trả kiểu khác hẳn — dán chồng là hỏng ngay cả hai mảnh kia.

**Đổi tên:** `la_pinyin_chat` (mảnh 3) · `la_pinyin_dong` (mảnh 4) · `la_pinyin_tu` (mảnh 5).
Sửa luôn chỗ gọi bên trong từng mảnh.

### 2.2 `NHAN_MUC` bị định nghĩa HAI lần, nghĩa ngược nhau

- Mảnh 3: nhãn **tiếng Việt** (`Luyện tập|Nhiệm vụ|Bài \d+…`) — lọc dòng bản dịch giả.
- Mảnh 4: nhãn **chữ Hán** (`生词|专名|语法|课文…`) — chặn dòng nối câu.

Hai thứ hoàn toàn khác nhau. **Đổi tên:** `NHAN_MUC_VI` và `NHAN_MUC_ZH`.

### 2.3 `NGUYEN_AM` hai lần, hai kiểu dữ liệu

- Mảnh 3: `r'[' + PY_DAU + r'aeiou]'` — đã là **lớp regex**.
- Mảnh 5: `'aeiouüāáǎà…'` — **chuỗi trơ**, dùng kiểu `'[' + NGUYEN_AM + ']'`.

Dán chồng là một trong hai thành `[[...]]`. **Đổi tên mảnh 5 thành `NGUYEN_AM_TU`.**

### 2.4 Hai mảnh cùng viết lại `tach_hoi_thoai()` — phải gộp làm MỘT

Mảnh 3 lọc câu rác, mảnh 4 vá câu cắt cụt. Dán chồng thì mảnh nào đứng sau thắng, mất việc của
mảnh kia. Bản gộp:

```python
def tach_hoi_thoai(text: str) -> list[dict]:
    luot = re.compile(r'^([' + HANZI + r']{1,6})\s*[：:]\s*(.+)$')

    def la_luot_moi(d: str) -> bool:
        return bool(luot.match(d))

    doan = []
    phan = re.split(r'课文\s*([一二三四五六])?\s*\n?\s*Kèwén', text)
    for i in range(1, len(phan), 2):
        than = phan[i + 1] if i + 1 < len(phan) else ''
        cau = []
        dong = [d.strip() for d in than.split('\n')]
        for k, d in enumerate(dong):
            m = luot.match(d)
            if not m:
                continue
            noi_dung = m.group(2).strip()
            if not re.search(r'[' + HANZI + r']', noi_dung):
                continue

            # mảnh 4: ghép lại câu tràn dòng, lấy luôn pinyin của khối
            zh, py = ghep_cau_tran_dong(dong, k, noi_dung, la_luot_moi)

            # mảnh 3: cứu 3 câu thật có pinyin lệch dòng (bài 5, 7, 15)
            if not py:
                py = tim_pinyin(dong, k)

            # mảnh 3: không pinyin = đáp án luyện tập, không phải bài khoá
            if not py:
                continue
            if '_' in zh or re.search(r'（\s*）|\(\s*\)', zh):
                continue

            cau.append({'ten': m.group(1), 'zh': zh, 'pinyin': py, 'vi': ''})

        if len(cau) < 2:
            continue
        doan.append({'cau': cau, 'boi_canh': ''})

    viet = [v for v in cau_tieng_viet(text) if not NHAN_MUC_VI.match(v[0])]
    tong = sum(len(d['cau']) for d in doan)
    if len(viet) == tong and tong > 0:
        i = 0
        for d in doan:
            for c in d['cau']:
                c['ten_vi'], c['vi'] = viet[i]
                i += 1
    return doan
```

**Rủi ro chưa đo:** thứ tự này chạy `ghep_cau_tran_dong()` **trước** bộ lọc rác. Hàm đó quét cả
khối nên có thể tìm được pinyin cho một câu rác vốn không có — làm lọt rác. Mảnh 4 đã tự nhận là
nó cũng vá 4 câu rác (bài 8 `小猫在下。`, bài 9, bài 11 hai câu). **Chạy xong phải đếm lại cột CÂU:
đúng 198 thì ổn, nhiều hơn là rác lọt, ít hơn là xoá nhầm câu thật.**

### 2.5 Hai hàm trùng tên nữa

- `la_noi_tiep` (mảnh 3) và `la_phan_noi` (mảnh 4): khác tên, không đụng. Giữ nguyên.
- `noi_tiep` (mảnh 5) không trùng ai. Giữ nguyên.

---

## 3. Ba chỗ báo cáo soát sách SAI — agent đo lại và bác bỏ

Ghi lại để người sau đừng làm theo báo cáo cũ.

### 3.1 “Cắt bài khoá tại nhãn 练习” — KHÔNG CHẠY ĐƯỢC

Báo cáo đề xuất dừng bóc 课文 tại nhãn 练习. Agent đo thật và bác bỏ:

- **Bài 5 không hề có chữ 练习** trong PDF — sách ghi nhãn tiếng Việt “Luyện tập tổng hợp”.
- **Bài 15 nhãn 练习 lại ra TRƯỚC cả 课文三** (nhãn in ở lề nên PyMuPDF trả lẫn lộn), cắt theo nó
  **mất 4 câu thật**.
- Đo: cắt theo `练习/句式/会认字/学习小结` → còn 208 câu, bài 4/5/7/9/11/12/14 không bỏ được rác nào.
  Thêm `生词/语法` → tụt còn 169 câu, bài 4 mất 6 câu thật.

**Cách đúng: dùng “thiếu pinyin” làm dấu hiệu chính.** Sách chỉ in pinyin cho bài khoá, không in
cho đáp án luyện tập. Nhưng phải vá hàm tìm pinyin trước, không thì xoá nhầm đúng 3 câu thật.

Hai câu rác `我很冷。` (bài 12) và `不早。` (bài 15) là **bản trùng lặp** của câu thật trong chính
bài khoá — so bằng tập hợp sẽ tưởng không bỏ được gì, phải đếm bằng multiset.

Hai câu `小猫在` (bài 8) và `他在` / `我不去，我` (bài 11) là rác **bị cắt cụt**, không còn dấu `_`
nào, chỉ pinyin rỗng mới bắt được.

### 3.2 “Đọc theo toạ độ để vá câu cắt cụt” — TỆ HƠN

Báo cáo đề xuất ghép dòng theo toạ độ y. Agent dựng thật rồi so:

- Tổng câu **tụt 221 → 208** (bài 4 mất 4 câu, bài 15 mất 9) vì khung bản dịch tiếng Việt và bảng
  từ vựng ở cột bên có y chen vào giữa các dòng bài khoá, phá cả luật cắt theo `课文 N Kèwén`.
- Chỉ vá được 6/7 mục tiêu, hụt đúng bài 5.

**Cách đúng: giữ text phẳng.** Text phẳng của `doc_pdf()` đã xếp đúng thứ tự tương đối ở cả 7 chỗ.
Câu tràn dòng ra hai kiểu tuỳ cách PyMuPDF gom khối — kiểu A `zh1/py1/zh2/py2` (bài 5, 6, 14) và
kiểu B `zh1/zh2/py1/py2` (bài 15) — nên phải bỏ từng dòng vào đúng giỏ **theo thứ tự xuất hiện**
chứ không đọc ở vị trí cố định.

Hai số điện thoại bài 6 là dòng **toàn số**, không chữ Hán nào, nên mọi luật “dòng nối phải có chữ
Hán” đều bỏ sót.

### 3.3 “Số thứ tự thiếu dấu chấm, nới regex thành `^(\d{1,2})[.\s]`” — VÔ ÍCH

Báo cáo nói sách in `N ` thay vì `N.`. Đọc text thật thì không phải: ở cả ba chỗ (bài 6 số 21 出租车,
bài 13 số 11 给, bài 14 số 11 不要) **số thứ tự mất hoàn toàn, chỉ còn một dòng trống**.

Thủ phạm là chính hằng `RAC` trong `tach-pdf.py`: vế `^\s*\d{1,3}\s*$` vốn để xoá số chân trang đã
xoá luôn mấy số in trơ đó. Nới regex không bắt thêm được gì — ba mục này **chỉ vớt được bằng luật
“mục không số nằm sát ngay mục trước”**.

Cùng cơ chế đó lại là thứ làm `tach_mau_cau()` cũ trả về 0: nó dò số thứ tự dòng bằng
`\n\s*\d{1,2}\s*\n`, mà RAC đã biến các số 1…6 thành dòng trống. Mảnh 1 đảo hướng: **lấy chính dòng
trống làm dấu ngăn**.

> **Ràng buộc ngầm:** cả `tach_mau_cau()` mới lẫn `tach_tu_vung()` mới đều dựa vào việc RAC xoá số
> trơ. Ai sửa `RAC` sau này phải chạy lại `kiem-tra.py` ngay.

---

## 4. Bảy chỗ cần CHỦ DỰ ÁN quyết — code không tự quyết được

### 4.1 Bài 5: sách tự mâu thuẫn, mất chữ 条 khỏi toàn sách

Trang 50 in 会写字 gồm 面、条、饺、子、些、好、看 nhưng **会认字 của chính bài đó không có bảy chữ ấy**
(ngược lại 会认字 có 息、米、馒、头、它 mà 会写字 không có). Sách bắt viết chữ chưa cho nhận biết.

Lấy 会认字 là đúng yêu cầu, nhưng hệ quả: union cũ 228 chữ → union mới **255 chữ**, 28 chữ xuất hiện
thêm và **đúng 1 chữ 条 mất hẳn khỏi toàn sách**. Muốn giữ 条 thì phải lấy từ 生词 bài 5.

### 4.2 Bài 13 và bài 12: ô chữ bị để trống trong chính file PDF

Bài 13 in `饺、、斤`. Agent soi tới mức ký tự bằng `get_text('rawdict')` trang 129: giữa 饺 và 斤 là
**HAI dấu 、 liền nhau** (bbox 438.5–452.4 và 452.5–466.4), **không có glyph nào ở giữa**, cũng không
phải glyph thiếu bảng mã. Người làm sách gõ `、、` liền.

Bằng chứng gián tiếp cho chữ 子: cùng trang 129 có ví dụ `我要一斤饺子。` Điền 子 thì bài 13 thành
**32 chữ** chứ không còn 31 — phải sửa mốc trong `kiem-tra.py`.

**Bài 12 cũng có đúng một ô trống như vậy:** sách in `有、点、、觉`. Quyết luôn thể.

### 4.3 Bài 5: bảng từ theo ảnh — nhận hay bỏ?

米饭/馒头 nằm trong một **bảng từ theo ảnh có thứ tự NGƯỢC** (pinyin ở trên, rồi chữ Hán, rồi nghĩa):
`mǐfàn / 米饭 / Cơm`. Bảng đó có 8 từ: 米饭, 馒头, 鸡蛋, 汤, 茶, 咖啡, 可乐, 啤酒.

- **Bỏ** → giữ mốc 346, mảnh 5 chạy đúng như đang có.
- **Nhận** → bài 5 thành 30 mục, tổng **354**, và phải đổi mốc trong `kiem-tra.py`.

Agent đã thử quét khuôn ngược trên cả cuốn: ra 73 khối, **chỉ 8 là thật**, còn lại là tiêu đề bài và
rác từ bảng luyện viết bài 1–3 (ghép sai hẳn như `一=wū`, `五=yū`, `钱=liū`). **Không có luật
khuôn-ngược nào an toàn đọc theo text thuần** — muốn nhận thì phải truyền thêm toạ độ:
thêm `doc_pdf_khoi(path)` rồi đổi chữ ký thành `tach_tu_vung(text, khoi=None)`.

### 4.4 Khoá JSON `chu_viet` giờ sai nghĩa

Nội dung đã là “chữ cần **nhận biết**” chứ không còn “chữ cần biết **viết**”. Đổi tên thành
`chu_nhan_biet` thì phải sửa đồng thời **3 file**: `tach-pdf.py`, `xuat-sql.py` (dòng 108 và 120),
`kiem-tra.py` (dòng 36 và 88). Nên làm thành một commit riêng, hoặc giữ nguyên tên và ghi chú.

### 4.5 `mau_cau.vi_du` đang là chuỗi, 5 dòng bị nối mất ranh giới

Bài 4 (`我有两个孩子。 我没有哥哥。`, `一个学生 四口人`, `你几岁？ 你多大？`) và bài 15
(`他喜欢这个，也喜欢那个。 我喜欢看书，还喜欢看电影。`, `明天见！ 首都机场见！`).

Bước sinh câu hỏi cần từng câu riêng thì đổi `vi_du` thành `list[str]` (bỏ `' '.join(...)`).

### 4.6 Bảy cảnh báo “câu chưa hết câu” không nên ép về 0

- **2 cái là thật nhưng không sửa được:** hai số điện thoại bài 6 và `早上八点四十` bài 7 — PDF in
  không có dấu `。`. Muốn hết cảnh báo thì phải **tự thêm dấu câu, tức là bịa**.
- **2 cái báo giả do dấu ASCII:** bài 10 `…真便宜!` và bài 14 `…你们都会写哪些汉字了?` — đã trọn vẹn.
  Nới luật cảnh báo thành `。？！!?…”` là hết.
- **2 cái là rác 练习**, bước lọc rác sẽ dọn. Sau khi lọc còn **5 cảnh báo**.

### 4.7 Pinyin bài 5 còn dính tên nhân vật

`Xiǎo gāng：Wǒ huì zuò…` — PDF in tên trong chính dòng pinyin. Lỗi có từ trước, chưa mảnh nào sửa.
Cắt tiền tố `^<tên>：` khỏi trường pinyin là xong.

---

## 5. Một chi tiết nhỏ nhưng làm đổ cả bước sinh SQL

**Đường dẫn PDF có dấu tiếng Việt:**

```
D:\Tiếng trung\HSK1 3.0 Hanu 24.6.26.pdf
```

149 trang, 20.579.118 byte. Viết thiếu dấu (`D:\Tieng trung\...`) là script báo không tìm thấy file.

---

## 6. Các bước làm tiếp

```bash
# 1. Ghép 5 mảnh vào tach-pdf.py, đổi tên 6 chỗ trùng ở mục 2
# 2. Chạy lại bộ tách
cd data/hsk1
python tach-pdf.py "D:/Tiếng trung/HSK1 3.0 Hanu 24.6.26.pdf" hsk1.json

# 3. Đo
python kiem-tra.py hsk1.json
```

Mốc phải đạt sau khi ghép:

| Hạng mục | Trước | Sau |
|---|---|---|
| Từ vựng | 326 | **346** |
| Chữ 会认字 | 336 | **389** |
| Dòng 句式 | 0 | **60** |
| Câu thoại | 221 | **198** |
| Câu còn dấu điền | 5 | **0** |
| Pinyin rỗng | 26 | **0** |

> `kiem-tra.py` đang để mốc chữ là **~389 (số ước)** và pinyin rỗng là **3**. Agent đo thật ra
> **389 chữ** và **0 pinyin rỗng** (vì 3 câu thật đã được cứu). **Sửa lại hai mốc đó trong
> `kiem-tra.py`** trước khi tin kết quả.

```bash
# 4. Sinh lại SQL rồi nạp lên server
python xuat-sql.py hsk1.json sql
```

Nạp lên: phpMyAdmin → Import `sql/02-hsk1-noi-dung.sql` rồi `sql/03-net-chu.sql`. File 01 đã chạy
xong rồi, không chạy lại. **Tắt “Allow the interruption of an import”** vì file chạy trong một
`START TRANSACTION`.

Sau đó xoá sạch `zika-app/storage/cache/content/` trên hosting, không thì API vẫn trả số cũ.

**Còn 12 đầu việc nữa sau bước này** — xem [../TIEN-DO.md](../TIEN-DO.md) mục “Việc còn lại”, từ
mục 3 (biên khối 语法) trở đi. Hai việc lớn nhất còn lại: bóc 36 điểm ngữ pháp còn thiếu, và viết
`sinh-cau-hoi.py` cho 2 phần học đang trống hoàn toàn.

---

## 7. Xoá thư mục này khi nào

Ghép xong, chạy đạt mốc, commit `tach-pdf.py` mới rồi thì xoá cả `data/hsk1/sua-dang-do/`.
Nó chỉ là chỗ giữ tạm cho lần bàn giao này.
