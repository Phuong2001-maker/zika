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
| A15 | Hàng chọn chế độ **Lật thẻ · Ghép cặp · Trắc nghiệm · Gõ chữ** (theo bài) | Mặc định Lật thẻ, như cũ; ĐT 4 nút chia đều không cắt chữ; đổi qua lại giữa các chế độ vẫn giữ lượt đang làm | | | |
| A16 | Ghép cặp | Mỗi lượt ≤ 6 cặp, chia đều (13 từ = 5+4+4); chữ Hán không bẻ dòng, nghĩa dài (HSK 2 Bài 8: 着) xuống dòng không cắt; đúng thì mờ đi và đọc chữ, sai thì rung đỏ; ẩn nhãn số/chữ phím ở ô, nhãn Lượt và chân đồng hồ/gợi ý phím; tiến độ vẫn hiện | | | |
| A17 | Trắc nghiệm | ĐT 4 hàng dài, MT lưới 2×2; chọn xong hiện pinyin không xô đáp án; sai thì câu đó hỏi lại ở cuối (tổng câu +1); Enter sang câu sau, không tự chọn đáp án câu mới | | | |
| A18 | Gõ chữ | Nhận chữ Hán hoặc pinyin không dấu / thanh bằng số (`nimen`, `ni3men`); Gợi ý lần 1 pinyin, lần 2 chữ Hán; Bỏ qua; ĐT ô gõ ≥ 16px (iPhone không tự phóng to) | | | |
| A19 | Hết một chế độ | Màn tổng kết: số đúng, thời gian, danh sách từ cần ôn có loa; "Luyện lại N từ sai" chỉ chơi lại các từ đó; ghi "xong phần Lật thẻ" như lật hết thẻ | | | |
| A20 | Kéo cửa sổ qua 768px giữa chừng; giao diện tối | Không mất tiến độ; không tràn ngang ở 375 / 767 / 768 / 1024 / 1280px; màu đúng/sai đọc rõ ở giao diện tối | | | |
| A21 | Mở Chuyển nhanh bằng admin, học viên và biên tập; thử cả khi đang ở Bài tập/Kiểm tra | Chỉ admin thấy Bài tập/Kiểm tra, kể cả ô Đang dùng. Các mục khác giữ nguyên | | | |

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
| C6 | Mở bài và "Ôn cả cấp" (tài khoản chưa bật trong Cài đặt) | Chỉ hiện chữ Hán, hai nút Pinyin / Nghĩa đều tắt; bật Cài đặt "Hội thoại: hiện …" thì mở bài thấy sẵn | | | |
| C7 | Mở hội thoại, thử mô tả dài trên điện thoại và máy tính | Hiện đầy đủ mô tả tình huống ngay từ đầu, xuống dòng tự nhiên; không có nút Xem tình huống/Thu gọn | | | |

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

## F. Giao diện đồng bộ — mọi màn học một khuôn (04/11/2026)

| # | Bước | Mong đợi | ĐT | MTB | MT |
|---|---|---|---|---|---|
| F1 | Mở từng màn học theo bài: Lật thẻ, Bài tập, Kiểm tra, Luyện viết, Hội thoại, Ngữ pháp | MT: đầu bài (← Chọn bài, "HSK · Bài · …", tên bài) + **một khung trắng** chứa trọn nội dung và nút; ĐT: thanh trên + một khung. Không còn nút/dãy số câu nằm rời ngoài khung | | | |
| F2 | Danh sách bài của 5 phần (Bài tập, Kiểm tra, Luyện viết, Hội thoại, Ngữ pháp) | Khung đầu lớn tô màu cấp, chữ "HSK" + số to, tiến độ "a/b bài" — giống màn chọn bài Lật thẻ | | | |
| F3 | 6 màn ôn tổng hợp | Cùng khuôn đầu bài + khung; ôn Lật thẻ có ← Lật thẻ → dưới thẻ, phím Space / ← / → | | | |
| F4 | Kiểm tra: tới câu cuối | Nút "Câu tiếp" đổi thành "Nộp bài"; dãy số câu và nút Nộp bài trong khung | | | |
| F5 | Ngữ pháp HSK 1 Bài 1–2 (chưa có điểm) | Khung báo "Bài này chưa có phần ngữ pháp.", không còn màn trắng | | | |
| F6 | Quét 375 / 768 / 1024 / 1280px, sáng + tối | Không tràn ngang; nút chính trên ĐT ≥ 44px | | | |

## G. Ảnh đại diện và tài khoản (`/account`)

| # | Bước | Mong đợi | ĐT | MTB | MT |
|---|---|---|---|---|---|
| G1 | Bấm **Thêm ảnh / Đổi ảnh**, chọn ảnh chụp điện thoại (vài MB, cả ảnh dọc) | Ảnh hiện tròn, đúng chiều (không bị xoay ngang); ảnh góc phải thanh trên đổi theo | | | |
| G2 | Xem dung lượng (DevTools → Network) | Trình duyệt gửi ~50KB WebP 384px; server lưu `uploads/avatar/…webp` 192×192 ~10–15KB | | | |
| G3 | Đổi ảnh lần hai | Đường dẫn ảnh cũ trả 404 (đã xoá tệp, không đầy ổ) | | | |
| G4 | **Gỡ ảnh** | Về chữ cái đầu tên; tệp ảnh bị xoá | | | |
| G5 | Chọn tệp không phải ảnh / đổi ảnh > 10 lần trong 1 giờ | Báo "Không đọc được ảnh này…" / "Bạn đổi ảnh nhiều quá…" | | | |
| G6 | Xoá tài khoản (tài khoản thử, có mật khẩu): gõ `XOA` + mật khẩu | Xoá được, về màn đăng nhập (trước đây nút này luôn hỏng) | | | |

## H. Dấu ấn giao diện — khối Hôm nay, ô chữ điền, dấu triện (04/11/2026)

| # | Làm gì | Mong đợi |
|---|---|---|
| H1 | Học viên mở `/learn/flashcards` | Lời chào "Chào {tên}, hôm nay học gì?"; thẻ "Học tiếp · HSK x · Bài y" đúng bài đang dở, bấm "Học tiếp" vào đúng bài và đúng thẻ đang dở |
| H2 | Tài khoản mới chưa học gì | Thẻ "Bài tiếp theo" chỉ HSK 1 Bài 1; ô số chuỗi màu xám "0", lời nhắc "Học một bài hôm nay để bắt đầu chuỗi." |
| H3 | Lật vài thẻ rồi quay lại Bàn học | Chấm ngày hôm nay (cuối hàng, chữ đỏ) thành chấm đỏ có dấu tích; chuỗi tăng nếu hôm qua cũng học |
| H4 | Chỉ mở web, không học | Không tính là ngày học |
| H5 | Xem các cấp | Chỉ cấp đã mở có thẻ to; cấp chưa mở nằm trên một dòng "Sắp có …"; mỗi thẻ cấp có số 壹/贰/叁 mờ ở góc |
| H6 | Lật thẻ một từ 1–4 chữ | Chữ Hán nằm trong ô chữ điền nét đứt, mỗi chữ một ô; đổi cỡ chữ Hán trong Cài đặt thì ô to/nhỏ theo; điện thoại 375px từ 4 chữ vẫn vừa một hàng |
| H7 | Danh sách bài (6 phần) có bài đã xong | Góc thẻ bài là dấu triện đỏ "完", nền thẻ màu giấy |
| H8 | Học xong một bài | Dấu "完成" đập xuống rồi đứng yên; máy bật "giảm chuyển động" thì hiện luôn, không chạy |
| H9 | Giao diện tối | Khối Hôm nay, ô chữ điền, dấu triện, hoạ tiết cấp đều đọc rõ, không chói |

## I. Quỹ thưởng, top chăm học và bé túi Shopee — giao diện chưa có backend (06/11/2026)

Danh sách top là dữ liệu minh hoạ; tiền quỹ do admin nhập (phần J). Kiểm ở 320 / 375 / 768 / 1280px, sáng và tối.
"Máy" là đã chạy trên trình duyệt máy dev ngày 06/11 bằng khổ giả lập; cột "ĐT thật" còn trống, chưa thử trên thiết bị thật.

| # | Làm gì | Mong đợi | Máy | ĐT thật |
|---|---|---|---|---|
| I1 | Học viên mở Bàn học và các màn chọn cấp (Luyện viết, Ngữ pháp…) trên điện thoại | Cùng một cụm túi, số tiền quỹ, cúp ở thanh đầu trang; không còn nơi có nơi không | Đạt | |
| I2 | Khổ 320px, xem thanh đầu trang | Cả cụm + chuyển nhanh + chuông + tài khoản vừa một hàng, không tràn ngang; chữ tiền ≥ 12px | Đạt | |
| I3 | Khổ 320px, xem thẻ cấp có nhãn "Đang học"/"Sắp có" | Nhãn xuống dòng, không bị cắt, trang không tràn ngang | Đạt | |
| I4 | Bấm số tiền quỹ | Hộp "Cùng học, cùng tiến bộ": tiêu đề chỉ một lần, không có khung lồng khung, có ngày cập nhật số tiền | Đạt | |
| I5 | Bấm Xem cuộc thi rồi Xem bảng xếp hạng | Tiêu đề ghi đúng tháng/năm; tab Cuộc thi báo chưa có kết quả, có đường về Thông tin cuộc thi | Đạt | |
| I6 | Bấm cúp | Top 10; ba hạng đầu có huy chương; tên và số phút đọc rõ; cuộn được, không tràn ngang | Đạt | |
| I7 | Bấm túi, rồi bấm số tiền ở thanh đầu trang | Cả hai đều mở cùng hộp quỹ, không còn hộp Shopee riêng | Đạt | |
| I8 | Mở bất kỳ hộp thoại nào | Bé túi tạm ẩn, đóng hộp thì hiện lại | Đạt | |
| I9 | Bật link Icon, F5 rồi vào màn chọn bài hoặc đang làm bài (lật thẻ, bài tập, kiểm tra, ôn tập, đọc giáo trình) trên điện thoại/máy tính | Bé túi được phép hiện trên mọi màn `/learn/`, kể cả có thể che nút theo yêu cầu 06/11; dialog/nhập liệu vẫn tạm ẩn | Test route đạt; chưa kiểm lại UI | |
| I10 | Bấm bé hoặc lời nhắn, rồi đổi sang màn học khác | Mở link ở tab mới, bé ẩn và vẫn ẩn khi đổi màn; F5 thì hiện lại (chính sách đang để test) | Đạt | |
| I11 | Xem bé đi trên màn chọn cấp | Chữ chỉ hiện khi bé đứng yên; lúc đi có dấu 👆; rê chuột vào thì dừng và hiện chữ | Đạt | |
| I16 | Điện thoại: bấm quỹ, cuộc thi, cúp | Hộp nằm sát dưới thanh đầu trang, không bị đẩy xuống đáy; hộp dài cuộn được, thấy đủ nút cuối ở màn 320×568 | Đạt | |
| I14 | Nhìn bé đi trên nền trơn | Không có lớp mờ hay vệt bóng đi theo; bé nhún, nghiêng theo đà, chân tay cử động | Đạt | |
| I15 | Nhìn bé đứng khoảng 10 giây | Tự đổi động tác (vẫy tay, nhảy, nhún nhảy, ngó, nháy mắt, xoay), chữ lời nhắn đứng yên | Đạt | |
| I12 | Bật "giảm chuyển động" ở hệ điều hành | Bé đứng yên, nói một lần rồi im; không có nhún, chớp | Chưa thử | |
| I13 | Giao diện tối: thanh đầu trang và 3 hộp thoại | Chữ đọc rõ, không chói | Đạt | |

Cần đối chiếu với điều khoản tiếp thị: lời nhắn của bé nói "Chạm là góp quỹ thưởng rồi", nhưng chưa có căn cứ là chỉ bấm đã sinh hoa hồng.

## J. Link Shopee: quản trị và người học (06/11/2026)

Mở `/admin/support` bằng tài khoản admin, một tab khác mở `/learn/flashcards`. "Máy" là đã chạy trên web local ngày 06/11. Lưu ý bước J8 không đi hết sang Shopee vì đó là link thử.

| # | Làm gì | Mong đợi | Máy |
|---|---|---|---|
| J1 | Bấm Thêm link | Không còn "Hiện từ ngày", "Đến ngày", "Bật ngay sau khi lưu"; Hiện ở đâu có Icon và Quỹ tháng | Đạt |
| J2 | Lưu link không phải shopee.vn, s.shopee.vn, shope.ee | Báo lỗi ở link, không lưu | Có sẵn từ trước, chưa thử lại |
| J3 | Gắn Icon cho link A, rồi bấm Thêm link hoặc sửa link B | Cả 2 ô vị trí mờ và không bấm được, Quỹ tháng được chọn sẵn, có dòng báo link A đang giữ Icon | Đạt |
| J3b | Sửa link A đang giữ Icon | 2 ô vẫn bấm được; bỏ Icon xong thì link khác gắn được | Sửa được đạt; nhường Icon có test API |
| J4 | Gắn Quỹ tháng cho nhiều link, bấm "Ghé Shopee" trong hộp quỹ | Mở màn sản phẩm liệt kê đủ link, bấm link mở tab mới, có nút Quay lại quỹ | Đạt, 9 link; chưa đi hết sang Shopee |
| J4b | Ở màn sản phẩm, gõ `khan giay`, gõ `田`, gõ từ không có | Lọc đúng cả khi không dấu; từ không có thì báo "Không tìm thấy sản phẩm"; cuộn danh sách thì ô tìm vẫn ở trên cùng | Đạt |
| J5 | Công tắc tổng **tắt** | Người học không có bé túi, hộp quỹ không có phần Shopee, `/go/{id}` về Bàn học | Đạt |
| J6 | Công tắc tổng **bật** rồi quay lại tab học | Bé và link hiện đúng vị trí, không cần tải lại trang | Bé và link đạt khi tải lại trang; tự cập nhật khi quay lại tab chưa thử |
| J7 | Tắt riêng một link, hoặc xoá | Link đó không hiện nữa | Có test API |
| J8 | Bấm bé, bấm link trong hộp quỹ | Mở tab mới qua `/go/{id}`, bé ẩn; số Bấm trong bảng admin tăng ngay | Đạt |
| J9 | Mở hộp quỹ khi có link | Số Hiển thị trong bảng admin tăng | Đạt |
| J10 | Khối Quỹ tháng: gõ `1250000abc` | Ô chỉ nhận số, tự thành `1.250.000` | Đạt |
| J11 | Bấm Lưu số tiền, mở trang học | Thanh đầu trang, hộp quỹ, hộp cuộc thi đều hiện đúng số; có tháng và ngày cập nhật; không còn chữ "minh họa" ở hai hộp này | Đạt |
| J12 | Để trống rồi lưu | Người học thấy "Đang cập nhật", khổ 320px không tràn | Hiển thị đạt; xoá có test API |
| J13 | Sang tháng mới mà chưa lưu lại | Vẫn hiện tháng cũ của số tiền đó; bấm Lưu thì đổi sang tháng mới | Có test API |

Kiểm tra tự động: `api/tests/Unit/SupportLinksTest.php`.

## K. Cuộc thi: tạo bài thi và đếm ngược (06/11/2026)

| # | Làm gì | Mong đợi | Máy |
|---|---|---|---|
| K1 | Vào `/admin/contests` bằng tài khoản biên tập | Bị chặn, chỉ quản trị mở được | Có test route API; màn chưa thử |
| K2 | Tạo bài thi: tên, giờ bắt đầu, số phút thi, chọn đề | Không còn ô giờ kết thúc, ô Cấp và "+ Tạo đề mới"; danh sách đề lấy từ tab Đề thi | Form đạt; lưu có test API |
| K2b | Tab Đề thi: nhập tên, Thêm đề; nút Soạn câu | Đề mới hiện "Chưa có câu"; mở trang soạn tạm, nút quay lại về tab Đề thi; không hiện ở Thi thử cuối cấp | Đạt |
| K3 | Số phút thi sai | Báo lỗi, không lưu | Có test API |
| K3b | Chọn đề chưa có câu | Lưu được, form và bảng cảnh báo; người học đến giờ thấy "Đề thi đang được cập nhật" | Form đạt; lưu có test API |
| K4 | Chọn đề đang Công khai | Form cảnh báo người học làm trước được | Chưa thử |
| K5 | Xoá đề đang gắn bài thi ở `/admin/mock-tests` | Báo đề đang dùng, không xoá | Có test API |
| K6 | Người học mở hộp quỹ khi chưa có bài thi | Nhãn "Chưa có lịch", không có đồng hồ | Có test API; màn chưa thử |
| K7 | Có bài thi sắp diễn ra | Nhãn Sắp diễn ra, đồng hồ chạy từng giây; còn trên 1 ngày thì có ô Ngày | Đạt |
| K8 | Chờ đồng hồ về 0 | Không tải lại trang, tự thành Đang diễn ra, đồng hồ đếm tới giờ đóng, nút Vào thi ngay | Đạt |
| K9 | Bấm Vào thi ngay | Vào được đề Nháp, làm bài được; bé túi được phép hiện như các màn học khác | Chưa kiểm lại UI sau đổi phạm vi bé túi |
| K10 | Trước giờ thi, gõ thẳng đường dẫn đề Nháp | Báo đề chưa mở | Có test API |
| K11 | Vào thi muộn, để đồng hồ về 0, không bấm nộp | Đồng hồ đếm tới lúc hết giờ thi của cả cuộc thi; về 0 thì màn khoá "Hết giờ", tự nộp câu đã chọn, ra kết quả "hết giờ tự nộp" | Đạt |
| K11b | Đóng tab trước khi hết giờ, người khác mở bảng xếp hạng sau giờ đóng | Lượt đó được chấm theo câu đã lưu, thời gian tính tới hạn nộp | Có test API |
| K11c | Tắt mạng đúng lúc hết giờ | Màn vẫn khoá, tự thử nộp lại mỗi 5 giây | Chưa thử |
| K13 | Bấm Nộp bài khi cuộc thi còn mở | Có kết quả đúng mấy câu, không hiện đáp án, báo đáp án hiện khi kết thúc; không có nút Làm lại | Đạt |
| K14 | Đã nộp, mở lại hộp quỹ | Nút thành Xem bảng xếp hạng; gõ thẳng đường dẫn đề chỉ thấy kết quả | Nút đạt; đường dẫn có test API |
| K15 | Bảng xếp hạng có nhiều người | Đúng nhiều xếp trên; bằng câu đúng thì làm nhanh hơn xếp trên; dòng của mình tô nền, có "(bạn)"; 3 hạng đầu có huy chương | Đạt |
| K16 | Mình ngoài top 50 | Vẫn có dòng hạng của mình ở cuối | Có test API |
| K17 | Hết giờ cuộc thi, mở lại kết quả | Đáp án các câu sai hiện ra | Có test API; màn đã thấy ở K11 |
| K18 | Thi thử thường | Vẫn làm lại được nhiều lần như cũ | Có test API |
| K12 | Nút Xem cuộc thi | Rộng hết hộp, nền đỏ khi có bài thi, dễ thấy; 320px và nền tối không vỡ | Đạt |

| K19 | Trước giờ thi bấm Đăng ký thi | Báo đã đăng ký, số người tăng, nút Huỷ đăng ký có trong hộp cuộc thi | Đạt (giả lập trạng thái sắp thi vì bài thi của bạn đang mở; lệnh đăng ký chạy thật) |
| K20 | Tab Đăng ký ở Top chăm học; nút số người ở admin | Thấy danh sách theo thứ tự đăng ký; admin có thêm email | Tab đạt; admin chưa mở |
| K21 | Đến giờ mà chưa đăng ký | Không có nút vào thi, báo chưa đăng ký; gõ thẳng đường dẫn bị chặn; không đăng ký muộn được | Đạt; chặn có test API |
| K22 | Đã đăng ký, đến giờ | Có nút Vào thi ngay, làm bài được | Đạt |
| K23 | Sửa cơ cấu giải ở admin, tổng quá 100% | Không cho lưu; hợp lệ thì hộp cuộc thi hiện đúng tên và số tiền | Hiện đạt; chặn có test API |
| K23b | Đã đăng ký, mở trang sau giờ bắt đầu nhưng trước giờ đóng | Hiện ngay nút **Vào thi ngay**; vào được dù muộn, đồng hồ/hạn nộp chỉ còn tới giờ đóng chung | Có test API |
| K23c | Đã đăng ký nhưng không bấm vào thi; chờ quá giờ đóng rồi mở bảng xếp hạng | Có tên ở cuối bảng, `0/tổng số câu`, nhãn **Không tham gia · 0 điểm**; không tạo lượt thi giả | Có test API |
| K23d | Đang ở hộp quỹ khi đồng hồ bắt đầu về 0 | Tự tải lại trạng thái, nút **Đăng ký/Xem thông tin** đổi thành **Vào thi ngay** mà không cần F5 | Luồng có `onend`; cần kiểm tay |
| K23e | Bấm vào đúng hoặc sau giờ đóng | Không còn nút Vào thi; server không cho tạo lượt mới | Có test API |

Kiểm tra tự động: `api/tests/Unit/ContestTest.php`.

### Hộp quỹ sau khi sắp lại

| # | Làm gì | Mong đợi | Máy |
|---|---|---|---|
| K24 | Máy tính, bấm Quỹ tháng | Thấy đủ đồng hồ, số tiền, dòng Shopee ngay dưới số tiền, ba giải đầu, nút chính, không phải cuộn | Đạt (cao 758px trong màn 846px) |
| K25 | Điện thoại 375px, bấm Quỹ tháng | Shopee và cơ cấu giải trong màn đầu; nút chính cuộn nhẹ khoảng 25px là đủ | Đạt |
| K26 | Bấm Xem thể lệ | Hộp cuộc thi hiện đủ các giải kèm phần trăm | Đạt |
| K27 | Bảng xếp hạng có người | Hạng có giải hiện tên giải và số tiền dưới tên người | Đạt |
| K28 | Chưa có lịch thi, mở hộp quỹ | Không có nút Xem cuộc thi; có **Lịch sử thi & thưởng** để xem các tháng trước | Giao diện đạt; lịch sử đang dùng dữ liệu mẫu |
| K29 | Mở Lịch sử thi & thưởng, chọn một tháng và thử nhận thưởng | Thấy tháng, hạng, số tiền, trạng thái; form ngân hàng chỉ là bản xem trước và ghi rõ chưa gửi dữ liệu thật | Giao diện mẫu, chưa có API |
| K30 | Rê chuột/focus vào các nút có tooltip trên các màn vừa sửa | Tooltip đồng bộ nền, màu, cỡ chữ; không còn tooltip mặc định của trình duyệt | Cần kiểm tay |

## L. Giáo trình: cha của bài học (06/11/2026)

| # | Làm gì | Mong đợi | Máy |
|---|---|---|---|
| L1 | Sau migration 013 mở `/admin/curricula` | Có "Giáo trình HSK chuẩn" giữ đủ 40 bài, 932 từ; số dòng các bảng không đổi | Đạt (chạy thử trên bản sao DB trước) |
| L2 | Thêm giáo trình mới | Khối mới, mọi cấp "Chưa có"; có nút xoá, giáo trình mặc định không có | Đạt |
| L3 | Soạn bài ở giáo trình mới, Thêm bài | Bài là Bài 1 của giáo trình đó, không đụng Bài 1 của bộ cũ; đường dẫn giữ giáo trình | Đạt |
| L4 | Thêm từ đã có ở bộ khác | Không báo trùng; thêm lần hai trong cùng bộ thì báo trùng | Đạt; có test API |
| L5 | Xem như người học ở bài Nháp | Mở đúng bài của giáo trình đó, không ghi tiến độ | Mở bài đạt; phần không ghi tiến độ chưa thử trên màn |
| L6 | Gán học viên vào bộ mới | Bàn học và mọi phần chỉ còn bài của bộ đó; cấp chưa có bài hiện Đang soạn; mở bài bộ khác báo 404 | Đạt |
| L7 | Gán lại bộ cũ | Tiến độ bộ cũ còn nguyên | Có test API |
| L8 | Xoá giáo trình còn bài, xoá giáo trình mặc định | Báo lỗi, không xoá | Đạt |
| L9 | Xoá giáo trình trống đang gán học viên | Học viên về giáo trình mặc định | Có test API |
| L10 | Giáo trình nhớ ở máy đã bị xoá, mở Bài học | Tự về giáo trình mặc định, không báo lỗi | Đạt |
| L11 | Đăng nhập biên tập, mở Bài học | Chọn được giáo trình, không có nút Tạo giáo trình; không vào được `/admin/curricula`, `/admin/exams` | Đạt |

Kiểm tra tự động: `api/tests/Unit/CurriculumTest.php`.

## M. Nút không dùng mũi tên thẳng (06/11/2026)

Kiểm ở 375px và 1280px, giao diện sáng/tối. Các mục dưới là checklist thủ công, chưa đánh dấu đạt chỉ dựa vào build/test.

| # | Làm gì | Mong đợi | Kết quả |
|---|---|---|---|
| M1 | Mở hộp cuộc thi đang diễn ra và đã nộp | Vào thi ngay/Tiếp tục làm bài, Xem bảng xếp hạng chỉ có chữ; nút vẫn mở đúng nơi | Chờ kiểm tay |
| M2 | Mở Bàn học, thẻ cấp HSK | Học tiếp/Bắt đầu học, Xem các bài không có mũi tên; nút Tổng hợp dùng chevron | Chờ kiểm tay |
| M3 | Gõ chữ/Trắc nghiệm, kiểm tra, Ngữ pháp, Luyện viết | Tiếp tục, Câu tiếp, Điểm sau, Bỏ qua không có mũi tên; bấm vẫn chuyển đúng nội dung | Chờ kiểm tay |
| M4 | Mở chuông và trang thông báo | Xem tất cả thông báo và nút liên kết không có mũi tên; liên kết hoạt động | Chờ kiểm tay |
| M5 | Quay lại ở màn học/tài khoản/cài đặt/đăng nhập/hộp toàn màn | Chevron hướng trái, nhãn hỗ trợ đọc màn hình còn nguyên; bấm về đúng nơi | Chờ kiểm tay |
| M6 | Chuyển từ trước/sau, trang sách, phân trang học viên/phiên admin | Chevron đúng chiều; không có nút trống; nút đầu/cuối bị vô hiệu đúng như trước | Chờ kiểm tay |
| M7 | Mở sách, học viên, Bài tiếp Ngữ pháp; bung hội thoại tổng hợp | Chevron gọn, hành động/mở gập đúng; không tràn chữ ở điện thoại | Chờ kiểm tay |

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
