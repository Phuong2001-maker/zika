# -*- coding: utf-8 -*-
"""Sinh SQL nạp nội dung HSK 1 thẳng vào MySQL.

Dùng khi không nhập được qua nút "Nhập Excel" (ví dụ nạp thẳng lên hosting).
Đổi lại là MẤT ba lớp bảo vệ của đường nhập thường: QuestionPayload::clean(),
ContentWriter::touch(), và HanziDataService tải nét chữ. Lớp 2 được chép lại
bằng tay ở cuối file SQL (các câu UPDATE đếm cnt_*); lớp 3 thì không — nét chữ
phải tải riêng.

    python xuat-sql.py hsk1.json sql
"""
import json
import os
import re
import sys

# Từ loại in trong sách (tiếng Việt) -> ENUM pos của bảng words.
POS = {
    'danh từ': 'n', 'động từ': 'v', 'động từ năng nguyện': 'v',
    'tính từ': 'adj', 'phó từ': 'adv', 'lượng từ': 'mw',
    'cụm số lượng từ': 'phrase', 'số từ': 'num', 'số từ, lượng từ': 'num',
    'đại từ': 'pron', 'đại từ nghi vấn': 'pron', 'đại từ chỉ nơi chốn': 'pron',
    'giới từ': 'prep', 'liên từ': 'conj',
    'trợ từ': 'part', 'trợ từ ngữ khí': 'part',
    'thán từ': 'other',
    'danh từ, lượng từ': 'n', 'danh từ / phương vị từ': 'n',
}


def pos_cua(loai):
    """Gộp khoảng trắng rồi tra bảng; không biết thì 'other'."""
    return POS.get(re.sub(r'\s+', ' ', (loai or '').strip()), 'other')


def q(s):
    """Chuỗi -> literal SQL. Thoát cả dấu chéo ngược vì MySQL coi nó là ký tự thoát."""
    if s is None:
        return 'NULL'
    s = str(s).replace('\\', '\\\\').replace("'", "''")
    return "'" + s.replace('\r', '').replace('\n', '\\n') + "'"


def j(obj):
    """Giá trị JSON -> literal SQL."""
    return q(json.dumps(obj, ensure_ascii=False))


def chen(ra, bang, cot, dong, moi_me=200):
    """Ghi INSERT nhiều dòng, cắt thành từng mẻ cho phpMyAdmin đỡ nghẹn."""
    if not dong:
        ra.append('-- (không có dòng nào cho %s)' % bang)
        return
    for i in range(0, len(dong), moi_me):
        me = dong[i:i + moi_me]
        ra.append('INSERT INTO %s (%s) VALUES' % (bang, ', '.join(cot)))
        ra.append(',\n'.join('  (' + ', '.join(v) + ')' for v in me) + ';')


LEVEL = 1


def main():
    nguon = sys.argv[1] if len(sys.argv) > 1 else 'hsk1.json'
    dich = sys.argv[2] if len(sys.argv) > 2 else 'sql'
    goc = os.path.dirname(os.path.abspath(nguon))
    dich = dich if os.path.isabs(dich) else os.path.join(goc, dich)
    os.makedirs(dich, exist_ok=True)

    bai_list = json.load(open(nguon, encoding='utf-8'))['bai']
    ra = []
    bo_qua = []

    # ---- words: UNIQUE (level_id, hanzi) nên từ lặp lại chỉ giữ lần đầu ---
    w_rows, w_id_cua, da_co, mau_cua = [], {}, {}, {}
    wid = 0
    for b in bai_list:
        for stt, t in enumerate(b['tu_vung'], 1):
            hz = (t.get('hanzi') or '').strip()
            if not hz:
                bo_qua.append('bài %d: một dòng từ vựng không có chữ Hán' % b['no'])
                continue
            if hz in da_co:
                bo_qua.append('bài %d: %s đã có ở bài %d (UNIQUE level_id+hanzi)'
                              % (b['no'], hz, da_co[hz]))
                continue
            wid += 1
            da_co[hz] = b['no']
            w_id_cua[(b['no'], hz)] = wid
            mau_cua.setdefault(b['no'], []).append(hz)
            w_rows.append([str(wid), str(LEVEL), str(b['no']), q(hz),
                           q(t.get('pinyin') or ''),
                           q(''),                       # han_viet: sách không ghi
                           q(t.get('nghia') or ''), q(pos_cua(t.get('loai'))), str(stt)])

    # ---- lessons: id = số bài, để khoá ngoại đoán trước được --------------
    #      sample_text tính sẵn ở đây thay vì trong SQL: MySQL không cho bảng dẫn
    #      xuất trong UPDATE tham chiếu cột của bảng ngoài, nên không viết được
    #      "2 từ đầu" bằng một câu UPDATE. Công thức y hệt ContentWriter::sampleText().
    l_rows = []
    for b in bai_list:
        l_rows.append([str(b['no']), str(LEVEL), str(b['no']),
                       q(b['ten'] or 'Bài %d' % b['no']),
                       q(' · '.join(mau_cua.get(b['no'], [])[:2])), q('draft')])

    # ---- characters: bảng dùng chung mọi cấp, UNIQUE theo hanzi ----------
    c_rows, c_id_cua = [], {}
    for b in bai_list:
        for ch in b['chu_viet']:
            ch = (ch or '').strip()
            if not ch or ch in c_id_cua:
                continue
            c_id_cua[ch] = len(c_id_cua) + 1
            c_rows.append([str(c_id_cua[ch]), q(ch), q(''), q(''), q(''),
                           '0', q(''), 'NULL', q('missing')])

    # ---- lesson_chars: nối chữ vào bài, kèm từ chứa chữ đó ---------------
    lc_rows = []
    for b in bai_list:
        tu_trong_bai = [(t.get('hanzi') or '').strip() for t in b['tu_vung']]
        chu_bai = list(dict.fromkeys(x.strip() for x in b['chu_viet'] if x.strip()))
        for stt, ch in enumerate(chu_bai, 1):
            nguon_tu = 'NULL'
            for hz in tu_trong_bai:
                if ch in hz and (b['no'], hz) in w_id_cua:
                    nguon_tu = str(w_id_cua[(b['no'], hz)])
                    break
            lc_rows.append([str(b['no']), str(c_id_cua[ch]), nguon_tu, str(stt), '1'])

    # ---- dialogues / speakers / lines ------------------------------------
    d_rows, s_rows, dl_rows = [], [], []
    did = sid = 0
    for b in bai_list:
        for thu_tu, doan in enumerate(b['hoi_thoai'], 1):
            cau = [c for c in doan['cau'] if (c.get('zh') or '').strip()]
            if not cau:
                continue
            did += 1
            d_rows.append([str(did), str(b['no']), str(thu_tu),
                           q('Bài khoá %d' % thu_tu), q(doan.get('boi_canh') or '')])
            spk = {}
            for c in cau:
                ten = (c.get('ten') or '').strip() or '?'
                if ten not in spk:
                    sid += 1
                    spk[ten] = sid
                    s_rows.append([str(sid), str(did), q(ten[:30]), q(ten[0]), q('#DCEBFF'),
                                   q((c.get('ten_vi') or '')[:40]), str(len(spk))])
            for k, c in enumerate(cau, 1):
                ten = (c.get('ten') or '').strip() or '?'
                dl_rows.append([str(did), str(spk[ten]), str(k), q(c['zh'][:500]),
                                q((c.get('pinyin') or '')[:800]),
                                q((c.get('vi') or '')[:800]), 'NULL', 'NULL'])

    # ---- grammar_points ---------------------------------------------------
    g_rows = []
    for b in bai_list:
        for stt, g in enumerate(b['ngu_phap'], 1):
            vi_du = [{'kind': '', 'formula': '', 'zh': v, 'highlight': None,
                      'pinyin': '', 'vi': ''} for v in (g.get('vi_du') or [])]
            g_rows.append([str(b['no']), str(stt), q((g.get('ten') or '')[:120]), q(''),
                           q(g.get('giai_thich') or ''), 'NULL', j([]), j(vi_du), j(None)])

    # =======================================================================
    ra.append('-- Zìkǎ · nội dung HSK 1 — sinh tự động bởi data/hsk1/xuat-sql.py')
    ra.append('-- Chạy TRƯỚC: 01-migration-003.sql (nếu server chưa chạy migration 003).')
    ra.append('-- %d bài · %d từ · %d chữ · %d đoạn thoại · %d câu · %d điểm ngữ pháp'
              % (len(l_rows), len(w_rows), len(c_rows), len(d_rows), len(dl_rows), len(g_rows)))
    ra.append('-- Bài tập và Kiểm tra: CHƯA CÓ CÂU NÀO (bộ sinh câu hỏi chưa viết).')
    ra.append('')
    ra.append('SET NAMES utf8mb4;')
    ra.append('START TRANSACTION;')
    ra.append('')
    ra.append('-- 1. Dọn nội dung HSK 1 cũ (chạy lại file này nhiều lần vẫn đúng).')
    ra.append('--    Xoá lessons kéo theo lesson_chars, dialogues, grammar_points, questions, *_configs.')
    ra.append('DELETE FROM lessons WHERE level_id = %d;' % LEVEL)
    ra.append('DELETE FROM words   WHERE level_id = %d;' % LEVEL)
    ra.append('DELETE FROM characters WHERE id NOT IN (SELECT char_id FROM lesson_chars);')
    ra.append('')
    ra.append('-- 2. Bài học')
    chen(ra, 'lessons', ['id', 'level_id', 'lesson_no', 'title', 'sample_text', 'status'], l_rows)
    ra.append('')
    ra.append('-- 3. Từ vựng (Lật thẻ)')
    chen(ra, 'words', ['id', 'level_id', 'lesson_id', 'hanzi', 'pinyin', 'han_viet',
                       'meaning_vi', 'pos', 'sort_order'], w_rows)
    ra.append('')
    ra.append('-- 4. Chữ (Luyện viết) — nét chữ phải tải riêng, xem ghi chú cuối file')
    chen(ra, 'characters', ['id', 'hanzi', 'pinyin', 'han_viet', 'meaning_vi', 'strokes',
                            'structure', 'radical', 'stroke_data_status'], c_rows)
    ra.append('')
    chen(ra, 'lesson_chars', ['lesson_id', 'char_id', 'source_word_id', 'sort_order',
                              'is_visible'], lc_rows)
    ra.append('')
    ra.append('-- 5. Hội thoại')
    chen(ra, 'dialogues', ['id', 'lesson_id', 'sort_order', 'title', 'scene'], d_rows)
    ra.append('')
    chen(ra, 'dialogue_speakers', ['id', 'dialogue_id', 'name', 'avatar_char', 'color',
                                   'role_vi', 'sort_order'], s_rows)
    ra.append('')
    chen(ra, 'dialogue_lines', ['dialogue_id', 'speaker_id', 'sort_order', 'zh', 'pinyin',
                                'vi', 'highlight', 'audio_path'], dl_rows)
    ra.append('')
    ra.append('-- 6. Ngữ pháp')
    chen(ra, 'grammar_points', ['lesson_id', 'sort_order', 'title', 'subtitle', 'explain_vi',
                                'note_vi', 'blocks', 'examples', 'practice'], g_rows)
    ra.append('')
    ra.append('-- 7. Cài đặt mặc định cho Luyện viết và Kiểm tra')
    chen(ra, 'writing_configs', ['lesson_id'], [[str(b['no'])] for b in bai_list])
    ra.append('')
    chen(ra, 'test_configs', ['lesson_id'], [[str(b['no'])] for b in bai_list])
    ra.append('')
    ra.append('-- 8. Đếm lại cnt_* và sinh sample_text — chép tay việc của ContentWriter::touch()')
    ra.append(DEM % LEVEL)
    ra.append('')
    ra.append('COMMIT;')
    ra.append(CUOI)

    out = os.path.join(dich, '02-hsk1-noi-dung.sql')
    open(out, 'w', encoding='utf-8', newline='\n').write('\n'.join(ra) + '\n')
    open(os.path.join(dich, '01-migration-003.sql'), 'w',
         encoding='utf-8', newline='\n').write(MIG)

    print(out)
    print('  %d bài · %d từ · %d chữ · %d nối chữ-bài'
          % (len(l_rows), len(w_rows), len(c_rows), len(lc_rows)))
    print('  %d đoạn thoại · %d câu · %d điểm ngữ pháp'
          % (len(d_rows), len(dl_rows), len(g_rows)))
    print('  bỏ qua %d dòng:' % len(bo_qua))
    for x in bo_qua[:30]:
        print('   -', x)
    if len(bo_qua) > 30:
        print('   … và %d dòng nữa' % (len(bo_qua) - 30))


DEM = """UPDATE lessons l SET
  l.cnt_words = (SELECT COUNT(*) FROM words w WHERE w.lesson_id = l.id),
  l.cnt_bt    = (SELECT COUNT(*) FROM questions q WHERE q.part = 'bt' AND q.lesson_id = l.id),
  l.cnt_kt    = (SELECT COUNT(*) FROM questions q WHERE q.part = 'kt' AND q.lesson_id = l.id),
  l.cnt_lv    = (SELECT COUNT(*) FROM lesson_chars c WHERE c.lesson_id = l.id AND c.is_visible = 1),
  l.cnt_ht    = (SELECT COUNT(*) FROM dialogue_lines dl
                   JOIN dialogues d ON d.id = dl.dialogue_id WHERE d.lesson_id = l.id),
  l.cnt_np    = (SELECT COUNT(*) FROM grammar_points g WHERE g.lesson_id = l.id)
WHERE l.level_id = %d;"""

CUOI = """
-- ---------------------------------------------------------------------------
-- Sau khi chạy xong, còn 3 việc KHÔNG nằm trong file này:
--   1. Nét chữ: chạy SQL thẳng nên HanziDataService không chạy, uploads/hanzi/
--      rỗng -> Luyện viết không vẽ được nét. Mở từng bài ở khu quản trị bấm lưu,
--      hoặc tải thư mục uploads/hanzi/ từ máy local lên.
--   2. Cho hiện:  UPDATE levels  SET is_visible = 1        WHERE id = 1;
--                 UPDATE lessons SET status = 'published'  WHERE level_id = 1;
--   3. Bài nghe: dialogue_lines.audio_path là một file cho MỘT CÂU, còn mp3 đang
--      có là một file cho CẢ ĐOẠN -> chưa gắn được.
-- ---------------------------------------------------------------------------"""

MIG = """-- Zìkǎ · migration 003 cho server chưa chạy — CHẠY LẠI BAO NHIÊU LẦN CŨNG ĐƯỢC.
-- Mỗi bước tự soi information_schema trước, đã làm rồi thì bỏ qua (DO 0) thay vì báo lỗi.
-- Cần vậy vì phpMyAdmin dừng ở câu lỗi đầu tiên, một lần vấp là các câu sau không chạy.
SET NAMES utf8mb4;

-- 1. Thêm cột sort_order
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dialogues'
                 AND COLUMN_NAME = 'sort_order') = 0,
  'ALTER TABLE dialogues ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER lesson_id',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Thêm index mới TRƯỚC khi bỏ khoá cũ: khoá ngoại fk_dlg_lesson đang dựa vào
--    uq_dlg_lesson, bỏ trước thì báo lỗi 1553.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dialogues'
                 AND INDEX_NAME = 'ix_dlg_lesson') = 0,
  'ALTER TABLE dialogues ADD KEY ix_dlg_lesson (lesson_id, sort_order)',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Bỏ khoá UNIQUE cũ (cái ép mỗi bài chỉ được 1 hội thoại)
SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dialogues'
                 AND INDEX_NAME = 'uq_dlg_lesson') > 0,
  'ALTER TABLE dialogues DROP INDEX uq_dlg_lesson',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Bỏ bảng câu hỏi hiểu bài (phần Kiểm tra đã làm đúng việc đó)
DROP TABLE IF EXISTS dialogue_questions;

-- 5. Ghi nhận để install.php --migrate không chạy lại
INSERT IGNORE INTO schema_migrations (filename) VALUES ('003_dialogue_nhieu_bai_khoa.sql');

-- Kiểm lại: phải ra 1 · 2 · 0 · 0 · 1
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogues' AND COLUMN_NAME = 'sort_order')   AS co_sort_order,
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogues' AND INDEX_NAME = 'ix_dlg_lesson') AS co_index_moi,
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogues' AND INDEX_NAME = 'uq_dlg_lesson') AS con_unique_cu,
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'dialogue_questions')                        AS con_bang_cu,
  (SELECT COUNT(*) FROM schema_migrations
     WHERE filename = '003_dialogue_nhieu_bai_khoa.sql')           AS da_ghi_nhan;
"""

main()
