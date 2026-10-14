# -*- coding: utf-8 -*-
"""
Tách giáo trình HSK 1 (PDF) thành hsk1.json — nguồn chuẩn để nạp vào Zìkǎ.

    python tach-pdf.py "<đường dẫn PDF>" hsk1.json

Vì sao không đọc thẳng PDF lúc nạp: bản PDF có thể đổi, còn JSON thì rà soát và sửa tay được.
Mọi bước sau (sinh câu hỏi, xuất Excel, nạp database) đều chỉ đọc JSON.

Hai cái bẫy của riêng file PDF này:

1. Chữ Hán trong file phần lớn là ký tự Kangxi Radicals (⽂ U+2F02) chứ không phải chữ Hán
   thường (文 U+6587). Nhìn giống hệt nhau nhưng khác mã, và Pinyin::isHanzi ở API sẽ từ chối.
   Chuẩn hoá NFKC đưa chúng về chữ Hán thường.

2. Thứ tự text PyMuPDF trả về không theo thứ tự nhìn thấy: bản dịch tiếng Việt của bài khoá
   nằm ở khung riêng nên thường ra trước cả câu tiếng Trung. Phần bài khoá vì thế ghép theo
   tên nhân vật chứ không theo thứ tự dòng.
"""

import io
import json
import re
import sys
import unicodedata

import pymupdf

# Dải chữ Hán dùng chung với Pinyin::isHanzi ở API.
HANZI = r'㐀-䶿一-鿿豈-﫿'

SO_BAI = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十',
          '十一', '十二', '十三', '十四', '十五']

# Dòng chân trang lặp ở mọi trang.
RAC = re.compile(
    r'Bản quyền thuộc.*?hình thức\.|Trường Đại học Hà Nội-Trung tâm.*?Trung Quốc|^\s*\d{1,3}\s*$',
    re.M)


def chuan_hoa(s: str) -> str:
    """
    Đưa Kangxi Radicals (⽂ U+2F02) về chữ Hán thường (文 U+6587).

    Chỉ chuẩn hoá đúng hai dải đó chứ không NFKC cả chuỗi: NFKC còn đổi dấu câu toàn hình
    “，。？！” thành dấu ASCII, làm câu tiếng Trung sai chính tả.
    """
    out = []
    for ch in s:
        o = ord(ch)
        if 0x2e80 <= o <= 0x2fd5:  # CJK Radicals Supplement + Kangxi Radicals
            out.append(unicodedata.normalize('NFKC', ch))
        elif ch == ' ':
            out.append(' ')
        elif ch != '​':
            out.append(ch)
    return re.sub(r'[ \t]+', ' ', ''.join(out)).strip()


def doc_pdf(path: str) -> list[str]:
    doc = pymupdf.open(path)
    trang = []
    for page in doc:
        t = chuan_hoa(page.get_text())
        trang.append(RAC.sub('', t))
    return trang


def cat_theo_bai(trang: list[str]) -> list[dict]:
    """
    Cắt cả cuốn thành 15 bài theo **trang**, không theo vị trí chữ “第N课”.
    Nhãn các mục (生词, 语法, 课文…) nằm ở lề trang nên PyMuPDF trả chúng ra lẫn giữa nội dung;
    riêng tiêu đề bài lại nằm phía trên chữ “第N课”. Lấy trọn trang thì không mất gì.
    """
    moc = []
    for i, so in enumerate(SO_BAI):
        for p, t in enumerate(trang):
            if re.search(r'第\s*' + so + r'\s*课', t):
                moc.append((i + 1, p))
                break
    moc.sort(key=lambda x: x[1])

    bai = []
    for k, (no, p) in enumerate(moc):
        het = moc[k + 1][1] if k + 1 < len(moc) else len(trang)
        bai.append({'no': no, 'tu_trang': p + 1, 'den_trang': het, 'text': '\n'.join(trang[p:het])})
    return bai


def ten_bai(trang_dau: str) -> str:
    """
    Tên bài nằm ở đầu trang mở bài, dạng ba dòng liền:
        Wǒ shì Yuènán rén
        我是越南人
        Tôi là người Việt Nam
    Chỉ soi 14 dòng đầu của trang đó để không vớ phải câu trong bài khoá.
    """
    dong = [d.strip() for d in trang_dau.split('\n')[:14] if d.strip()]
    for i, d in enumerate(dong[:-1]):
        if re.fullmatch(r'[' + HANZI + r'，。？！…]{2,20}', d):
            sau = dong[i + 1].strip(' .')
            if re.match(r'^[A-ZĐÀ-ỹ]', sau) and not re.search(
                    r'(Bài khóa|Mục tiêu|Nhiệm vụ|Từ mới|Ngữ pháp|Luyện tập|Chú thích)', sau):
                return sau[:110]
    return ''


def tach_tu_vung(text: str) -> list[dict]:
    """
    Mục từ trong 生词 / 专名 có khuôn:
        1.
        请问
        qǐngwèn
        (động từ)
        Xin hỏi
    Từ loại có thể xuống dòng hoặc không có (tên riêng). Nghĩa có thể dài 2 dòng.
    """
    pat = re.compile(
        r'(?:^|\n)\s*(\d{1,2})\.\s*\n'
        r'\s*([' + HANZI + r']{1,8})\s*\n'
        r'\s*([a-zA-ZüÜÀ-ɏ][^\n(]{0,30}?)\s*\n'
        r'(?:\s*\(([^)]{0,60})\)?\s*\n)?'
        r'([^\n]{1,90})',
        re.M)

    ra = []
    seen = set()
    for m in pat.finditer(text):
        hanzi = m.group(2).strip()
        pinyin = m.group(3).strip()
        loai = (m.group(4) or '').strip()
        nghia = m.group(5).strip(' ;.')

        # Pinyin thật luôn có nguyên âm; loại nhầm các dòng bảng ngữ âm.
        if not re.search(r'[aeiouüāáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ]', pinyin):
            continue
        # Nghĩa trống, là số thứ tự của mục kế tiếp, hay lẫn chữ Hán thì coi như chưa có.
        # (“的” trong giáo trình không ghi nghĩa nên hay vớ phải số “7” của mục sau.)
        if not nghia or re.fullmatch(r'\d{1,2}\.?', nghia) or re.search(r'[' + HANZI + r']', nghia):
            nghia = ''
        if hanzi in seen:
            continue
        seen.add(hanzi)
        ra.append({'hanzi': hanzi, 'pinyin': pinyin, 'loai': loai, 'nghia': nghia})
    return ra


def tach_hoi_thoai(text: str) -> list[dict]:
    """
    Mỗi 课文 là một đoạn hội thoại. Dòng tiếng Trung có khuôn "Tên：câu", dòng ngay sau là
    pinyin. Bản dịch tiếng Việt nằm ở khung khác, khuôn "Tên: câu tiếng Việt".
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
            # Dòng kế tiếp là pinyin nếu không có chữ Hán và có nguyên âm có dấu.
            py = ''
            if k + 1 < len(dong):
                nx = dong[k + 1]
                if nx and not re.search(r'[' + HANZI + r']', nx) and re.search(
                        r'[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜaeiou]', nx):
                    py = nx.strip()
            cau.append({'ten': m.group(1), 'zh': noi_dung, 'pinyin': py, 'vi': ''})

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


def cau_tieng_viet(text: str) -> list[tuple[str, str]]:
    """Các dòng bản dịch dạng "Lý Văn: Xin hỏi…". Câu bị ngắt dòng giữa chừng thì nối lại."""
    ra = []
    dong = [d.strip() for d in text.split('\n')]
    mo = re.compile(r'^([A-ZĐÀ-ỹ][\wÀ-ỹ ]{1,24})\s*[：:]\s*(.+)$')

    for k, d in enumerate(dong):
        m = mo.match(d)
        if not m or re.search(r'[' + HANZI + r']', d):
            continue
        noi = m.group(2).strip()
        if len(noi) < 3 or not re.search(r'[À-ỹa-z]', noi):
            continue
        # Nối tiếp các dòng sau nếu chúng là phần đuôi của câu (không mở đầu bằng tên mới).
        j = k + 1
        while j < len(dong) and noi[-1] not in '.?!…':
            nx = dong[j]
            if not nx or mo.match(nx) or re.search(r'[' + HANZI + r']', nx) or nx.startswith(('•', '·')):
                break
            noi += ' ' + nx.strip()
            j += 1
        ra.append((m.group(1).strip(), re.sub(r'\s+', ' ', noi)))
    return ra


def tach_ngu_phap(text: str) -> list[dict]:
    """
    Không cắt theo nhãn 语法 (nhãn nằm ở lề nên vị trí không tin được) mà nhận theo khuôn nội
    dung: "N." → tên cấu trúc viết hoa → giải thích → các ví dụ gạch đầu dòng "•" bằng chữ Hán.
    Chính các ví dụ chữ Hán sau dấu "•" là thứ phân biệt khối ngữ pháp với mục từ vựng.
    """
    ra = []
    khoi = re.finditer(
        r'(?:^|\n)\s*(\d{1,2})\.\s*\n\s*([A-ZĐÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠƯĂẠ-ỹ][^\n]{2,90})\n'
        r'(.*?)(?=\n\s*\d{1,2}\.\s*\n|\Z)',
        text, re.S)

    for m in khoi:
        ten = m.group(2).strip(' .：:')
        than = m.group(3)

        vi_du = [chuan_hoa(x).strip(' .') for x in re.findall(r'[•·]\s*\n?\s*([^\n•]+)', than)]
        vi_du = [v for v in vi_du if re.search(r'[' + HANZI + r']', v) and len(v) >= 3]
        if len(vi_du) < 1:
            continue

        giai_thich = ' '.join(
            d.strip() for d in than.split('\n')
            if d.strip() and not d.strip().startswith(('•', '·'))
            and not re.match(r'^[' + HANZI + r'，。？！、：；0-9 ]+$', d.strip())
        ).strip()
        if len(giai_thich) < 10:
            continue

        # Tên cấu trúc có chữ Hán hoặc chữ hoa là dấu hiệu của tiêu đề ngữ pháp thật.
        if not (re.search(r'[' + HANZI + r']', ten) or re.search(r'[A-ZĐ]{3,}', ten)):
            continue

        ra.append({'ten': ten, 'giai_thich': giai_thich[:3500], 'vi_du': vi_du[:6]})
    return ra


def tach_chu_viet(text: str) -> list[str]:
    """Danh sách 会写字 (chữ cần biết viết) ở phần Tổng kết bài học."""
    m = re.search(r'会写字[^\n]*\n(.*?)(?=\n\s*(?:学习小结|会认字|句式|$))', text, re.S)
    if not m:
        m = re.search(r'会认字[^\n]*\n(.*?)(?=\n\s*(?:学习小结|会写字|句式|$))', text, re.S)
    if not m:
        return []
    chu = re.findall(r'[' + HANZI + r']', m.group(1))
    ra = []
    for c in chu:
        if c not in ra:
            ra.append(c)
    return ra


def tach_mau_cau(text: str) -> list[dict]:
    """句式 MẪU CÂU ở phần Tổng kết: bảng "mẫu câu | ví dụ"."""
    m = re.search(r'句式[^\n]*MẪU CÂU(.*?)(?=会认字|会写字|\Z)', text, re.S)
    if not m:
        return []
    ra = []
    for mm in re.finditer(r'\n\s*\d{1,2}\s*\n([^\n]+)\n([^\n]*[' + HANZI + r'][^\n]*)', m.group(1)):
        ten = mm.group(1).strip()
        vd = mm.group(2).strip()
        if ten and vd and not re.search(r'[' + HANZI + r']', ten):
            ra.append({'ten': ten, 'vi_du': vd})
    return ra


def main() -> None:
    pdf = sys.argv[1]
    ra_file = sys.argv[2] if len(sys.argv) > 2 else 'hsk1.json'

    trang = doc_pdf(pdf)
    bai = cat_theo_bai(trang)

    ket_qua = []
    for b in bai:
        t = b['text']
        ket_qua.append({
            'no': b['no'],
            'ten': ten_bai(trang[b['tu_trang'] - 1]),
            'tu_vung': tach_tu_vung(t),
            'hoi_thoai': tach_hoi_thoai(t),
            'ngu_phap': tach_ngu_phap(t),
            'mau_cau': tach_mau_cau(t),
            'chu_viet': tach_chu_viet(t),
        })

    io.open(ra_file, 'w', encoding='utf-8').write(
        json.dumps({'nguon': 'HSK1 3.0 Hanu', 'bai': ket_qua}, ensure_ascii=False, indent=1))

    cot = ('BÀI', 'TÊN', 'TỪ', 'THOẠI', 'CÂU', 'NP', 'MẪU', 'CHỮ')
    print('%-4s %-32s %4s %6s %5s %4s %5s %5s' % cot)
    for b in ket_qua:
        print('%-4d %-32s %4d %6d %5d %4d %5d %5d' % (
            b['no'], b['ten'][:32], len(b['tu_vung']), len(b['hoi_thoai']),
            sum(len(d['cau']) for d in b['hoi_thoai']), len(b['ngu_phap']),
            len(b['mau_cau']), len(b['chu_viet'])))
    tong = lambda f: sum(f(b) for b in ket_qua)
    print('%-4s %-32s %4d %6d %5d %4d %5d %5d' % ('TỔNG', '',
        tong(lambda b: len(b['tu_vung'])), tong(lambda b: len(b['hoi_thoai'])),
        tong(lambda b: sum(len(d['cau']) for d in b['hoi_thoai'])),
        tong(lambda b: len(b['ngu_phap'])), tong(lambda b: len(b['mau_cau'])),
        tong(lambda b: len(b['chu_viet']))))


if __name__ == '__main__':
    main()
