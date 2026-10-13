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

Tài khoản mẫu: `admin@zika.local` và `hocvien@zika.local`, mật khẩu `Zika@2026`.
Quên thì `cd api && php app/tools/install.php --reset-demo-password`.

Email ở máy dev không gửi thật, ghi vào `api/app/storage/logs/mail-*.log`.

## Kiểm tra trước khi commit — bắt buộc chạy hết

```bash
cd web && npm run check && npm run lint && npm test
```

```bash
cd api && "D:/zika-devtools/php/php.exe" app/vendor/bin/phpunit -c phpunit.xml
```

Mốc hiện tại: web 489 file **0 lỗi 0 cảnh báo**, lint sạch, 18/18 test; API 35/35.
Đừng commit khi còn đỏ hoặc còn cảnh báo.

## Bản đồ nhanh

```
web/src/routes/          màn hình (SvelteKit)
web/src/lib/components/  component dùng chung (Select, Modal, Icon, Switcher…)
web/src/lib/admin/       riêng khu quản trị (menu.ts, api.ts, LessonHeader.svelte)
web/src/lib/auth/        routes.ts — toàn bộ luật chặn và điều hướng theo vai trò
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

## Nếp viết

- **Tiếng Việt** cho chữ hiển thị, comment và thông điệp commit. Tên biến/hàm có thể tiếng Việt khi rõ nghĩa hơn.
- Mỗi màn phức tạp tách trạng thái ra `state.svelte.ts` (class runes), bố cục máy tính và điện thoại **dùng chung** trạng thái đó — kéo cửa sổ qua 768px không mất chữ đang gõ.
- Bố cục điện thoại/máy tính chọn bằng `media.isMobile` (quản trị dùng `media.isAdminMobile`, ngắt ở 900px).
- Comment giải thích **vì sao**, không mô tả lại code.
- Svelte 5 runes (`$state`, `$derived`, `$effect`, `$props`), không dùng cú pháp Svelte 4.

## Việc còn treo

- API còn khoảng 56 chỗ dùng `NOW()`, phần lớn vô hại (truy vấn thống kê, hoặc đặt mốc thời
  gian lúc ghi). Chỉ thành vấn đề khi hàm đó có test đóng băng đồng hồ **và** so sánh với một
  mốc cắt — như `MaintenanceService` đã dính. Gặp trường hợp đó thì chuyển sang `Clock`.
- 9 chỗ class trùng tên còn lại là **cố ý** dùng lại utility (`.inp`, `.side`, `.block`,
  `.tian`, `.tag`, `.row`, `.mtop`, `.mcontent`, `.lbl`) — để nguyên, đừng "sửa".
- Đăng nhập Google chỉ chạy khi `config.google.client_id` có giá trị; máy nào chưa điền thì
  nút tự ẩn, đó là chủ ý chứ không phải lỗi.
