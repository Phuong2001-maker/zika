<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\{Audit, Clock, Config, Db, HttpError, Request, Response, Upload, Validator};
use Zika\Services\DemoActivity;

/** Dữ liệu minh họa chỉ đọc/ghi qua API admin, không đi vào thống kê học hay xét thưởng. */
final class DemoLearnerController
{
    public function learners(Request $r): array
    {
        DemoActivity::tick();
        return array_map(static fn ($n) => [
            'id' => (int) $n['id'], 'ten' => $n['name'], 'avatarUrl' => $n['avatar_path'], 'muc' => $n['activity_level'],
            'phut' => (int) $n['minutes'], 'ngay' => (int) $n['study_days'], 'bai' => (int) $n['completed_parts'],
            'hien' => (bool) $n['is_visible'], 'dangKy' => (bool) $n['is_registered'], 'thi' => (bool) $n['show_contest'],
            'tuDong' => (bool) $n['auto_enabled'], 'thang' => $n['stats_month'],
        ], Db::all('SELECT * FROM demo_learners ORDER BY id DESC'));
    }

    public function rewards(Request $r): array
    {
        return array_map(static fn ($n) => [
            'id' => (int) $n['id'], 'nguoiId' => (int) $n['learner_id'], 'tenNguoi' => $n['name'],
            'cuocThi' => $n['contest_name'], 'thang' => $n['reward_month'], 'ngay' => $n['contest_date'],
            'hang' => (int) $n['ranking'], 'giai' => $n['prize_name'], 'tien' => (int) $n['amount'],
            'trangThai' => $n['reward_status'], 'hien' => (bool) $n['is_visible'],
        ], Db::all('SELECT r.*, l.name FROM demo_rewards r JOIN demo_learners l ON l.id = r.learner_id ORDER BY r.reward_month DESC, r.ranking, r.id'));
    }

    public function saveLearner(Request $r): array
    {
        $v = new Validator($r->body);
        $row = [
            'name' => $v->str('ten', 'Tên hiển thị', max: 80),
            'activity_level' => $v->in('muc', 'Nhịp học', ['it', 'vua', 'cham']),
            'minutes' => $v->int('phut', 'Phút học', 0, 50000),
            'study_days' => $v->int('ngay', 'Ngày học', 0, 31),
            'completed_parts' => $v->int('bai', 'Phần hoàn thành', 0, 1000),
            'is_visible' => (int) $v->bool('hien', true),
            'is_registered' => (int) $v->bool('dangKy'),
            'show_contest' => (int) $v->bool('thi'),
            'auto_enabled' => (int) $v->bool('tuDong'),
        ];
        $avatar = $v->strOrNull('avatarUrl', 'Ảnh đại diện', 500);
        if ($avatar !== null && !$this->validAvatar($avatar)) {
            $v->error('avatarUrl', 'Hãy chọn ảnh đại diện đã tải lên cho người học minh họa.');
        }
        $v->done();
        $original = null;
        if ($v->present('goc')) {
            $goc = $v->arr('goc', 'Số liệu ban đầu');
            $v->done();
            $base = new Validator($goc);
            $original = ['minutes' => $base->int('phut', 'Phút ban đầu', 0, 50000),
                'study_days' => $base->int('ngay', 'Ngày ban đầu', 0, 31),
                'completed_parts' => $base->int('bai', 'Phần ban đầu', 0, 1000)];
            $base->done();
        }
        $old = $r->id() > 0 ? Db::val('SELECT avatar_path FROM demo_learners WHERE id = ?', [$r->id()]) : null;
        $id = DemoActivity::save($r->id(), $row + ['avatar_path' => $avatar], $original);
        Audit::log($r, 'demo.save', 'demo_learners', $id);
        if ($old !== null && $old !== $avatar) $this->cleanAvatar((string) $old);
        return ['id' => $id];
    }

    public function activity(Request $r): array
    {
        return DemoActivity::history($r->id());
    }

    /** Ảnh minh họa có thư mục riêng, không dùng chung tệp avatar của tài khoản thật. */
    public function avatar(Request $r): array
    {
        $out = Upload::image($r->files['file'] ?? null, 'demo-avatar');
        Audit::log($r, 'demo.avatar', 'demo_learners', null, ['path' => $out['path']]);
        return ['avatarUrl' => $out['url']];
    }

    private function validAvatar(string $path): bool
    {
        $prefix = Config::uploadsUrl('demo-avatar/');
        if (!str_starts_with($path, $prefix)) return false;
        $rel = substr($path, strlen($prefix));
        return preg_match('/^[a-f0-9]{2}\/[a-f0-9]{40}\.webp$/', $rel) === 1
            && is_file(Config::uploadsDir('demo-avatar/' . $rel));
    }

    private function cleanAvatar(string $path): void
    {
        if (!$this->validAvatar($path) || (int) Db::val('SELECT COUNT(*) FROM demo_learners WHERE avatar_path = ?', [$path]) > 0) return;
        $rel = substr($path, strlen(Config::uploadsUrl('demo-avatar/')));
        @unlink(Config::uploadsDir('demo-avatar/' . $rel));
    }

    public function saveReward(Request $r): array
    {
        $v = new Validator($r->body);
        $row = [
            'learner_id' => $v->int('nguoiId', 'Người đạt giải', 1),
            'contest_name' => $v->str('cuocThi', 'Tên cuộc thi', max: 120),
            'reward_month' => $v->str('thang', 'Tháng giải thưởng', max: 7),
            'contest_date' => $v->date('ngay', 'Ngày thi', true),
            'ranking' => $v->int('hang', 'Thứ hạng', 1, 1000),
            'prize_name' => $v->str('giai', 'Tên giải', max: 80),
            'amount' => $v->int('tien', 'Tiền thưởng', 0, 1000000000),
            'reward_status' => $v->in('trangThai', 'Trạng thái', ['cho', 'dang-xu-ly', 'da-trao']),
            'is_visible' => (int) $v->bool('hien', true),
        ];
        if (!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/', $row['reward_month'])) {
            $v->error('thang', 'Tháng giải thưởng không hợp lệ.');
        }
        $v->done();
        if (Db::val('SELECT id FROM demo_learners WHERE id = ?', [$row['learner_id']]) === null) {
            throw HttpError::notFound('Người học minh họa không còn tồn tại.');
        }
        return $this->save($r, 'demo_rewards', $row);
    }

    private function save(Request $r, string $table, array $row): array
    {
        $id = $r->id();
        $row['updated_at'] = Clock::sql();
        if ($id > 0) {
            $this->exists($table, $id);
            Db::update($table, $row, ['id' => $id]);
        } else {
            $id = Db::insert($table, $row + ['created_at' => Clock::sql()]);
        }
        Audit::log($r, 'demo.save', $table, $id);
        return ['id' => $id];
    }

    public function deleteLearner(Request $r): Response
    {
        $avatar = Db::val('SELECT avatar_path FROM demo_learners WHERE id = ?', [$r->id()]);
        $result = $this->delete($r, 'demo_learners');
        if ($avatar !== null) $this->cleanAvatar((string) $avatar);
        return $result;
    }

    public function deleteReward(Request $r): Response
    {
        return $this->delete($r, 'demo_rewards');
    }

    private function delete(Request $r, string $table): Response
    {
        $this->exists($table, $r->id());
        Db::exec("DELETE FROM $table WHERE id = ?", [$r->id()]);
        Audit::log($r, 'demo.delete', $table, $r->id());
        return Response::noContent();
    }

    private function exists(string $table, int $id): void
    {
        if (Db::val("SELECT id FROM $table WHERE id = ?", [$id]) === null) {
            throw HttpError::notFound('Dữ liệu minh họa không còn tồn tại.');
        }
    }
}
