# Nạp giáo trình HSK 1 vào Zìkǎ — đang làm dở

Ghi lúc dừng việc ngày **23/10/2026**. Mở file này ra là làm tiếp được ngay, không cần đọc lại
lịch sử chat. Đọc hết mục "Việc còn lại" ở cuối là biết phải làm gì tiếp.

Nguồn: `D:\Tiếng trung\HSK1 3.0 Hanu 24.6.26.pdf`
(HSK 1 bản 3.0 — Trung tâm Ngôn ngữ & Văn hoá Trung Quốc, ĐH Hà Nội · 149 trang · 15 bài).

Web thật: <https://tiengtrunghanu.io.vn> · database `buwsofujhosting_zika` trên hosting 000nethost.

---

## Phần A — Nhập nội dung từ Excel ✅ XONG, ĐÃ COMMIT

Hai commit đã vào `main`:

| Commit | Nội dung |
|---|---|
| `5272982` | Sửa lỗi dạng câu “Chọn từ điền câu” ở phần Kiểm tra không lưu được (web gọi `fill`, API chỉ biết `blank`) |
| `8184c70` | Nhập nội dung từ Excel cho 6 phần soạn bài |

Cả 6 trang soạn bài đã có nút **Nhập Excel**. Cách dùng xem [README.md](../../README.md) mục
“Nhập nội dung từ Excel”, chi tiết kỹ thuật xem [CLAUDE.md](../../CLAUDE.md) bẫy số 6.

---

## Phần B — Nạp thẳng bằng SQL ✅ XONG 23/10/2026

Đường nhập Excel chỉ chạy được ở máy local. Để đưa 15 bài lên hosting đã làm thêm đường thứ hai:
sinh SQL từ `hsk1.json` rồi nhập qua phpMyAdmin.

### Bộ sinh

`data/hsk1/xuat-sql.py` đọc `hsk1.json`, ghi ra `data/hsk1/sql/`:

```bash
cd data/hsk1 && python xuat-sql.py hsk1.json sql
```

| File | Việc |
|---|---|
| `01-migration-003.sql` | Chạy migration 003 cho server chưa có. Viết kiểu tự soi `information_schema` nên **chạy lại bao nhiêu lần cũng được** |
| `02-hsk1-noi-dung.sql` | Toàn bộ nội dung HSK 1. Tự dọn dữ liệu cấp 1 ở đầu file nên cũng chạy lại được |
| `03-net-chu.sql` | Trỏ `stroke_data_path` cho 228 chữ |

### Chạy SQL thẳng mất 3 lớp bảo vệ — đã bù 2, còn 1

Đường nhập Excel đi qua API nên có 3 lớp. Chạy SQL thẳng mất cả 3:

1. `QuestionPayload::clean()` / `Validator` — **chưa bù**. Hiện chưa sao vì chưa có câu hỏi nào.
2. `ContentWriter::touch()` đếm lại `cnt_*` — **đã bù** bằng câu `UPDATE lessons` cuối file 02.
   `sample_text` phải tính sẵn trong Python vì MySQL không cho bảng dẫn xuất trong `UPDATE`
   tham chiếu cột của bảng ngoài (lỗi `#1054 Unknown column 'l.id' in 'WHERE'`).
3. `HanziDataService` tải nét chữ — **đã bù** bằng `03-net-chu.sql`.

### Đã nạp lên server

Đo trên server sau khi nạp: 15 bài · 303 từ · 228 chữ · 336 nối chữ-bài · 41 đoạn thoại ·
108 nhân vật · 221 câu · 6 điểm ngữ pháp · 15 + 15 bản cấu hình.

**303 chứ không phải 326** vì bảng `words` có `UNIQUE KEY uq_words_level_hanzi (level_id, hanzi)`,
mà 20 từ xuất hiện lại ở bài sau (家 3 lần, 在 3 lần, 要 3 lần…). Bộ sinh giữ lần đầu và in ra
23 dòng bị bỏ kèm số bài.

**15 bài đang ở trạng thái `draft`**, học viên chưa thấy gì. Bật khi dữ liệu đã sạch:

```sql
UPDATE levels  SET is_visible = 1       WHERE id = 1;
UPDATE lessons SET status = 'published' WHERE level_id = 1;
```

---

## Phần C — Đưa code lên hosting ✅ XONG 23/10/2026

Server trước đó chạy bản `zika-release-20261016-1721`, thiếu đúng 1 commit code: `2549335`
(phần Giáo trình). Đã đóng gói `zika-release-20261023-0630.zip` và đẩy lên.

| Trong zip | Giải nén vào |
|---|---|
| `public/` | `/home/buwsofujhosting/domains/tiengtrunghanu.io.vn/` |
| `app/` | `/home/buwsofujhosting/zika-app/` |

### Bốn cái bẫy khi deploy bằng File Manager — ĐỌC TRƯỚC KHI ĐẨY LẦN SAU

1. **Paste của 1Panel THAY CẢ THƯ MỤC, không trộn.** Dán `app/` vào `zika-app/` là **xoá mất
   `config.php`** (zip không chứa file đó) và xoá sạch `storage/logs`, `storage/cache`. Triệu
   chứng: API trả 500 với thân rỗng, không ghi log nào. Chép `config.php` ra chỗ khác trước khi dán.

2. **`api/index.php` trong repo trỏ sai đường dẫn.** Bản gốc là `__DIR__ . '/../../../../zika_app'`
   — tính ra `/home/zika_app`, vừa sai số cấp vừa sai tên (gạch dưới `zika_app`, trong khi thư mục
   thật là gạch ngang `zika-app`). Mỗi lần deploy đè file này đều phải sửa lại thành
   `/home/buwsofujhosting/zika-app`, hoặc đặt biến môi trường `ZIKA_APP_DIR`.

3. **Đừng copy thư mục `uploads` từ zip.** Trong zip nó chỉ có `.htaccess`, mà trên server
   `uploads/` chứa 43MB ảnh giáo trình và toàn bộ file nét chữ.

4. **Service worker cache cả đường dẫn 404.** Khi `uploads/` còn rỗng, SW đã cache `index.html`
   cho `/uploads/giao-trinh/...`; sau khi đẩy ảnh lên, trình duyệt vẫn trả `index.html`. Dò bằng
   `fetch` thường sẽ **báo sai là file chưa có**. Luôn thêm `?v=<ngẫu nhiên>` khi kiểm, và bảo
   người dùng Ctrl+Shift+R. Thêm nữa, `.htaccess` gốc có `RewriteCond %{REQUEST_FILENAME} !-f`
   nên mọi đường dẫn không tồn tại đều trả `index.html` kèm mã **200** — đừng dùng mã 200 làm bằng
   chứng file có thật, phải xem `content-type`.

### Thư mục uploads

`uploads/` trên server trước đó **rỗng hoàn toàn**, chỉ có `.htaccess`. Đã đẩy lên từ
`api/dev-uploads/`: `uploads/giao-trinh/` 472 ảnh WebP (43MB) và `uploads/hanzi/` 269 file nét chữ.

7 file nét bổ sung (二 三 四 六 七 九 十) tải từ
`https://cdn.jsdelivr.net/npm/hanzi-writer-data@2.0/<chữ>.json` — **phải urlencode chữ Hán**,
không thì jsDelivr trả về trang HTML 4.5MB chứ không phải JSON.

---

## Phần D — Chất lượng dữ liệu ❌ CHƯA ĐẠT

Ngày 23/10/2026 đã cho 5 agent đọc cả 15 bài trên PDF và đối chiếu từng con số với `hsk1.json`.
Kết luận: **dữ liệu chưa dùng được cho học viên.** 8 nhóm lỗi, xếp theo mức nặng.

### 1. Lấy nhầm bảng chữ — một nguyên nhân dính 14 bài ⬅ nặng nhất

`tach_chu_viet()` ưu tiên bảng **会写字** (chữ cần biết *viết*) rồi mới fallback **会认字** (chữ
cần *nhận biết*). Dữ liệu đúng phải là 会认字. Đã kiểm bằng máy: `chu_viet` trùng khít **100%**
với 会写字 ở cả 14 bài, không lệch một chữ.

Hậu quả: **thiếu 66 chữ, dư 10 chữ**. Nặng nhất bài 13 (thiếu 18), bài 14 (thiếu 15), bài 2 (thiếu 10).

Bài 8 sách in tiêu đề là **会认词** (chữ 词) chứ không phải 字 — regex phải nhận cả hai.

### 2. Mất trắng mục 句式 MẪU CÂU

Sách có **60 dòng** ở 14 bài, nạp được **0**. `tach_mau_cau()` có sẵn nhưng regex không khớp layout
thật. Số dòng từng bài: bài 2=7, 3=4, 4=5, 5=4, 6=5, 7=5, 8=4, 9=2, 10=3, 11=7, 12=3, 13=4, 14=4, 15=3.

### 3. Mất gần hết mục 语法

Sách có **42 điểm** (không phải 27 như ghi chú cũ), nạp được **6**. Regex hiện chỉ bắt khuôn A
(`N.` → tên VIẾT HOA → giải thích → ví dụ `•`), dùng được ở bài 3 và 4. Từ bài 5 sách đổi khuôn.

Xác nhận bài 1 và bài 2 nạp 0 là **đúng** — quét toàn PDF, chữ 语法 không xuất hiện ở hai bài này.

### 4. `giai_thich` của ngữ pháp nuốt cả trang

Bài 4 điểm “Lượng từ” nuốt trọn phần 练习 + cả bài khoá 3 + bản dịch + mục Nhiệm vụ. Đây chính là
chỗ “ăn mất” hội thoại của bài 4. Phải cắt đúng biên dưới: dừng ở nhãn kế tiếp
(课文/生词/专名/练习/注释/语法) hoặc dòng bản quyền chân trang.

### 5. 23 câu rác từ 练习 lọt vào hội thoại, ở 12 bài

Toàn là đáp án mẫu của 选词填空 / sắp xếp từ. Dấu hiệu: **pinyin rỗng**, hoặc còn nguyên dấu điền
(`我是 ________ 。`, `我 ________ 孩子。`, `“大家（ ）。”`).

Cẩn thận: **3 câu THẬT cũng có pinyin rỗng** (bài 5 `今天11 月21 号（日）。`, bài 7 câu 2 của 课文二,
bài 15 câu dài nhất của 课文三). Dùng mốc 练习 làm chính, lọc pinyin rỗng chỉ là lưới phụ.

### 6. Sót 20 mục từ vựng, bốn nguyên nhân khác nhau

- **Số thứ tự thiếu dấu chấm** (sách in `N ` thay vì `N.`): bài 6 số 21 出租车, bài 13 số 11 给,
  bài 14 số 11 不要.
- **Mục con dùng chung số với mục mẹ**: bài 4 có 没有 (dưới 有), 多 và 少 (dưới 多少); bài 5 có
  星期天 (dưới 星期日); bài 15 gộp 去年/今年/明年 vào cùng số 3.
- **Cụm từ phụ không có số**: bài 1 你好, bài 2 没事, bài 3 请/问/那/男朋友.
- **Từ trùng chữ khác nghĩa bị dedupe oan**: bài 12 天 số 19 (số từ, “ngày”) khác 天 số 3 (danh từ,
  “trời”); bài 14 上 số 17 (“bắt đầu, đi học”) khác 上 số 1 (“lên”).

Nặng nhất: bài 3 sót 老师, bài 12 sót 雨 và 生病 — ba từ này xuất hiện ngay trong chính bài khoá.

### 7. Thiếu và gộp bản ghi hội thoại

Nạp 41 bài khoá, sách có **44**. Bài 1 mất trắng bài khoá duy nhất. Bài 4 và bài 15 mỗi bài chỉ có
2 bản ghi vì hai bài khoá bị dính thành một khối — sách in sai nhãn (bài khoá 3 in thành 课文二),
nên phải tách theo cả nhãn pinyin/tiếng Việt chứ không chỉ chữ Hán 课文.

### 8. 7 câu thật bị cắt cụt giữa câu

Khi câu tràn sang dòng thứ hai trong PDF, bộ tách chỉ lấy dòng đầu.

- **Bài 6 nặng nhất**: mất cả hai số điện thoại `0932463321` và `0932581127` khỏi trường `zh`
  (in ở dòng riêng) trong khi `vi` vẫn đủ số — mà bài 6 tên là “Số điện thoại của bạn là bao nhiêu?”.
  Thêm 2 câu mất nửa sau.
- Bài 5 mất `一些菜，星期天我也做饭。` · bài 14 mất `有些人在看书，有些人睡觉了。` · bài 15 mất
  `去机场接你们，你们也可以住我家。` và cả pinyin của câu đó.

### Những chỗ khác, mức nhẹ hơn

- **Bản dịch**: 173/221 câu không có `vi`. Riêng bài 12 và 14 **lệch dòng** — nguy hiểm hơn thiếu
  vì sai mà trông như đúng. Bài 12 nhận “Chọn đáp án đúng” làm bản dịch. Phải loại các dòng tiêu đề
  bài tập trước khi ghép, và nạp lại hẳn hai bài này.
- **`boi_canh` rỗng 41/41** — sách in câu dẫn cảnh đầy đủ (`在校园里，李文向白家月要手机号。`).
  Riêng bài 15 rỗng là đúng, sách trình bày khác.
- **Tên bài 8 và 15 sai** — đang bị gán câu lệnh của mục 热身. Tên đúng: bài 8 `我爸爸在医院工作`
  “Bố tôi làm việc tại bệnh viện”, bài 15 `首都机场见!` “Hẹn gặp lại ở sân bay thủ đô”.
- **~10 nghĩa/loại từ bị cắt giữa câu**, 21 từ thiếu hẳn trường `loai`.
- **3 câu thật thiếu pinyin**; bài 5 còn giữ tiền tố tên người trong trường pinyin
  (`Xiǎo lì：Nǐ huì zuò fàn ma?`) nên lên web sẽ lặp tên hai lần.

### Lỗi của chính cuốn sách — phải quyết chính sách, không phải lỗi bộ tách

1. **Bài 8**: cả hai bảng 会认词 và 会写字 đều in 钱 (tiền) và cả trang không hề có 前, trong khi
   前 là 生词 số 14 và 钱 không thuộc từ vựng nào của bài. **Nên sửa 钱 → 前.**
2. **Bài 13**: 会认字 in `饺、、斤` có một ô **trống** giữa 饺 và 斤. Gần như chắc chắn là 子, vì
   句式 cùng trang có ví dụ `我要一斤饺子。` **Nên điền 子.**
3. **Bài 6**: `吃完饭` phải là `吃晚饭` (晚饭 là từ mới số 13). Câu `那里的包子非常好吃` lại có
   pinyin `jiǎozi` và dịch “sủi cảo” (包子 là bánh bao).
4. **Bài 7**: `我们下午两天吧` phải là `两点见`.
5. **Sai tên người nói**: bài 4 bài khoá 1 câu 4 in 刘明 nhưng phải là 王一雪 (không ai vừa hỏi vừa
   trả lời); bài 9 课文三 câu 4 in 王同乐 trong khi 5 lượt còn lại là 杨同乐; bài 12 `ten_vi` lệch
   hệ thống.

Sửa gì thì ghi lại vào mục này, để lần nạp sau không coi là sai lệch.

---

## Phần E — Lưới hồi quy

`data/hsk1/kiem-tra.py` đo `hsk1.json` theo các mốc trên:

```bash
cd data/hsk1 && python kiem-tra.py hsk1.json
```

Trả mã thoát khác 0 khi còn sai trong phạm vi. Mốc đo ngày 23/10/2026, **trước khi sửa**:

| Hạng mục | Đang có | Mong đợi |
|---|---|---|
| Từ vựng | 326 | 346 |
| Chữ 会认字 | 336 | ~389 (số ước, cần đếm lại trên PDF) |
| Dòng 句式 | 0 | 60 |
| Câu thoại | 221 | 198 (sau khi lọc 23 câu rác) |
| Câu còn dấu điền | 5 | 0 |
| Pinyin rỗng | 26 | 3 |

Ngoài phạm vi nhưng vẫn đo để theo dõi: đoạn hội thoại 41/44, điểm ngữ pháp 6/42, câu có bản dịch
48/221, bối cảnh rỗng 41/3, câu chưa hết câu 16/0, nghĩa bị cắt 7/0, từ thiếu loại 21/0.

Bản trước khi sửa giữ ở `hsk1.truoc-khi-sua.json` và `tach-pdf.truoc-khi-sua.py` để so
(hai file này **không commit**, xoá được).

---

## Việc còn lại, theo thứ tự nên làm

> **ĐANG LÀM DỞ Ở ĐÂY.** Code cho các việc 1, 2, 5, 6, 7 đã viết xong và đã đo đạt mốc, nhưng
> **chưa ghép vào `tach-pdf.py`**. Nằm ở [sua-dang-do/](sua-dang-do/) — **đọc
> [sua-dang-do/DOC-TRUOC.md](sua-dang-do/DOC-TRUOC.md) trước**, trong đó có chỗ đụng tên phải sửa,
> ba chỗ báo cáo soát sách đã bị đo lại và bác bỏ, và bảy chỗ cần chủ dự án quyết.

Thứ tự do bộ soát đề xuất, đã cân nhắc chỗ nào phải làm trước vì ảnh hưởng chỗ sau.

1. **Sửa `tach_chu_viet()` lấy 会认字** (nhận cả 会认词 của bài 8). Một mốc đổi là trả lại 66 chữ
   cho 14 bài — rẻ nhất mà nặng nhất.
2. **Lọc 23 câu rác**: dừng bóc 课文 tại nhãn 练习, hậu kiểm bỏ câu pinyin rỗng và câu chứa `_`
   hoặc `（ ）`. **Làm trước bước ghép bản dịch**, không thì bản dịch ghép vào câu rác.
3. **Sửa biên dưới khối 语法** (đang nuốt cả trang). Việc này đồng thời trả lại bài khoá 2 và 3 của
   bài 4, nên phải làm **trước** khi tách lại hội thoại.
4. **Tách đúng mốc 课文 cho bài 4 và 15**, nạp bài khoá còn thiếu của bài 1. Mục tiêu 44 bản ghi.
5. **Vá 7 câu cắt cụt** — ghép dòng theo toạ độ trước khi cắt theo dấu câu. Ưu tiên bài 6.
6. **Sửa nhận diện 生词**: nới regex số thứ tự, bóc mục con và cụm phụ, dedupe theo
   `(hanzi, loai, nghia)` thay vì theo `hanzi`. Mục tiêu 346 mục.
7. **Bóc mục 句式** — 60 dòng, bảng 2 cột, tỉ lệ công/kết quả tốt nhất trong các việc còn lại.
8. **Bóc mục 语法** — 36 điểm còn thiếu. Nặng nhất vì nhiều điểm không có số thứ tự và trang ngữ
   pháp bị in trộn với bảng 生词. Nên bóc theo toạ độ y dưới nhãn 语法.
9. **Ghép bản dịch cho 173 câu** — loại trước các dòng tiêu đề bài tập, ghép theo thứ tự lượt nói,
   báo lỗi nếu số dòng dịch không bằng số câu. Nạp lại hẳn bài 12 và 14.
10. **Nạp `boi_canh`** cho 38 bài khoá (bỏ qua bài 15).
11. **Dọn trường bị cắt**: ~10 nghĩa/loại, 21 từ thiếu `loai`, 1 trường còn ký tự `\n` thô, tiền tố
    tên người trong pinyin bài 5.
12. **Sửa tên bài 8 và 15** — chỉ là 2 chuỗi, sửa tay ngay được.
13. **Quyết chính sách với lỗi của sách** (钱→前, ô trống 饺_斤, 吃完饭→吃晚饭, 两天→两点见, 3 tên
    người nói sai) và ghi vào Phần D.
14. **Quyết mục 注释** — thêm trường `chu_thich` vào schema, gộp vào `ngu_phap`, hay bỏ hẳn. Bài 1
    còn 11 chữ của mục “认读 Nhận mặt chữ và đọc” chưa nạp.
15. **Viết `sinh-cau-hoi.py`** — 10 câu Bài tập + 10 câu Kiểm tra mỗi bài. Hiện 2/6 phần học đang
    trống hoàn toàn. Nguyên tắc “suy ra, đừng bịa” và khuôn payload xem
    [data/giao-trinh/TIEN-DO.md](../giao-trinh/TIEN-DO.md) Phần E, và
    `api/app/src/Services/QuestionPayload.php` — viết sai là API trả 422.
16. **Chạy lại `kiem-tra.py`**, rồi `xuat-sql.py`, rồi nạp lại server.
17. **Điền SMTP** vào `zika-app/config.php` — hiện để trống nên email xác minh và quên mật khẩu
    không gửi được. Hộp thư nên trùng domain để thư không vào spam.
18. **Bật hiện cho học viên** — chỉ làm sau khi 1–16 xong và đã soát mắt vài bài.

---

## Ba cái bẫy khi đọc PDF, đừng sa lại

1. **Chữ Hán trong PDF là ký tự Kangxi Radicals**, không phải chữ Hán thường — `⽂` (U+2F02) chứ
   không phải `文` (U+6587). Nhìn giống hệt nhau. `Pinyin::isHanzi` ở API sẽ từ chối, API báo
   “Không phải chữ Hán”. Hàm `chuan_hoa()` đã xử lý.

2. **Đừng NFKC cả chuỗi** để sửa bẫy số 1: NFKC đổi luôn dấu câu toàn hình `，。？！` thành dấu
   ASCII `,.?!`, làm câu tiếng Trung sai chính tả. `chuan_hoa()` chỉ chuẩn hoá đúng dải
   `U+2E80–U+2FD5`, giữ nguyên mọi ký tự khác.

3. **Nhãn các mục (`生词` `语法` `课文` `练习`) nằm ở lề trang**, nên PyMuPDF trả chúng ra lẫn giữa
   nội dung, không đúng thứ tự nhìn thấy. **Không dùng nhãn làm mốc cắt.** Vì vậy `cat_theo_bai()`
   cắt theo **trang**, còn `tach_ngu_phap()` nhận theo **khuôn nội dung**. Bản dịch tiếng Việt của
   bài khoá cũng nằm ở khung riêng nên thường ra **trước** cả câu tiếng Trung.

## Lưu ý ngoài kỹ thuật

Mỗi trang PDF đều ghi *“Bản quyền thuộc Trung tâm Ngôn ngữ Văn hoá Trung Quốc - ĐH Hà Nội. Không
sao in dưới mọi hình thức”*, trong khi README mô tả Zìkǎ là web học **miễn phí** (công khai). Đã
báo chủ dự án và làm tiếp theo giả định chủ dự án có quyền dùng nội dung này. Nếu web mở công khai
thì nên cân nhắc lại phần bài khoá và phần giải thích ngữ pháp.
