<?php
declare(strict_types=1);

namespace Zika\Services;

/**
 * Chấm câu hỏi (06 §3). Bản TypeScript tương ứng: web/src/lib/utils/grade.ts.
 * Hai bản phải cho cùng kết quả trên cùng bộ test (09 §3) — sửa bên này thì sửa cả bên kia.
 *
 * Câu trả lời truyền vào đây luôn theo **chỉ số gốc**; với bài Kiểm tra / Thi thử,
 * TestService đã quy đổi từ chỉ số hiển thị về chỉ số gốc bằng option_maps trước khi gọi.
 */
final class QuestionGrader
{
    /** Nhóm dạng trắc nghiệm: đáp án là một chỉ số trong options (06 §1). */
    public const CHOICE = ['meaning', 'pinyin', 'tone', 'audio', 'listen', 'blank', 'pick', 'toZh'];

    /** Mọi dạng câu dùng được ở Bài tập. */
    public const BT_TYPES = ['meaning', 'blank', 'audio', 'pinyin', 'order', 'trans', 'tf', 'match'];

    /** Mọi dạng câu dùng được ở Kiểm tra và Thi thử. */
    public const KT_TYPES = ['blank', 'listen', 'meaning', 'pick', 'order', 'trans', 'tf', 'match', 'tone', 'toZh'];

    public static function isChoice(string $type): bool
    {
        return in_array($type, self::CHOICE, true);
    }

    /**
     * @param array<string,mixed> $payload
     * @param mixed               $answer câu trả lời theo chỉ số gốc
     */
    public static function check(string $type, array $payload, mixed $answer): bool
    {
        if (self::isChoice($type)) {
            return is_int($answer) && $answer === (int) ($payload['answer'] ?? -1);
        }

        return match ($type) {
            'order' => self::checkOrder($payload, $answer),
            'trans' => self::checkTrans($payload, $answer),
            'tf'    => is_bool($answer) && $answer === (bool) ($payload['answer'] ?? false),
            'match' => self::checkMatch($payload, $answer),
            default => false,
        };
    }

    /** Người học đã trả lời câu này chưa (để đếm “đã làm / chưa làm”). */
    public static function answered(string $type, array $payload, mixed $answer): bool
    {
        if ($answer === null) {
            return false;
        }
        if (self::isChoice($type)) {
            return is_int($answer);
        }
        return match ($type) {
            'order' => is_array($answer) && count($answer) === count((array) ($payload['tiles'] ?? [])),
            'trans' => is_string($answer) && trim($answer) !== '',
            'tf'    => is_bool($answer),
            'match' => is_array($answer) && count($answer) === count((array) ($payload['pairs'] ?? [])),
            default => false,
        };
    }

    /** @param mixed $answer list<int> — chỉ số ô theo tiles gốc, đúng thứ tự người học xếp */
    private static function checkOrder(array $payload, mixed $answer): bool
    {
        $tiles = (array) ($payload['tiles'] ?? []);
        if (!is_array($answer) || $tiles === [] || count($answer) !== count($tiles)) {
            return false;
        }
        $built = '';
        foreach ($answer as $i) {
            if (!is_int($i) || !isset($tiles[$i])) {
                return false;
            }
            $built .= (string) $tiles[$i];
        }
        return self::normZh($built) === self::normZh((string) ($payload['answer'] ?? ''));
    }

    /** Dịch: mọi ý trong keywords phải xuất hiện (mỗi ý có thể có nhiều cách viết, ngăn bằng “|”). */
    private static function checkTrans(array $payload, mixed $answer): bool
    {
        if (!is_string($answer) || trim($answer) === '') {
            return false;
        }
        $got = self::normVi($answer);
        foreach ((array) ($payload['keywords'] ?? []) as $kw) {
            $hit = false;
            foreach (explode('|', (string) $kw) as $variant) {
                $v = trim(self::normVi($variant));
                if ($v !== '' && str_contains($got, ' ' . $v . ' ')) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        return true;
    }

    /** @param mixed $answer array<int,int> — { chỉSốTrái: chỉSốPhải }, đúng khi i → i theo pairs gốc */
    private static function checkMatch(array $payload, mixed $answer): bool
    {
        $pairs = (array) ($payload['pairs'] ?? []);
        if (!is_array($answer) || $pairs === []) {
            return false;
        }
        foreach (array_keys($pairs) as $i) {
            $got = $answer[$i] ?? $answer[(string) $i] ?? null;
            if (!is_int($got) || $got !== (int) $i) {
                return false;
            }
        }
        return count($answer) === count($pairs);
    }

    /** Chữ Hán: bỏ khoảng trắng và dấu câu Trung/Anh (06 §3). */
    public static function normZh(string $s): string
    {
        $s = \Normalizer::isNormalized($s) ? $s : (string) \Normalizer::normalize($s);
        return (string) preg_replace('/[\s，。！？、；：“”‘’（）《》,.!?;:\'"()\-]/u', '', $s);
    }

    /** Tiếng Việt: thường hoá, bỏ dấu câu, gộp khoảng trắng, GIỮ dấu thanh; bọc 2 đầu bằng khoảng trắng. */
    public static function normVi(string $s): string
    {
        $s = \Normalizer::isNormalized($s) ? $s : (string) \Normalizer::normalize($s);
        $s = mb_strtolower($s);
        $s = (string) preg_replace('/[.,!?;:"\'“”()\-…]/u', ' ', $s);
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        return ' ' . trim($s) . ' ';
    }

    /**
     * Chuỗi đáp án đúng để hiển thị ở màn kết quả (06 §3).
     * @param array<string,mixed> $payload
     */
    public static function rightAnswerText(string $type, array $payload): string
    {
        if (self::isChoice($type)) {
            $opts = (array) ($payload['options'] ?? []);
            return (string) ($opts[(int) ($payload['answer'] ?? 0)] ?? '');
        }
        return match ($type) {
            'order' => (string) ($payload['answer'] ?? ''),
            'trans' => (string) ($payload['sample'] ?? ''),
            'tf'    => ((bool) ($payload['answer'] ?? false)) ? 'Đúng' : 'Sai',
            'match' => implode(' · ', array_map(
                static fn (array $p): string => ($p[0] ?? '') . ' ' . ($p[1] ?? ''),
                (array) ($payload['pairs'] ?? []),
            )),
            default => '',
        };
    }

    /**
     * Chuỗi mô tả câu trả lời của người học (chỉ số gốc).
     * @param array<string,mixed> $payload
     */
    public static function yourAnswerText(string $type, array $payload, mixed $answer): string
    {
        if ($answer === null) {
            return 'Chưa trả lời';
        }
        if (self::isChoice($type)) {
            $opts = (array) ($payload['options'] ?? []);
            return is_int($answer) ? (string) ($opts[$answer] ?? 'Chưa trả lời') : 'Chưa trả lời';
        }
        return match ($type) {
            'order' => is_array($answer)
                ? implode('', array_map(static fn ($i) => (string) (((array) ($payload['tiles'] ?? []))[$i] ?? ''), $answer))
                : 'Chưa trả lời',
            'trans' => is_string($answer) ? trim($answer) : 'Chưa trả lời',
            'tf'    => is_bool($answer) ? ($answer ? 'Đúng' : 'Sai') : 'Chưa trả lời',
            'match' => self::matchAnswerText($payload, $answer),
            default => '',
        };
    }

    private static function matchAnswerText(array $payload, mixed $answer): string
    {
        $pairs = (array) ($payload['pairs'] ?? []);
        if (!is_array($answer) || $pairs === []) {
            return 'Chưa trả lời';
        }
        $out = [];
        foreach ($pairs as $i => $p) {
            $j = $answer[$i] ?? $answer[(string) $i] ?? null;
            $right = is_int($j) && isset($pairs[$j]) ? (string) ($pairs[$j][1] ?? '') : '…';
            $out[] = ($p[0] ?? '') . ' ' . $right;
        }
        return implode(' · ', $out);
    }

    /**
     * Mô tả ngắn câu hỏi, hiện ở danh sách trong trình soạn và ở màn kết quả.
     * @param array<string,mixed> $payload
     */
    public static function promptText(string $type, array $payload): string
    {
        $p = static fn (string $k): string => trim((string) ($payload[$k] ?? ''));
        return match ($type) {
            'meaning' => $p('word') . ' → nghĩa?',
            'pinyin'  => $p('word') . ' → pinyin?',
            'tone'    => $p('word') . ' → thanh điệu?',
            'audio', 'listen' => 'Nghe: ' . $p('audioText'),
            'blank'   => $p('sentence'),
            'pick'    => $p('ask'),
            'toZh'    => $p('vi'),
            'order'   => implode(' / ', array_map('strval', (array) ($payload['tiles'] ?? []))),
            'trans'   => $p('zh'),
            'tf'      => '★ ' . $p('statement'),
            'match'   => implode(' · ', array_map(
                static fn (array $x): string => (string) ($x[0] ?? ''),
                (array) ($payload['pairs'] ?? []),
            )),
            default   => '',
        };
    }

    /**
     * Bỏ mọi thứ lộ đáp án khỏi payload trước khi gửi đề Kiểm tra / Thi thử ra trình duyệt (04 §7.1).
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function hideAnswer(array $payload): array
    {
        unset($payload['answer'], $payload['keywords'], $payload['sample']);
        return $payload;
    }
}
