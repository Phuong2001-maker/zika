<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\HttpError;
use Zika\Core\Json;
use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\ProgressService;

/** Tiến độ học (04 §5). Không cache. Xem trước của biên tập/quản trị: không ghi gì. */
final class ProgressController
{
    private static function part(string $p, bool $simpleOnly = false): string
    {
        $allowed = $simpleOnly ? ProgressService::SIMPLE_PARTS : ProgressService::PARTS;
        if (!in_array($p, $allowed, true)) {
            throw HttpError::notFound();
        }
        return $p;
    }

    /** GET /progress/home?part=lt */
    public function home(Request $r): array
    {
        return ProgressService::home($r->userId(), self::part((string) $r->q('part', 'lt')));
    }

    /** GET /progress/levels/{id}?part=lt */
    public function level(Request $r): array
    {
        return ProgressService::level($r->userId(), $r->id(), self::part((string) $r->q('part', 'lt')));
    }

    /** PUT /progress/lessons/{id}/{part} { position, total, state? } */
    public function save(Request $r): Response
    {
        $part = self::part($r->param('part'), true);
        if (!$r->isPreview()) {
            [$pos, $total, $state] = self::readSave($r->body);
            ProgressService::save($r->userId(), $r->id(), $part, $pos, $total, $state);
        }
        return Response::noContent();
    }

    /** POST /progress/lessons/{id}/{part}/complete */
    public function complete(Request $r): array
    {
        $part = self::part($r->param('part'), true);
        if ($r->isPreview()) {
            return ['firstTime' => false, 'levelDone' => ['done' => 0, 'total' => 0], 'lessonsDone' => 0, 'preview' => true];
        }
        return ProgressService::complete($r->userId(), $r->id(), $part);
    }

    /** POST /progress/lessons/{id}/{part}/restart */
    public function restart(Request $r): Response
    {
        $part = self::part($r->param('part'));
        if (!$r->isPreview()) {
            ProgressService::restart($r->userId(), $r->id(), $part);
        }
        return Response::noContent();
    }

    /** PUT /progress/review/{id}/{part} */
    public function saveReview(Request $r): Response
    {
        $part = self::part($r->param('part'));
        if (!$r->isPreview()) {
            [$pos, $total, $state] = self::readSave($r->body);
            ProgressService::saveReview($r->userId(), $r->id(), $part, $pos, $total, $state);
        }
        return Response::noContent();
    }

    /** POST /progress/review/{id}/{part}/complete */
    public function completeReview(Request $r): array
    {
        $part = self::part($r->param('part'));
        if ($r->isPreview()) {
            return ['timesDone' => 0, 'preview' => true];
        }
        return ProgressService::completeReview($r->userId(), $r->id(), $part);
    }

    /**
     * POST /progress/beacon — form `csrf`, `payload` (JSON của 1 lệnh PUT) khi rời trang (sendBeacon).
     * payload: { kind: "lesson"|"review", id, part, position, total, state? }
     */
    public function beacon(Request $r): Response
    {
        $p = Json::decode((string) $r->input('payload', ''), null);
        if (!is_array($p) || $r->isPreview()) {
            return Response::noContent();
        }
        try {
            $part = self::part((string) ($p['part'] ?? ''));
            [$pos, $total, $state] = self::readSave($p);
            $id = (int) ($p['id'] ?? 0);
            if (($p['kind'] ?? '') === 'review') {
                ProgressService::saveReview($r->userId(), $id, $part, $pos, $total, $state);
            } elseif (in_array($part, ProgressService::SIMPLE_PARTS, true)) {
                ProgressService::save($r->userId(), $id, $part, $pos, $total, $state);
            }
        } catch (HttpError) {
            // sendBeacon không đọc phản hồi; bỏ qua dữ liệu sai.
        }
        return Response::noContent();
    }

    /** @param array<string,mixed> $body @return array{0:int,1:int,2:array<string,mixed>|null} */
    private static function readSave(array $body): array
    {
        $v = new Validator($body);
        $pos = (int) $v->int('position', 'Vị trí', 0, 5000);
        $total = (int) $v->int('total', 'Tổng', 0, 5000);
        $state = $body['state'] ?? null;
        if ($state !== null && !is_array($state)) {
            $v->error('state', 'Trạng thái không hợp lệ.');
        }
        $v->done();
        if (is_array($state) && strlen(Json::encode($state)) > 60_000) {
            throw HttpError::invalid('Trạng thái quá lớn.');
        }
        return [$pos, $total, $state];
    }
}
