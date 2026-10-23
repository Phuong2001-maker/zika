// ===== Dat canh cac hang so san co o dau file (sau HANZI / RAC) =====

PY_DAU = 'āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜüĀÁǍÀĒÉĚÈĪÍǏÌŌÓǑÒŪÚǓÙÜ'

# Dòng pinyin “sạch”: chỉ chữ Latin + dấu thanh pinyin, không ký tự riêng của tiếng Việt.
PY_SACH = re.compile(r"[A-Za-z" + PY_DAU + r"0-9 ,.;:?!'\"()\[\]/\-–—…·’‘“”：，。？！、]*$")

NGUYEN_AM = r'[' + PY_DAU + r'aeiou]'

# Tên “người nói” của các dòng nhãn mục, không phải nhân vật hội thoại.
NHAN_MUC = re.compile(
    r'^(?:Luyện tập|Nhiệm vụ|Ví dụ|Chú thích|Bài khóa|Bài khoá|Từ mới|Ngữ pháp|Mẫu câu'
    r'|Tổng kết|Mở rộng|Ghi chú|LƯU Ý|Lưu ý|Bài \d+|Kuòzhǎn|Liànxí)\b', re.I)


def la_pinyin(d: str) -> bool:
    """
    Chặt hơn phép thử ở dòng liền kề, vì nó dùng cho việc dò xa hơn: dòng tiếng Việt
    (“dụng các từ vựng…”) cũng không có chữ Hán và cũng có nguyên âm, chỉ phân biệt được
    nhờ các ký tự riêng tiếng Việt (ụ ừ ơ đ ã…) không nằm trong bộ dấu pinyin.
    """
    if not d or re.search(r'[' + HANZI + r']', d):
        return False
    return bool(re.search(NGUYEN_AM, d)) and bool(PY_SACH.match(d))


def la_noi_tiep(d: str) -> bool:
    """Dòng chữ Hán thuần là phần đuôi của câu bị ngắt dòng — không tên người nói, không chữ Latin."""
    if not d or not re.search(r'[' + HANZI + r']', d):
        return False
    return not re.search(r'[A-Za-zÀ-ỹ：:]', d)


def tim_pinyin(dong: list[str], k: int) -> str:
    """
    Pinyin thường nằm ngay dòng sau câu tiếng Trung, nhưng đúng ba câu thật trong sách bị
    lệch: bài 5 và bài 7 có một dòng trống chen vào, bài 15 có câu dài ngắt làm hai dòng
    chữ Hán. Thử dòng liền kề trước bằng luật cũ (nới tay, để không đổi kết quả đang đúng),
    không được thì dò thêm tối đa 3 dòng — bỏ qua dòng trống và **một** dòng đuôi tiếng
    Trung — lần này đòi pinyin sạch để không vớ phải dòng tiếng Việt.
    """
    if k + 1 < len(dong):
        nx = dong[k + 1]
        if nx and not re.search(r'[' + HANZI + r']', nx) and re.search(NGUYEN_AM, nx):
            return nx.strip()

    da_noi_tiep = False
    for j in range(k + 1, min(k + 4, len(dong))):
        d = dong[j]
        if not d:
            continue
        if la_pinyin(d):
            return d.strip()
        if not da_noi_tiep and la_noi_tiep(d):
            da_noi_tiep = True
            continue
        break
    return ''


def tach_hoi_thoai(text: str) -> list[dict]:
    """
    Mỗi 课文 là một đoạn hội thoại. Dòng tiếng Trung có khuôn "Tên：câu", dòng ngay sau là
    pinyin. Bản dịch tiếng Việt nằm ở khung khác, khuôn "Tên: câu tiếng Việt".

    Khối 课文 cuối mỗi bài nuốt luôn phần 练习 nằm sau nó, nên lọt vào 23 câu là đáp án mẫu
    của 选词填空 / sắp xếp từ. **Không cắt được theo nhãn 练习**: nhãn nằm ở lề trang nên
    PyMuPDF trả ra sai chỗ — bài 5 không có chữ 练习 nào (sách ghi “Luyện tập tổng hợp”),
    còn bài 15 nhãn lại ra trước cả 课文三 nên cắt theo nó mất 4 câu thật.

    Dấu hiệu chắc chắn là **thiếu pinyin**: sách chỉ in pinyin cho bài khoá, không in cho
    đáp án luyện tập. Cả 23 câu rác đều có dòng kế tiếp là mục luyện tập khác (chữ Hán kèm
    dấu “：”) hoặc câu dẫn tiếng Việt, nên không câu nào vớ được pinyin giả.
    Thêm lưới phụ bắt câu còn giữ dấu điền chỗ trống.
    """
    doan = []
    phan = re.split(r'课文\s*([一二三四五六])?\s*\n?\s*Kèwén', text)
    # phan[0] là phần trước bài khoá đầu tiên.
    for i in range(1, len(phan), 2):
        than = phan[i + 1] if i + 1 < len(phan) else ''
        cau = []
        dong = [d.strip() for d in than.split('\n')]
        for k, d in enumerate(dong):
            m = re.match(r'^([' + HANZI + r']{1,6})\s*[：:]\s*(.+)$', d)
            if not m:
                continue
            noi_dung = m.group(2).strip()
            if not re.search(r'[' + HANZI + r']', noi_dung):
                continue

            py = tim_pinyin(dong, k)
            # Không có pinyin → đáp án luyện tập, không phải bài khoá.
            if not py:
                continue
            # Lưới phụ: đáp án mẫu còn giữ dấu điền chỗ trống.
            if '_' in noi_dung or re.search(r'（\s*）|\(\s*\)', noi_dung):
                continue

            cau.append({'ten': m.group(1), 'zh': noi_dung, 'pinyin': py, 'vi': ''})

        if len(cau) < 2:
            continue
        doan.append({'cau': cau, 'boi_canh': ''})

    # Bản dịch nằm ở khung riêng nên PyMuPDF trả nó ra ngoài khối 课文 — phải quét cả bài
    # rồi ghép theo thứ tự. Chỉ ghép khi tổng số câu Việt khớp tổng số câu Trung của cả bài,
    # lệch một câu là bỏ hết, thà thiếu bản dịch còn hơn gán nhầm câu.
    # Bỏ các dòng mà “tên người nói” thật ra là nhãn mục (“Luyện tập: sắp xếp từ thành câu”):
    # chúng làm tổng số câu Việt lệch đi một, giết luôn phép ghép của cả bài.
    viet = [v for v in cau_tieng_viet(text) if not NHAN_MUC.match(v[0])]
    tong = sum(len(d['cau']) for d in doan)
    if len(viet) == tong and tong > 0:
        i = 0
        for d in doan:
            for c in d['cau']:
                c['ten_vi'], c['vi'] = viet[i]
                i += 1
    return doan