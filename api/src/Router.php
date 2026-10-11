<?php
declare(strict_types=1);

namespace Zika;

use Zika\Http\ApiError;
use Zika\Http\Req;

/**
 * Router tối giản: so khớp method + path, hỗ trợ tham số {ten}.
 *
 * Số route của dự án này khoảng 60, nên vòng lặp tuyến tính là đủ nhanh
 * (vài micro giây); không cần cây tiền tố hay cache route.
 */
final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable,3:array<int,string>}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        // '/api/lessons/{id}/words' -> regex
        $names   = [];
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $m) use (&$names): string {
                $names[] = $m[1];
                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [strtoupper($method), '#^' . $pattern . '$#', $handler, $names];
    }

    public function get(string $p, callable $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST', $p, $h); }
    public function patch(string $p, callable $h): void  { $this->add('PATCH', $p, $h); }
    public function put(string $p, callable $h): void    { $this->add('PUT', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(Req $req): mixed
    {
        $pathMatchedButNotMethod = false;

        foreach ($this->routes as [$method, $pattern, $handler, $names]) {
            if (!preg_match($pattern, $req->path, $m)) {
                continue;
            }
            if ($method !== $req->method) {
                $pathMatchedButNotMethod = true;
                continue;
            }
            array_shift($m);
            $req->params = $names === [] ? [] : array_combine($names, $m);
            return $handler($req);
        }

        if ($pathMatchedButNotMethod) {
            throw new ApiError('Phương thức không dùng được cho đường dẫn này.', 405, 'method_not_allowed');
        }
        throw ApiError::notFound('Không có API nào ở ' . $req->path);
    }
}
