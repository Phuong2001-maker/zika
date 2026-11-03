# Kịch bản test — Lật thẻ, Phát âm, Luyện viết, Hội thoại

Chạy trên web thật (local `http://localhost:5173` hoặc server), đăng nhập bằng tài khoản học viên
(hoặc admin — admin vào được cả trang học). Mỗi mục chạy ở **3 cỡ màn hình**:

| Mã | Thiết bị | Cỡ | Cách mở |
|---|---|---|---|
| ĐT | Điện thoại | 375 × 812 | DevTools → Toggle device (iPhone) hoặc điện thoại thật cùng mạng Wi-Fi |
| MTB | Máy tính bảng | 768 × 1024 | DevTools → iPad Mini |
| MT | Máy tính | ≥ 1280 | cửa sổ thường |

Điện thoại thật: mở `http://<IP máy tính>:5173` (Vite chạy `--host`). iPhone cần bấm một lần mới phát
âm thanh — đó là luật của Safari, không phải lỗi.

Kết quả ghi vào cột cuối: ✅ đạt · ❌ lỗi (ghi kèm mô tả) · — không áp dụng.

---

## A. Lật thẻ (`/learn/flashcards`)

| # | Bước | Mong đợi | ĐT | MTB | MT |
|---|---|---|---|---|---|
| A1 | Trang chọn cấp | HSK 1: **15 bài · 320 từ**, HSK 2: **10 bài · 172 từ**, HSK 3: **15 bài · 440 từ**. Không còn dạng "320/150" | | | |
| A2 | Mở HSK 1 → Bài 3 | Thẻ đầu hiện mặt trước, có chữ "Chạm để lật"/"Bấm để lật"; thẻ nằm trọn trong màn hình đầu, không bị tràn ngang | | | |
| A3 | Chạm/bấm thẻ | Thẻ **lật dọc từ trên xuống**, mượt, không giật; cả hai mặt và chồng thẻ phía sau **nền trắng**; mặt sau có pinyin tô màu thanh, chữ Hán, âm Hán Việt, nghĩa | | | |
| A4 | Ngay sau khi lật | **Tự đọc từ giọng nam Yunxi**, dùng file theo chữ + pinyin; chậm, rõ từng âm | | | |
| A5 | Khối "Câu ví dụ" | Hiện **2 câu** ngắn đúng cấp (HSK 1 ≤ 12 chữ, HSK 2 ≤ 16, HSK 3 ≤ 22), từ đang học tô màu; **mặc định chỉ hiện chữ Hán**; cặp nút **Pinyin | Nghĩa** ở hàng tiêu đề bật/tắt riêng pinyin tô màu thanh và nghĩa tiếng Việt cho cả khối; sang thẻ khác vẫn giữ lựa chọn, mở bài khác thì tắt lại | | | |
| A6 | Bấm loa cạnh mỗi câu ví dụ | Đọc đúng câu đó, giọng người, tốc độ vừa | | | |
| A7 | Bấm loa ở góc thẻ | Đọc lại từ | | | |
| A8 | ĐT: bấm nút ← / →; chạm thẻ để lật, vuốt trái/phải không chuyển từ. Kiểm cả theo bài và tổng hợp. MTB/MT: nút chuyển từ; phím ← / → chuyển từ, Space lật thẻ ở màn theo bài | Bấm nút mới chuyển thẻ; thẻ mới hiện mặt trước. Chạm thẻ chỉ lật, không đổi từ | | | |
| A9 | Từ lặp âm: HSK 1 tìm 常常/谢谢/妈妈, HSK 3 thẻ bất kỳ có câu lặp âm (不…不) | Lật bình thường, **không trắng trang** (lỗi cũ: trùng khoá pinyin làm dừng hiển thị) | | | |
| A10 | Lật hết bài | Màn "Xong bài", có nút Lật lại / Bài tiếp | | | |
| A11 | Lật thẻ theo bài / tổng hợp, kể cả cài đặt chung đang chọn giọng nữ | Từ và câu ví dụ ở lật thẻ dùng giọng nam theo yêu cầu | | | |
| A12 | Cài đặt → Tốc độ 0.75× / 1.25× | Đọc chậm / nhanh hơn tương ứng | | | |
| A13 | Chữ đa âm: HSK 1 还 (hái), HSK 2 得 (de/děi), 觉得, 长 (cháng/zhǎng) | Đọc **đúng** âm như pinyin trên thẻ | | | |
| A14 | Tìm thẻ `还 hái`, `还 huán`, `头 tóu`, `道 dào` | File khác nhau theo pinyin; không lấy thanh nhẹ từ chữ tách trong từ ghép; URL `?v=6` | | | |

## B. Luyện viết (`/learn/writing`)

| # | Bước | Mong đợi | ĐT | MTB | MT |
|---|---|---|---|---|---|
| B1 | Mở HSK 1 → Bài 5 | ĐT: dải chữ cuộn ngang, **ô viết nằm ngay trong màn hình đầu**, rộng gần hết bề ngang | | | |
| B2 | Chế độ "Xem mẫu" | Chữ tự viết từng nét, có số thứ tự nét | | | |
| B3 | Chế độ "Tô theo" — viết bằng **ngón tay** (ĐT/MTB) hoặc chuột (MT) | Nét đúng hiện màu, nét sai rung và cho viết lại; **kéo ngón tay không làm cuộn trang** | | | |
| B4 | Viết sai 2 lần một nét | Nét cần viết nháy gợi ý | | | |
| B5 | Viết xong cả chữ 2 lượt | Báo "Xong chữ …", tự sang chữ kế; dải chữ cuộn theo | | | |
| B6 | "Tự viết" | Không có chữ mờ, vẫn chấm được nét | | | |
| B7 | Khối thông tin chữ | Có: pinyin, Hán Việt, nghĩa, số nét, cấu tạo, **Bộ … (số …) – nghĩa**, đoạn giải thích cách ghép chữ (hình thanh/hội ý/tượng hình, mẹo nhớ) | | | |
| B8 | "Chữ khác cùng bộ" | 3–4 chữ cùng bộ (ưu tiên chữ đã học), mỗi chữ có pinyin, Hán Việt, nghĩa, từ chứa nó; bấm vào thì đọc chữ | | | |
| B9 | Bấm loa cạnh chữ | Đọc chữ bằng giọng người | | | |
| B10 | Duyệt hết chữ của 3 cấp (công cụ `data/nap-sach` kiểm tra tự động) | Chữ nào cũng có dữ liệu nét, số nét khớp | | | |

## C. Hội thoại (`/learn/dialogues`)

| # | Bước | Mong đợi | ĐT | MTB | MT |
|---|---|---|---|---|---|
| C1 | HSK 1 Bài 6, HSK 2 Bài 8 → "Nghe" | Phát **file thu thật** của giáo trình | | | |
| C2 | HSK 3 Bài 1 → "Nghe" | Phát bài khoá đọc bằng Kokoro, **đúng giọng nhân vật** (nam/nữ), nghỉ giữa hai người nói | | | |
| C3 | HSK 1 Bài 2, khoá 1–3 | Có nút Nghe (Kokoro), khoá 4 là file thu thật | | | |
| C4 | Bấm từng câu thoại | Đọc câu đó, giọng đúng nhân vật | | | |
| C5 | Bấm "Nghe" rồi "Dừng" | Dừng ngay | | | |

## D. Phát âm ở các màn khác

| # | Bước | Mong đợi | ĐT | MTB | MT |
|---|---|---|---|---|---|
| D1 | Ngữ pháp → loa cạnh câu ví dụ | Giọng người | | | |
| D1b | Ngữ pháp → câu ví dụ (HSK 1 Bài 4) | Mặc định chỉ hiện chữ Hán; cặp nút **Pinyin | Nghĩa** ở đầu phần ví dụ bật/tắt riêng từng thứ; đổi điểm ngữ pháp thì tắt lại | | | |
| D2 | Bài tập/Kiểm tra → câu "Nghe và chọn" | Giọng người, nghe lại được theo giới hạn | | | |
| D3 | Cài đặt → "Nghe thử" | Giọng người theo giọng đã chọn | | | |
| D4 | Tắt mạng sau khi đã nghe một lượt (MT) | Âm thanh đã nghe vẫn phát từ bộ nhớ đệm trình duyệt | | | |

## Đọc giáo trình (`/learn/books`) — chỉ tài khoản quản trị

| # | Bước | Mong đợi | ĐT | MTB | MT |
|---|---|---|---|---|---|
| GT0 | Học viên, biên tập: mở Chuyển nhanh; gõ thẳng `/learn/books`; mở `/uploads/giao-trinh/hsk1/001.webp` và `/api/admin/books/hsk1/1` | Không có mục Giáo trình; URL sách đưa về trang nhà; ảnh trả 403 cả hai đường | | | |
| GT1 | Mở từng quyển HSK 1–3, cuộn/vuốt dọc | Các trang nối tiếp, đọc qua ranh giới trang mà không cần bấm lật | | | |
| GT2 | Nhập số trang rồi bấm Đi tới / Enter | Cuộn đến đúng trang; ảnh tải xong không đẩy lệch vị trí | | | |
| GT3 | Cuộn đến giữa sách, về kệ rồi mở lại | Nhớ đúng trang đang đọc của từng quyển | | | |
| GT4 | Đổi quyển qua URL; thay đổi chiều rộng qua 768px | Hiện đúng quyển, nội dung và trạng thái đọc dùng chung giữa hai bố cục | | | |
| GT5 | Giả lập một ảnh trang lỗi | Chỉ trang đó báo lỗi và có nút Thử lại; vẫn đọc được các trang khác | | | |

## E. Kiểm tra tự động (chạy ở máy dev)

```bash
cd web && npm run check && npm run lint && npm test
```

```bash
cd api && "D:/zika-devtools/php/php.exe" app/vendor/bin/phpunit -c phpunit.xml
```

```bash
cd data/nap-sach && python kiem_vi_du.py 1 2 3
```

---

## Kết quả chạy trên web thật — 02/11/2026 (https://tiengtrunghanu.io.vn)

Sau khi đưa gói `dong-goi-server` lên (SQL + uploads + code `af65846`). Đăng nhập tài khoản admin.

### So dữ liệu web thật với máy local

Cùng một đoạn kiểm tra chạy trên hai bên, băm nội dung từng bài (từ + ví dụ, chữ viết + bộ thủ +
phân tích, hội thoại + đường dẫn âm thanh, ngữ pháp, câu hỏi bài tập):

| Hạng mục | Web thật | Local | |
|---|---|---|---|
| Bài | 40 | 40 | ✅ |
| Từ / câu ví dụ | 932 / 1.864 | 932 / 1.864 | ✅ |
| Chữ luyện viết / có bộ thủ | 1.567 / 1.567 | 1.567 / 1.567 | ✅ |
| Bài khoá / có file nghe | 144 / 144 | 144 / 144 | ✅ |
| Câu thoại / có âm thanh | 822 / 822 | 822 / 822 | ✅ |
| Điểm ngữ pháp | 160 | 160 | ✅ |
| Câu hỏi bài tập | 1.273 | 1.273 | ✅ |
| Dấu vân tay toàn bộ 40 bài × 5 phần | `82a90358be` | `82a90358be` | ✅ **giống hệt** |

Tải thử trên web thật: **8.551 file âm thanh** (giọng nữ + nam của mọi từ, ví dụ, chữ, ví dụ ngữ
pháp; bài khoá; câu thoại) và **697 file nét chữ** — 0 lỗi, đúng kiểu `audio/mpeg` / `json`.

### Kịch bản giao diện

| # | Mục | ĐT 375px | MTB 768px | MT 1366px |
|---|---|---|---|---|
| A2–A5 | Lật thẻ HSK 1 bài 3: "Chạm để lật", lật tự đọc giọng Kokoro, 2 câu ví dụ | ✅ | ✅ (thử trên local) | ✅ (thử trên local) |
| A6 | Loa câu ví dụ đọc đúng câu, không dùng giọng trình duyệt | ✅ | ✅ | ✅ |
| A9 | Từ/câu lặp âm không trắng trang | ✅ | ✅ | ✅ |
| B1 | Luyện viết: dải chữ cuộn ngang, ô viết 340px trong màn hình đầu | ✅ | — | — |
| B3 | Viết bằng chạm ngón tay, nét được nhận, trang không cuộn | ✅ | ✅ (local) | ✅ chuột (local) |
| B7–B8 | Bộ thủ (Bộ Nhân · số 9), giải thích cấu tạo, 4 chữ cùng bộ | ✅ | ✅ (local) | ✅ (local) |
| C1 | HSK 1 bài 6 "Nghe" phát file thu thật | — | — | ✅ |
| C2 | HSK 3 bài 1: 4 nút Nghe, phát file ghép, đổi "Dừng" | — | ✅ | — |
| C4 | Bấm câu thoại đọc đúng giọng nhân vật | — | ✅ | ✅ (local) |
| D1 | Ngữ pháp: loa câu ví dụ giọng Kokoro | — | — | ✅ (local) |
| — | Chuông thông báo hiện cạnh ảnh đại diện, `/me/notifications` trả 0 thông báo (server chưa đăng thông báo nào) | ✅ | ✅ | ✅ |
| — | Service worker đang chạy, mp3 vẫn phát (bản sửa "câm qua service worker" có tác dụng) | ✅ | ✅ | ✅ |
| — | Quản trị → Từ vựng hiện "320 từ / 172 từ / 440 từ", hết dạng "320/150" | — | — | ✅ |

Chưa thử trên web thật: đăng một thông báo thật (sẽ hiện cho mọi học viên) — luồng này đã thử đủ ở
local (đăng → số đỏ → đọc → "Đánh dấu đã xem tất cả").
