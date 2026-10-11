<?php
declare(strict_types=1);

namespace Zika\Core;

/**
 * Bảng định tuyến. Mỗi route: [method, pattern, [Controller, method], quyền, tuỳ chọn].
 * Quyền: P công khai · L đã đăng nhập · E biên tập + quản trị · A chỉ quản trị (04 §1).
 * Pattern: {id} = số nguyên; {name:regex} = biểu thức riêng.
 */
final class Router
{
    /** @var list<array{0:string,1:string,2:array{0:class-string,1:string},3:string,4?:array<string,mixed>}>|null */
    private static ?array $routes = null;

    /** @var array<string,array{regex:string,names:list<string>}> */
    private static array $compiled = [];

    /** @param list<array> $routes */
    public static function setRoutes(array $routes): void
    {
        self::$routes = $routes;
        self::$compiled = [];
    }

    /** @return list<array> */
    public static function routes(): array
    {
        return self::$routes ??= require ZIKA_APP_DIR . '/src/routes.php';
    }

    /**
     * @return array{handler:array{0:class-string,1:string},auth:string,opts:array<string,mixed>,params:array<string,string>}
     */
    public static function match(string $method, string $path): array
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $pathMatched = false;
        foreach (self::routes() as $route) {
            [$m, $pattern, $handler, $auth] = $route;
            $c = self::$compiled[$pattern] ??= self::compile($pattern);
            if (!preg_match($c['regex'], $path, $mm)) {
                continue;
            }
            $pathMatched = true;
            if ($m !== $method) {
                continue;
            }
            $params = [];
            foreach ($c['names'] as $n) {
                $params[$n] = $mm[$n];
            }
            return ['handler' => $handler, 'auth' => $auth, 'opts' => $route[4] ?? [], 'params' => $params];
        }
        if ($pathMatched) {
            throw new HttpError(405, 'METHOD_NOT_ALLOWED');
        }
        throw HttpError::notFound();
    }

    /** @return array{regex:string,names:list<string>} */
    private static function compile(string $pattern): array
    {
        $names = [];
        $regex = preg_replace_callback(
            // Biểu thức riêng được phép chứa 1 cấp ngoặc nhọn, ví dụ {token:[A-Za-z0-9_-]{20,100}}.
            '/\{([a-zA-Z_]+)(?::((?:[^{}]|\{[^{}]*\})+))?\}|([^{]+)/',
            static function (array $m) use (&$names): string {
                if (($m[3] ?? '') !== '') {
                    return preg_quote($m[3], '#');
                }
                $names[] = $m[1];
                $re = ($m[2] ?? '') !== '' ? $m[2] : '\d+';
                return '(?P<' . $m[1] . '>' . $re . ')';
            },
            $pattern,
        );
        return ['regex' => '#^' . $regex . '$#u', 'names' => $names];
    }
}
