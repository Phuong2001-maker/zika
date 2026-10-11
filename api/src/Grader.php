<?php
declare(strict_types=1);

namespace Zika;

/**
 * Chấm một câu trả lời.
 *
 * Phải khớp từng dạng với bản chấm ở frontend (web/src/lib/grade.js) — bản JS
 * dùng để hiện đúng/sai ngay khi làm Bài tập, còn bản PHP này là bản có thẩm
 * quyền: điểm bài Kiểm tra luôn do server tính.
 */
final class Grader
{
    /** Các dạng trắc nghiệm chọn 1 trong 4. */
    public const CHOICE = ['meaning', 'blank', 'audio', 'pinyin', 'fill', 'listen', 'pick', 'tone', 'toZh'];

    /**
     * @param array $q      Dòng questions lấy từ CSDL (có data, answer, kind, id)
     * @param mixed $given  Câu trả lời của người học
     */
    public static function check(array $q, mixed $given): bool
    {
        $kind = (string) $q['kind'];
        $ans  = Util::jsonDecode($q['answer'] ?? null, []);
        $data = Util::jsonDecode($q['data'] ?? null, []);

        if (!is_array($ans)) {
            return false;
        }

        if (in_array($kind, self::CHOICE, true)) {
            return is_numeric($given) && (int) $given === (int) ($ans['ans'] ?? -1);
        }

        switch ($kind) {
            case 'order':
                // $given là mảng chỉ số các thẻ chữ theo thứ tự người học xếp.
                if (!is_array($given)) {
                    return false;
                }
                $tiles = $data['tiles'] ?? [];
                if (!is_array($tiles) || count($given) !== count($tiles)) {
                    return false;
                }
                $built = '';
                foreach ($given as $k) {
                    if (!isset($tiles[(int) $k])) {
                        return false;
                    }
                    $built .= $tiles[(int) $k];
                }
                return $built === (string) ($ans['text'] ?? '');

            case 'trans':
                // Dịch tự do: chỉ cần có đủ các từ khoá, không bắt đúng từng chữ.
                if (!is_string($given) || trim($given) === '') {
                    return false;
                }
                $norm = Util::normalizeText($given);
                foreach ((array) ($ans['keywords'] ?? []) as $kw) {
                    if (!str_contains($norm, Util::normalizeText((string) $kw))) {
                        return false;
                    }
                }
                return true;

            case 'tf':
                if (!is_bool($given)) {
                    return false;
                }
                return $given === (bool) ($ans['tf'] ?? false);

            case 'match':
                // $given = ['pairs' => [leftIndex => rightIndexTrongCotDaTron]]
                if (!is_array($given) || !isset($given['pairs']) || !is_array($given['pairs'])) {
                    return false;
                }
                $key = self::matchKey($q);
                if ($key === []) {
                    return false;
                }
                foreach ($key as $leftIdx => $rightIdx) {
                    $got = $given['pairs'][$leftIdx] ?? $given['pairs'][(string) $leftIdx] ?? null;
                    if ($got === null || (int) $got !== (int) $rightIdx) {
                        return false;
                    }
                }
                return count($given['pairs']) === count($key);

            default:
                return false;
        }
    }

    /**
     * Khoá đáp án của câu nối từ, tính lại đúng phép trộn mà ContentCtrl đã dùng
     * khi gửi đề ra giao diện (trộn theo id câu hỏi nên luôn tái lập được).
     */
    public static function matchKey(array $q): array
    {
        $data  = Util::jsonDecode($q['data'] ?? null, []);
        $pairs = $data['pairs'] ?? [];
        if (!is_array($pairs) || $pairs === []) {
            return [];
        }

        $n     = count($pairs);
        $order = range(0, $n - 1);
        mt_srand((int) $q['id']);
        shuffle($order);
        mt_srand();

        $key = [];
        for ($i = 0; $i < $n; $i++) {
            $pos = array_search($i, $order, true);
            $key[$i] = $pos === false ? $i : (int) $pos;
        }
        return $key;
    }
}
