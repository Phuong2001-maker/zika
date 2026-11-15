<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\Json;

/**
 * Pinyin in trên đầu chữ ở đề Thi thử HSK 1–2 (migration 030). Pinyin sinh sẵn theo đúng chuỗi chữ (data/hsk30/sinh_pinyin.py),
 * màn thi tra theo chuỗi đang hiện: chuỗi bị sửa thì không khớp nữa và màn hiện chữ trơn, không hiện pinyin sai.
 */
final class PinyinDe
{
    /** Đề giấy HSK 3.0 chỉ in pinyin ở cấp 1–2. */
    public const CAP_TOI_DA = 2;

    /**
     * Các chuỗi chữ Hán màn thi hiện ra của một câu (payload đã bỏ đáp án, đã xáo). Câu điền chỗ trống tách hai nửa quanh
     * `___` vì màn thi chèn ô trống vào giữa.
     * @param array<string,mixed> $p
     * @return list<string>
     */
    public static function chuoi(string $type, array $p): array
    {
        if ($type === 'listen' || $type === 'speak') {
            // Phần nghe không hiện lời nghe; chỉ phương án chữ.
            return self::han((array) ($p['options'] ?? []));
        }
        $out = [];
        foreach (['ask', 'statement', 'passage', 'word'] as $k) {
            if (isset($p[$k]) && is_string($p[$k])) {
                $out[] = $p[$k];
            }
        }
        if (isset($p['sentence']) && is_string($p['sentence'])) {
            foreach (explode('___', $p['sentence']) as $nua) {
                $out[] = $nua;
            }
        }
        foreach (['options', 'left', 'tiles'] as $k) {
            $out = [...$out, ...array_map('strval', (array) ($p[$k] ?? []))];
        }
        return self::han($out);
    }

    /** @param list<mixed> $ds @return list<string> */
    private static function han(array $ds): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $s): string => (string) $s, $ds),
            static fn (string $s): bool => preg_match('/\p{Han}/u', $s) === 1,
        ));
    }

    /**
     * Bảng chuỗi → pinyin cho cả đề (chỉ cấp 1–2). Rỗng nếu đề cấp cao hoặc chưa sinh pinyin.
     * @param list<array{type:string,payload:array<string,mixed>}> $questions
     * @return array<string,list<array{0:string,1:string}>>
     */
    public static function choDe(array $questions, int $levelId): array
    {
        if ($levelId < 1 || $levelId > self::CAP_TOI_DA) {
            return [];
        }
        $chuoi = [];
        foreach ($questions as $q) {
            foreach (self::chuoi((string) $q['type'], (array) $q['payload']) as $s) {
                $chuoi[sha1($s)] = $s;
            }
        }
        if ($chuoi === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk(array_keys($chuoi), 400) as $lo) {
            $dau = implode(',', array_fill(0, count($lo), '?'));
            foreach (Db::all("SELECT hash, text, tokens FROM text_pinyin WHERE hash IN ($dau)", $lo) as $r) {
                $tok = Json::decode((string) $r['tokens'], []);
                if (is_array($tok) && $r['text'] === $chuoi[$r['hash']]) {
                    $out[(string) $r['text']] = $tok;
                }
            }
        }
        return $out;
    }

    /**
     * Lưu pinyin đã sinh. Bỏ mục mà ghép cột chữ không ra đúng chuỗi (sinh lỗi), trả số đã lưu và lý do bỏ.
     * @param list<mixed> $items [{text, tokens:[[chữ, pinyin], …]}]
     * @return array{saved:int,skipped:list<string>}
     */
    public static function luu(array $items): array
    {
        $ok = 0;
        $bo = [];
        $now = Clock::sql();
        foreach ($items as $k => $it) {
            $text = is_array($it) ? (string) ($it['text'] ?? '') : '';
            $tok = is_array($it) && is_array($it['tokens'] ?? null) ? $it['tokens'] : null;
            if ($text === '' || $tok === null || mb_strlen($text) > 2000) {
                $bo[] = 'Dòng ' . ($k + 1) . ': thiếu chữ hoặc pinyin.';
                continue;
            }
            $sach = [];
            foreach ($tok as $t) {
                if (!is_array($t) || !isset($t[0])) {
                    continue;
                }
                $sach[] = [(string) $t[0], mb_substr((string) ($t[1] ?? ''), 0, 60)];
            }
            if (implode('', array_column($sach, 0)) !== $text) {
                $bo[] = 'Dòng ' . ($k + 1) . ': các mảnh pinyin không ghép ra đúng câu.';
                continue;
            }
            Db::exec(
                'INSERT INTO text_pinyin (hash, text, tokens, updated_at) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE text = VALUES(text), tokens = VALUES(tokens), updated_at = VALUES(updated_at)',
                [sha1($text), $text, json_encode($sach, JSON_UNESCAPED_UNICODE), $now],
            );
            $ok++;
        }
        return ['saved' => $ok, 'skipped' => $bo];
    }
}
