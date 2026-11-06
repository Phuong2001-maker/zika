<?php
declare(strict_types=1);

namespace Zika\Controllers\Admin;

use Zika\Core\Audit;
use Zika\Core\Clock;
use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Settings;
use Zika\Core\Validator;
use Zika\Services\ContestService;

/** Quản trị · Cuộc thi: giờ bắt đầu + số phút thi, người đăng ký, cơ cấu giải. */
final class ContestController
{
    /** GET /admin/contests */
    public function index(Request $r): array
    {
        $rows = Db::all(
            'SELECT c.*, m.title AS mock_title, m.status AS mock_status,
                    (SELECT COUNT(*) FROM questions q WHERE q.mock_test_id = m.id) AS qn,
                    (SELECT COUNT(*) FROM contest_registrations x WHERE x.contest_id = c.id) AS registrants
               FROM contests c JOIN mock_tests m ON m.id = c.mock_test_id
              ORDER BY c.starts_at DESC, c.id DESC',
        );

        return [
            'contests' => array_map(static fn (array $c): array => [
                'id' => (int) $c['id'],
                'title' => (string) $c['title'],
                'mockTestId' => (int) $c['mock_test_id'],
                'mockTitle' => (string) $c['mock_title'],
                'mockStatus' => (string) $c['mock_status'],
                'questionCount' => (int) $c['qn'],
                'startsAt' => (string) $c['starts_at'],
                'durationMin' => (int) $c['duration_min'],
                'registrants' => (int) $c['registrants'],
                'status' => ContestService::status($c),
            ], $rows),
            'exams' => self::examRows(),
            'prizes' => ContestService::prizes(),
        ];
    }

    /** GET /admin/exams */
    public function exams(Request $r): array
    {
        return self::examRows();
    }

    /** POST /admin/exams { title } — chỉ cần tên để chọn khi tạo bài thi, câu hỏi nạp sau. */
    public function createExam(Request $r): Response
    {
        $v = new Validator($r->body);
        $title = $v->str('title', 'Tên đề', min: 1, max: 80);
        $v->done();

        // Đề luôn là Nháp nên không lọt vào mục Thi thử của người học; cấp 1 chỉ để lấp cột bắt buộc,
        // cuộc thi không phân cấp và mọi người học đều vào được cấp 1.
        $id = Db::insert('mock_tests', ['level_id' => 1, 'title' => $title, 'status' => 'draft', 'for_contest' => 1]);
        Audit::log($r, 'exam.create', 'mock_test', $id, ['title' => $title]);
        return Response::json(['id' => $id], 201);
    }

    /**
     * Đề của tab Đề thi, cộng đề thi thử cũ đang gắn với bài thi để sửa bài thi đó không mất ô đã chọn.
     * @return list<array<string,mixed>>
     */
    private static function examRows(): array
    {
        return array_map(static fn (array $m): array => [
            'id' => (int) $m['id'],
            'title' => (string) $m['title'],
            'status' => (string) $m['status'],
            'questionCount' => (int) $m['qn'],
            'contestCount' => (int) $m['used'],
        ], Db::all(
            'SELECT m.id, m.title, m.status,
                    (SELECT COUNT(*) FROM questions q WHERE q.mock_test_id = m.id) AS qn,
                    (SELECT COUNT(*) FROM contests c WHERE c.mock_test_id = m.id) AS used
               FROM mock_tests m
              WHERE m.for_contest = 1 OR EXISTS (SELECT 1 FROM contests c WHERE c.mock_test_id = m.id)
              ORDER BY m.id DESC',
        ));
    }

    /** POST /admin/contests · PUT /admin/contests/{id} */
    public function save(Request $r): Response
    {
        $id = $r->id();
        $v = new Validator($r->body);
        $title = $v->str('title', 'Tên bài thi', min: 1, max: 120);
        $mockId = $v->int('mockTestId', 'Đề thi', 1);
        $startsAt = $v->datetime('startsAt', 'giờ bắt đầu', true);
        $minutes = $v->int('durationMin', 'Thời gian thi', 1, 10080);

        // Đề chưa có câu vẫn nhận: đề nạp sau được, trang quản trị cảnh báo còn người học thấy "đang cập nhật".
        if ($mockId !== null && Db::val('SELECT id FROM mock_tests WHERE id = ?', [$mockId]) === null) {
            $v->error('mockTestId', 'Không có đề này.');
        }
        $v->done();

        $row = [
            'title' => $title,
            'mock_test_id' => $mockId,
            'starts_at' => $startsAt,
            'duration_min' => $minutes,
            'ends_at' => Clock::sql(Clock::parse((string) $startsAt)->modify('+' . (int) $minutes . ' minutes')),
        ];
        if ($id > 0) {
            if (Db::val('SELECT id FROM contests WHERE id = ?', [$id]) === null) {
                throw HttpError::notFound('Không có bài thi này.');
            }
            Db::update('contests', $row, ['id' => $id]);
            Audit::log($r, 'contest.update', 'contest', $id);
            return Response::json(['id' => $id]);
        }

        $newId = Db::insert('contests', $row);
        Audit::log($r, 'contest.create', 'contest', $newId);
        return Response::json(['id' => $newId], 201);
    }

    /** DELETE /admin/contests/{id} */
    public function delete(Request $r): Response
    {
        Db::exec('DELETE FROM contests WHERE id = ?', [$r->id()]);
        Audit::log($r, 'contest.delete', 'contest', $r->id());
        return Response::noContent();
    }

    /** GET /admin/contests/{id}/registrants */
    public function registrants(Request $r): array
    {
        return array_map(static fn (array $x): array => [
            'name' => (string) $x['display_name'],
            'email' => (string) $x['email'],
            'at' => Clock::parse((string) $x['created_at'])->format('H:i, d/m/Y'),
        ], Db::all(
            'SELECT u.display_name, u.email, x.created_at FROM contest_registrations x JOIN users u ON u.id = x.user_id
              WHERE x.contest_id = ? ORDER BY x.created_at, x.user_id',
            [$r->id()],
        ));
    }

    /** PUT /admin/contests/prizes — thứ tự dòng là thứ hạng nhận giải. */
    public function savePrizes(Request $r): array
    {
        $v = new Validator($r->body);
        $in = $v->arr('rows', 'Cơ cấu giải');
        $v->done();

        $rows = [];
        $total = 0.0;
        foreach (array_values($in) as $i => $raw) {
            $label = trim((string) ($raw['label'] ?? ''));
            $percent = $raw['percent'] ?? null;
            if ($label === '' || !is_numeric($percent) || (float) $percent <= 0) {
                throw HttpError::validation(['rows' => 'Dòng ' . ($i + 1) . ' cần có tên giải và phần trăm lớn hơn 0.']);
            }
            $rows[] = ['label' => mb_substr($label, 0, 60), 'percent' => round((float) $percent, 1)];
            $total += (float) $percent;
        }
        if (count($rows) > 20) {
            throw HttpError::validation(['rows' => 'Tối đa 20 giải.']);
        }
        if ($total > 100) {
            throw HttpError::validation(['rows' => 'Tổng phần trăm đang là ' . round($total, 1) . '%, không được quá 100%.']);
        }

        Settings::set('prizes', ['rows' => $rows]);
        Audit::log($r, 'contest.prizes', 'settings');
        return ContestService::prizes();
    }
}
