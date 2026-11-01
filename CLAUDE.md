# CLAUDE.md — đọc cái này trước

Zìkǎ (字卡): web học tiếng Trung theo cấp HSK 1→6, có 6 phần học và khu quản trị.
SvelteKit 2 + Svelte 5 (SPA tĩnh) · PHP 8.4 thuần, không framework · MariaDB.

Đọc thêm khi cần: [README.md](README.md) (cấu trúc, đưa lên hosting, SMTP, Google),
[DECISIONS.md](DECISIONS.md) (vì sao chọn cách làm đó), [CHAY-O-MAY.md](CHAY-O-MAY.md)
(chạy trên máy Windows). Đặc tả gốc nằm ở `../zika-spec/`, **không** nằm trong repo.

## Chạy

Cần 3 thứ chạy cùng lúc. Nhanh nhất: bấm đúp `dev\CHAY-TAT-CA.cmd`.

| Phần | Cổng | Lệnh |
|---|---|---|
| MariaDB | 3306 | `"D:/zika-devtools/mariadb/bin/mariadbd.exe" --defaults-file="D:/zika-devtools/mariadb-data/my.ini" --console` |
| API PHP | 8787 | `cd api && "D:/zika-devtools/php/php.exe" -S 127.0.0.1:8787 dev-router.php` |
| Web | 5173 | `cd web && npm run dev` |

Mở http://localhost:5173. Vite proxy `/api`, `/go`, `/uploads` sang 8787.

Tài khoản mẫu, mỗi vai trò một: `admin@zika.local`, `bientap@zika.local`, `hocvien@zika.local`,
mật khẩu `Zika@2026`.
Quên thì `cd api && php app/tools/install.php --reset-demo-password`.

Email ở máy dev không gửi thật, ghi vào `api/app/storage/logs/mail-*.log`.

## Kiểm tra trước khi commit — bắt buộc chạy hết

```bash
cd web && npm run check && npm run lint && npm test
```

```bash
cd api && "D:/zika-devtools/php/php.exe" app/vendor/bin/phpunit -c phpunit.xml
```

Mốc hiện tại: web 500 file **0 lỗi 0 cảnh báo**, lint sạch, 43/43 test; API 44/44.
Đừng commit khi còn đỏ hoặc còn cảnh báo.

## Bản đồ nhanh

```
web/src/routes/          màn hình (SvelteKit)
web/src/lib/components/  component dùng chung (Select, Modal, Icon, Switcher…)
web/src/lib/admin/       riêng khu quản trị (menu.ts, api.ts, LessonHeader.svelte)
web/src/lib/admin/import/ nhập Excel cho 6 phần soạn bài — xem bẫy số 6
web/src/lib/auth/        routes.ts — toàn bộ luật chặn và điều hướng theo vai trò
web/src/lib/learn/sach.ts  3 quyển giáo trình đọc ở /learn/books (ảnh trang nằm ngoài git)
web/src/app.css          tokens + class dùng chung TOÀN CỤC (xem bẫy số 1)
api/app/src/             Controllers/, Services/, Core/
api/app/config.php       bí mật (DB, Google, SMTP) — gitignore, KHÔNG commit
```

Ba vai trò: `learner`, `editor`, `admin`.

## Bẫy — đọc kỹ, đã sa vào thật

### 1. Trùng tên class với `app.css`

`app.css` có nhiều class dùng chung (`.pick`, `.who`, `.opt`, `.bar`, `.empty`, `.hl`,
`.row`, `.card`, `.tag`, `.inp`, `.side`, `.block`, `.tian`…). Svelte thêm hậu tố
`.svelte-hash` nên luật cục bộ thắng — **nhưng chỉ ở thuộc tính nó có đặt**. Thuộc tính
nào luật toàn cục đặt mà cục bộ bỏ trống thì vẫn rò xuống.

Đã gây 4 lỗi thật: `.pick` tô viền mọi `<span>` con làm vỡ thẻ thông báo; `.who` ép khung
tên thành hình tròn 40×40; `.bar` ép thanh công cụ cao 8px `overflow: hidden` cắt mất 76px
nội dung; `.opt` thêm viền và đổ bóng cho từng dòng dropdown.

**Trước khi đặt tên class mới trong `<style>` của component, grep `app.css` xem đã có chưa.**
Nguy hiểm nhất là luật toàn cục nhắm vào con cháu (`.pick span`) — style cục bộ không với tới.

### 2. Đổi tham số route thì SvelteKit không dựng lại trang

Đi từ `/admin/lessons/1/exercises` sang `/admin/lessons/16/exercises` là **cùng một route**,
component không remount nên `onMount` không chạy lại → URL đổi mà nội dung vẫn là bài cũ.
Dùng `$effect` bám theo tham số:

```ts
$effect(() => {
  void lessonId;   // tạo phụ thuộc; ESLint chặn câu lệnh trơ nên phải có `void`
  void load();
});
```

Đổi tab (khác slug) thì remount bình thường, không cần lo.

### 3. Không dùng `<select>` nữa

Danh sách bung ra của `<select>` do hệ điều hành vẽ, CSS không đụng được. Dùng
`web/src/lib/components/Select.svelte`. Panel của nó là `position: fixed` (không phải
`absolute`) vì `.modal` có `overflow-y: auto` sẽ cắt mất panel.

### 4. PHP: dùng `Clock`, đừng dùng `NOW()` của SQL

`NOW()` lấy giờ máy chủ database nên bỏ qua `Clock::freeze()`, làm test phụ thuộc ngày chạy
thật. Tính mốc thời gian bằng `Clock::sql(Clock::now()->modify('-N days'))` rồi bind vào.

### 5. Điều hướng theo vai trò nằm một chỗ

`web/src/lib/auth/routes.ts`: `homePath(role)`, `canView(role, path)`, `landingPath()`.
Đừng hardcode đường dẫn đích ở chỗ khác. Admin → `/admin`, editor → `/admin/lessons`,
learner → `/learn/flashcards`. Guard thật nằm ở `web/src/routes/+layout.svelte`.

### 6. Nhập Excel: đọc file ở trình duyệt, không đọc ở PHP

`web/src/lib/admin/xlsx.ts` tự đọc/ghi `.xlsx` (ZIP + XML, dùng `DecompressionStream`), nên hosting
không phải cài PhpSpreadsheet. Mỗi phần khai báo một `ImportSpec` trong `web/src/lib/admin/import/`:
cột, dòng mẫu, và hàm đổi bảng thành dữ liệu API. Hộp thoại `ImportModal.svelte` không biết gì về
từng phần — thêm bộ nhập mới chỉ là thêm một file spec.

Lọc ở trình duyệt **không thay** cho kiểm tra ở server: 4 endpoint `*/bulk` vẫn chạy
`QuestionPayload::clean()` / `Validator` như khi soạn tay, dòng nào sai thì bỏ qua kèm số dòng
chứ không làm hỏng cả mẻ.

## Nếp viết

- **Tiếng Việt** cho chữ hiển thị, comment và thông điệp commit. Tên biến/hàm có thể tiếng Việt khi rõ nghĩa hơn.
- Mỗi màn phức tạp tách trạng thái ra `state.svelte.ts` (class runes), bố cục máy tính và điện thoại **dùng chung** trạng thái đó — kéo cửa sổ qua 768px không mất chữ đang gõ.
- Bố cục điện thoại/máy tính chọn bằng `media.isMobile` (quản trị dùng `media.isAdminMobile`, ngắt ở 900px).
- Comment giải thích **vì sao**, không mô tả lại code.
- Svelte 5 runes (`$state`, `$derived`, `$effect`, `$props`), không dùng cú pháp Svelte 4.

## Việc còn treo

- **Đang làm dở: nạp nội dung 3 quyển giáo trình vào 6 phần học.** Bản đồ "mục sách → phần học"
  đã chốt. **Mở [data/hsk1/TIEN-DO.md](data/hsk1/TIEN-DO.md) trước** — mục "Việc còn lại" ở cuối
  liệt kê 18 đầu việc theo đúng thứ tự nên làm. Bản đồ tổng cả 3 quyển ở
  [data/giao-trinh/TIEN-DO.md](data/giao-trinh/TIEN-DO.md).

  Tình hình 23/10/2026: HSK 1 **đã nạp lên server thật** (15 bài, draft, học viên chưa thấy),
  nhưng **dữ liệu chưa chuẩn** — bộ tách bóc nhầm bảng 会写字 thay 会认字 nên thiếu 66 chữ ở 14 bài,
  mất trắng 60 dòng 句式, mất 36/42 điểm ngữ pháp, 23 câu rác từ 练习 lọt vào hội thoại. Phải sửa
  `data/hsk1/tach-pdf.py` rồi nạp lại trước khi bật cho học viên. Đo bằng `data/hsk1/kiem-tra.py`.

  HSK 2 và HSK 3 là bản **scan**, không có text nào để bóc — để sau.

- **Hai đường nạp nội dung, dùng đường nào cũng được:**
  - **Nhập Excel** (bẫy số 6) — đi qua API nên giữ đủ 3 lớp kiểm tra. Chỉ chạy ở máy local.
  - **Sinh SQL** `data/hsk1/xuat-sql.py` → `data/hsk1/sql/*.sql` → phpMyAdmin. Dùng khi đẩy lên
    hosting. Mất 3 lớp kiểm tra, đã bù 2 (đếm `cnt_*` và trỏ nét chữ), còn `QuestionPayload::clean()`
    thì chưa — xem `data/hsk1/TIEN-DO.md` Phần B.

- **Màn đọc giáo trình đã xong** (`/learn/books`): 3 quyển HSK 1–3 đọc theo ảnh từng trang.
  Ảnh sinh bằng `data/giao-trinh/tach-giao-trinh.py`, nằm ở `<uploads>/giao-trinh/` và
  **không có trong git** — xem mục "Giáo trình" ở [README.md](README.md) trước khi triển khai.

- API còn khoảng 56 chỗ dùng `NOW()`, phần lớn vô hại (truy vấn thống kê, hoặc đặt mốc thời
  gian lúc ghi). Chỉ thành vấn đề khi hàm đó có test đóng băng đồng hồ **và** so sánh với một
  mốc cắt — như `MaintenanceService` đã dính. Gặp trường hợp đó thì chuyển sang `Clock`.
- 9 chỗ class trùng tên còn lại là **cố ý** dùng lại utility (`.inp`, `.side`, `.block`,
  `.tian`, `.tag`, `.row`, `.mtop`, `.mcontent`, `.lbl`) — để nguyên, đừng "sửa".
- Đăng nhập Google chỉ chạy khi `config.google.client_id` có giá trị; máy nào chưa điền thì
  nút tự ẩn, đó là chủ ý chứ không phải lỗi.
- **SMTP trên server thật đang để trống** nên email xác minh và quên mật khẩu không gửi được,
  chỉ ghi vào `storage/logs/mail-*.log`. Xem README mục "Gửi email thật (SMTP)".
- **Đẩy code lên hosting có 4 cái bẫy đã sa vào thật** (Paste của 1Panel thay cả thư mục nên xoá
  mất `config.php`; `api/index.php` trong repo trỏ sai đường dẫn; đừng copy `uploads`; service
  worker cache cả đường dẫn 404). Đọc README mục "Đưa lên hosting" trước khi deploy.
