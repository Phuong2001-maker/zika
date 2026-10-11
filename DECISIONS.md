# DECISIONS — các quyết định ngoài tài liệu đặc tả

Đặc tả gốc: `../zika-spec/` (README + docs/01–09). File này ghi những chỗ tài liệu chưa nói rõ hoặc phải chọn cách làm, theo nguyên tắc ở README §4 của đặc tả: chọn phương án đơn giản nhất, không trái README §3.

## Quyết định

| # | Chủ đề | Quyết định | Lý do |
|---|---|---|---|
| 1 | Công nghệ | Giữ nguyên stack đã chốt: SvelteKit SPA tĩnh + PHP 8.4 thuần + MySQL/MariaDB. | Hosting dùng chung LiteSpeed không có root, 2 GB RAM: không chạy được Node/SSR hay Docker. SPA tĩnh + PHP là phương án rẻ và nhẹ nhất cho cấu hình này. |
| 2 | Phiên bản thư viện | Dùng bản mới hiện có: SvelteKit 2.70, Svelte 5.57, Vite 8, `firebase/php-jwt` ^7, `phpmailer/phpmailer` ^7, PHPUnit 12. | Tài liệu ghi ^6 cho 2 gói PHP; bản 7 tương thích API đang dùng và còn được vá lỗi. |
| 3 | Cổng API máy dev | `php -S 127.0.0.1:8787 api/dev-router.php` (tài liệu ghi 8080). Vite proxy đọc biến `ZIKA_API`. | 8080 hay bị phần mềm khác chiếm trên Windows. Bộ chạy thử trong `dev/*.cmd` dùng sẵn 8787. |
| 4 | Trạng thái màn hình | Mỗi màn có 1 file trạng thái `state.svelte.ts` (class runes) + bố cục máy tính/điện thoại dùng chung trạng thái đó. | Khi xoay máy hay kéo cửa sổ qua điểm ngắt 768px, không mất chữ đang gõ (02 §3.2 yêu cầu “1 file logic dùng chung + 2 layout”). |
| 5 | Route `/` | Layout gốc tự chuyển: đã đăng nhập → `/learn/flashcards`, chưa → `/login` (không kèm `next`). | Tránh vòng `/login?next=/`. |
| 6 | Nút Google | Chỉ hiện khi admin bật **và** `config.google.client_id` khác rỗng. | Tránh nút bấm vào là lỗi trên máy chưa cấu hình Google. |
| 7 | Chuyển HTTPS trong `.htaccess` | Thêm điều kiện `X-Forwarded-Proto != https`. | Đứng sau Cloudflare/proxy, `%{HTTPS}` có thể là off dù trình duyệt đã dùng https → vòng chuyển hướng. |
| 8 | `deploy/htaccess-deny` | Đặt thêm vào `<APP_DIR>` và `storage/` khi đóng gói. | Phòng trường hợp thư mục app lỡ nằm trong `public_html`. |
| 9 | Cron 5 phút | Bước “chấm bài kiểm tra quá hạn” chỉ chạy khi đã có `TestService` (Giai đoạn 2). Bước chuyển trạng thái thông báo theo lịch làm luôn từ Giai đoạn 0. | Cron dựng một lần, các giai đoạn sau chỉ bổ sung. |
| 10 | Kiểm thử PHP cần DB | Dùng database riêng `zika_test` (đổi bằng `ZIKA_TEST_DB`), lấy tài khoản DB từ `config.php` máy dev; mỗi test xoá sạch bảng trừ dữ liệu nền. Không kết nối được DB thì bỏ qua thay vì báo đỏ. | Không bao giờ chạm DB thật; test độc lập nhau. |
| 11 | Đặc tả trong repo | Không chép `zika-spec/` vào repo; README trỏ tới thư mục đặc tả nằm cạnh. | Tránh 2 bản đặc tả lệch nhau. |

## Nhật ký giai đoạn

### Giai đoạn 0 — Nền móng (08/10/2026)
- **Đã có từ trước:** lõi PHP (Router, Request/Response, Db, Auth, Csrf, RateLimit, Validator, FileCache, Mailer + hàng đợi, Upload, Ua, Audit), API xác thực đầy đủ (email, Google, quên/đặt lại mật khẩu, xác nhận email, lời mời), `/me`, heartbeat `/visit/ping|leave`, migrations + `tools/install.php` + trang cài đặt web, `dev-router.php`, `app.css` = tokens, `Icon.svelte`, các store (session, settings, media, toast, heartbeat), `client.ts`, tiện ích định dạng/xáo trộn.
- **Làm thêm:** layout gốc (chặn route theo đăng nhập, xử lý 401/khoá tài khoản/mất mạng, bật heartbeat, trang quản trị luôn sáng); màn `/login` (đăng nhập + tạo tài khoản + trạng thái “Kiểm tra email”), `/forgot`, `/reset`, `/verify-email`, `/invite`, `/pages/privacy|terms`, trang 404; component `Modal`, `Toaster`, `Markdown`, `DocModal`, `AuthShell`, `IconTile`; `cron/every5min.php` (đóng phiên, `online_samples`, thông báo theo lịch, gửi mail); `deploy/` (3 `.htaccess` + `build-release.sh`); ESLint + Prettier; PHPUnit (26 test) + Vitest (11 test).
- **Tự soát “Xong khi”:** đăng ký email → email xác nhận (ghi log khi `env=local`) → xác nhận → tự đăng nhập → Bàn học ✔ · đăng nhập email ✔ · cookie `zk_sid` HttpOnly + SameSite=Lax (Secure khi `cookie.secure=true`) ✔ · sai mật khẩu 5 lần → lần 6 bị chặn 15 phút ✔ · heartbeat mở/cộng dồn/đóng phiên đúng (test `VisitServiceTest`) ✔ · giao diện đăng nhập khớp mockup ở 1280px và 375px ✔. Đăng nhập Google: luồng có sẵn ở server, cần `client_id` thật để thử đầu-cuối.
