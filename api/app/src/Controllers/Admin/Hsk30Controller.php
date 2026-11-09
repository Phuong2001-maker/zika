<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;

/**
 * Quản trị · Đề HSK 3.0 (migration 027). Danh sách và tạo đề; sửa tên/thời gian/trạng thái, xoá đề và soạn câu dùng
 * chung các API /admin/mock-tests/{id}… có sẵn. Quyền E như soạn đề thi thử.
 */
final class Hsk30Controller
{
    /** GET /admin/hsk30 — mọi đề HSK 3.0, kể cả Nháp, kèm số câu từng phần. */
    public function index(Request $r): array
    {
        return array_map(static fn (array $m): array => [
            'id' => (int) $m['id'],
            'levelId' => (int) $m['level_id'],
            'title' => (string) $m['title'],
            'status' => (string) $m['status'],
            'durationSec' => (int) $m['duration_sec'],
            'questionCount' => (int) $m['so_cau'],
            'sections' => [
                'listening' => (int) $m['nghe'], 'reading' => (int) $m['doc'],
                'writing' => (int) $m['viet'], 'speaking' => (int) $m['noi'],
            ],
            'attempts' => (int) $m['luot'],
        ], Db::all(
            "SELECT m.*, COUNT(q.id) AS so_cau,
                    COALESCE(SUM(q.section = 'listening'), 0) AS nghe, COALESCE(SUM(q.section = 'reading'), 0) AS doc,
                    COALESCE(SUM(q.section = 'writing'), 0) AS viet, COALESCE(SUM(q.section = 'speaking'), 0) AS noi,
                    (SELECT COUNT(*) FROM test_attempts a WHERE a.mock_test_id = m.id AND a.submitted_at IS NOT NULL) AS luot
               FROM mock_tests m LEFT JOIN questions q ON q.mock_test_id = m.id
              WHERE m.kind = 'hsk30'
              GROUP BY m.id ORDER BY m.level_id, m.sort_order, m.id",
        ));
    }

    /** POST /admin/hsk30 { levelId, title, durationSec } — đề mới luôn là Nháp, soạn xong mới công khai. */
    public function create(Request $r): Response
    {
        $v = new Validator($r->body);
        $levelId = (int) $v->int('levelId', 'Cấp', 1, 6);
        $title = $v->str('title', 'Tên đề', min: 1, max: 80);
        $duration = (int) $v->int('durationSec', 'Thời gian', 60, 10800, false, 3600);
        $v->done();
        if (Db::val('SELECT id FROM levels WHERE id = ?', [$levelId]) === null) {
            throw HttpError::notFound('Không có cấp này.');
        }

        $id = Db::insert('mock_tests', [
            'level_id' => $levelId,
            'title' => $title,
            'duration_sec' => $duration,
            // Đề HSK 3.0 chấm theo tỉ lệ đúng từng phần, không có điểm đạt.
            'max_points' => 100,
            'pass_points' => 0,
            'status' => 'draft',
            'kind' => 'hsk30',
            'sort_order' => 1 + (int) Db::val("SELECT COALESCE(MAX(sort_order), 0) FROM mock_tests WHERE kind = 'hsk30' AND level_id = ?", [$levelId]),
        ]);
        Audit::log($r, 'hsk30.create', 'mock_test', $id, ['title' => $title, 'levelId' => $levelId]);
        return Response::json(['id' => $id], 201);
    }
}
