<?php
declare(strict_types=1);
namespace Zika\Controllers\Admin;

use Zika\Core\{Audit, Clock, Db, HttpError, Request, Validator};
use Zika\Services\FeatureAccess;

final class FeatureAccessController
{
    public function index(Request $r): array
    {
        Db::exec('INSERT IGNORE INTO feature_access_state (id) VALUES (1)');
        return Db::tx(static function () {
            $revision = (int) Db::val('SELECT revision FROM feature_access_state WHERE id = 1 FOR UPDATE');
            FeatureAccess::clearCache();
            return ['catalog' => FeatureAccess::catalog(), 'matrix' => FeatureAccess::matrix(), 'revision' => $revision];
        });
    }

    public function save(Request $r): array
    {
        $v = new Validator($r->body);
        $revision = $v->int('revision', 'Phiên bản cấu hình', 0);
        $changes = $v->arr('changes', 'Danh sách quyền');
        if (count($changes) > 300) $v->error('changes', 'Có quá nhiều thay đổi.');
        $v->done();
        $catalog = array_column(FeatureAccess::catalog(), null, 'key');
        foreach ($changes as $change) {
            if (!is_array($change) || !is_string($change['feature'] ?? null) || !isset($catalog[$change['feature']]) || !in_array($change['role'] ?? null, FeatureAccess::ROLES, true) || !is_bool($change['enabled'] ?? null)) {
                throw HttpError::validation(['changes' => 'Cấu hình quyền không hợp lệ.']);
            }
            if ($catalog[$change['feature']]['locked'] && !$change['enabled']) throw HttpError::validation(['changes' => 'Không thể tắt trang tài khoản thiết yếu.']);
        }
        Db::exec('INSERT IGNORE INTO feature_access_state (id) VALUES (1)');
        Db::tx(static function () use ($r, $revision, $changes) {
            $current = (int) Db::val('SELECT revision FROM feature_access_state WHERE id = 1 FOR UPDATE');
            if ($current !== $revision) throw new HttpError(409, 'CONFLICT', 'Cấu hình đã được người khác sửa. Tải lại trước khi lưu.');
            foreach ($changes as $c) Db::exec('INSERT INTO feature_access (feature_key, role, enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)', [$c['feature'], $c['role'], (int) $c['enabled']]);
            Db::exec('UPDATE feature_access_state SET revision = revision + 1, updated_at = ? WHERE id = 1', [Clock::sql()]);
            Audit::log($r, 'features.update', 'feature_access', null, ['changes' => $changes]);
        });
        FeatureAccess::clearCache();
        return $this->index($r);
    }
}
