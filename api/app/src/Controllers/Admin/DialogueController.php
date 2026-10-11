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
    /* ------------------------------------------------------------------ */
    /* Hội thoại                                                           */
    /* ------------------------------------------------------------------ */

    /** GET /admin/lessons/{id}/dialogue — trả null khi bài chưa có hội thoại. */
    public function get(Request $r): ?array
    {
        $lesson = ContentWriter::lesson($r->id());
        $d = PartContent::dialogueBlock((int) $lesson['id'], true);
        if ($d === null) {
            return null;
        }
        // Trình soạn tham chiếu nhân vật bằng “key” để thêm/xoá nhân vật không cần biết id.
        $d['speakers'] = array_map(static fn (array $s): array => $s + ['key' => (string) $s['id']], $d['speakers']);
        $d['lines'] = array_map(static fn (array $l): array => $l + ['speakerKey' => (string) $l['speakerId']], $d['lines']);
        return $d;
    }

    /**
     * PUT /admin/lessons/{id}/dialogue — lưu cả khối trong một transaction.
     * Nhân vật được giữ id cũ nếu trình soạn gửi lên `id`, còn câu thoại và câu hỏi
     * thì xoá hết rồi ghi lại (dữ liệu nhỏ, tránh phải so từng dòng).
     */
    public function save(Request $r): array
    {
        $lesson = ContentWriter::lesson($r->id());
        $lessonId = (int) $lesson['id'];

        $v = new Validator($r->body);
        $title = $v->str('title', 'Tên hội thoại', min: 1, max: 120);
        $scene = $v->str('scene', 'Tình huống', false, 0, 2000);
        $imagePath = $v->strOrNull('imagePath', 'Ảnh', 255);
        $speakersIn = $v->arr('speakers', 'Nhân vật');
        $linesIn = $v->arr('lines', 'Câu thoại');
        $questionsIn = $v->arr('questions', 'Câu hỏi', false);

        if (count($speakersIn) < 1) {
            $v->error('speakers', 'Cần ít nhất một nhân vật.');
        }
        if (count($speakersIn) > 6) {
            $v->error('speakers', 'Nhiều nhất 6 nhân vật.');
        }
        if (count($linesIn) < 1) {
            $v->error('lines', 'Cần ít nhất một câu thoại.');
        }
        if (count($linesIn) > 60) {
            $v->error('lines', 'Nhiều nhất 60 câu thoại.');
        }
        $v->done();

        // Chuẩn hoá nhân vật trước để biết key nào hợp lệ.
        $speakers = [];
        foreach ($speakersIn as $i => $s) {
            $name = trim((string) ($s['name'] ?? ''));
            $key = trim((string) ($s['key'] ?? $name));
            if ($name === '' || $key === '') {
                throw HttpError::validation(['speakers' => 'Nhân vật ' . ($i + 1) . ' chưa có tên.']);
            }
            $color = (string) ($s['color'] ?? '');
            $speakers[$key] = [
                'id' => isset($s['id']) ? (int) $s['id'] : null,
                'name' => mb_substr($name, 0, 30),
                'avatar_char' => mb_substr(trim((string) ($s['avatarChar'] ?? '')) ?: mb_substr($name, -1), 0, 4),
                'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#DCEBFF',
                'role_vi' => mb_substr(trim((string) ($s['roleVi'] ?? '')), 0, 40),
                'sort_order' => $i + 1,
            ];
        }

        $lines = [];
        foreach ($linesIn as $i => $l) {
            $zh = trim((string) ($l['zh'] ?? ''));
            $key = trim((string) ($l['speakerKey'] ?? ''));
            if ($zh === '') {
                throw HttpError::validation(['lines' => 'Câu thoại ' . ($i + 1) . ' chưa có nội dung.']);
            }
            if (!isset($speakers[$key])) {
                throw HttpError::validation(['lines' => 'Câu thoại ' . ($i + 1) . ' chưa chọn nhân vật.']);
            }
            $highlight = trim((string) ($l['highlight'] ?? ''));
            if ($highlight !== '' && mb_strpos($zh, $highlight) === false) {
                throw HttpError::validation(['lines' => 'Câu thoại ' . ($i + 1) . ' không chứa từ ' . $highlight . '.']);
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

        $questions = [];
        foreach (array_slice($questionsIn, 0, 5) as $i => $q) {
            $prompt = trim((string) ($q['prompt'] ?? ''));
            $options = array_values(array_filter(array_map(
                static fn ($o): string => mb_substr(trim((string) $o), 0, 200),
                (array) ($q['options'] ?? []),
            ), static fn (string $o): bool => $o !== ''));
            if ($prompt === '') {
                continue;
            }
            if (count($options) < 2) {
                throw HttpError::validation(['questions' => 'Câu hỏi ' . ($i + 1) . ' cần ít nhất 2 phương án.']);
            }
            $answer = (int) ($q['answer'] ?? 0);
            if ($answer < 0 || $answer >= count($options)) {
                throw HttpError::validation(['questions' => 'Câu hỏi ' . ($i + 1) . ' chưa chọn đáp án đúng.']);
            }
            $questions[] = ['prompt_zh' => mb_substr($prompt, 0, 255), 'options' => $options, 'answer' => $answer, 'sort_order' => $i + 1];
        }

        Db::tx(static function () use ($lessonId, $title, $scene, $imagePath, $speakers, $lines, $questions): void {
            $dlgId = Db::val('SELECT id FROM dialogues WHERE lesson_id = ?', [$lessonId]);
            if ($dlgId === null) {
                $dlgId = Db::insert('dialogues', [
                    'lesson_id' => $lessonId, 'title' => $title, 'scene' => $scene, 'image_path' => $imagePath,
                ]);
            } else {
                $dlgId = (int) $dlgId;
                Db::update('dialogues', ['title' => $title, 'scene' => $scene, 'image_path' => $imagePath], ['id' => $dlgId]);
            }

            // Nhân vật: giữ id cũ để câu thoại của lượt trước không mất tham chiếu.
            $keep = [];
            $idByKey = [];
            foreach ($speakers as $key => $s) {
                $row = ['dialogue_id' => $dlgId] + array_diff_key($s, ['id' => null]);
                if ($s['id'] !== null && Db::val('SELECT id FROM dialogue_speakers WHERE id = ? AND dialogue_id = ?', [$s['id'], $dlgId]) !== null) {
                    Db::update('dialogue_speakers', $row, ['id' => $s['id']]);
                    $idByKey[$key] = (int) $s['id'];
                } else {
                    $idByKey[$key] = Db::insert('dialogue_speakers', $row);
                }
                $keep[] = $idByKey[$key];
            }

            Db::exec('DELETE FROM dialogue_lines WHERE dialogue_id = ?', [$dlgId]);
            if ($keep !== []) {
                Db::exec(
                    'DELETE FROM dialogue_speakers WHERE dialogue_id = ? AND id NOT IN (' . Db::in($keep) . ')',
                    [$dlgId, ...$keep],
                );
            }

            foreach ($lines as $l) {
                $key = $l['speakerKey'];
                unset($l['speakerKey']);
                Db::insert('dialogue_lines', ['dialogue_id' => $dlgId, 'speaker_id' => $idByKey[$key]] + $l);
            }

            Db::exec('DELETE FROM dialogue_questions WHERE dialogue_id = ?', [$dlgId]);
            foreach ($questions as $q) {
                $q['options'] = Json::encode($q['options']);
                Db::insert('dialogue_questions', ['dialogue_id' => $dlgId] + $q);
            }
        });

        ContentWriter::touch($lessonId, $r);
        Audit::log($r, 'dialogue.save', 'lesson', $lessonId, ['lines' => count($lines)]);
        return $this->get($r) ?? [];
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
        $v = new Validator($r->body);
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
