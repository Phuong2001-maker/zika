<?php
declare(strict_types=1);

namespace Zika\Services;

use Zika\Core\{Db, HttpError, Request};

/** Nguồn duy nhất của danh mục quyền. API trả quyền hiệu lực, admin sửa trạng thái riêng từng ô. */
final class FeatureAccess
{
    public const ROLES = ['learner', 'editor', 'admin'];
    private static ?array $stored = null;

    public static function catalog(): array
    {
        $groups = [
            'header' => ['Thanh đầu trang', 'display', ['points' => 'Điểm mở bài', 'fund' => 'Quỹ tháng', 'ranking' => 'Top chăm học / bảng xếp hạng', 'notifications' => 'Chuông thông báo', 'switcher' => 'Chuyển nhanh']],
            'learn' => ['Các phần học', 'access', ['flashcards' => 'Lật thẻ', 'exercises' => 'Bài tập', 'tests' => 'Kiểm tra / thi thử', 'writing' => 'Luyện viết', 'dialogues' => 'Đọc hội thoại', 'grammar' => 'Ngữ pháp']],
            'books' => ['Giáo trình', 'access', ['audio' => 'File nghe', 'answers' => 'Đáp án', 'points' => 'Điểm và nhận điểm mở bài']],
            'community' => ['Cộng đồng', 'access', ['chat' => 'Quán trà', 'feedback' => 'Góp ý · báo lỗi', 'study' => 'Top chăm học', 'notifications' => 'Thông báo', 'contests' => 'Cuộc thi']],
            'support' => ['Ủng hộ', 'access', ['buddy' => 'Bé túi Shopee', 'links' => 'Link Shopee trong Quỹ tháng']],
            'fund' => ['Thông tin quỹ thưởng', 'display', []],
            'home' => ['Các khối trên Bàn học', 'display', ['continue' => 'Học tiếp', 'streak' => 'Chuỗi ngày học', 'words' => 'Thống kê từ đã học', 'review' => 'Gợi ý ôn tập']],
            'account' => ['Tài khoản', 'display', ['stats' => 'Thống kê học tập', 'avatar' => 'Đổi / xóa avatar', 'name' => 'Chỉnh tên', 'preferences' => 'Cài đặt học', 'sessions' => 'Quản lý phiên đăng nhập', 'delete' => 'Xóa tài khoản']],
        ];
        $out = [];
        foreach ($groups as $key => [$label, $kind, $children]) {
            $out[] = ['key' => $key, 'label' => $label, 'parent' => null, 'kind' => $kind, 'locked' => $key === 'account'];
            foreach ($children as $child => $name) {
                $out[] = ['key' => "$key.$child", 'label' => $name, 'parent' => $key,
                    'kind' => in_array($key, ['header', 'home']) || $child === 'stats' ? 'display' : 'access', 'locked' => false];
                if ($child === 'flashcards') {
                    foreach (['cards' => 'Lật thẻ', 'match' => 'Ghép cặp', 'quiz' => 'Trắc nghiệm', 'type' => 'Gõ chữ'] as $mode => $title) {
                        $out[] = ['key' => "learn.flashcards.$mode", 'label' => $title, 'parent' => 'learn.flashcards', 'kind' => 'display', 'locked' => false];
                    }
                }
                if ($key === 'community' && $child === 'contests') {
                    foreach (['info' => 'Thông tin cuộc thi', 'register' => 'Đăng ký / hủy đăng ký', 'enter' => 'Vào thi', 'ranking' => 'Bảng xếp hạng cuộc thi', 'registrants' => 'Danh sách đăng ký', 'history' => 'Lịch sử thi', 'rewards' => 'Thông tin giải thưởng / nhận thưởng'] as $item => $title) {
                        $out[] = ['key' => "community.contests.$item", 'label' => $title, 'parent' => 'community.contests', 'kind' => in_array($item, ['info', 'rewards']) ? 'display' : 'access', 'locked' => false];
                    }
                }
            }
        }
        return $out;
    }

    public static function clearCache(): void { self::$stored = null; }

    public static function defaults(string $role, string $key): bool
    {
        return $role === 'admin' || !in_array($key, ['learn.exercises', 'learn.tests', 'community.chat'], true);
    }

    public static function matrix(): array
    {
        if (self::$stored === null) {
            self::$stored = [];
            foreach (Db::all('SELECT * FROM feature_access') as $row) self::$stored[$row['role']][$row['feature_key']] = (bool) $row['enabled'];
        }
        $out = [];
        foreach (self::ROLES as $role) foreach (self::catalog() as $f) {
            $out[$role][$f['key']] = $f['locked'] || (self::$stored[$role][$f['key']] ?? self::defaults($role, $f['key']));
        }
        return $out;
    }

    public static function effective(string $role): array
    {
        $matrix = self::matrix()[$role] ?? [];
        $out = [];
        foreach (self::catalog() as $f) $out[$f['key']] = ($matrix[$f['key']] ?? false) && (!$f['parent'] || ($out[$f['parent']] ?? false));
        return $out;
    }

    public static function allows(string $role, string $key): bool { return self::effective($role)[$key] ?? false; }

    public static function require(Request $r, string $key): void
    {
        if (!self::allows($r->role(), $key)) throw new HttpError(403, 'FEATURE_DISABLED', 'Phần này hiện chưa được mở cho vai trò của bạn.');
    }

    /** Lượt kiểm tra đang làm vẫn đọc, lưu và nộp được dù admin vừa tắt quyền. */
    public static function ongoing(Request $r): bool
    {
        if (preg_match('~^/tests/attempts/(\d+)(?:/submit)?$~', $r->path, $m)) {
            return Db::val('SELECT id FROM test_attempts WHERE id = ? AND user_id = ? AND submitted_at IS NULL', [(int) $m[1], $r->userId()]) !== null;
        }
        $mock = filter_var($r->input('mockTestId'), FILTER_VALIDATE_INT) ?: null;
        $lesson = filter_var($r->input('lessonId'), FILTER_VALIDATE_INT) ?: null;
        if (preg_match('~^/tests/(mock|lessons)/(\d+)/summary$~', $r->path, $m)) {
            if ($m[1] === 'mock') $mock = (int) $m[2]; else $lesson = (int) $m[2];
        }
        if ($r->path === '/tests/start' || isset($m[2])) {
            if ($mock && !$lesson) return Db::val('SELECT id FROM test_attempts WHERE mock_test_id = ? AND user_id = ? AND submitted_at IS NULL', [$mock, $r->userId()]) !== null;
            if ($lesson && !$mock) return Db::val('SELECT id FROM test_attempts WHERE lesson_id = ? AND user_id = ? AND submitted_at IS NULL', [$lesson, $r->userId()]) !== null;
        }
        return false;
    }

    public static function enforce(Request $r): void
    {
        $p = $r->path;
        if (str_starts_with($p, '/admin/') || $r->user === null) return;
        if (self::ongoing($r)) return;
        $key = null;
        $parts = ['lt' => 'flashcards', 'bt' => 'exercises', 'kt' => 'tests', 'lv' => 'writing', 'ht' => 'dialogues', 'np' => 'grammar', 'test' => 'tests', 'dialogue' => 'dialogues'];
        if ($p === '/learn/levels' || preg_match('~^/learn/levels/\d+/lessons$~', $p)) $key = 'learn';
        elseif (preg_match('~^/learn/lessons/\d+/(\w+)$~', $p, $m)) $key = 'learn.' . ($parts[$m[1]] ?? $m[1]);
        elseif (preg_match('~^/learn/levels/\d+/review/(\w+)$~', $p, $m)) $key = 'learn.' . ($parts[$m[1]] ?? $m[1]);
        elseif (str_starts_with($p, '/progress/')) {
            $part = $r->params['part'] ?? $r->q('part', 'lt');
            if ($p === '/progress/beacon') {
                $payload = \Zika\Core\Json::decode((string) $r->input('payload', ''), []);
                $part = is_array($payload) ? ($payload['part'] ?? $part) : $part;
            }
            if (!is_string($part)) throw HttpError::validation(['part' => 'Phần học không hợp lệ.']);
            $key = 'learn.' . ($parts[$part] ?? 'flashcards');
        }
        elseif (str_starts_with($p, '/learn/books')) $key = str_contains($p, '/nghe/') ? 'books.audio' : (str_contains($p, '/dap-an/') ? 'books.answers' : (str_contains($p, '/mo/') ? 'books.points' : 'books'));
        elseif (str_starts_with($p, '/learn/points')) $key = 'books.points';
        elseif (str_starts_with($p, '/exercises/')) $key = 'learn.exercises';
        elseif (str_starts_with($p, '/writing/')) $key = 'learn.writing';
        elseif (str_starts_with($p, '/tests/')) {
            $mock = filter_var($r->input('mockTestId') ?? $r->input('mockId'), FILTER_VALIDATE_INT) ?: null;
            if (preg_match('~^/tests/mock/(\d+)/summary$~', $p, $m)) $mock = (int) $m[1];
            if (str_starts_with($p, '/tests/attempts/')) $mock = Db::val('SELECT mock_test_id FROM test_attempts WHERE id = ? AND user_id = ?', [$r->id(), $r->userId()]);
            $isContest = $mock && Db::val('SELECT id FROM contests WHERE mock_test_id = ? LIMIT 1', [$mock]) !== null;
            $key = $isContest ? 'community.contests.enter' : 'learn.tests';
        }
        elseif ($p === '/chat' || str_starts_with($p, '/chat/')) $key = 'community.chat';
        elseif ($p === '/feedback' || str_starts_with($p, '/feedback/')) $key = 'community.feedback';
        elseif ($p === '/contest/study-top') $key = 'community.study';
        elseif ($p === '/contest/current') $key = 'community.contests';
        elseif (str_starts_with($p, '/contest/')) $key = 'community.contests.' . (['ranking' => 'ranking', 'registrants' => 'registrants', 'history' => 'history', 'register' => 'register'][substr($p, 9)] ?? 'info');
        elseif (str_starts_with($p, '/me/notifications') || str_starts_with($p, '/announcements/')) $key = 'community.notifications';
        elseif ($p === '/me/avatar') $key = 'account.avatar';
        elseif ($p === '/me/settings') $key = 'account.preferences';
        elseif (str_starts_with($p, '/me/sessions')) $key = 'account.sessions';
        elseif ($p === '/me' && $r->method === 'DELETE') $key = 'account.delete';
        elseif ($p === '/me' && $r->method === 'PATCH') $key = 'account.name';
        if ($key !== null) self::require($r, $key);
    }
}
