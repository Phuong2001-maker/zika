def tach_chu_viet(text: str) -> list[str]:
    """
    Danh sách 会认字 — chữ cần NHẬN BIẾT — ở phần Tổng kết bài học.

    Lấy 会认字 chứ **không** lấy 会写字. Hai mục này in cạnh nhau trên cùng trang Tổng kết
    và 会写字 là tập hẹp hơn: bài 13 in 31 chữ cần nhận biết nhưng chỉ 15 chữ cần biết viết.
    Bản tách cũ ưu tiên 会写字 nên thiếu 66 chữ trên toàn sách.

    Bài 8 sách in tiêu đề 会认词 (chữ 词) chứ không phải 会认字, nên nhận cả hai.

    Không dựa vào thứ tự nhãn: PyMuPDF trả 句式 ra **trước** 会认字 ở bài 5, 9, 10, 11 nhưng
    sau nó ở các bài còn lại, nên phải chặn cuối khối bằng cả ba nhãn 会写字 / 句式 / 学习小结.
    Bài 1 không có mục Tổng kết nên trả về rỗng.

    Lọc bằng dải HANZI nên tự loại dòng phụ đề tiếng Việt ("Các chữ cần nhận biết") và dấu
    phân cách 、，. Bỏ trùng mà giữ thứ tự in trong sách: bài 7, 14 liệt kê theo **từ**
    (现在、早上、中学生…) nên cùng một chữ xuất hiện nhiều lần.
    """
    m = re.search(
        r'会认[字词][^\n]*\n(.*?)(?=\n\s*(?:会写[字词]|句式|学习小结)|\Z)', text, re.S)
    if not m:
        # Chỉ khi sách thiếu hẳn mục 会认字 mới đành lấy 会写字 — đừng đảo lại thứ tự này,
        # đảo là tái hiện đúng con bug cũ.
        m = re.search(
            r'会写[字词][^\n]*\n(.*?)(?=\n\s*(?:会认[字词]|句式|学习小结)|\Z)', text, re.S)
    if not m:
        return []
    ra = []
    for c in re.findall(r'[' + HANZI + r']', m.group(1)):
        if c not in ra:
            ra.append(c)
    return ra
