<?php
declare(strict_types=1);

/**
 * Xuất giáo trình "Hán ngữ 6 tập" từ DB local thành một file SQL để chạy trên server.
 *
 *   php xuat_sql.php <đường dẫn file ra>
 *
 * Chỉ đụng đúng giáo trình này: chạy lại được nhiều lần (xoá bài cũ của giáo trình rồi nạp mới), không
 * chạm bài HSK, tài khoản hay tiến độ của giáo trình khác. Không ghi id cứng của local vì id trên server
 * có thể khác: bài, từ, hội thoại nối với nhau qua biến phiên, chữ Hán nối qua cột hanzi (bảng dùng chung).
 * Vì dùng biến phiên nên cả file phải chạy trong cùng một kết nối (phpMyAdmin Import hoặc lệnh mysql).
 */

const TEN = 'Hán ngữ 6 tập';

$cfg = require dirname(__DIR__, 2) . '/api/app/config.php';
$d = $cfg['db'];
$ten_db = getenv('ZIKA_XUAT_DB') ?: $d['name'];
$pdo = new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$ten_db};charset=utf8mb4", $d['user'], $d['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$ra = $argv[1] ?? null;
if ($ra === null) {
    fwrite(STDERR, "Thiếu đường dẫn file ra.\n");
    exit(2);
}

/** @param array<string,mixed> $row */
function chen(PDO $pdo, string $bang, array $row): string
{
    $cot = implode(', ', array_map(static fn (string $c): string => "`$c`", array_keys($row)));
    $gt = implode(', ', array_map(static function ($v) use ($pdo): string {
        if ($v === null) {
            return 'NULL';
        }
        if (is_array($v) && isset($v['raw'])) {
            return $v['raw'];
        }
        return $pdo->quote((string) $v);
    }, array_values($row)));
    return "INSERT INTO `$bang` ($cot) VALUES ($gt)";
}

/** @param array<string,mixed> $row @param list<string> $bo @return array<string,mixed> */
function bo_cot(array $row, array $bo): array
{
    return array_diff_key($row, array_flip($bo));
}

$st = $pdo->prepare('SELECT * FROM curricula WHERE name = ?');
$st->execute([TEN]);
$gt = $st->fetch();
if ($gt === false) {
    fwrite(STDERR, "Local chưa có giáo trình " . TEN . ".\n");
    exit(1);
}
$cid = (int) $gt['id'];

$bai = $pdo->query("SELECT * FROM lessons WHERE curriculum_id = $cid ORDER BY level_id, lesson_no")->fetchAll();
$cap = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['level_id'], $bai)));

$f = fopen($ra, 'wb');
$viet = static function (string $s) use ($f): void {
    fwrite($f, $s . "\n");
};

$viet('-- Zìkǎ · Giáo trình "' . TEN . '": ' . count($bai) . ' bài. Xuất lúc ' . date('d/m/Y H:i') . '.');
$viet('-- Chạy SAU các migration tới 015 (cần bảng curricula và cột level_label). Chạy lại được, không đụng giáo trình khác.');
$viet('-- Sau khi chạy: chép thư mục uploads đi kèm và xoá storage/cache/content.');
$viet('SET NAMES utf8mb4;');
$viet('SET time_zone = \'+07:00\';');
$viet('START TRANSACTION;');
$viet(chen($pdo, 'curricula', ['name' => TEN, 'description' => $gt['description'], 'color' => $gt['color'], 'level_label' => $gt['level_label']])
    . ' ON DUPLICATE KEY UPDATE description = VALUES(description), color = VALUES(color), level_label = VALUES(level_label);');
$viet('SET @c = (SELECT id FROM curricula WHERE name = ' . $pdo->quote(TEN) . ');');
$viet('-- Xoá bản nạp trước của giáo trình này (khoá ngoại xoá theo câu thoại, ngữ pháp, chữ viết của bài).');
$viet('DELETE FROM words WHERE curriculum_id = @c;');
$viet('DELETE FROM lessons WHERE curriculum_id = @c;');
if ($cap !== []) {
    $viet('UPDATE levels SET is_visible = 1 WHERE id IN (' . implode(',', $cap) . ');');
}

// Chữ dùng chung nhiều giáo trình: chỉ thêm chữ server chưa có, không ghi đè chữ HSK đang dùng.
$viet('-- Bảng chữ dùng chung: chữ đã có thì giữ nguyên.');
$chu = $pdo->query(
    "SELECT DISTINCT c.* FROM characters c JOIN lesson_chars lc ON lc.char_id = c.id JOIN lessons l ON l.id = lc.lesson_id
      WHERE l.curriculum_id = $cid ORDER BY c.id",
)->fetchAll();
foreach ($chu as $c) {
    $row = bo_cot($c, ['id', 'updated_at']);
    $viet(chen($pdo, 'characters', $row) . ' ON DUPLICATE KEY UPDATE hanzi = hanzi;');
    // Chữ server có sẵn mà chưa có phân tích (nạp từ bản cũ) thì điền, chữ đã có thì không đổi.
    if ($c['analysis'] !== null && $c['analysis'] !== '') {
        $viet('UPDATE characters SET radical = COALESCE(radical, ' . $pdo->quote((string) $c['radical']) . '), radical_no = COALESCE(radical_no, '
            . ($c['radical_no'] === null ? 'NULL' : (int) $c['radical_no']) . '), analysis = COALESCE(analysis, ' . $pdo->quote((string) $c['analysis'])
            . '), radical_examples = COALESCE(radical_examples, ' . ($c['radical_examples'] === null ? 'NULL' : $pdo->quote((string) $c['radical_examples']))
            . ') WHERE hanzi = ' . $pdo->quote((string) $c['hanzi']) . ' AND analysis IS NULL;');
    }
}

$dem = ['bai' => 0, 'tu' => 0, 'vd' => 0, 'chu' => 0, 'khoa' => 0, 'cau' => 0, 'np' => 0];
foreach ($bai as $l) {
    $lid = (int) $l['id'];
    $viet('-- Quyển ' . $l['level_id'] . ' · Bài ' . $l['lesson_no']);
    $row = bo_cot($l, ['id', 'updated_by', 'created_at', 'updated_at']);
    $row['curriculum_id'] = ['raw' => '@c'];
    $viet(chen($pdo, 'lessons', $row) . ';');
    $viet('SET @l = LAST_INSERT_ID();');
    $dem['bai']++;

    foreach ($pdo->query("SELECT * FROM words WHERE lesson_id = $lid ORDER BY sort_order, id")->fetchAll() as $w) {
        $row = bo_cot($w, ['id', 'created_at', 'updated_at']);
        $row['curriculum_id'] = ['raw' => '@c'];
        $row['lesson_id'] = ['raw' => '@l'];
        $viet(chen($pdo, 'words', $row) . ';');
        $viet('SET @w = LAST_INSERT_ID();');
        $dem['tu']++;
        foreach ($pdo->query('SELECT * FROM word_examples WHERE word_id = ' . (int) $w['id'] . ' ORDER BY sort_order, id')->fetchAll() as $e) {
            $row = bo_cot($e, ['id']);
            $row['word_id'] = ['raw' => '@w'];
            $viet(chen($pdo, 'word_examples', $row) . ';');
            $dem['vd']++;
        }
    }

    foreach ($pdo->query("SELECT lc.*, c.hanzi, w.hanzi AS tu_nguon FROM lesson_chars lc JOIN characters c ON c.id = lc.char_id
                           LEFT JOIN words w ON w.id = lc.source_word_id WHERE lc.lesson_id = $lid ORDER BY lc.sort_order, lc.id")->fetchAll() as $x) {
        $nguon = $x['tu_nguon'] === null ? 'NULL'
            : '(SELECT id FROM words WHERE curriculum_id = @c AND level_id = ' . (int) $l['level_id'] . ' AND hanzi = ' . $pdo->quote((string) $x['tu_nguon']) . ')';
        $viet(chen($pdo, 'lesson_chars', [
            'lesson_id' => ['raw' => '@l'],
            'char_id' => ['raw' => '(SELECT id FROM characters WHERE hanzi = ' . $pdo->quote((string) $x['hanzi']) . ')'],
            'source_word_id' => ['raw' => $nguon],
            'sort_order' => $x['sort_order'],
            'is_visible' => $x['is_visible'],
        ]) . ';');
        $dem['chu']++;
    }
    foreach ($pdo->query("SELECT * FROM writing_configs WHERE lesson_id = $lid")->fetchAll() as $x) {
        $x['lesson_id'] = ['raw' => '@l'];
        $viet(chen($pdo, 'writing_configs', $x) . ';');
    }

    foreach ($pdo->query("SELECT * FROM dialogues WHERE lesson_id = $lid ORDER BY sort_order, id")->fetchAll() as $k) {
        $row = bo_cot($k, ['id', 'updated_at']);
        $row['lesson_id'] = ['raw' => '@l'];
        $viet(chen($pdo, 'dialogues', $row) . ';');
        $viet('SET @d = LAST_INSERT_ID();');
        $dem['khoa']++;
        $bien = [];
        foreach ($pdo->query('SELECT * FROM dialogue_speakers WHERE dialogue_id = ' . (int) $k['id'] . ' ORDER BY sort_order, id')->fetchAll() as $i => $s) {
            $row = bo_cot($s, ['id']);
            $row['dialogue_id'] = ['raw' => '@d'];
            $viet(chen($pdo, 'dialogue_speakers', $row) . ';');
            $viet("SET @s$i = LAST_INSERT_ID();");
            $bien[(int) $s['id']] = "@s$i";
        }
        foreach ($pdo->query('SELECT * FROM dialogue_lines WHERE dialogue_id = ' . (int) $k['id'] . ' ORDER BY sort_order, id')->fetchAll() as $c) {
            $row = bo_cot($c, ['id']);
            $row['dialogue_id'] = ['raw' => '@d'];
            $row['speaker_id'] = ['raw' => $bien[(int) $c['speaker_id']]];
            $viet(chen($pdo, 'dialogue_lines', $row) . ';');
            $dem['cau']++;
        }
    }

    foreach ($pdo->query("SELECT * FROM grammar_points WHERE lesson_id = $lid ORDER BY sort_order, id")->fetchAll() as $g) {
        $row = bo_cot($g, ['id', 'updated_at']);
        $row['lesson_id'] = ['raw' => '@l'];
        $viet(chen($pdo, 'grammar_points', $row) . ';');
        $dem['np']++;
    }
}
$viet('COMMIT;');
fclose($f);
echo json_encode(['file' => $ra, 'bytes' => filesize($ra), 'dem' => $dem, 'chu_chung' => count($chu)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
