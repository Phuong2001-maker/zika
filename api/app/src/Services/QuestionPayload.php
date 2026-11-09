<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\HttpError;
use Zika\Core\Pinyin;

/**
 * Kiểm tra và làm sạch payload câu hỏi khi admin lưu (06 §4).
 * Thông báo lỗi chỉ rõ trường để trình soạn tô đúng ô đang sai.
 */
final class QuestionPayload
{
    /** Số phương án của câu trắc nghiệm. */
    private const MIN_OPTIONS = 2;
    private const MAX_OPTIONS = 4;

    /**
     * @param array<string,mixed> $in payload thô từ trình soạn
     * @return array<string,mixed> payload đã làm sạch, sẵn sàng lưu vào cột JSON
     */
    public static function clean(string $type, array $in): array
    {
        $errors = [];
        $out = match ($type) {
            'meaning', 'pinyin', 'tone' => self::word($type, $in, $errors),
            'audio', 'listen' => self::audio($type, $in, $errors),
            'blank' => self::blank($in, $errors),
            'pick' => self::ask($in, $errors, 'ask', 'Câu hỏi'),
            'toZh' => self::ask($in, $errors, 'vi', 'Câu tiếng Việt'),
            'order' => self::order($in, $errors),
            'trans' => self::trans($in, $errors),
            'tf' => self::tf($in, $errors),
            'match' => self::match($in, $errors),
            'speak' => self::speak($in, $errors),
            'pic' => self::pic($in, $errors),
            default => throw HttpError::validation(['type' => 'Dạng câu không hợp lệ.']),
        };
        // Hình minh hoạ đi kèm câu (đề HSK 3.0: nhìn tranh trả lời, nhìn tranh nói). Chỉ nhận hình trong kho của web.
        $hinh = self::hinh($in['imageUrl'] ?? null);
        if ($hinh !== null) {
            $out['imageUrl'] = $hinh;
        } elseif ($type === 'speak' && ($out['task'] ?? '') === 'picture') {
            $errors['imageUrl'] = 'Câu nhìn tranh nói cần chọn một hình.';
        }

        if ($errors !== []) {
            throw HttpError::validation($errors);
        }
        return $out;
    }

    /* ---------------- Nhóm trắc nghiệm ---------------- */

    /**
     * @param array<string,string> $errors
     * @return array<string,mixed>
     */
    private static function options(array $in, array &$errors, bool $hanziOnly = false): array
    {
        $opts = [];
        // Ô trống bị lọc bỏ làm lệch chỉ số: đáp án phải được đổi theo vị trí mới, nếu không đáp án lưu sẽ trỏ sang phương án khác.
        $newIndex = [];
        foreach (array_values((array) ($in['options'] ?? [])) as $old => $o) {
            $t = trim((string) $o);
            if ($t !== '') {
                $newIndex[$old] = count($opts);
                $opts[] = mb_substr($t, 0, 200);
            }
        }

        if (count($opts) < self::MIN_OPTIONS) {
            $errors['options'] = 'Cần ít nhất ' . self::MIN_OPTIONS . ' phương án.';
            return [[], 0];
        }
        if (count($opts) > self::MAX_OPTIONS) {
            $opts = array_slice($opts, 0, self::MAX_OPTIONS);
        }

        // Trùng phương án thì người học có 2 đáp án đúng như nhau → chỉ rõ chữ cái.
        foreach ($opts as $i => $a) {
            foreach (array_slice($opts, $i + 1, null, true) as $j => $b) {
                if ($a === $b) {
                    $errors['options'] = 'Đáp án ' . chr(65 + $i) . ' và ' . chr(65 + $j) . ' đang trùng nhau.';
                    break 2;
                }
            }
        }
        if ($hanziOnly) {
            foreach ($opts as $o) {
                if (!Pinyin::isHanzi(str_replace(['，', '。', '？', '！', '、'], '', $o))) {
                    $errors['options'] = 'Các phương án phải là chữ Hán.';
                    break;
                }
            }
        }

        $answer = $newIndex[(int) ($in['answer'] ?? -1)] ?? -1;
        if ($answer < 0 || $answer >= count($opts)) {
            $errors['answer'] = 'Chưa chọn đáp án đúng.';
            $answer = 0;
        }

        return [$opts, $answer];
    }

    /** meaning · pinyin · tone — có `word` + `ask`. */
    private static function word(string $type, array $in, array &$errors): array
    {
        $word = trim((string) ($in['word'] ?? ''));
        if ($word === '' || !Pinyin::isHanzi($word)) {
            $errors['word'] = 'Nhập chữ Hán của từ.';
        } elseif ($type === 'tone' && mb_strlen($word) !== 1) {
            $errors['word'] = 'Dạng thanh điệu chỉ dùng cho một chữ.';
        } elseif (mb_strlen($word) > 16) {
            $errors['word'] = 'Từ dài quá 16 chữ.';
        }

        [$opts, $answer] = self::options($in, $errors);
        return [
            'word' => mb_substr($word, 0, 16),
            'ask' => mb_substr(trim((string) ($in['ask'] ?? '')), 0, 120),
            'options' => $opts,
            'answer' => $answer,
        ];
    }

    /** audio (đáp án chữ Hán) · listen (đáp án tiếng Việt). */
    private static function audio(string $type, array $in, array &$errors): array
    {
        $text = trim((string) ($in['audioText'] ?? ''));
        $max = $type === 'audio' ? 32 : 200;
        if ($text === '') {
            $errors['audioText'] = 'Nhập câu hoặc từ để đọc.';
        } elseif (mb_strlen($text) > $max) {
            $errors['audioText'] = "Nội dung đọc dài quá $max ký tự.";
        }

        [$opts, $answer] = self::options($in, $errors, $type === 'audio');
        return [
            'audioText' => mb_substr($text, 0, $max),
            'audioUrl' => self::path($in['audioUrl'] ?? null),
            'options' => $opts,
            'answer' => $answer,
        ];
    }

    /** blank — câu có đúng một chỗ trống “___”. */
    private static function blank(array $in, array &$errors): array
    {
        $sentence = trim((string) ($in['sentence'] ?? ''));
        if (substr_count($sentence, '___') !== 1) {
            $errors['sentence'] = 'Câu phải có đúng một chỗ trống ___.';
        }
        if (mb_strlen($sentence) > 255) {
            $errors['sentence'] = 'Câu dài quá 255 ký tự.';
        }

        [$opts, $answer] = self::options($in, $errors);
        return [
            'sentence' => mb_substr($sentence, 0, 255),
            'vi' => mb_substr(trim((string) ($in['vi'] ?? '')), 0, 255),
            'showMeaning' => (bool) ($in['showMeaning'] ?? true),
            'options' => $opts,
            'answer' => $answer,
        ];
    }

    /** pick (`ask`) · toZh (`vi`) — chỉ khác tên trường đề bài. */
    private static function ask(array $in, array &$errors, string $field, string $label): array
    {
        $text = trim((string) ($in[$field] ?? ''));
        if ($text === '') {
            $errors[$field] = "Nhập $label.";
        } elseif (mb_strlen($text) > 200) {
            $errors[$field] = "$label dài quá 200 ký tự.";
        }

        [$opts, $answer] = self::options($in, $errors);
        return [$field => mb_substr($text, 0, 200), 'options' => $opts, 'answer' => $answer];
    }

    /* ---------------- Các dạng còn lại ---------------- */

    private static function order(array $in, array &$errors): array
    {
        $answer = trim((string) ($in['answer'] ?? ''));
        if ($answer === '') {
            $errors['answer'] = 'Nhập câu đúng.';
        } elseif (mb_strlen($answer) > 60) {
            $errors['answer'] = 'Câu đúng dài quá 60 ký tự.';
        }

        $tiles = [];
        foreach ((array) ($in['tiles'] ?? []) as $t) {
            $s = trim((string) $t);
            if ($s !== '') {
                $tiles[] = mb_substr($s, 0, 20);
            }
        }
        if (count($tiles) < 2 || count($tiles) > 8) {
            $errors['tiles'] = 'Cần 2 đến 8 ô chữ.';
        } elseif (QuestionGrader::normZh(implode('', $tiles)) !== QuestionGrader::normZh($answer)) {
            $errors['tiles'] = 'Các ô chữ ghép lại chưa đúng với câu đúng.';
        }

        return [
            'vi' => mb_substr(trim((string) ($in['vi'] ?? '')), 0, 200),
            'answer' => mb_substr($answer, 0, 60),
            'tiles' => $tiles,
        ];
    }

    private static function trans(array $in, array &$errors): array
    {
        $zh = trim((string) ($in['zh'] ?? ''));
        if ($zh === '') {
            $errors['zh'] = 'Nhập câu tiếng Trung.';
        } elseif (mb_strlen($zh) > 200) {
            $errors['zh'] = 'Câu dài quá 200 ký tự.';
        }

        $keywords = [];
        foreach ((array) ($in['keywords'] ?? []) as $k) {
            $s = trim((string) $k);
            if ($s !== '') {
                $keywords[] = mb_substr($s, 0, 60);
            }
        }
        if ($keywords === []) {
            $errors['keywords'] = 'Nhập ít nhất một ý bắt buộc để chấm.';
        } elseif (count($keywords) > 6) {
            $keywords = array_slice($keywords, 0, 6);
        }

        return [
            'zh' => mb_substr($zh, 0, 200),
            'pinyin' => mb_substr(trim((string) ($in['pinyin'] ?? '')), 0, 500),
            'sample' => mb_substr(trim((string) ($in['sample'] ?? '')), 0, 300),
            'keywords' => $keywords,
        ];
    }

    private static function tf(array $in, array &$errors): array
    {
        $passage = trim((string) ($in['passage'] ?? ''));
        $statement = trim((string) ($in['statement'] ?? ''));
        if ($passage === '') {
            $errors['passage'] = 'Nhập đoạn văn.';
        } elseif (mb_strlen($passage) > 400) {
            $errors['passage'] = 'Đoạn văn dài quá 400 ký tự.';
        }
        if ($statement === '') {
            $errors['statement'] = 'Nhập câu cần xét đúng/sai.';
        } elseif (mb_strlen($statement) > 200) {
            $errors['statement'] = 'Câu dài quá 200 ký tự.';
        }

        return [
            'passage' => mb_substr($passage, 0, 400),
            'statement' => mb_substr($statement, 0, 200),
            'answer' => (bool) ($in['answer'] ?? false),
        ];
    }

    private static function match(array $in, array &$errors): array
    {
        $pairs = [];
        foreach ((array) ($in['pairs'] ?? []) as $p) {
            $a = trim((string) ($p[0] ?? ''));
            $b = trim((string) ($p[1] ?? ''));
            if ($a !== '' && $b !== '') {
                $pairs[] = [mb_substr($a, 0, 32), mb_substr($b, 0, 200)];
            }
        }

        if (count($pairs) < 2 || count($pairs) > 6) {
            $errors['pairs'] = 'Cần 2 đến 6 cặp từ – nghĩa.';
        }
        if (count(array_unique(array_column($pairs, 0))) !== count($pairs)) {
            $errors['pairs'] = 'Có hai dòng dùng cùng một từ.';
        }
        if (count(array_unique(array_column($pairs, 1))) !== count($pairs)) {
            $errors['pairs'] = 'Có hai dòng dùng cùng một nghĩa.';
        }

        return ['pairs' => $pairs];
    }

    /** Các kiểu câu nói của đề HSK 3.0 → đề bài cần trường nào. */
    public const SPEAK_TASKS = ['repeat', 'read', 'answer', 'topic', 'picture'];

    /** Mã hình trong kho web/static/hinh-hsk/ (chữ thường, số, gạch nối). */
    private const MA_HINH = '~^[a-z0-9][a-z0-9-]{0,39}$~';

    /** Đường dẫn hình hợp lệ: kho hình của web hoặc file đã tải lên /uploads/; còn lại bỏ qua. */
    private static function hinh(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if (preg_match('~^/hinh-hsk/([a-z0-9-]+)\.svg$~', $s, $m) && preg_match(self::MA_HINH, $m[1])) {
            return $s;
        }
        return self::path($s);
    }

    /**
     * pic — chọn tranh đúng (HSK 1–2): nghe `audioText` hoặc đọc `ask` rồi chọn một trong 2–4 hình. Phương án là mã
     * hình trong kho, chấm như trắc nghiệm theo chỉ số.
     */
    private static function pic(array $in, array &$errors): array
    {
        $audio = trim((string) ($in['audioText'] ?? ''));
        $ask = trim((string) ($in['ask'] ?? ''));
        if ($audio === '' && $ask === '') {
            $errors['audioText'] = 'Nhập câu để nghe hoặc câu hỏi để đọc.';
        }
        if (mb_strlen($audio) > 200 || mb_strlen($ask) > 200) {
            $errors['audioText'] = 'Dài quá 200 ký tự.';
        }
        [$opts, $answer] = self::options($in, $errors);
        foreach ($opts as $o) {
            if (!preg_match(self::MA_HINH, $o)) {
                $errors['options'] = 'Mỗi phương án phải là một hình trong kho.';
                break;
            }
        }
        return ['audioText' => mb_substr($audio, 0, 200), 'ask' => mb_substr($ask, 0, 200), 'options' => $opts, 'answer' => $answer];
    }

    /**
     * speak — câu nói (Thi thử HSK 3.0). Người học ghi âm ở máy mình nên không có đáp án để chấm; `sample` là bài nói
     * mẫu, chỉ hiện sau khi nộp hoặc khi luyện tập.
     *   repeat: nghe `audioText` rồi nhắc lại (không hiện chữ) · read: đọc to `prompt`
     *   answer: nghe/đọc câu hỏi rồi trả lời · topic: nói theo chủ đề `prompt`
     */
    private static function speak(array $in, array &$errors): array
    {
        $task = in_array($in['task'] ?? null, self::SPEAK_TASKS, true) ? (string) $in['task'] : 'answer';
        $prompt = trim((string) ($in['prompt'] ?? ''));
        $audio = trim((string) ($in['audioText'] ?? ''));
        if ($task === 'repeat' && $audio === '') {
            $errors['audioText'] = 'Nhập câu để người học nghe rồi nhắc lại.';
        } elseif (in_array($task, ['read', 'topic'], true) && $prompt === '') {
            $errors['prompt'] = $task === 'read' ? 'Nhập đoạn để người học đọc to.' : 'Nhập chủ đề để người học nói.';
        } elseif ($task !== 'picture' && $prompt === '' && $audio === '') {
            $errors['prompt'] = 'Nhập câu hỏi (chữ hoặc nội dung đọc).';
        }
        foreach (['prompt' => $prompt, 'audioText' => $audio] as $k => $v) {
            if (mb_strlen($v) > 300) {
                $errors[$k] = 'Dài quá 300 ký tự.';
            }
        }
        $prep = filter_var($in['prepSec'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 300]]);
        $answer = filter_var($in['answerSec'] ?? 30, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 300]]);
        if ($prep === false) {
            $errors['prepSec'] = 'Thời gian chuẩn bị từ 0 đến 300 giây.';
        }
        if ($answer === false) {
            $errors['answerSec'] = 'Thời gian nói từ 5 đến 300 giây.';
        }

        return [
            'task' => $task,
            'prompt' => mb_substr($prompt, 0, 300),
            'audioText' => mb_substr($audio, 0, 300),
            'vi' => mb_substr(trim((string) ($in['vi'] ?? '')), 0, 300),
            'prepSec' => $prep === false ? 10 : $prep,
            'answerSec' => $answer === false ? 30 : $answer,
            'sample' => mb_substr(trim((string) ($in['sample'] ?? '')), 0, 500),
            'sampleVi' => mb_substr(trim((string) ($in['sampleVi'] ?? '')), 0, 500),
        ];
    }

    /** Chỉ nhận đường dẫn nội bộ /uploads/… để không nhúng được URL ngoài. */
    private static function path(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s !== '' && str_starts_with($s, '/uploads/') ? mb_substr($s, 0, 255) : null;
    }
}
