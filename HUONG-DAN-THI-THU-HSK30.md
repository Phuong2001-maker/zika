# Thi thử HSK 3.0 — hướng dẫn (10/11/2026)

Kho đề luyện thi theo **định dạng HSK 3.0**, dùng chung cho **mọi giáo trình**: học viên học Hán ngữ hay HSK 3.0 đều
thấy cùng một kho đề. Không gắn với cây Giáo trình → Ẩn/hiện nội dung; tắt/bật ở Cài đặt → Quyền truy cập & hiển thị
→ Các phần học → **Thi thử HSK 3.0** (`learn.hsk30`, mặc định bật cho mọi vai trò).

Ý tưởng tham khảo từ các trang luyện thi HSK (đề đủ 4 phần, luyện tập/thi thật, ghi âm phần nói) nhưng **giao diện và
nội dung là của Zìkǎ**, không chép câu hỏi, âm thanh, hình của trang nào.

## Người học

- **Chuyển nhanh → Thi thử HSK 3.0** (`/learn/hsk30`): chọn cấp HSK 1–6, mỗi đề có số câu, thời gian, số câu từng
  phần, điểm thi thật tốt nhất.
- **Luyện tập** (`/learn/hsk30/{id}?mode=practice`): không tính giờ, nghe không giới hạn; mỗi câu bấm **Kiểm tra** thấy
  đúng/sai, đáp án, giải thích; câu nói bấm **Xem bài mẫu** rồi tự đánh giá Tốt / Tạm được / Chưa được.
- **Thi thật** (`/learn/hsk30/{id}`): tính giờ, mỗi đoạn nghe tối đa 2 lần, hết giờ tự nộp, không xem đáp án giữa
  chừng. Thanh trên cùng giống màn Kiểm tra: đang thi thì không có lối sang màn khác.
- Luyện tập và thi thật là **hai lượt riêng**, làm dở cái nào thì vào lại tiếp cái đó.
- **Kết quả**: tỉ lệ đúng chung và **từng phần** (Nghe, Đọc, Viết); phần Nói không chấm tự động, chỉ đếm số câu đã nói
  và cho nghe lại bản ghi cạnh bài mẫu; danh sách câu sai kèm đáp án, giải thích.
- **Ghi âm**: ngay trong trình duyệt (micro), có thời gian chuẩn bị, vạch âm lượng, nghe lại, ghi lại. Bản ghi **chỉ
  nằm trên máy người học** (IndexedDB), không gửi lên server, tự xoá sau 7 ngày. Cần web chạy **https** và cho phép
  micro; iPhone ghi được (định dạng mp4).

## Quản trị (quyền E: biên tập và quản trị)

- **Quản trị → Đề HSK 3.0** (`/admin/hsk30`): đề theo cấp, công tắc Công khai, Soạn câu, Xoá. **Tạo đề** điền sẵn thời
  gian gợi ý theo cấp (HSK 1: 40 câu/32 phút … HSK 6: 82 câu/115 phút — `GOI_Y_CAP` trong `lib/learn/thi-hsk/cau-truc.ts`).
- **Soạn đề** (`/admin/hsk30/{id}`): câu gom theo 4 phần, mỗi phần chỉ có dạng câu hợp lệ:
  Nghe — `listen`, `pic` · Đọc — `pic`, `blank`, `pick`, `tf`, `match` · Viết — `order`, `toZh`, `blank` · Nói — `speak`.
  Mọi câu gắn được **hình minh hoạ** từ kho hình; câu **chọn tranh** (`pic`) chọn 2–4 hình làm phương án.
- Câu nói có 5 kiểu: nghe và nhắc lại, đọc to, trả lời câu hỏi, nói theo chủ đề, nhìn tranh nói; thời gian chuẩn bị,
  thời gian nói, bài nói mẫu + nghĩa.
- Đề mới luôn là **Nháp**; học viên chỉ thấy đề **Công khai**. Đề không có điểm đạt — chấm theo tỉ lệ đúng.
- **Nhập Excel** (nút ở đầu màn soạn): tải file mẫu, mỗi dòng một câu, cột **Phần** ghi Nghe / Đọc / Viết (để trống thì
  câu nghe vào Nghe, sắp xếp và chọn câu tiếng Trung vào Viết, còn lại vào Đọc). Câu chọn tranh và câu nói soạn trên web.
- **Đề cuộc thi dùng chung màn soạn này** (10/11): Quản trị → Đề thi → Soạn câu mở `/admin/exams/{id}`, soạn y như đề
  HSK 3.0. Khác: không có công tắc Công khai (đề cuộc thi luôn Nháp, chỉ mở cho người đã đăng ký trong giờ thi), không đặt
  thời gian (theo số phút của cuộc thi). Học viên làm cuộc thi ở màn thi HSK 3.0 (`/learn/cuoc-thi/{id}`, luôn thi thật,
  mỗi người một lượt, đáp án ẩn tới khi cuộc thi kết thúc).

## Kỹ thuật

- Migration `027_thi_thu_hsk30.sql`: `mock_tests.kind` (`mock` thi thử cuối cấp cũ / `hsk30`), `questions.section`
  thêm `writing`, `speaking`, `test_attempts.mode` (`exam` / `practice`).
- Dùng lại bộ máy đề thi có sẵn (`TestService`): `start` nhận `mode`, `POST /tests/attempts/{id}/check` (chỉ luyện
  tập), kết quả thêm `sections`, `speaking`. Câu `speak` không chấm (`QuestionGrader::UNGRADED`), điểm 0, không vào
  điểm tối đa. Đề HSK 3.0 luôn xếp Nghe → Đọc → Viết → Nói.
- API: `GET /learn/hsk30` (người học) · `GET/POST /admin/hsk30` · sửa/xoá/soạn câu dùng `/admin/mock-tests/{id}…`.
- Quyền: `FeatureAccess` key `learn.hsk30`; đường dẫn `/tests/*` của đề hsk30 xét theo quyền này (không theo
  `learn.tests`). `ContentVisibility` bỏ qua đề hsk30.
- Web: `lib/learn/thi-hsk/` (`state.svelte.ts` trạng thái màn làm đề, `GhiAm.svelte`, `ghi-am.ts`, `KetQuaHsk.svelte`,
  `hinh.ts` danh mục hình, `cau-truc.ts`), route `routes/learn/hsk30`, `routes/admin/hsk30`.
- **Kho hình**: 75 hình SVG tự vẽ bằng `data/hsk30/hinh/ve.py` → `web/static/hinh-hsk/` (mỗi hình 1–2 KB, đi theo bản
  build web). Thêm hình: thêm vào `hinh.ts` và `ve.py` rồi chạy lại script.
- **Đề**: `data/hsk30/` (đặc tả `README.md`, `kiem_de.py` kiểm, `nap_de.py` nạp qua API). Đề do Zìkǎ tự soạn nên có
  trong git.
- **Giọng đọc**: `data/nap-sach/viec_doc.py` gom thêm `audioText` và `sample` của mọi đề hsk30; chạy
  `sinh-giong-doc.ps1` như các phần khác. Chưa có file giọng thì web đọc bằng giọng trình duyệt.
  Hội thoại "男：… 女：… 问：…" đọc hai giọng trong cùng một file (bỏ nhãn, nam giọng nam, nữ giọng nữ, câu hỏi giọng
  người học chọn) — xem "Hội thoại đề HSK 3.0" trong `data/nap-sach/HUONG-DAN-GIONG-DOC.md`.
- **Khung HSK 3.0** = `TestService::khungHsk30()`: đề `kind = 'hsk30'` hoặc đề cuộc thi (`for_contest = 1`). Đề khung này
  nhận phần viết/nói, dạng câu nói/chọn tranh, xếp câu theo phần; soạn ở `lib/admin/SoanDeHsk.svelte`, làm ở
  `ThiHskRunner` (`cuocThi` cho cuộc thi). Chế độ luyện tập chỉ có ở đề `hsk30`.

## Đưa lên server thật (chưa làm)

1. Đưa code mới (web build có `hinh-hsk/`, PHP).
2. `php app/tools/install.php --migrate` (migration 027).
3. Nạp đề: chạy `nap_de.py` trỏ `ZIKA_API` vào server, hoặc xuất từ DB local.
4. Chép file giọng mới (`uploads/tts/...`) như các lần trước; xoá `storage/cache/content`.
5. Soát đề rồi bật Công khai.

## Test

- `api/tests/Unit/Hsk30Test.php`: tạo đề, câu nói/chọn tranh, danh sách chung mọi giáo trình, thi thật giấu đáp án,
  luyện tập xem đáp án và tách lượt, chấm không tính câu nói và kết quả theo phần, quyền riêng và lượt đang làm, đề nháp.
- `web/src/lib/learn/thi-hsk/thi-hsk.test.ts`: quyền đường dẫn, câu nói đã làm, định dạng ghi âm, lỗi ghi âm, gom phần,
  kho hình.
- 10/11 (chưa chạy): `Hsk30Test::testDeCuocThiSoanTheoKhungHsk30` (đề cuộc thi nhận phần viết/nói, cuộc thi luôn thi thật,
  thứ tự phần), `import.test.ts` (nhập Excel có cột Phần), `test_doc_edge.py` (tách hội thoại 男/女).
- Kịch bản thủ công: mục "Thi thử HSK 3.0" trong `KICH-BAN-TEST.md`.
