<?php
declare(strict_types=1);

/**
 * Thử file SQL trước khi đưa lên server, trên một DB bản sao tên test_zika_sql:
 *   1. Sao chép DB local, bỏ giáo trình Hán ngữ (giống server chưa có giáo trình này).
 *   2. Chạy file SQL → so nội dung với local từng bài, từng từ, từng câu (bỏ qua id).
 *   3. Chạy file SQL lần nữa → nội dung không đổi và không sinh dòng thừa.
 *   4. Bài và từ của giáo trình mặc định không đổi.
 *
 *   php thu_sql.php <file sql>
 */

const TEN = 'Hán ngữ 6 tập';
const BIN = 'D:/zika-devtools/mariadb/bin';

$cfg = require dirname(__DIR__, 2) . '/api/app/config.php';
$d = $cfg['db'];
$sql = $argv[1] ?? '';
if (!is_file($sql)) {
    exit("Thiếu file SQL.\n");
}
putenv('MYSQL_PWD=' . $d['pass']);
$dsn = "mysql:host={$d['host']};port={$d['port']};charset=utf8mb4";
$pdo = new PDO($dsn, $d['user'], $d['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

function chay(string $file, string $db): void
{
    global $d;
    $lenh = '"' . BIN . "/mariadb.exe\" -h{$d['host']} -u{$d['user']} --default-character-set=utf8mb4 $db < \"$file\" 2>&1";
    exec($lenh, $out, $ma);
    if ($ma !== 0) {
        exit("Lỗi chạy SQL: " . implode("\n", array_slice($out, 0, 5)) . "\n");
    }
}

/** Dấu vân tay nội dung một giáo trình, bỏ qua id và thời gian. @return array<string,string> */
function van_tay(PDO $p, string $db, string $ten): array
{
    $c = $p->query("SELECT id FROM `$db`.curricula WHERE name = " . $p->quote($ten))->fetchColumn();
    if ($c === false) {
        return [];
    }
    $ra = [];
    $cau = [
        'lessons' => "SELECT level_id, lesson_no, title, sample_text, status, cnt_words, cnt_bt, cnt_kt, cnt_lv, cnt_ht, cnt_np FROM `$db`.lessons WHERE curriculum_id = $c ORDER BY level_id, lesson_no",
        'words' => "SELECT w.level_id, l.lesson_no, w.hanzi, w.pinyin, w.han_viet, w.meaning_vi, w.pos, w.note, w.audio_path, w.sort_order FROM `$db`.words w JOIN `$db`.lessons l ON l.id = w.lesson_id WHERE w.curriculum_id = $c ORDER BY w.level_id, w.hanzi",
        'word_examples' => "SELECT w.level_id, w.hanzi, e.sort_order, e.zh, e.pinyin, e.vi, e.highlight, e.audio_path FROM `$db`.word_examples e JOIN `$db`.words w ON w.id = e.word_id WHERE w.curriculum_id = $c ORDER BY w.level_id, w.hanzi, e.sort_order, e.zh",
        'lesson_chars' => "SELECT l.level_id, l.lesson_no, ch.hanzi, x.sort_order, x.is_visible, w.hanzi AS nguon, ch.pinyin, ch.han_viet, ch.meaning_vi, ch.strokes, ch.structure, ch.radical, ch.analysis, ch.stroke_data_status, ch.stroke_data_path FROM `$db`.lesson_chars x JOIN `$db`.lessons l ON l.id = x.lesson_id JOIN `$db`.characters ch ON ch.id = x.char_id LEFT JOIN `$db`.words w ON w.id = x.source_word_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, x.sort_order, ch.hanzi",
        'dialogues' => "SELECT l.level_id, l.lesson_no, k.sort_order, k.title, k.scene, k.audio_path FROM `$db`.dialogues k JOIN `$db`.lessons l ON l.id = k.lesson_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, k.sort_order",
        'dialogue_lines' => "SELECT l.level_id, l.lesson_no, k.sort_order AS khoa, x.sort_order, s.name, s.avatar_char, s.color, s.role_vi, x.zh, x.pinyin, x.vi, x.highlight, x.audio_path FROM `$db`.dialogue_lines x JOIN `$db`.dialogues k ON k.id = x.dialogue_id JOIN `$db`.dialogue_speakers s ON s.id = x.speaker_id JOIN `$db`.lessons l ON l.id = k.lesson_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, k.sort_order, x.sort_order",
        'grammar_points' => "SELECT l.level_id, l.lesson_no, g.sort_order, g.title, g.subtitle, g.explain_vi, g.note_vi, g.blocks, g.examples, g.practice FROM `$db`.grammar_points g JOIN `$db`.lessons l ON l.id = g.lesson_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, g.sort_order",
    ];
    foreach ($cau as $k => $q) {
        $rows = $p->query($q)->fetchAll();
        $ra[$k] = count($rows) . ':' . sha1(json_encode($rows, JSON_UNESCAPED_UNICODE));
    }
    return $ra;
}

$pdo->exec('DROP DATABASE IF EXISTS test_zika_sql');
$pdo->exec('CREATE DATABASE test_zika_sql CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$tam = sys_get_temp_dir() . '/thu_sql_dump.sql';
exec('"' . BIN . "/mariadb-dump.exe\" -h{$d['host']} -u{$d['user']} --single-transaction --skip-triggers --no-tablespaces --default-character-set=utf8mb4 --result-file=\"$tam\" {$d['name']}", $o, $ma);
if ($ma !== 0) {
    exit("Lỗi dump\n");
}
chay($tam, 'test_zika_sql');
unlink($tam);

// Giả lập server: chưa có giáo trình này, và chưa có các chữ chỉ Hán ngữ mới dùng.
$cid = $pdo->query('SELECT id FROM test_zika_sql.curricula WHERE name = ' . $pdo->quote(TEN))->fetchColumn();
$pdo->exec("DELETE FROM test_zika_sql.words WHERE curriculum_id = $cid");
$pdo->exec("DELETE FROM test_zika_sql.lessons WHERE curriculum_id = $cid");
$pdo->exec("UPDATE test_zika_sql.users SET curriculum_id = NULL WHERE curriculum_id = $cid");
$pdo->exec("DELETE FROM test_zika_sql.curricula WHERE id = $cid");
$pdo->exec('DELETE FROM test_zika_sql.characters WHERE id NOT IN (SELECT char_id FROM test_zika_sql.lesson_chars) AND id NOT IN (SELECT char_id FROM test_zika_sql.char_stats)');
// Tạo lệch id so với local để bắt lỗi ghi id cứng.
$pdo->exec('ALTER TABLE test_zika_sql.lessons AUTO_INCREMENT = 5000');
$pdo->exec('ALTER TABLE test_zika_sql.words AUTO_INCREMENT = 90000');
$pdo->exec('ALTER TABLE test_zika_sql.dialogues AUTO_INCREMENT = 5000');
$pdo->exec('ALTER TABLE test_zika_sql.characters AUTO_INCREMENT = 50000');

$hsk =static fn (string $db): string => sha1(json_encode($pdo->query(
    "SELECT l.id, l.title, l.cnt_words, (SELECT COUNT(*) FROM `$db`.words w WHERE w.lesson_id = l.id) n FROM `$db`.lessons l WHERE l.curriculum_id = 1 ORDER BY l.id",
)->fetchAll()));
$hsk_truoc = $hsk('test_zika_sql');
$chu_truoc = sha1(json_encode($pdo->query('SELECT id, hanzi, pinyin, han_viet, meaning_vi, structure, analysis FROM test_zika_sql.characters ORDER BY id')->fetchAll(), JSON_UNESCAPED_UNICODE));

$local = van_tay($pdo, $d['name'], TEN);

$t = microtime(true);
chay($sql, 'test_zika_sql');
$giay = round(microtime(true) - $t, 1);
$lan1 = van_tay($pdo, 'test_zika_sql', TEN);
chay($sql, 'test_zika_sql');
$lan2 = van_tay($pdo, 'test_zika_sql', TEN);

$loi = 0;
foreach ($local as $k => $v) {
    $ok1 = $lan1[$k] === $v;
    $ok2 = $lan2[$k] === $v;
    $loi += ($ok1 ? 0 : 1) + ($ok2 ? 0 : 1);
    echo sprintf("%-16s %-48s lần 1: %s · lần 2: %s\n", $k, $v, $ok1 ? 'khớp' : 'LỆCH ' . $lan1[$k], $ok2 ? 'khớp' : 'LỆCH ' . $lan2[$k]);
}
$hsk_sau = $hsk('test_zika_sql');
echo 'HSK không đổi: ', $hsk_sau === $hsk_truoc ? 'đúng' : 'SAI', "\n";
$loi += $hsk_sau === $hsk_truoc ? 0 : 1;
$chu_sau = sha1(json_encode($pdo->query('SELECT id, hanzi, pinyin, han_viet, meaning_vi, structure, analysis FROM test_zika_sql.characters WHERE id < 50000 ORDER BY id')->fetchAll(), JSON_UNESCAPED_UNICODE));
echo 'Chữ có sẵn không đổi: ', $chu_sau === $chu_truoc ? 'đúng' : 'SAI', " · thời gian chạy file: {$giay}s\n";
$loi += $chu_sau === $chu_truoc ? 0 : 1;
$pdo->exec('DROP DATABASE test_zika_sql');
echo $loi === 0 ? "ĐẠT\n" : "CÓ LỖI: $loi\n";
exit($loi === 0 ? 0 : 1);
