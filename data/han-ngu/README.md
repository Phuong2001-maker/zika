# Giáo trình "Hán ngữ 6 tập"

Giáo trình thứ hai của Zìkǎ, nạp từ bộ sách Hán ngữ phiên bản 3 ở `../../../HanNgu/`. Chỉ có 4 phần:

| Sách | Màn trên web |
|---|---|
| Từ mới | Lật thẻ |
| Tập viết chữ Hán (写汉字) | Luyện viết. Quyển 5–6 không có mục này nên tách chữ từ Từ mới |
| Bài khóa | Đọc hội thoại |
| Ngữ pháp, chú thích, ngữ âm | Cấu trúc ngữ pháp |

Quyển n nằm ở cấp n, học viên thấy "Quyển n" nhờ cột `curricula.level_label` (URL vẫn là `/hsk/n`). Số bài giữ đúng số sách in: Q1 1–15, Q2 16–25, Q3 1–12, Q4 13–25, Q5 1–13, Q6 14–26.
`anh/ bai/ ra/ nghe/` có nội dung sách nên không đưa lên git.

## Thứ tự chạy

Đặt `PYTHONUTF8=1`. Cần MariaDB, API và web local đang chạy. Sách đã chuyển vào `HanNgu/New folder/`: đặt
`ZIKA_HANNGU_DIR="D:/zika-devtools/Project/HanNgu/New folder"` cho các bước đọc PDF (`xem_trang.py --cat`, `kiem_tra_goc`).

```bash
python sinh.py all             # bai/ → ra/qN/baiNN.json
python kiem_tra.py all         # soát bản chép
python nap.py all              # nạp qua API quản trị
python phan_tich_hn.py         # phân tích chữ mới
python nghe_kiem.py all        # nghe băng bằng FunASR → nghe/ (chỉ chạy lại khi băng đổi)
python nghe_doi_chieu.py       # bài khóa nào dùng được băng → ra/nghe_gan.json
python viec_hn.py && python doc_hn.py   # sinh giọng còn thiếu, dùng chung kho với HSK
python gan_hn.py               # gắn giọng vào câu thoại và bài khóa
python quet_hn.py              # tải thử mọi file như người học; còn lỗi thì không đóng gói được
python dong_goi.py             # ra ../../../dong-goi-han-ngu/
php thu_server.php ../../../dong-goi-han-ngu ../../../dong-goi-server   # thử gói trên DB giống server cũ
```

`nap.py` thay toàn bộ nội dung của bài nên làm mất âm thanh đã gắn: nạp lại thì chạy lại từ `viec_hn.py`.

## Đưa lên server

`dong_goi.py` tạo gói kèm `HUONG-DAN.txt` từng bước. Thứ tự không đổi được: code mới (web + PHP) trước, rồi `01-cau-truc.sql`
(migration 006–018, đã chạy thì tự bỏ qua), `02-han-ngu.sql`, giải nén `uploads-han-ngu.zip`, xoá file trong `storage/cache/content`.
Code và cấu trúc DB phải lên cùng đợt: code mới đọc `curricula.level_label` khi trả `/me`, thiếu migration 015 là không đăng nhập được, thiếu 016 là học viên không vào được màn học.
`02-han-ngu.sql` tự đặt Hán ngữ làm giáo trình mặc định (`is_default`), vì migration 016 chạy lúc server chưa có Hán ngữ:
mọi học viên chưa phân quyền chuyển sang Hán ngữ, ai học HSK thì admin gán "Giáo trình HSK 3.0".
Thêm migration mới thì sửa `DEN_SO` trong `dong_goi.py` rồi đóng gói và chạy lại `thu_server.php`.

## Các quyết định cần nhớ

- **Câu sách đánh dấu sai** (`kieu` là "Câu sai"): chỉ hiện ở Ngữ pháp kèm nhãn, không lấy làm ví dụ lật thẻ hay bài xếp câu.
  `la_cau_sai` trong `sinh.py` gom các cách ghi nhãn khác nhau của từng bài.
- **Ví dụ lật thẻ** chỉ lấy câu có trong chính bài, không dùng câu soạn riêng của HSK, vì câu đó tính theo từ vựng HSK. Từ nào
  sách không có câu chứa nó thì không có ví dụ.
- **Từ sách không ghi từ loại** (cụm, cấu trúc như 又…又…) là `other`, không phải danh từ.
- **Bản dịch câu**: sách không in bản dịch nên câu tiếng Việt là bản tham khảo tự dịch, web có ghi chú ngay dưới bài khóa.
  Pinyin Q3–6 cũng tự thêm vì sách không in.
- **Băng (không dùng từ 08/11/2026)**: mọi bài khóa ghép giọng tự tạo (`DUNG_BANG = False` trong `gan_hn.py`). Khi bật lại thì băng
  chấm theo chữ nghe được chứ không theo tên file. Băng Q1, Q5, Q6 là bản 修订本, chữ khác sách phiên bản 3, nên đoạn nào
  lệch thì dùng giọng mẫu. File `B14 - Bài khóa` của Q4 thật ra là Luyện tập B13.
- **Bài khóa dài** vượt giới hạn 2 MB của form tải lên (tới 8 phút), nên `gan_hn.py` ghi thẳng vào uploads như `Upload::store`.
- **File SQL** nối bản ghi qua biến phiên, không ghi id cứng; chữ Hán nối qua cột `hanzi` và không ghi đè chữ HSK đang có.
  Chạy lại file sẽ xoá tiến độ của học viên ở giáo trình này.
