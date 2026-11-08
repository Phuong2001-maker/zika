<?php
declare(strict_types=1);

namespace Zika\Controllers;

use Zika\Core\Request;
use Zika\Core\Response;
use Zika\Core\Validator;
use Zika\Services\ContestService;
use Zika\Services\TopHoc;

/** Cuộc thi phía người học. */
final class ContestController
{
    /** GET /contest/current */
    public function current(Request $r): array
    {
        return ['contest' => ContestService::current($r->userId()), 'prizes' => ContestService::prizes()];
    }

    /** GET /contest/ranking */
    public function ranking(Request $r): array
    {
        return ContestService::ranking($r->userId());
    }

    /** GET /contest/history — cuộc thi đã kết thúc. */
    public function history(Request $r): array
    {
        return ContestService::history($r->userId());
    }

    /** GET /contest/study-top — Top chăm học tháng này. */
    public function studyTop(Request $r): array
    {
        return TopHoc::thang($r->userId());
    }

    /** GET /contest/registrants */
    public function registrants(Request $r): array
    {
        return ContestService::registrants($r->userId());
    }

    /** POST /contest/register */
    public function register(Request $r): Response
    {
        return $this->setRegistered($r, true);
    }

    /** DELETE /contest/register?contestId= */
    public function unregister(Request $r): Response
    {
        return $this->setRegistered($r, false);
    }

    private function setRegistered(Request $r, bool $on): Response
    {
        $v = new Validator($on ? $r->body : $r->query);
        $id = (int) $v->int('contestId', 'Bài thi', 1);
        $v->done();
        ContestService::setRegistered($id, $r->userId(), $on);
        return Response::noContent();
    }
}
