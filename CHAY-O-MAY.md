# Chạy Zìkǎ trên máy (VS Code)

Mở thư mục `D:\zika-devtools\update\zika` trong VS Code (`File → Open Folder`).

Dự án cần **3 thứ chạy cùng lúc**:

| Phần | Cổng | Là gì |
|---|---|---|
| MariaDB | 3306 | database |
| API PHP | 8787 | backend JSON |
| Web | 5173 | giao diện (SvelteKit) |

---

## Cách 1 — bấm nút trong VS Code (nhanh nhất)

`Ctrl + Shift + P` → gõ `Run Task` → chọn **Zika: chạy tất cả**.

VS Code mở 3 terminal song song. Xong thì vào http://localhost:5173

Muốn tắt: bấm thùng rác ở từng terminal, hoặc `Ctrl + Shift + P` → `Terminate Task`.

> Các task nằm trong `.vscode/tasks.json`. Thư mục `.vscode/` bị git bỏ qua nên file này chỉ có trên máy bạn.

---

## Cách 2 — gõ lệnh trong Terminal của VS Code

Mở terminal: `Ctrl + ~`. Mỗi lệnh dưới đây cần **một terminal riêng** (bấm dấu `+` ở góc phải panel terminal để mở thêm).

**Terminal 1 — MariaDB**

```bash
"D:/zika-devtools/mariadb/bin/mariadbd.exe" --defaults-file="D:/zika-devtools/mariadb-data/my.ini" --console
```

**Terminal 2 — API PHP**

```bash
cd api && "D:/zika-devtools/php/php.exe" -S 127.0.0.1:8787 dev-router.php
```

**Terminal 3 — Web**

```bash
cd web && npm run dev
```

Rồi mở http://localhost:5173

---

## Cách 3 — bấm đúp file .cmd

Trong Explorer mở `dev\CHAY-TAT-CA.cmd`. Nó tự bật cả 3 phần, mỗi phần một cửa sổ đen, và tự mở trình duyệt.

Đóng cửa sổ đen nào là tắt phần đó.

---

## Đăng nhập

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị | `admin@zika.local` | `Zika@2026` |
| Người học | `hocvien@zika.local` | `Zika@2026` |

- Quản trị vào http://localhost:5173/admin để nhập từ mới, bài tập, kiểm tra…
- Người học vào http://localhost:5173/learn/flashcards

Quên mật khẩu thì đặt lại:

```bash
cd api && "D:/zika-devtools/php/php.exe" app/tools/install.php --reset-demo-password
```

---

## Lần đầu tiên (hoặc sau khi `git clone`)

Chỉ cần làm **một lần**:

```bash
cd web && npm install
```

```bash
cd api/app && "D:/zika-devtools/php/php.exe" D:/zika-devtools/composer.phar install
```

Chưa có file `api/app/config.php` thì chép từ mẫu rồi sửa (file này không nằm trong git vì chứa mật khẩu database):

```bash
cp api/app/config.example.php api/app/config.php
```

Sửa trong đó: `'env' => 'local'`, `'app_url' => 'http://localhost:5173'`, `cookie.secure => false`, phần `db` điền tên database và mật khẩu, `paths.uploads_dir` trỏ tới thư mục `api/dev-uploads`.

Rồi tạo bảng + dữ liệu mẫu:

```bash
cd api && "D:/zika-devtools/php/php.exe" app/tools/install.php
```

---

## Khi gặp lỗi

**Mở web ra trang trắng, F12 báo lỗi gọi API**
→ API PHP chưa chạy. Kiểm tra terminal 2, hoặc mở http://127.0.0.1:8787/api/public/config — phải ra chữ JSON.

**API báo không kết nối được database**
→ MariaDB chưa chạy. Kiểm tra terminal 1.

**`Port 5173 is already in use`**
→ Web đang chạy sẵn ở cửa sổ khác rồi, cứ mở http://localhost:5173. Muốn tắt hẳn:

```bash
powershell "Get-NetTCPConnection -LocalPort 5173 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }"
```

**Sửa code mà web không đổi**
→ `Ctrl + C` ở terminal 3 rồi `npm run dev` lại.

**Muốn xem bản build thật (có PWA, cài được như app)**
→ Tắt terminal 3 trước, rồi:

```bash
cd web && npm run build && npm run preview
```

Mở http://127.0.0.1:4173 (không phải 5173). Service worker chỉ có ở bản build này, `npm run dev` không sinh ra.

---

## Kiểm tra code trước khi đẩy lên git

```bash
cd web && npm run check && npm run lint && npm test
```

```bash
cd api && "D:/zika-devtools/php/php.exe" app/vendor/bin/phpunit -c phpunit.xml
```

---

## Đưa lên server thật

Xem `README.md`, mục **Đưa lên hosting**.
