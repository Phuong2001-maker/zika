def tach_mau_cau(text: str) -> list[dict]:
    """
    句式 MẪU CÂU ở phần 学习小结 cuối bài: bảng hai cột "MẪU CÂU | VÍ DỤ".

    Không dò số thứ tự dòng nữa — đó là lý do hàm cũ trả về rỗng. Sách in số 1, 2, 3… ở
    đầu mỗi dòng bảng, nhưng RAC đã xoá sạch mọi dòng chỉ có số (vì chân trang cũng chỉ có
    số), nên trong text số thứ tự chỉ còn lại là **dòng trống** — chính nó mới là thứ ngăn
    các dòng bảng. Bài 11 in số cuối là "7." có dấu chấm nên RAC không xoá, phải tự coi nó
    là dấu ngăn.

    PyMuPDF trả cột trái (mẫu câu) trước cột phải (ví dụ) trong cùng một dòng bảng, nên mỗi
    khối chỉ cần lấy các dòng tiếng Việt ở đầu khối làm tên, phần chữ Hán còn lại làm ví dụ.
    Tên có thể xuống dòng (bài 7, bài 15) và một mẫu câu có thể có 2 ví dụ (bài 4, bài 15).
    """
    m = re.search(r'句式', text)
    if not m:
        return []

    # Bỏ đầu bảng "句式 MẪU CÂU / MẪU CÂU / VÍ DỤ" (bài 9 in 句式 riêng một dòng).
    dau = re.search(r'VÍ\s*DỤ[^\n]*\n', text[m.start():])
    if not dau:
        return []
    than = text[m.start() + dau.end():]

    # Bảng kết thúc ở nhãn mục kế tiếp của phần Tổng kết bài học.
    het = re.search(r'会认字|会写字|会认词|会读字|学习小结|文化知识|生词|练习', than)
    if het:
        than = than[:het.start()]

    ra: list[dict] = []
    ten: list[str] = []
    vi_du: list[str] = []

    def chot() -> None:
        if ten and vi_du:
            ra.append({'ten': ' '.join(ten), 'vi_du': ' '.join(vi_du)})
        ten.clear()
        vi_du.clear()

    for d in than.split('\n'):
        d = chuan_hoa(d)
        # Dòng trống (chỗ số thứ tự bị RAC xoá) hoặc số thứ tự còn sót: hết một dòng bảng.
        if not d or re.fullmatch(r'\d{1,3}\.?', d):
            chot()
            continue
        # Cột trái là tiếng Việt; dòng tên xuống hàng có khi chỉ còn khuôn "……" bằng chữ Hán.
        if re.search(r'[A-Za-zÀ-ỹ]', d) or '…' in d:
            if vi_du:  # dòng bảng mới mà không có dấu ngăn nào
                chot()
            ten.append(d)
        elif re.search(r'[' + HANZI + r']', d):
            vi_du.append(d)
    chot()
    return ra