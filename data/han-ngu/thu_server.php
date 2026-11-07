<?php
declare(strict_types=1);

/**
 * Thử gói server trên một DB giống server thật nhất có thể (test_zika_server): cấu trúc tới migration 005 +
 * nội dung HSK của gói 02/11 — đúng trạng thái server trước khi có giáo trình.
 *   1. Chạy 01-cau-truc.sql hai lần → cấu trúc giống hệt local, lần 2 không lỗi.
 *   2. Chạy 02-han-ngu.sql hai lần → nội dung khớp local, HSK và chữ có sẵn không đổi.
 *
 *   php thu_server.php <thư mục gói> <thư mục dong-goi-server>
 */

const TEN = 'Hán ngữ 6 tập';
const BIN = 'D:/zika-devtools/mariadb/bin';
const DB = 'test_zika_server';

$cfg = require dirname(__DIR__, 2) . '/api/app/config.php';
$d = $cfg['db'];
$goi = $argv[1] ?? '';
$cu = $argv[2] ?? '';
if (!is_file("$goi/01-cau-truc.sql") || !is_file("$cu/02-noi-dung.sql")) {
    exit("Thiếu file gói.\n");
}
putenv('MYSQL_PWD=' . $d['pass']);
$pdo = new PDO("mysql:host={$d['host']};port={$d['port']};charset=utf8mb4", $d['user'], $d['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function chay(string $file): void
{
    global $d;
    $lenh = '"' . BIN . "/mariadb.exe\" -h{$d['host']} -u{$d['user']} --default-character-set=utf8mb4 " . DB . " < \"$file\" 2>&1";
    exec($lenh, $out, $ma);
    if ($ma !== 0) {
        exit('Lỗi chạy ' . basename($file) . ': ' . implode("\n", array_slice($out, 0, 5)) . "\n");
    }
}

/** Cấu trúc bảng đã chuẩn hoá để so: bỏ AUTO_INCREMENT, xếp cột và khoá theo tên (ALTER thêm cột ở vị trí khác nhau không phải lỗi). */
function cau_truc(PDO $p, string $db): array
{
    $ra = [];
    foreach ($p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$db'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $dong = $p->query("SHOW CREATE TABLE `$db`.`$t`")->fetch()['Create Table'];
        $dong = preg_replace('/ AUTO_INCREMENT=\d+/', '', $dong);
        $phan = array_map(static fn ($x) => rtrim(trim($x), ','), explode("\n", $dong));
        sort($phan);
        $ra[$t] = $phan;
    }
    ksort($ra);
    return $ra;
}

function van_tay(PDO $p, string $db): array
{
    $c = (int) $p->query("SELECT id FROM `$db`.curricula WHERE name = " . $p->quote(TEN))->fetchColumn();
    $cau = [
        'lessons' => "SELECT level_id, lesson_no, title, status, cnt_words, cnt_lv, cnt_ht, cnt_np FROM `$db`.lessons WHERE curriculum_id = $c ORDER BY level_id, lesson_no",
        'words' => "SELECT w.level_id, l.lesson_no, w.hanzi, w.pinyin, w.meaning_vi, w.sort_order FROM `$db`.words w JOIN `$db`.lessons l ON l.id = w.lesson_id WHERE w.curriculum_id = $c ORDER BY w.level_id, w.hanzi",
        'word_examples' => "SELECT w.level_id, w.hanzi, e.sort_order, e.zh, e.vi FROM `$db`.word_examples e JOIN `$db`.words w ON w.id = e.word_id WHERE w.curriculum_id = $c ORDER BY w.level_id, w.hanzi, e.sort_order, e.zh",
        'lesson_chars' => "SELECT l.level_id, l.lesson_no, ch.hanzi, x.sort_order, w.hanzi AS nguon FROM `$db`.lesson_chars x JOIN `$db`.lessons l ON l.id = x.lesson_id JOIN `$db`.characters ch ON ch.id = x.char_id LEFT JOIN `$db`.words w ON w.id = x.source_word_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, x.sort_order, ch.hanzi",
        'dialogues' => "SELECT l.level_id, l.lesson_no, k.sort_order, k.title, k.scene, k.audio_path FROM `$db`.dialogues k JOIN `$db`.lessons l ON l.id = k.lesson_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, k.sort_order",
        'dialogue_lines' => "SELECT l.level_id, l.lesson_no, k.sort_order AS khoa, x.sort_order, s.name, x.zh, x.pinyin, x.vi, x.audio_path FROM `$db`.dialogue_lines x JOIN `$db`.dialogues k ON k.id = x.dialogue_id JOIN `$db`.dialogue_speakers s ON s.id = x.speaker_id JOIN `$db`.lessons l ON l.id = k.lesson_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, k.sort_order, x.sort_order",
        'grammar_points' => "SELECT l.level_id, l.lesson_no, g.sort_order, g.title, g.explain_vi, g.note_vi, g.blocks, g.examples, g.practice FROM `$db`.grammar_points g JOIN `$db`.lessons l ON l.id = g.lesson_id WHERE l.curriculum_id = $c ORDER BY l.level_id, l.lesson_no, g.sort_order",
    ];
    $ra = [];
    foreach ($cau as $k => $q) {
        $rows = $p->query($q)->fetchAll();
        $ra[$k] = count($rows) . ':' . sha1(json_encode($rows, JSON_UNESCAPED_UNICODE));
    }
    return $ra;
}

$pdo->exec('DROP DATABASE IF EXISTS ' . DB);
$pdo->exec('CREATE DATABASE ' . DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE ' . DB . '.schema_migrations (filename VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$mig = dirname(__DIR__, 2) . '/api/app/migrations';
foreach (glob("$mig/*.sql") as $f) {
    if ((int) substr(basename($f), 0, 3) <= 5) {
        chay($f);
        $pdo->exec('INSERT INTO ' . DB . '.schema_migrations (filename) VALUES (' . $pdo->quote(basename($f)) . ')');
    }
}
chay("$cu/02-noi-dung.sql");
$pdo->exec('INSERT INTO ' . DB . ".users (email, password_hash, full_name, display_name, role) VALUES ('hv@thu.local', 'x', 'Học viên thử', 'HV', 'learner')");
echo 'Giống server cũ: ', $pdo->query('SELECT COUNT(*) FROM ' . DB . '.lessons')->fetchColumn(), " bài HSK\n";

$loi = 0;
chay("$goi/01-cau-truc.sql");
chay("$goi/01-cau-truc.sql");
$a = cau_truc($pdo, DB);
$b = cau_truc($pdo, $d['name']);
foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $t) {
    if (($a[$t] ?? null) !== ($b[$t] ?? null)) {
        echo "Cấu trúc lệch ở bảng $t: ", json_encode(array_values(array_diff($a[$t] ?? [], $b[$t] ?? [])), JSON_UNESCAPED_UNICODE), ' / ', json_encode(array_values(array_diff($b[$t] ?? [], $a[$t] ?? [])), JSON_UNESCAPED_UNICODE), "\n";
        $loi++;
    }
}
echo 'Cấu trúc khớp local: ', $loi === 0 ? 'đúng' : 'SAI', ' · chạy lại lần 2 không lỗi', "\n";

$hsk = static fn (): string => sha1(json_encode($pdo->query('SELECT l.id, l.title, l.cnt_words, (SELECT COUNT(*) FROM ' . DB . '.words w WHERE w.lesson_id = l.id) n FROM ' . DB . '.lessons l WHERE l.curriculum_id = 1 ORDER BY l.id')->fetchAll()));
$chu = static fn (string $them = ''): string => sha1(json_encode($pdo->query('SELECT id, hanzi, pinyin, han_viet, meaning_vi, structure, analysis FROM ' . DB . ".characters $them ORDER BY id")->fetchAll(), JSON_UNESCAPED_UNICODE));
$h0 = $hsk();
$maxChu = (int) $pdo->query('SELECT MAX(id) FROM ' . DB . '.characters')->fetchColumn();
$c0 = $chu("WHERE id <= $maxChu");
$lc = van_tay($pdo, $d['name']);
$t = microtime(true);
chay("$goi/02-han-ngu.sql");
$giay = round(microtime(true) - $t, 1);
$l1 = van_tay($pdo, DB);
chay("$goi/02-han-ngu.sql");
$l2 = van_tay($pdo, DB);
foreach ($lc as $k => $v) {
    $ok = $l1[$k] === $v && $l2[$k] === $v;
    $loi += $ok ? 0 : 1;
    echo sprintf("%-16s %-50s %s\n", $k, $v, $ok ? 'khớp cả 2 lần' : "LỆCH {$l1[$k]} / {$l2[$k]}");
}
foreach ([['HSK không đổi', $hsk() === $h0], ['Chữ có sẵn không đổi', $chu("WHERE id <= $maxChu") === $c0],
          ['Tài khoản còn', (int) $pdo->query('SELECT COUNT(*) FROM ' . DB . ".users WHERE email = 'hv@thu.local'")->fetchColumn() === 1]] as [$ten, $ok]) {
    echo "$ten: ", $ok ? 'đúng' : 'SAI', "\n";
    $loi += $ok ? 0 : 1;
}
echo "Thời gian chạy 02-han-ngu.sql: {$giay}s\n";
$pdo->exec('DROP DATABASE ' . DB);
echo $loi === 0 ? "ĐẠT\n" : "CÓ LỖI: $loi\n";
exit($loi === 0 ? 0 : 1);
