<?php
declare(strict_types=1);

/**
 * Nạp hàng loạt file đọc mẫu vào uploads + bảng audio_assets, đúng như POST /admin/media/tts
 * nhưng không đi qua HTTP (server dev php -S chỉ xử lý một yêu cầu một lúc: 10.000 file mất ~2 giờ).
 *
 *   php app/tools/nhap_tts.php <danh-sach.json> <ket-qua.json>
 *
 * danh-sach.json: [{"file": "đường dẫn mp3", "text": "...", "voice": "nu", "reading": "pinyin|null"}]
 * ket-qua.json:   {"đường dẫn mp3": "/uploads/tts/…"} — tên file tính bằng Upload::ttsHash như API.
 */

use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\Upload;

require dirname(__DIR__) . '/bootstrap.php';

[$_, $vao, $ra] = $argv + [null, null, null];
if (!$vao || !$ra) {
    fwrite(STDERR, "Dùng: php app/tools/nhap_tts.php <danh-sach.json> <ket-qua.json>\n");
    exit(2);
}
$giong = ['nu', 'nam', 'nu2', 'nam2', 'xiaoxiao', 'xiaoyi', 'xiaoxiao2', 'yunxi', 'yunjian', 'yunyang', 'yunxia'];
$ds = json_decode((string) file_get_contents($vao), true, 512, JSON_THROW_ON_ERROR);
$kq = [];
$loi = 0;
foreach ($ds as $v) {
    $bytes = (string) @file_get_contents($v['file']);
    if (!in_array($v['voice'], $giong, true) || !Upload::isMp3($bytes)) {
        fwrite(STDERR, "[bỏ] {$v['file']}\n");
        $loi++;
        continue;
    }
    $reading = $v['reading'] ?? null;
    $hash = Upload::ttsHash((string) $v['text'], $reading);
    $rel = 'tts/' . $v['voice'] . '/' . substr($hash, 0, 2) . '/' . $hash . '.mp3';
    $abs = Config::uploadsDir($rel);
    if (!is_dir(dirname($abs))) {
        mkdir(dirname($abs), 0755, true);
    }
    file_put_contents($abs, $bytes, LOCK_EX);
    $url = Config::uploadsUrl($rel);
    Db::exec(
        'INSERT INTO audio_assets (text_hash, text_zh, voice, path) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE path = VALUES(path), created_at = CURRENT_TIMESTAMP',
        [$hash, trim((string) $v['text']), $v['voice'], $url],
    );
    $kq[$v['file']] = $url;
}
file_put_contents($ra, json_encode($kq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo count($kq), " file đã nạp, {$loi} file bỏ\n";
