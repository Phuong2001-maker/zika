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
| A3 | Chạm/bấm thẻ | Thẻ lật; mặt sau có pinyin tô màu thanh, chữ Hán, âm Hán Việt, nghĩa | | | |
| A4 | Ngay sau khi lật | **Tự đọc từ** bằng giọng người (Kokoro), không phải giọng robot của trình duyệt; tốc độ vừa, nghe rõ từng âm | | | |
| A5 | Khối "Câu ví dụ" | Hiện **2 câu** ngắn đúng cấp (HSK 1 ≤ 12 chữ, HSK 2 ≤ 16, HSK 3 ≤ 22), từ đang học tô màu, pinyin tô màu thanh, có nghĩa tiếng Việt | | | |
| A6 | Bấm loa cạnh mỗi câu ví dụ | Đọc đúng câu đó, giọng người, tốc độ vừa | | | |
| A7 | Bấm loa ở góc thẻ | Đọc lại từ | | | |
| A8 | Vuốt trái/phải (ĐT) · nút ← → (MTB, MT) · phím ← → Space (MT) | Chuyển thẻ, thẻ mới trượt vào; mặt trước lại hiện | | | |
| A9 | Từ lặp âm: HSK 1 tìm 常常/谢谢/妈妈, HSK 3 thẻ bất kỳ có câu lặp âm (不…不) | Lật bình thường, **không trắng trang** (lỗi cũ: trùng khoá pinyin làm dừng hiển thị) | | | |
| A10 | Lật hết bài | Màn "Xong bài", có nút Lật lại / Bài tiếp | | | |
| A11 | Cài đặt → Giọng đọc = **Giọng nam** → quay lại lật thẻ | Từ và câu ví dụ đọc bằng giọng nam | | | |
| A12 | Cài đặt → Tốc độ 0.75× / 1.25× | Đọc chậm / nhanh hơn tương ứng | | | |
| A13 | Chữ đa âm: HSK 1 还 (hái), HSK 2 得 (de/děi), 觉得, 长 (cháng/zhǎng) | Đọc **đúng** âm như pinyin trên thẻ | | | |

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
| D2 | Bài tập/Kiểm tra → câu "Nghe và chọn" | Giọng người, nghe lại được theo giới hạn | | | |
| D3 | Cài đặt → "Nghe thử" | Giọng người theo giọng đã chọn | | | |
| D4 | Tắt mạng sau khi đã nghe một lượt (MT) | Âm thanh đã nghe vẫn phát từ bộ nhớ đệm trình duyệt | | | |

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
