<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Json;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\ContentBuilder;
use Zika\Services\ContentWriter;
use Zika\Services\PartContent;

/** Quản trị · Hội thoại và Ngữ pháp (04 §10.6). Quyền E. */
final class DialogueController
{
    /** Trần cho một lượt nhập từ Excel. */
    private const BULK_MAX = 100;

    /* ------------------------------------------------------------------ */
    /* Hội thoại                                                           */
    /* ------------------------------------------------------------------ */

    /** GET /admin/lessons/{id}/dialogue — danh sách bài khoá của bài (rỗng nếu chưa soạn). */
    public function get(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        return ['dialogues' => array_map(
            static function (array $d): array {
                // Trình soạn tham chiếu nhân vật bằng “key” để thêm/xoá không cần biết id.
                $d['speakers'] = array_map(static fn (array $s): array => $s + ['key' => (string) $s['id']], $d['speakers']);
                $d['lines'] = array_map(static fn (array $l): array => $l + ['speakerKey' => (string) $l['speakerId']], $d['lines']);
                return $d;
            },
            PartContent::dialogueBlocks((int) $lesson['id']),
        )];
    }

    /**
     * PUT /admin/lessons/{id}/dialogue — lưu toàn bộ bài khoá của bài trong một transaction.
     *
     * Xoá hết rồi ghi lại: dữ liệu nhỏ, không bảng nào trỏ vào id hội thoại (tiến độ học ghi
     * theo bài + phần), nên so từng dòng chỉ thêm việc chứ không được gì.
     */
    public function save(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $lessonId = (int) $lesson['id'];

        $v = new Validator($r->body);
        $khoiIn = $v->arr('dialogues', 'Bài khoá');
        $v->done();

        if (count($khoiIn) > 10) {
            throw HttpError::validation(['dialogues' => 'Mỗi bài nhiều nhất 10 bài khoá.']);
        }

        $khoi = [];
        foreach (array_values($khoiIn) as $i => $raw) {
            $khoi[] = self::chuanHoaBaiKhoa(is_array($raw) ? $raw : [], $i + 1);
        }

        Db::tx(static function () use ($lessonId, $khoi): void {
            Db::exec('DELETE FROM dialogues WHERE lesson_id = ?', [$lessonId]);
            foreach ($khoi as $k) {
                $dlgId = Db::insert('dialogues', [
                    'lesson_id' => $lessonId,
                    'sort_order' => $k['no'],
                    'title' => $k['title'],
                    'scene' => $k['scene'],
                    'image_path' => $k['imagePath'],
                ]);
                $idByKey = [];
                foreach ($k['speakers'] as $key => $sp) {
                    $idByKey[$key] = Db::insert('dialogue_speakers', ['dialogue_id' => $dlgId] + $sp);
                }
                foreach ($k['lines'] as $l) {
                    $key = $l['speakerKey'];
                    unset($l['speakerKey']);
                    Db::insert('dialogue_lines', ['dialogue_id' => $dlgId, 'speaker_id' => $idByKey[$key]] + $l);
                }
            }
        });

        ContentWriter::touch($lessonId, $r);
        Audit::log($r, 'dialogue.save', 'lesson', $lessonId, [
            'baiKhoa' => count($khoi),
            'lines' => array_sum(array_map(static fn (array $k): int => count($k['lines']), $khoi)),
        ]);
        return $this->get($r);
    }

    /**
     * Kiểm tra và chuẩn hoá một bài khoá gửi lên.
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    private static function chuanHoaBaiKhoa(array $in, int $no): array
    {
        $nhan = 'Bài khoá ' . $no;
        $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 120);
        if ($title === '') {
            $title = 'Bài khoá ' . $no;
        }
        $speakersIn = is_array($in['speakers'] ?? null) ? $in['speakers'] : [];
        $linesIn = is_array($in['lines'] ?? null) ? $in['lines'] : [];

        if (count($speakersIn) < 1 || count($speakersIn) > 6) {
            throw HttpError::validation(['dialogues' => $nhan . ': cần 1 đến 6 nhân vật.']);
        }
        if (count($linesIn) < 1 || count($linesIn) > 60) {
            throw HttpError::validation(['dialogues' => $nhan . ': cần 1 đến 60 câu thoại.']);
        }

        $speakers = [];
        foreach (array_values($speakersIn) as $i => $s) {
            $name = trim((string) ($s['name'] ?? ''));
            $key = trim((string) ($s['key'] ?? $name));
            if ($name === '' || $key === '') {
                throw HttpError::validation(['dialogues' => $nhan . ': nhân vật ' . ($i + 1) . ' chưa có tên.']);
            }
            $color = (string) ($s['color'] ?? '');
            $speakers[$key] = [
                'name' => mb_substr($name, 0, 30),
                'avatar_char' => mb_substr(trim((string) ($s['avatarChar'] ?? '')) ?: mb_substr($name, -1), 0, 4),
                'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1 ? $color : '#DCEBFF',
                'role_vi' => mb_substr(trim((string) ($s['roleVi'] ?? '')), 0, 40),
                'sort_order' => $i + 1,
            ];
        }

        $lines = [];
        foreach (array_values($linesIn) as $i => $l) {
            $zh = trim((string) ($l['zh'] ?? ''));
            $key = trim((string) ($l['speakerKey'] ?? ''));
            if ($zh === '') {
                throw HttpError::validation(['dialogues' => $nhan . ': câu thoại ' . ($i + 1) . ' chưa có nội dung.']);
            }
            if (!isset($speakers[$key])) {
                throw HttpError::validation(['dialogues' => $nhan . ': câu thoại ' . ($i + 1) . ' chưa chọn nhân vật.']);
            }
            $highlight = trim((string) ($l['highlight'] ?? ''));
            if ($highlight !== '' && mb_strpos($zh, $highlight) === false) {
                throw HttpError::validation(['dialogues' => $nhan . ': câu ' . ($i + 1) . ' không chứa từ ' . $highlight . '.']);
            }
            $lines[] = [
                'speakerKey' => $key,
                'zh' => mb_substr($zh, 0, 500),
                'pinyin' => mb_substr(trim((string) ($l['pinyin'] ?? '')), 0, 800),
                'vi' => mb_substr(trim((string) ($l['vi'] ?? '')), 0, 800),
                'highlight' => $highlight !== '' ? mb_substr($highlight, 0, 32) : null,
                'audio_path' => self::uploadPath($l['audioPath'] ?? null),
                'sort_order' => $i + 1,
            ];
        }

        return [
            'no' => $no,
            'title' => $title,
            'scene' => mb_substr(trim((string) ($in['scene'] ?? '')), 0, 2000),
            'imagePath' => self::uploadPath($in['imagePath'] ?? null),
            'speakers' => $speakers,
            'lines' => $lines,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Ngữ pháp                                                            */
    /* ------------------------------------------------------------------ */

    /** GET /admin/lessons/{id}/grammar */
    public function grammar(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        return self::points((int) $lesson['id']);
    }

    /** POST /admin/lessons/{id}/grammar */
    public function createGrammar(Request $r): Response
    {
        $lesson = ContentWriter::lesson($r->id());
        $set = $this->readGrammar($r);
        $set['lesson_id'] = (int) $lesson['id'];
        $set['sort_order'] = 1 + (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM grammar_points WHERE lesson_id = ?', [(int) $lesson['id']]);

        $id = Db::insert('grammar_points', $set);
        ContentWriter::touch((int) $lesson['id'], $r);
        Audit::log($r, 'grammar.create', 'grammar_point', $id, ['title' => $set['title']]);
        return Response::json(self::point($id), 201);
    }

    /**
     * POST /admin/lessons/{id}/grammar/bulk — nhập nhiều cấu trúc một lượt (từ Excel).
     * Cấu trúc nào sai thì bỏ qua kèm số dòng, phần còn lại vẫn vào.
     */
    public function bulkGrammar(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $lessonId = (int) $lesson['id'];

        $v = new Validator($r->body);
        $mode = (string) $v->in('mode', 'Cách nhập', ['append', 'replace'], 'append');
        $items = $v->arr('items', 'Danh sách cấu trúc');
        $v->done();

        if (count($items) > self::BULK_MAX) {
            throw HttpError::validation(['items' => 'Mỗi lần nhập tối đa ' . self::BULK_MAX . ' cấu trúc.']);
        }

        $created = 0;
        $skipped = [];

        Db::tx(static function () use ($items, $lessonId, $mode, &$created, &$skipped): void {
            if ($mode === 'replace') {
                Db::exec('DELETE FROM grammar_points WHERE lesson_id = ?', [$lessonId]);
            }
            $sort = (int) Db::val('SELECT COALESCE(MAX(sort_order), 0) FROM grammar_points WHERE lesson_id = ?', [$lessonId]);

            foreach (array_values($items) as $i => $raw) {
                try {
                    $set = self::grammarSet(is_array($raw) ? $raw : []);
                } catch (HttpError $e) {
                    $fields = $e->fields;
                    $skipped[] = ['row' => $i + 1, 'reason' => reset($fields) ?: $e->getMessage()];
                    continue;
                }
                $set['lesson_id'] = $lessonId;
                $set['sort_order'] = ++$sort;
                Db::insert('grammar_points', $set);
                $created++;
            }
        });

        ContentWriter::touch($lessonId, $r);
        Audit::log($r, 'grammar.bulk', 'lesson', $lessonId, ['mode' => $mode, 'created' => $created, 'skipped' => count($skipped)]);
        return ['created' => $created, 'skipped' => $skipped];
    }

    /** PUT /admin/grammar/{id} */
    public function updateGrammar(Request $r): array
    {
        $g = self::findGrammar($r->id());
        Db::update('grammar_points', $this->readGrammar($r), ['id' => (int) $g['id']]);
        ContentWriter::touch((int) $g['lesson_id'], $r);
        Audit::log($r, 'grammar.update', 'grammar_point', (int) $g['id']);
        return self::point((int) $g['id']);
    }

    /** DELETE /admin/grammar/{id} */
    public function deleteGrammar(Request $r): Response
    {
        $g = self::findGrammar($r->id());
        Db::exec('DELETE FROM grammar_points WHERE id = ?', [(int) $g['id']]);
        ContentWriter::touch((int) $g['lesson_id'], $r);
        Audit::log($r, 'grammar.delete', 'grammar_point', (int) $g['id']);
        return Response::noContent();
    }

    /** PUT /admin/lessons/{id}/grammar/order */
    public function reorderGrammar(Request $r): Response
    {
        $lesson = ContentWriter::lesson($r->id());
        $v = new Validator($r->body);
        $ids = $v->ids('ids', 'Danh sách điểm ngữ pháp');
        $v->done();

        ContentWriter::reorder('grammar_points', 'id', $ids, 'lesson_id', (int) $lesson['id']);
        ContentBuilder::forgetLesson((int) $lesson['id'], (int) $lesson['level_id']);
        return Response::noContent();
    }

    /* ------------------------------------------------------------------ */
    /* Helper                                                              */
    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed> cột sẵn sàng ghi vào grammar_points */
    private function readGrammar(Request $r): array
    {
        return self::grammarSet($r->body);
    }

    /**
     * @param array<string,mixed> $in
     * @return array<string,mixed> cột sẵn sàng ghi vào grammar_points
     */
    private static function grammarSet(array $in): array
    {
        $v = new Validator($in);
        $title = $v->str('title', 'Tên cấu trúc', min: 1, max: 120);
        $subtitle = $v->str('subtitle', 'Mô tả ngắn', false, 0, 160);
        $explain = $v->str('explain', 'Giải thích', min: 1, max: 4000);
        $note = $v->strOrNull('note', 'Lưu ý', 2000);
        $blocksIn = $v->arr('blocks', 'Khối công thức', false);
        $examplesIn = $v->arr('examples', 'Ví dụ', false);
        $practiceIn = $v->arr('practice', 'Bài luyện tập', false);
        $v->done();

        $blocks = [];
        foreach (array_slice($blocksIn, 0, 6) as $b) {
            $label = trim((string) ($b['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $color = (string) ($b['color'] ?? '');
            $blocks[] = [
                'label' => mb_substr($label, 0, 40),
                'hz' => mb_substr(trim((string) ($b['hz'] ?? '')), 0, 40),
                'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#DCEBFF',
            ];
        }

        $examples = [];
        foreach (array_slice($examplesIn, 0, 6) as $i => $e) {
            $zh = trim((string) ($e['zh'] ?? ''));
            if ($zh === '') {
                continue;
            }
            $highlight = trim((string) ($e['highlight'] ?? ''));
            if ($highlight !== '' && mb_strpos($zh, $highlight) === false) {
                throw HttpError::validation(['examples' => 'Ví dụ ' . ($i + 1) . ' không chứa ' . $highlight . '.']);
            }
            $examples[] = [
                'kind' => mb_substr(trim((string) ($e['kind'] ?? '')), 0, 40),
                'formula' => mb_substr(trim((string) ($e['formula'] ?? '')), 0, 120),
                'zh' => mb_substr($zh, 0, 255),
                'highlight' => $highlight !== '' ? mb_substr($highlight, 0, 32) : null,
                'pinyin' => mb_substr(trim((string) ($e['pinyin'] ?? '')), 0, 500),
                'vi' => mb_substr(trim((string) ($e['vi'] ?? '')), 0, 500),
            ];
        }

        $practice = null;
        $answer = trim((string) ($practiceIn['answer'] ?? ''));
        if ($answer !== '') {
            $tiles = array_values(array_filter(array_map(
                static fn ($t): string => mb_substr(trim((string) $t), 0, 20),
                (array) ($practiceIn['tiles'] ?? []),
            ), static fn (string $t): bool => $t !== ''));

            if (count($tiles) < 2) {
                throw HttpError::validation(['practice' => 'Bài luyện tập cần ít nhất 2 ô chữ.']);
            }
            if (\Zika\Services\QuestionGrader::normZh(implode('', $tiles)) !== \Zika\Services\QuestionGrader::normZh($answer)) {
                throw HttpError::validation(['practice' => 'Các ô chữ ghép lại chưa đúng với câu đúng.']);
            }
            $practice = [
                'answer' => mb_substr($answer, 0, 120),
                'vi' => mb_substr(trim((string) ($practiceIn['vi'] ?? '')), 0, 200),
                'tiles' => $tiles,
            ];
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'explain_vi' => $explain,
            'note_vi' => $note,
            'blocks' => Json::encode($blocks),
            'examples' => Json::encode($examples),
            'practice' => Json::encode($practice),
        ];
    }

    /** @return array<string,mixed> */
    private static function findGrammar(int $id): array
    {
        $g = Db::one('SELECT * FROM grammar_points WHERE id = ?', [$id]);
        if ($g === null) {
            throw HttpError::notFound('Không tìm thấy điểm ngữ pháp.');
        }
        return $g;
    }

    /** @return list<array<string,mixed>> */
    private static function points(int $lessonId): array
    {
        return array_map(
            static fn (array $g): array => self::toApi($g),
            Db::all('SELECT * FROM grammar_points WHERE lesson_id = ? ORDER BY sort_order, id', [$lessonId]),
        );
    }

    /** @return array<string,mixed> */
    private static function point(int $id): array
    {
        return self::toApi(self::findGrammar($id));
    }

    /** @param array<string,mixed> $g @return array<string,mixed> */
    private static function toApi(array $g): array
    {
        return [
            'id' => (int) $g['id'],
            'title' => (string) $g['title'],
            'subtitle' => (string) $g['subtitle'],
            'explain' => (string) $g['explain_vi'],
            'note' => $g['note_vi'] !== null ? (string) $g['note_vi'] : null,
            'blocks' => ContentBuilder::json($g['blocks'], []),
            'examples' => ContentBuilder::json($g['examples'], []),
            'practice' => ContentBuilder::json($g['practice'], null),
            'sortOrder' => (int) $g['sort_order'],
        ];
    }

    /** Chỉ nhận đường dẫn nội bộ /uploads/… */
    private static function uploadPath(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s !== '' && str_starts_with($s, '/uploads/') ? mb_substr($s, 0, 255) : null;
    }
}
