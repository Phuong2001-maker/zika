# ===== Dán vào tach-pdf.py: THAY nguyên hàm tach_tu_vung() (dòng 108–145) bằng cả khối dưới.
# HANZI và `import re` đã có sẵn ở đầu file nên không khai lại.

NGUYEN_AM = 'aeiouüāáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ'

# Số thứ tự đứng một mình: "7." — nhận cả "7" trơ cho chắc, dù RAC đã xoá số trơ như số trang.
LA_SO = re.compile(r'^(\d{1,2})\s*\.?$')
LA_HANZI = re.compile(r'^([' + HANZI + r']{1,8})$')
CO_HANZI = re.compile('[' + HANZI + ']')

# Nhãn các mục in ở lề trang. "生词 / Shēngcí / Từ mới" trùng đúng khuôn mục từ không số
# (chữ Hán, pinyin, nghĩa tiếng Việt) và nằm ngay sau mục cuối của bảng nên phải loại tên.
# 汉字 KHÔNG nằm trong danh sách này: nó là mục từ thật của bài 14.
NHAN = ('生词', '专名', '语法', '课文', '练习', '句式', '热身', '目标', '语音', '注释',
        '学习小结', '会认字', '会写字', '任务')


def la_pinyin(s: str):
    """
    Dòng pinyin của một mục từ. Trả về (pinyin, phần sau dấu "(") hoặc None.

    Từ loại đôi khi dính ngay sau pinyin trên cùng một dòng — "shēngbìng (động từ)" ở bài 12
    số 14 — nên phải cắt nó ra ở đây, không thì cả mục đó rơi mất.
    """
    s = s.strip()
    if not s or CO_HANZI.search(s):
        return None
    con = None
    m = re.match(r'^([^(]*)\((.*)$', s)
    if m:
        s, con = m.group(1).strip(), m.group(2)
    if not s or not re.fullmatch(r"[A-Za-züÜÀ-ɏᴀ-ỿ'’/· .\-]{1,36}", s):
        return None
    # Pinyin thật luôn có nguyên âm; loại nhầm các dòng bảng ngữ âm.
    if not re.search('[' + NGUYEN_AM + ']', s, re.I):
        return None
    return s, con


def la_bien(s: str) -> bool:
    """Dòng này mở một mục khác hoặc thuộc phần khác — chặn việc nối tiếp nghĩa/từ loại."""
    s = s.strip()
    if not s:
        return True
    if LA_SO.match(s) or LA_HANZI.match(s) or CO_HANZI.search(s):
        return True
    return s[0] in '•·'


def noi_tiep(dong: list[str], j: int, buf: str, chi_ngoac: bool) -> tuple[str, int]:
    """
    Nối thêm dòng vào `buf` khi dòng trên bị ngắt giữa câu.

    Bảng từ mới in nhiều dòng nên nghĩa và từ loại dài hay bị cắt. Chỉ nối khi có dấu hiệu
    chắc chắn rằng câu chưa hết, chứ không đếm số dòng — đếm dòng thì vơ cả pinyin tiêu đề
    bài ("tám" + "dì yī kè") và tên người nói trong bản dịch ("Tiểu Lệ" + "Cô giáo").

        • ngoặc còn hở   bài 4 口  "(lượng từ cho thành" + "viên gia đình)"
        • hết bằng , ; / bài 5 做饭 "nấu cơm," + "nấu ăn"; bài 9 家 "cho gia đình/" + "công ty"
        • dòng sau mở "(" bài 14 上 "bắt đầu; lên; đi" + "(học/làm)"

    `chi_ngoac` dùng cho từ loại: chỉ nối khi ngoặc hở, để nó không ăn luôn dòng nghĩa.
    """
    while j < len(dong) and not la_bien(dong[j]):
        if buf.count('(') <= buf.count(')') and (
                chi_ngoac
                or not (buf.rstrip().endswith((',', ';', '/'))
                        or dong[j].lstrip().startswith('('))):
            break
        buf += ' ' + dong[j].strip()
        j += 1
    return buf, j


def doc_muc(dong: list[str], i: int, co_so: bool):
    """
    Đọc một mục từ bắt đầu ở dòng `i`. Trả về (mục, dòng kế) hoặc None.

        [7.]        ← `co_so`: mục chính có số; mục con / cụm phụ thì không có
        老师
        lǎoshī
        (danh từ)
        giáo viên, thầy, cô
    """
    j = i
    if co_so:
        if j >= len(dong) or not LA_SO.match(dong[j].strip()):
            return None
        j += 1
    if j >= len(dong) or not LA_HANZI.match(dong[j].strip()):
        return None
    hanzi = dong[j].strip()
    j += 1
    if j >= len(dong):
        return None
    py = la_pinyin(dong[j])
    if not py:
        return None
    pinyin, con = py
    j += 1

    # Từ loại: cụm trong ngoặc đầu tiên, dù nằm cùng dòng pinyin hay ở dòng riêng.
    loai = ''
    if con is None and j < len(dong) and dong[j].strip().startswith('('):
        con = dong[j].strip()[1:]
        j += 1
    if con is not None:
        con, j = noi_tiep(dong, j, '(' + con, True)
        loai = con[1:].split(')')[0].strip()

    # Nghĩa. Mục không ghi nghĩa (的 bài 3, 了 bài 12) thì dòng kế là số thứ tự của mục sau —
    # phải để nguyên dòng đó, nuốt nó là mất luôn mục kế tiếp.
    nghia = ''
    if j < len(dong) and not la_bien(dong[j]):
        nghia, j = noi_tiep(dong, j + 1, dong[j].strip(), False)

    nghia = re.sub(r'\s+', ' ', nghia).strip().strip(' ;')
    nghia = re.sub(r'/\s+', '/', nghia)  # "cho gia đình/ công ty" — nối ở giữa dấu gạch chéo
    # Bỏ dấu chấm cuối câu nhưng giữ "..." của "anh, chị, bạn, ông, bà,..."
    nghia = re.sub(r'(?<!\.)\.$', '', nghia).strip()
    # Nghĩa lẫn chữ Hán thì coi như chưa có.
    if CO_HANZI.search(nghia):
        nghia = ''

    return {'hanzi': hanzi, 'pinyin': pinyin, 'loai': re.sub(r'\s+', ' ', loai),
            'nghia': nghia}, j


def tach_tu_vung(text: str) -> list[dict]:
    """
    Bóc 生词 / 专名. Quét theo dòng chứ không dùng một regex khối: regex khối nuốt mất dòng số
    thứ tự của mục kế khi mục trước không ghi nghĩa (mất 老师 bài 3, 雨 bài 12), và không với
    được ba kiểu mục dưới đây.

    Ngoài mục chính có số, bảng từ mới còn ba thứ cũng là mục từ thật:
      • mục con dùng chung số với mục mẹ — 没有 dưới 有, 星期天 dưới 星期日;
      • cụm từ phụ không có số — 你好 sau 好, 没事 sau 没关系;
      • mục mà số thứ tự in trơ không có dấu chấm nên RAC xoá oan như số trang — 出租车 bài 6
        số 21, 给 bài 13 số 11, 不要 bài 14 số 11.
    Cả ba đều không có số nên chỉ nhận khi nằm **sát ngay** mục vừa đọc (giữa hai mục chỉ được
    có dòng trống). Không có điều kiện đó thì khuôn "chữ Hán + pinyin + nghĩa" vơ cả câu tiêu
    đề bài và dòng hội thoại có bản dịch bên dưới.
    """
    dong = text.split('\n')
    ra = []
    seen = set()
    ket = -1  # dòng ngay sau mục vừa nhận

    i = 0
    while i < len(dong):
        kq = doc_muc(dong, i, True)
        if kq is None and 0 <= ket <= i and all(not d.strip() for d in dong[ket:i]):
            kq = doc_muc(dong, i, False)
        if kq is None:
            i += 1
            continue
        muc, j = kq
        if muc['hanzi'].startswith(NHAN):
            i += 1
            continue

        # Gộp theo cả từ loại và nghĩa chứ không riêng chữ Hán: 天 bài 12 có hai mục thật khác
        # nhau — số 3 là danh từ "trời/thời tiết", số 19 là số từ "ngày"; 上 bài 14 cũng vậy.
        khoa = (muc['hanzi'], muc['loai'], muc['nghia'])
        if khoa not in seen:
            seen.add(khoa)
            ra.append(muc)
        ket = j
        i = j
    return ra
