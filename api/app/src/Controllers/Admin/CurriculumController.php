<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\Curricula;

/** Quản trị · Giáo trình: tạo bộ giáo trình và gán cho từng học viên. */
final class CurriculumController
{
    private const COLORS = ['#E4EEDF', '#E3EAF6', '#F0E6D6', '#EDE4F4', '#F5E0DE'];

    /** GET /admin/curricula — biên tập cũng cần để chọn giáo trình khi soạn bài. */
    public function index(Request $r): array
    {
        $byLevel = [];
        foreach (Db::all('SELECT curriculum_id, level_id, COUNT(*) AS n FROM lessons GROUP BY curriculum_id, level_id') as $x) {
            $byLevel[(int) $x['curriculum_id']][(int) $x['level_id']] = (int) $x['n'];
        }
        $words = [];
        foreach (Db::all('SELECT curriculum_id, COUNT(*) AS n FROM words GROUP BY curriculum_id') as $x) {
            $words[(int) $x['curriculum_id']] = (int) $x['n'];
        }
        $learners = [];
        foreach (Db::all("SELECT COALESCE(curriculum_id, ?) AS c, COUNT(*) AS n FROM users WHERE role = 'learner' GROUP BY c", [Curricula::DEFAULT_ID]) as $x) {
            $learners[(int) $x['c']] = (int) $x['n'];
        }

        return array_map(static function (array $c) use ($byLevel, $words, $learners): array {
            $id = (int) $c['id'];
            $levels = [];
            foreach ([1, 2, 3, 4, 5, 6] as $lv) {
                $levels[] = $byLevel[$id][$lv] ?? 0;
            }
            return [
                'id' => $id,
                'name' => (string) $c['name'],
                'description' => (string) $c['description'],
                'color' => (string) $c['color'],
                'isDefault' => $id === Curricula::DEFAULT_ID,
                'levels' => $levels,
                'lessonCount' => array_sum($levels),
                'wordCount' => $words[$id] ?? 0,
                'learnerCount' => $learners[$id] ?? 0,
            ];
        }, Db::all('SELECT * FROM curricula ORDER BY id'));
    }

    /** POST /admin/curricula · PUT /admin/curricula/{id} */
    public function save(Request $r): Response
    {
        $id = $r->id();
        $v = new Validator($r->body);
        $row = [
            'name' => $v->str('name', 'Tên giáo trình', min: 1, max: 100),
            'description' => $v->str('description', 'Giới thiệu', false, 0, 300),
            'color' => $v->in('color', 'Màu nhận diện', self::COLORS, self::COLORS[0]),
        ];
        if (Db::val('SELECT id FROM curricula WHERE name = ? AND id <> ?', [$row['name'], $id]) !== null) {
            $v->error('name', 'Đã có giáo trình tên này.');
        }
        $v->done();

        if ($id > 0) {
            if (Db::val('SELECT id FROM curricula WHERE id = ?', [$id]) === null) {
                throw HttpError::notFound('Không có giáo trình này.');
            }
            Db::update('curricula', $row, ['id' => $id]);
            Audit::log($r, 'curriculum.update', 'curriculum', $id, ['name' => $row['name']]);
            return Response::json(['id' => $id]);
        }

        $newId = Db::insert('curricula', $row);
        Audit::log($r, 'curriculum.create', 'curriculum', $newId, ['name' => $row['name']]);
        return Response::json(['id' => $newId], 201);
    }

    /** DELETE /admin/curricula/{id} — còn bài hoặc từ thì không xoá, tránh mất nội dung đã soạn. */
    public function delete(Request $r): Response
    {
        $id = $r->id();
        if ($id === Curricula::DEFAULT_ID) {
            throw HttpError::conflict('Đây là giáo trình mặc định của học viên chưa được gán nên không xoá được, bạn có thể đổi tên.');
        }
        $lessons = (int) Db::val('SELECT COUNT(*) FROM lessons WHERE curriculum_id = ?', [$id]);
        $words = (int) Db::val('SELECT COUNT(*) FROM words WHERE curriculum_id = ?', [$id]);
        if ($lessons > 0 || $words > 0) {
            throw HttpError::conflict("Giáo trình này còn $lessons bài và $words từ. Hãy xoá nội dung trước.");
        }
        Db::exec('DELETE FROM curricula WHERE id = ?', [$id]);
        Audit::log($r, 'curriculum.delete', 'curriculum', $id);
        return Response::noContent();
    }

    /** GET /admin/curricula/learners?q=&page= */
    public function learners(Request $r): Response
    {
        $where = "role = 'learner'";
        $params = [];
        if (($q = trim((string) $r->q('q', ''))) !== '') {
            $where .= ' AND (display_name LIKE ? OR full_name LIKE ? OR email LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $per = $r->qInt('per', 50, 1, 200);
        $page = $r->qInt('page', 1, 1);
        $total = (int) Db::val("SELECT COUNT(*) FROM users WHERE $where", $params);
        $rows = Db::all(
            "SELECT id, display_name, full_name, email, curriculum_id FROM users WHERE $where
             ORDER BY display_name, id LIMIT $per OFFSET " . (($page - 1) * $per),
            $params,
        );

        return Response::json(
            array_map(static fn (array $u): array => [
                'id' => (int) $u['id'],
                'name' => (string) ($u['display_name'] !== '' ? $u['display_name'] : $u['full_name']),
                'email' => (string) $u['email'],
                'curriculumId' => $u['curriculum_id'] === null ? null : (int) $u['curriculum_id'],
            ], $rows),
            200,
            ['total' => $total, 'page' => $page, 'per' => $per],
        );
    }

    /** PUT /admin/curricula/assign — mỗi học viên một giáo trình; null = về giáo trình mặc định. */
    public function assign(Request $r): array
    {
        $v = new Validator($r->body);
        $rows = $v->arr('rows', 'Danh sách gán');
        $v->done();
        if (count($rows) > 200) {
            throw HttpError::validation(['rows' => 'Mỗi lần lưu tối đa 200 học viên.']);
        }

        $changed = 0;
        Db::tx(static function () use ($rows, &$changed): void {
            foreach ($rows as $row) {
                $userId = (int) ($row['userId'] ?? 0);
                $raw = $row['curriculumId'] ?? null;
                $curriculumId = $raw === null || $raw === '' ? null : Curricula::resolve((int) $raw);
                $old = Db::one("SELECT curriculum_id FROM users WHERE id = ? AND role = 'learner'", [$userId]);
                if ($old === null || ($old['curriculum_id'] === null ? null : (int) $old['curriculum_id']) === $curriculumId) {
                    continue;
                }
                Db::update('users', ['curriculum_id' => $curriculumId], ['id' => $userId]);
                // Tổng hợp lưu theo (người, cấp), không theo bài: giữ lại thì lượt dở trỏ vào câu của giáo trình cũ.
                // Tiến độ từng bài vẫn còn nên gán lại giáo trình cũ thì học tiếp được.
                Db::exec('DELETE FROM review_progress WHERE user_id = ?', [$userId]);
                $changed++;
            }
        });

        Audit::log($r, 'curriculum.assign', 'curriculum', null, ['changed' => $changed]);
        return ['changed' => $changed];
    }
}
