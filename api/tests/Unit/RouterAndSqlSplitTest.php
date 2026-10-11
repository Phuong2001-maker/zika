<?php
declare(strict_types=1);

namespace Zika\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zika\Core\HttpError;
use Zika\Core\Router;
use Zika\Services\Migrator;

final class RouterAndSqlSplitTest extends TestCase
{
    protected function setUp(): void
    {
        Router::setRoutes([
            ['GET', '/learn/lessons/{id}/flashcards', ['C', 'flash'], 'L'],
            ['POST', '/announcements/{id}/{action:seen|dismiss|click}', ['C', 'ann'], 'L'],
            ['GET', '/go/{id}', ['C', 'go'], 'P', ['csrf' => false]],
            ['GET', '/auth/invite/{token:[A-Za-z0-9_-]{20,100}}', ['C', 'inv'], 'P'],
            ['PUT', '/progress/lessons/{id}/{part:[a-z]{2}}', ['C', 'save'], 'L'],
        ]);
    }

    protected function tearDown(): void
    {
        Router::setRoutes(require ZIKA_APP_DIR . '/src/routes.php');
    }

    public function testMatchesParamsAndOptions(): void
    {
        $m = Router::match('GET', '/learn/lessons/21/flashcards');
        self::assertSame(['id' => '21'], $m['params']);
        self::assertSame('L', $m['auth']);

        $m = Router::match('POST', '/announcements/7/dismiss');
        self::assertSame(['id' => '7', 'action' => 'dismiss'], $m['params']);

        self::assertSame(['csrf' => false], Router::match('HEAD', '/go/12')['opts']);
    }

    public function testRegexParamMayContainQuantifierBraces(): void
    {
        $token = str_repeat('a', 43);
        self::assertSame(['token' => $token], Router::match('GET', '/auth/invite/' . $token)['params']);
        self::assertSame(['id' => '21', 'part' => 'lt'], Router::match('PUT', '/progress/lessons/21/lt')['params']);
        $this->expectException(HttpError::class);
        Router::match('GET', '/auth/invite/short');
    }

    public function testNotFoundAndMethodNotAllowed(): void
    {
        try {
            Router::match('GET', '/learn/lessons/abc/flashcards');
            self::fail('Phải 404');
        } catch (HttpError $e) {
            self::assertSame(404, $e->status);
        }
        try {
            Router::match('DELETE', '/learn/lessons/1/flashcards');
            self::fail('Phải 405');
        } catch (HttpError $e) {
            self::assertSame(405, $e->status);
        }
    }

    public function testEveryRealRouteHasValidShape(): void
    {
        $routes = require ZIKA_APP_DIR . '/src/routes.php';
        self::assertNotEmpty($routes);
        foreach ($routes as $r) {
            self::assertContains($r[0], ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $r[1]);
            self::assertContains($r[3], ['P', 'L', 'E', 'A'], $r[1]);
            self::assertTrue(method_exists($r[2][0], $r[2][1]), 'Thiếu handler ' . $r[2][0] . '::' . $r[2][1]);
        }
    }

    public function testSqlSplitIgnoresSemicolonsInStringsAndComments(): void
    {
        $sql = "-- chú thích; có chấm phẩy\nINSERT INTO t VALUES ('a;b', \"c;d\");\n/* khối; chú thích */ UPDATE t SET x = 'it''s;'; # cuối;\nSELECT 1";
        $parts = Migrator::split($sql);
        self::assertCount(3, $parts);
        self::assertStringContainsString("'a;b'", $parts[0]);
        self::assertStringContainsString("'it''s;'", $parts[1]);
        self::assertSame('SELECT 1', $parts[2]);
    }
}
