<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\Pinyin;
use Zika\Core\Request;
use Zika\Core\Upload;
use Zika\Core\Validator;
use Zika\Services\Curricula;
use Zika\Services\UserService;

/** Quản trị · tiện ích dùng chung: số trên menu, danh sách nhân sự, tra từ điển, tải ảnh/âm thanh (04 §10.1). */
final class MiscController
{
    /** GET /admin/badges — các con số hiện cạnh mục menu. */
    public function badges(Request $r): array
    {
        return [
            'users' => (int) Db::val('SELECT COUNT(*) FROM users'),
            'lessons' => (int) Db::val('SELECT COUNT(*) FROM lessons'),
            'words' => (int) Db::val('SELECT COUNT(*) FROM words'),
            'unreadFeedback' => (int) Db::val('SELECT COUNT(*) FROM feedback WHERE is_read = 0'),
        ];
    }

    /** GET /admin/staff — người có thể nhận xử lý góp ý. */
    public function staff(Request $r): array
    {
        return array_map(static fn (array $u): array => [
            'id' => (int) $u['id'],
            'name' => (string) $u['display_name'],
            'role' => (string) UserService::withEffectiveRole($u)['role'],
        ], Db::all("SELECT id, display_name, email, role FROM users WHERE role IN ('editor','admin') ORDER BY role, display_name"));
    }

    /**
     * GET /admin/dict?hanzi=资源&level=5
     * Trợ giúp khi admin gõ chữ Hán: lấy sẵn pinyin, Hán Việt, nghĩa từ từ điển nội bộ,
     * và báo nếu cấp đó đã có từ này rồi (tránh nhập trùng).
     */
    public function dict(Request $r): array
    {
        $hanzi = trim((string) $r->q('hanzi', ''));
        if ($hanzi === '' || !Pinyin::isHanzi($hanzi)) {
            return ['found' => false, 'entries' => [], 'hanVietByChar' => '', 'existing' => null];
        }

        $entries = array_map(static fn (array $d): array => [
            'pinyin' => (string) $d['pinyin'],
            'hanViet' => (string) $d['han_viet'],
            'meaning' => (string) $d['meaning_vi'],
            'pos' => (string) $d['pos'],
        ], Db::all('SELECT * FROM dict_words WHERE hanzi = ? ORDER BY id LIMIT 5', [$hanzi]));

        // Không có cả từ thì ghép Hán Việt của từng chữ (06 §7).
        $parts = [];
        foreach (Pinyin::chars($hanzi) as $c) {
            $hv = Db::val('SELECT han_viet FROM characters WHERE hanzi = ?', [$c]);
            $parts[] = $hv !== null && $hv !== '' ? mb_strtoupper((string) $hv) : '?';
        }

        $existing = null;
        if (($level = $r->qInt('level')) > 0) {
            $w = Db::one(
                'SELECT w.id, w.lesson_id, l.lesson_no FROM words w LEFT JOIN lessons l ON l.id = w.lesson_id
                 WHERE w.curriculum_id = ? AND w.level_id = ? AND w.hanzi = ?',
                [Curricula::resolve($r->qInt('curriculum')), $level, $hanzi],
            );
            if ($w !== null) {
                $existing = [
                    'wordId' => (int) $w['id'],
                    'lessonId' => $w['lesson_id'] !== null ? (int) $w['lesson_id'] : null,
                    'lessonNo' => $w['lesson_no'] !== null ? (int) $w['lesson_no'] : null,
                ];
            }
        }

        return [
            'found' => $entries !== [],
            'entries' => $entries,
            'hanVietByChar' => implode(' ', $parts),
            'existing' => $existing,
        ];
    }

    /** POST /admin/media/image — multipart `file` + `kind`. */
    public function image(Request $r): array
    {
        $v = new Validator($r->body);
        $kind = $v->in('kind', 'Loại ảnh', ['lesson', 'dialogue', 'shopee', 'qr', 'logo'], 'lesson');
        $v->done();

        $out = Upload::image($r->files['file'] ?? null, (string) $kind);
        Audit::log($r, 'media.image', 'media', null, ['kind' => $kind, 'path' => $out['path'] ?? null]);
        return $out;
    }

    /** POST /admin/media/audio — multipart `file` (mp3). */
    public function audio(Request $r): array
    {
        $out = Upload::audio($r->files['file'] ?? null);
        Audit::log($r, 'media.audio', 'media', null, ['path' => $out['path'] ?? null]);
        return $out;
    }

    /**
     * POST /admin/media/tts (multipart: file, text, voice, reading tuỳ chọn) — file đọc mẫu theo cách đọc.
     * Gọi hàng nghìn lần mỗi lượt sinh nên không ghi nhật ký từng file; bảng audio_assets đã đủ để tra.
     */
    public function tts(Request $r): array
    {
        $v = new Validator($r->body);
        $text = $v->str('text', 'Câu chữ', true, 1, 500);
        $reading = $v->str('reading', 'Pinyin', false, 0, 500);
        // nu/nam: giọng chung theo Cài đặt; các tên còn lại là giọng riêng của nhân vật hội thoại.
        $voice = (string) $v->in('voice', 'Giọng', ['nu', 'nam', 'nu2', 'nam2', 'xiaoxiao', 'xiaoyi', 'xiaoxiao2', 'yunxi', 'yunjian', 'yunyang', 'yunxia'], 'nu');
        $v->done();
        $out = Upload::tts($r->files['file'] ?? null, $text, $voice, $reading);
        Db::exec(
            'INSERT INTO audio_assets (text_hash, text_zh, voice, path) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE path = VALUES(path), created_at = CURRENT_TIMESTAMP',
            [Upload::ttsHash($text, $reading), trim($text), $voice, $out['path']],
        );
        return $out;
    }
}
