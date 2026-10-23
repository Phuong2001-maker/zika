# -*- coding: utf-8 -*-
"""Lưới hồi quy cho hsk1.json — chạy sau mỗi lần sửa tach-pdf.py.

    python kiem-tra.py hsk1.json

Mốc mong đợi lấy từ báo cáo đối chiếu PDF ngày 23/10/2026 (5 agent đọc cả 15 bài).
Số nào chưa nằm trong phạm vi sửa lần này thì đánh dấu "ngoài phạm vi" chứ không báo đỏ.
"""
import json
import re
import sys

HANZI = r'㐀-䶿一-鿿豈-﫿'

# chữ 会认字 mỗi bài (bài 1 không có mục Tổng kết).
CHU = {1: 0, 2: 22, 3: 35, 4: 31, 5: 27, 6: 36, 7: 28, 8: 32,
       9: 26, 10: 26, 11: 21, 12: 23, 13: 31, 14: 30, 15: 21}
# dòng 句式 mỗi bài.
MAU = {1: 0, 2: 7, 3: 4, 4: 5, 5: 4, 6: 5, 7: 5, 8: 4,
       9: 2, 10: 3, 11: 7, 12: 3, 13: 4, 14: 4, 15: 3}
# câu rác từ 练习 phải bị loại.
RAC = {3: 2, 4: 2, 5: 2, 6: 1, 7: 1, 8: 2, 9: 2, 10: 2, 11: 2, 12: 2, 14: 2, 15: 3}

TU_MONG_DOI = 346      # 326 hiện tại + 20 mục sót
CAU_MONG_DOI = 198     # 221 hiện tại − 23 câu rác
# 3 câu thật vốn không có pinyin trong sách — không tính là lỗi.
PINYIN_RONG_CHO_PHEP = 3


def dem(d):
    bai = d['bai']
    r = {
        'tu': sum(len(b['tu_vung']) for b in bai),
        'doan': sum(len(b['hoi_thoai']) for b in bai),
        'cau': sum(len(x['cau']) for b in bai for x in b['hoi_thoai']),
        'chu': sum(len(b['chu_viet']) for b in bai),
        'mau': sum(len(b['mau_cau']) for b in bai),
        'np': sum(len(b['ngu_phap']) for b in bai),
    }
    cau = [c for b in bai for x in b['hoi_thoai'] for c in x['cau']]
    r['pinyin_rong'] = sum(1 for c in cau if not (c.get('pinyin') or '').strip())
    r['co_dau_dien'] = sum(1 for c in cau if re.search(r'[_＿]|（\s*）|\(\s*\)', c.get('zh') or ''))
    r['chua_het_cau'] = sum(1 for c in cau if (c.get('zh') or '').strip()[-1:] not in '。？！…')
    r['co_vi'] = sum(1 for c in cau if (c.get('vi') or '').strip())
    r['boi_canh_rong'] = sum(1 for b in bai for x in b['hoi_thoai'] if not (x.get('boi_canh') or '').strip())
    r['nghia_cut'] = sum(1 for b in bai for t in b['tu_vung']
                         if (t.get('nghia') or '').rstrip().endswith((',', ';'))
                         or ((t.get('nghia') or '').count('(') > (t.get('nghia') or '').count(')')))
    r['thieu_loai'] = sum(1 for b in bai for t in b['tu_vung'] if not (t.get('loai') or '').strip())
    return r


def main():
    d = json.load(open(sys.argv[1] if len(sys.argv) > 1 else 'hsk1.json', encoding='utf-8'))
    bai = {b['no']: b for b in d['bai']}
    r = dem(d)
    loi = 0

    def dong(ten, duoc, mong, pham_vi=True):
        nonlocal loi
        ok = duoc == mong
        if not ok and pham_vi:
            loi += 1
        dau = 'OK ' if ok else ('SAI' if pham_vi else '— ')
        print('%s %-26s %6s / %-6s' % (dau, ten, duoc, mong))

    print('=== TỔNG ===')
    dong('từ vựng', r['tu'], TU_MONG_DOI)
    dong('chữ 会认字', r['chu'], sum(CHU.values()))
    dong('dòng 句式', r['mau'], sum(MAU.values()))
    dong('câu thoại', r['cau'], CAU_MONG_DOI)
    dong('câu có dấu điền (phải 0)', r['co_dau_dien'], 0)
    dong('pinyin rỗng', r['pinyin_rong'], PINYIN_RONG_CHO_PHEP)
    print()
    print('=== NGOÀI PHẠM VI LẦN SỬA NÀY ===')
    dong('đoạn hội thoại', r['doan'], 44, False)
    dong('điểm ngữ pháp', r['np'], 42, False)
    dong('câu có bản dịch', r['co_vi'], r['cau'], False)
    dong('bối cảnh rỗng (muốn 3)', r['boi_canh_rong'], 3, False)
    dong('câu chưa hết câu', r['chua_het_cau'], 0, False)
    dong('nghĩa bị cắt', r['nghia_cut'], 0, False)
    dong('từ thiếu loại', r['thieu_loai'], 0, False)

    print()
    print('=== CHỮ 会认字 THEO BÀI ===')
    print('%-5s %6s %6s   %s' % ('bài', 'được', 'mong', ''))
    for n in sorted(CHU):
        c = len(bai[n]['chu_viet']) if n in bai else 0
        dau = 'OK ' if c == CHU[n] else 'SAI'
        if c != CHU[n]:
            loi += 1
        print('%s   %-3d %5d %6d' % (dau, n, c, CHU[n]))

    print()
    print('=== DÒNG 句式 THEO BÀI ===')
    for n in sorted(MAU):
        c = len(bai[n]['mau_cau']) if n in bai else 0
        dau = 'OK ' if c == MAU[n] else 'SAI'
        if c != MAU[n]:
            loi += 1
        print('%s   %-3d %5d %6d' % (dau, n, c, MAU[n]))

    print()
    print('TỔNG SỐ CHỖ CÒN SAI TRONG PHẠM VI:', loi)
    sys.exit(1 if loi else 0)


main()
