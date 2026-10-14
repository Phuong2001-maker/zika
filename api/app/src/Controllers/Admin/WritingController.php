<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Pinyin;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Upload;
use Zika\Core\Validator;
use Zika\Services\ContentWriter;
use Zika\Services\HanziDataService;
use Zika\Services\PartContent;

/** Quản trị · Luyện viết (04 §10.5). Quyền E. */
final class WritingController
{
    private const BULK_MAX = 200;
    /** Thời gian tối đa dành cho việc tải dữ liệu nét trong một lượt nhập. */
    private const FETCH_BUDGET_SEC = 20.0;

    /** GET /admin/lessons/{id}/writing */
    public function index(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        return [
            'config' => PartContent::writingConfig((int) $lesson['id']),
            'chars' => self::chars((int) $lesson['id']),
        ];
    }

    /**
     * POST /admin/lessons/{id}/writing/from-words
     * Lấy mọi chữ Hán trong từ vựng của bài (không trùng) thêm vào cuối danh sách.
     * Chữ chưa có trong bảng characters thì tạo mới, điền từ từ điển và tải dữ liệu nét.
     */
    public function fromWords(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $lessonId = (int) $lesson['id'];

        $words = Db::all('SELECT id, hanzi FROM words WHERE lesson_id = ? ORDER BY sort_order, id', [$lessonId]);
        $sort = (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM lesson_chars WHERE lesson_id = ?', [$lessonId]);

        $added = 0;
        $fetched = 0;
        $seen = array_map('strval', Db::col(
            'SELECT c.hanzi FROM lesson_chars lc JOIN characters c ON c.id = lc.char_id WHERE lc.lesson_id = ?',
            [$lessonId],
        ));

        foreach ($words as $w) {
            foreach (Pinyin::chars((string) $w['hanzi']) as $hz) {
                if (in_array($hz, $seen, true)) {
                    continue;
                }
                $seen[] = $hz;

                [$charId, $isNew] = self::ensureChar($hz);
                if ($isNew && HanziDataService::fetch($charId)) {
                    $fetched++;
                }

                Db::exec(
                    'INSERT IGNORE INTO lesson_chars (lesson_id, char_id, source_word_id, sort_order) VALUES (?, ?, ?, ?)',
                    [$lessonId, $charId, (int) $w['id'], ++$sort],
                );
                $added++;
            }
        }

        ContentWriter::touch($lessonId, $r);
        Audit::log($r, 'writing.from_words', 'lesson', $lessonId, ['added' => $added]);
        return ['added' => $added, 'strokeFetched' => $fetched, 'chars' => self::chars($lessonId)];
    }

    /** POST /admin/lessons/{id}/writing/chars — thêm tay một chữ. */
    public function addChar(Request $r): Response
    {
        $lesson = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);
        $hanzi = $v->str('hanzi', 'Chữ Hán', min: 1, max: 4);
        if (!Pinyin::isHanzi($hanzi) || mb_strlen($hanzi) !== 1) {
            $v->error('hanzi', 'Nhập đúng một chữ Hán.');
        }
        $v->done();

        [$charId, $isNew] = self::ensureChar($hanzi);
        if ($isNew) {
            HanziDataService::fetch($charId);
        }

        $sort = 1 + (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM lesson_chars WHERE lesson_id = ?', [(int) $lesson['id']]);
        Db::exec(
            'INSERT IGNORE INTO lesson_chars (lesson_id, char_id, sort_order) VALUES (?, ?, ?)',
            [(int) $lesson['id'], $charId, $sort],
        );

        ContentWriter::touch((int) $lesson['id'], $r);
        Audit::log($r, 'writing.add_char', 'lesson', (int) $lesson['id'], ['hanzi' => $hanzi]);
        return Response::json(['chars' => self::chars((int) $lesson['id'])], 201);
    }

    /**
     * POST /admin/lessons/{id}/writing/bulk — nhập nhiều chữ một lượt (từ Excel).
     *
     * Dữ liệu nét tải từ CDN, mỗi chữ tốn tới 5 giây, nên chỉ tải trong FETCH_BUDGET_SEC đầu;
     * chữ còn lại để trạng thái “missing” và người soạn bấm tải lại ở từng ô — thà nhập xong
     * 100 chữ còn thiếu nét hơn là để request chạy quá giờ rồi hỏng cả mẻ.
     */
    public function bulk(Request $r): Response
    {
        $lesson = ContentWriter::lesson($r->id());
        $lessonId = (int) $lesson['id'];

        $v = new Validator($r->body);
        $mode = (string) $v->in('mode', 'Cách nhập', ['append', 'replace'], 'append');
        $items = $v->arr('items', 'Danh sách chữ');
        $v->done();

        if (count($items) > self::BULK_MAX) {
            throw HttpError::validation(['items' => 'Mỗi lần nhập tối đa ' . self::BULK_MAX . ' chữ.']);
        }

        if ($mode === 'replace') {
            Db::exec('DELETE FROM lesson_chars WHERE lesson_id = ?', [$lessonId]);
        }

        $sort = (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM lesson_chars WHERE lesson_id = ?', [$lessonId]);
        $seen = array_map('strval', Db::col(
            'SELECT c.hanzi FROM lesson_chars lc JOIN characters c ON c.id = lc.char_id WHERE lc.lesson_id = ?',
            [$lessonId],
        ));

        $added = 0;
        $fetched = 0;
        $skipped = [];
        $deadline = microtime(true) + self::FETCH_BUDGET_SEC;

        foreach (array_values($items) as $i => $raw) {
            $row = is_array($raw) ? $raw : [];
            $hanzi = trim((string) ($row['hanzi'] ?? ''));

            if (!Pinyin::isHanzi($hanzi) || mb_strlen($hanzi) !== 1) {
                $skipped[] = ['row' => $i + 1, 'reason' => 'Không phải một chữ Hán đơn.'];
                continue;
            }
            if (in_array($hanzi, $seen, true)) {
                $skipped[] = ['row' => $i + 1, 'reason' => 'Bài đã có chữ ' . $hanzi . '.'];
                continue;
            }
            $seen[] = $hanzi;

            [$charId, $isNew] = self::ensureChar($hanzi);
            self::fillChar($charId, $row);
            if ($isNew && microtime(true) < $deadline && HanziDataService::fetch($charId)) {
                $fetched++;
            }

            Db::exec(
                'INSERT IGNORE INTO lesson_chars (lesson_id, char_id, sort_order, is_visible) VALUES (?, ?, ?, ?)',
                [$lessonId, $charId, ++$sort, ($row['visible'] ?? true) ? 1 : 0],
            );
            $added++;
        }

        ContentWriter::touch($lessonId, $r);
        Audit::log($r, 'writing.bulk', 'lesson', $lessonId, ['mode' => $mode, 'added' => $added, 'skipped' => count($skipped)]);
        return Response::json([
            'created' => $added,
            'strokeFetched' => $fetched,
            'skipped' => $skipped,
            'chars' => self::chars($lessonId),
        ], 201);
    }

    /**
     * Bổ sung mô tả cho chữ. Bảng characters dùng chung mọi bài nên chỉ điền vào ô đang trống,
     * không ghi đè thứ người khác đã soạn.
     * @param array<string,mixed> $row
     */
    private static function fillChar(int $charId, array $row): void
    {
        $c = Db::one('SELECT pinyin, han_viet, meaning_vi, structure FROM characters WHERE id = ?', [$charId]);
        if ($c === null) {
            return;
        }

        $set = [];
        $map = ['pinyin' => ['pinyin', 20], 'hanViet' => ['han_viet', 60], 'meaning' => ['meaning_vi', 255], 'structure' => ['structure', 160]];
        foreach ($map as $from => [$col, $max]) {
            $value = trim((string) ($row[$from] ?? ''));
            if ($value !== '' && (string) $c[$col] === '') {
                $set[$col] = mb_substr($value, 0, $max);
            }
        }
        if ($set !== []) {
            Db::update('characters', $set, ['id' => $charId]);
        }
    }

    /** PATCH /admin/lesson-chars/{id} — bật/tắt hiện chữ, sửa mô tả cấu tạo. */
    public function updateLessonChar(Request $r): array
    {
        $lc = Db::one('SELECT * FROM lesson_chars WHERE id = ?', [$r->id()]);
        if ($lc === null) {
            throw HttpError::notFound('Không tìm thấy chữ trong bài.');
        }

        $v = new Validator($r->body);
        if ($v->present('visible')) {
            Db::update('lesson_chars', ['is_visible' => $v->bool('visible') ? 1 : 0], ['id' => (int) $lc['id']]);
        }
        if ($v->present('structure')) {
            // Cấu tạo là thuộc tính của chữ, không của riêng bài → ghi vào bảng characters.
            Db::update('characters', ['structure' => $v->str('structure', 'Cấu tạo', false, 0, 160)], ['id' => (int) $lc['char_id']]);
        }
        $v->done();

        ContentWriter::touch((int) $lc['lesson_id'], $r);
        return ['ok' => true];
    }

    /** DELETE /admin/lesson-chars/{id} */
    public function deleteLessonChar(Request $r): Response
    {
        $lc = Db::one('SELECT * FROM lesson_chars WHERE id = ?', [$r->id()]);
        if ($lc === null) {
            throw HttpError::notFound('Không tìm thấy chữ trong bài.');
        }
        Db::exec('DELETE FROM lesson_chars WHERE id = ?', [(int) $lc['id']]);
        ContentWriter::touch((int) $lc['lesson_id'], $r);
        return Response::noContent();
    }

    /** PUT /admin/lessons/{id}/writing/order */
    public function reorder(Request $r): Response
    {
        $lesson = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);
        $ids = $v->ids('ids', 'Danh sách chữ');
        $v->done();

        ContentWriter::reorder('lesson_chars', 'id', $ids, 'lesson_id', (int) $lesson['id']);
        ContentWriter::touch((int) $lesson['id'], $r);
        return Response::noContent();
    }

    /** PUT /admin/lessons/{id}/writing/config */
    public function config(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);
        $set = [
            'start_mode' => $v->in('startMode', 'Bắt đầu ở chế độ', ['demo', 'trace', 'free'], 'trace'),
            'grid' => $v->in('grid', 'Kiểu ô', ['tian', 'mi'], 'mi'),
            'pass_count' => $v->int('passCount', 'Số lần viết đúng', 1, 10, false, 2),
            'hint_after_misses' => $v->int('hintAfterMisses', 'Gợi ý sau số lần sai', 1, 10, false, 2),
            'show_animation' => $v->bool('showAnimation', true) ? 1 : 0,
            'highlight_radical' => $v->bool('highlightRadical', true) ? 1 : 0,
        ];
        $v->done();

        Db::exec(
            'INSERT INTO writing_configs (lesson_id, start_mode, grid, pass_count, hint_after_misses, show_animation, highlight_radical)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE start_mode = VALUES(start_mode), grid = VALUES(grid), pass_count = VALUES(pass_count),
                 hint_after_misses = VALUES(hint_after_misses), show_animation = VALUES(show_animation),
                 highlight_radical = VALUES(highlight_radical)',
            [(int) $lesson['id'], ...array_values($set)],
        );

        ContentWriter::touch((int) $lesson['id'], $r);
        Audit::log($r, 'writing.config', 'lesson', (int) $lesson['id'], $set);
        return PartContent::writingConfig((int) $lesson['id']);
    }

    /** POST /admin/characters/{id}/stroke-data — admin tải tệp nét tự vẽ lên. */
    public function uploadStroke(Request $r): array
    {
        $c = self::character($r->id());
        $up = Upload::strokeJson($r->files['file'] ?? null);

        HanziDataService::save((int) $c['id'], (string) $c['hanzi'], $up['json'], $up['strokes'], 'custom');
        Audit::log($r, 'character.stroke_upload', 'character', (int) $c['id'], ['hanzi' => $c['hanzi']]);

        return self::charApi(self::character((int) $c['id']));
    }

    /** POST /admin/characters/{id}/stroke-data/fetch — thử tải lại từ nguồn mở. */
    public function fetchStroke(Request $r): array
    {
        $c = self::character($r->id());
        $ok = HanziDataService::fetch((int) $c['id']);
        Audit::log($r, 'character.stroke_fetch', 'character', (int) $c['id'], ['ok' => $ok]);

        return ['ok' => $ok] + self::charApi(self::character((int) $c['id']));
    }

    /* ------------------------------------------------------------------ */
    /* Helper                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Tìm hoặc tạo dòng trong bảng characters, điền sẵn pinyin/nghĩa từ từ điển nếu có.
     * @return array{0:int,1:bool} [charId, vừa tạo mới]
     */
    private static function ensureChar(string $hanzi): array
    {
        $id = Db::val('SELECT id FROM characters WHERE hanzi = ?', [$hanzi]);
        if ($id !== null) {
            return [(int) $id, false];
        }

        $d = Db::one('SELECT * FROM dict_words WHERE hanzi = ? ORDER BY id LIMIT 1', [$hanzi]);
        return [Db::insert('characters', [
            'hanzi' => $hanzi,
            'pinyin' => mb_substr((string) ($d['pinyin'] ?? ''), 0, 20),
            'han_viet' => mb_substr((string) ($d['han_viet'] ?? ''), 0, 60),
            'meaning_vi' => mb_substr((string) ($d['meaning_vi'] ?? ''), 0, 255),
        ]), true];
    }

    /** @return array<string,mixed> */
    private static function character(int $id): array
    {
        $c = Db::one('SELECT * FROM characters WHERE id = ?', [$id]);
        if ($c === null) {
            throw HttpError::notFound('Không tìm thấy chữ này.');
        }
        return $c;
    }

    /** @return list<array<string,mixed>> */
    private static function chars(int $lessonId): array
    {
        $rows = Db::all(
            'SELECT lc.id AS lesson_char_id, lc.is_visible, lc.sort_order, c.*,
                    w.hanzi AS word_hanzi, w.meaning_vi AS word_meaning
             FROM lesson_chars lc
             JOIN characters c ON c.id = lc.char_id
             LEFT JOIN words w ON w.id = lc.source_word_id
             WHERE lc.lesson_id = ? ORDER BY lc.sort_order, lc.id',
            [$lessonId],
        );
        return array_map(static fn (array $c): array => [
            'lessonCharId' => (int) $c['lesson_char_id'],
            'visible' => (bool) $c['is_visible'],
            'sourceWord' => $c['word_hanzi'] !== null
                ? ['hanzi' => (string) $c['word_hanzi'], 'meaning' => (string) $c['word_meaning']]
                : null,
        ] + self::charApi($c), $rows);
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private static function charApi(array $c): array
    {
        return [
            'charId' => (int) $c['id'],
            'hanzi' => (string) $c['hanzi'],
            'pinyin' => (string) $c['pinyin'],
            'tone' => Pinyin::tone((string) $c['pinyin']),
            'hanViet' => (string) $c['han_viet'],
            'meaning' => (string) $c['meaning_vi'],
            'strokes' => (int) $c['strokes'],
            'structure' => (string) $c['structure'],
            'strokeStatus' => (string) $c['stroke_data_status'],
            'strokeDataUrl' => HanziDataService::url(
                $c['stroke_data_path'] !== null ? (string) $c['stroke_data_path'] : null,
                (string) $c['updated_at'],
            ),
        ];
    }
}
