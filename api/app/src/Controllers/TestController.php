<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Db;
use Zika\Core\HttpError;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\TestService;

/** Bài kiểm tra và thi thử (04 §7). */
final class TestController
{
    /** GET /tests/lessons/{id}/summary */
    public function lessonSummary(Request $r): array
    {
        return TestService::summary($r->userId(), $r->id(), null);
    }

    /** GET /tests/mock/{id}/summary */
    public function mockSummary(Request $r): array
    {
        return TestService::summary($r->userId(), null, $r->id());
    }

    /** POST /tests/start  { lessonId } | { mockTestId } */
    public function start(Request $r): Response
    {
        $v = new Validator($r->body);
        $lessonId = $v->int('lessonId', 'Bài', 1, null, false);
        $mockId = $v->int('mockTestId', 'Đề thi thử', 1, null, false);
        $v->done();

        if ($lessonId === null && $mockId === null) {
            throw HttpError::validation(['lessonId' => 'Thiếu bài hoặc đề thi thử.']);
        }

        return Response::json(TestService::start($r->userId(), $lessonId, $mockId), 201);
    }

    /** PATCH /tests/attempts/{id} — tự lưu khi đang làm. */
    public function save(Request $r): Response
    {
        TestService::patch($this->attempt($r), $r->body);
        return Response::noContent();
    }

    /** POST /tests/attempts/{id}/submit */
    public function submit(Request $r): array
    {
        $a = $this->attempt($r);
        if ($a['submitted_at'] === null) {
            // Nhận nốt câu trả lời gửi kèm lúc nộp rồi mới chấm.
            TestService::patch($a, $r->body);
            $a = Db::one('SELECT * FROM test_attempts WHERE id = ?', [(int) $a['id']]) ?? $a;
        }
        $out = TestService::grade((int) $a['id'], false);
        $this->markSeen((int) $a['id']);
        return $out;
    }

    /** GET /tests/attempts/{id} — xem lại kết quả đã nộp. */
    public function show(Request $r): array
    {
        $a = $this->attempt($r);
        if ($a['submitted_at'] === null) {
            throw HttpError::invalid('Lượt làm bài này chưa nộp.');
        }
        $this->markSeen((int) $a['id']);
        return TestService::result($a);
    }

    /** @return array<string,mixed> */
    private function attempt(Request $r): array
    {
        $a = Db::one('SELECT * FROM test_attempts WHERE id = ? AND user_id = ?', [$r->id(), $r->userId()]);
        if ($a === null) {
            throw HttpError::notFound('Không tìm thấy lượt làm bài.');
        }
        return $a;
    }

    /** Đánh dấu người học đã xem kết quả, để lần sau không bị chặn ở bước “còn kết quả chưa xem”. */
    private function markSeen(int $attemptId): void
    {
        Db::exec('UPDATE test_attempts SET result_seen_at = NOW() WHERE id = ? AND result_seen_at IS NULL', [$attemptId]);
    }
}
