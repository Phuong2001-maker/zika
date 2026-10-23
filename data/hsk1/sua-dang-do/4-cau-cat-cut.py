« # ---------------------------------------------------------------------------
# Thêm 3 hằng số này cạnh HANZI / SO_BAI / RAC ở đầu file.
# ---------------------------------------------------------------------------

# Dấu kết thúc một câu. Ngoài 。？！ còn nhận ! ? … ” vì sách in lẫn dấu nửa chiều ở
# vài chỗ (bài 10 “真便宜!”, bài 15 “大家（ ）。”): coi chúng là hết câu để **không**
# ghép thêm gì vào một câu vốn đã trọn vẹn.
HET_CAU = '。？！…!?”'

# Nhãn mục in ở lề trang. PyMuPDF trả chúng ra lẫn giữa nội dung bài khoá nên phải
# loại riêng, không bao giờ là nửa sau của một câu hội thoại.
NHAN_MUC = re.compile(
    r'^(生词|专名|语法|课文|练习|注释|学习小结|会写字|会认字|句式|拼音|目标|任务)'
    r'[一二三四五六七八九十\d\s]*$')

# Chữ chỉ tiếng Việt mới có, pinyin không bao giờ dùng: ă â ê ô ơ ư đ, dấu ngã, và cả
# dải Ạ-ỹ. Dùng để khỏi nhận dòng tiếng Việt làm dòng pinyin — câu lệnh bài tập
# (“Hãy viết các số sau đây bằng tiếng Trung”) in ngay dưới bài khoá nên rất dễ vớ phải.
CHU_VIET_RIENG = re.compile(r'[ăâêôơưđĂÂÊÔƠƯĐãõũẽÃÕŨẼẠ-ỹ]')


# ---------------------------------------------------------------------------
# Ba hàm MỚI, đặt ngay trên tach_hoi_thoai().
# ---------------------------------------------------------------------------

def la_pinyin(d: str) -> bool:
    """Dòng pinyin: không có chữ Hán, không phải tiếng Việt, có chữ Latin và nguyên âm."""
    if not d or re.search(r'[' + HANZI + r']', d):
        return False
    if CHU_VIET_RIENG.search(d):
        return False
    if not re.search(r'[a-zA-ZüÜÀ-ɏ]', d):
        return False
    return bool(re.search(r'[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜaeiouüÜ]', d))


def la_phan_noi(d: str) -> bool:
    """
    Dòng có thể là nửa sau của một câu tiếng Trung bị tràn dòng.

    Chỉ nhận dòng **thuần tiếng Trung** (chữ Hán + dấu câu) hoặc **dãy số trơn** — hai
    số điện thoại ở bài 6 in ở dòng riêng, không có chữ Hán nào. Có chữ Latin là dòng
    tiếng Việt hoặc đầu đề bài tập (“2. 用“怎么”提问Đặt câu hỏi…”), ghép vào là hỏng câu.
    """
    d = d.strip()
    if not d or NHAN_MUC.match(d):
        return False
    if re.search(r'[A-Za-zÀ-ỹ]', d):        # lẫn chữ Latin → tiếng Việt / đầu đề
        return False
    if re.match(r'^[（(]\s*\d', d):          # “（1）” mở một mục bài tập mới
        return False
    if re.fullmatch(r'[\d\s]{3,}', d):       # dãy số trơn: số điện thoại
        return True
    return bool(re.search(r'[' + HANZI + r']', d))


def ghep_cau_tran_dong(dong: list[str], k: int, noi_dung: str, la_luot_moi) -> tuple[str, str]:
    """
    Ghép lại một câu hội thoại bị tràn sang dòng thứ hai trong PDF.

    Sách in mỗi dòng tiếng Trung kèm một dòng pinyin ngay dưới, nên một câu dài hai
    dòng ra **hai kiểu thứ tự** khác nhau tuỳ cách PyMuPDF gom khối:

        kiểu A (bài 5, 6, 14):   zh1 / py1 / zh2 / py2
        kiểu B (bài 15):         zh1 / zh2 / py1 / py2

    Vì vậy không đọc theo vị trí cố định (dòng k+1) mà quét tới lượt nói kế tiếp rồi bỏ
    mỗi dòng vào đúng giỏ theo thứ tự xuất hiện — đúng cho cả hai kiểu, không cần toạ độ.

    Dừng quét ngay khi gặp một dòng không phải pinyin cũng không phải phần nối: đó là
    nhãn mục hoặc đầu đề bài tập, và lấy thêm sẽ vớ phải pinyin của nhãn (“shēngcí”).
    """
    khoi = []
    for d in dong[k + 1:]:
        d = d.strip()
        if not d:
            continue
        if la_luot_moi(d) or not (la_pinyin(d) or la_phan_noi(d)):
            break
        khoi.append(d)
        if len(khoi) >= 6:
            break

    zh = [noi_dung]
    for d in khoi:
        # Câu đã trọn vẹn thì dừng — đây là thứ bảo vệ 205 câu vốn đã đúng.
        if zh[-1][-1:] in HET_CAU or len(zh) >= 3:
            break
        if la_pinyin(d):
            continue
        if not la_phan_noi(d):
            break
        zh.append(d)

    # Mỗi dòng tiếng Trung in kèm đúng một dòng pinyin, nên lấy đúng số dòng đã ghép.
    py = [d for d in khoi if la_pinyin(d)][:len(zh)]
    return ''.join(zh), ' '.join(py)


# ---------------------------------------------------------------------------
# THAY hàm tach_hoi_thoai() hiện có bằng bản này.
# ---------------------------------------------------------------------------

def tach_hoi_thoai(text: str) -> list[dict]:
    """
    Mỗi 课文 là một đoạn hội thoại. Dòng tiếng Trung có khuôn "Tên：câu", dòng ngay sau là
    pinyin. Bản dịch tiếng Việt nằm ở khung khác, khuôn "Tên: câu tiếng Việt".

    Câu dài tràn sang dòng thứ hai thì ghép lại bằng ghep_cau_tran_dong().
    """
    luot = re.compile(r'^([' + HANZI + r']{1,6})\s*[：:]\s*(.+)$')

    def la_luot_moi(d: str) -> bool:
        return bool(luot.match(d))

    doan = []
    phan = re.split(r'课文\s*([一二三四五六])?\s*\n?\s*Kèwén', text)
    # phan[0] là phần trước bài khoá đầu tiên.
    for i in range(1, len(phan), 2):
        than = phan[i + 1] if i + 1 < len(phan) else ''
        cau = []
        dong = [d.strip() for d in than.split('\n')]
        for k, d in enumerate(dong):
            m = luot.match(d)
            if not m:
                continue
            noi_dung = m.group(2).strip()
            if not re.search(r'[' + HANZI + r']', noi_dung):
                continue
            zh, py = ghep_cau_tran_dong(dong, k, noi_dung, la_luot_moi)
            cau.append({'ten': m.group(1), 'zh': zh, 'pinyin': py, 'vi': ''})

        if len(cau) < 2:
            continue
        doan.append({'cau': cau, 'boi_canh': ''})

    # Bản dịch nằm ở khung riêng nên PyMuPDF trả nó ra ngoài khối 课文 — phải quét cả bài
    # rồi ghép theo thứ tự. Chỉ ghép khi tổng số câu Việt khớp tổng số câu Trung của cả bài,
    # lệch một câu là bỏ hết, thà thiếu bản dịch còn hơn gán nhầm câu.
    viet = cau_tieng_viet(text)
    tong = sum(len(d['cau']) for d in doan)
    if len(viet) == tong and tong > 0:
        i = 0
        for d in doan:
            for c in d['cau']:
                c['ten_vi'], c['vi'] = viet[i]
                i += 1
    return doan


# ---------------------------------------------------------------------------
# Hàm cảnh báo MỚI, đặt cạnh tach_hoi_thoai().
# ---------------------------------------------------------------------------

def canh_bao_cau_cut(ket_qua: list[dict]) -> list[str]:
    """
    Liệt kê các câu tiếng Trung không kết thúc bằng 。？！ — dấu hiệu câu còn bị cắt cụt.

    Giữ luật chặt (chỉ 。？！) chứ không dùng HET_CAU: HET_CAU nới ra để **tránh ghép
    thừa**, còn cảnh báo thì cố tình bắt cả dấu ASCII để người rà soát nhìn thấy.
    """
    ra = []
    for b in ket_qua:
        for d in b['hoi_thoai']:
            for c in d['cau']:
                if not c['zh'].rstrip().endswith(('。', '？', '！')):
                    ra.append('bài %d — %s：%s' % (b['no'], c['ten'], c['zh']))
    return ra


# ---------------------------------------------------------------------------
# Trong main(), chèn đoạn này ngay sau vòng in bảng tổng kết.
# ---------------------------------------------------------------------------

    cat = canh_bao_cau_cut(ket_qua)
    if cat:
        # In ra stderr để không lẫn vào bảng tổng kết khi hứng stdout vào file.
        print('\nCẢNH BÁO: %d câu tiếng Trung không kết thúc bằng 。？！' % len(cat),
              file=sys.stderr)
        for d in cat:
            print('  ' + d, file=sys.stderr)
»