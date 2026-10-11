<?php
declare(strict_types=1);

namespace Zika\Core;

use Throwable;

/** Vòng đời một request API (02 §4.1). */
final class App
{
    public static function run(): void
    {
        try {
            $req = Request::fromGlobals();
            Log::$path = $req->method . ' ' . $req->path;
            $res = self::handle($req);
        } catch (HttpError $e) {
            $res = Response::error($e);
        } catch (Throwable $e) {
            Log::exception($e);
            $res = Response::error(new HttpError(500, 'SERVER'));
        }
        self::finalize($res)->send();
    }

    public static function handle(Request $req): Response
    {
        try {
            if ($req->method === 'OPTIONS' && self::corsOrigin($req) !== null) {
                return self::cors($req, Response::noContent()->withHeader('Access-Control-Max-Age', '600'));
            }

            $route = Router::match($req->method, $req->path);
            $req->params = $route['params'];
            Auth::attach($req);

            $auth = $route['auth'];
            if ($auth !== 'P') {
                if ($req->user === null) {
                    throw $req->lockedAccount ? new HttpError(403, 'ACCOUNT_LOCKED') : new HttpError(401, 'UNAUTHENTICATED');
                }
                if ($auth === 'E' && !$req->isStaff() || $auth === 'A' && $req->role() !== 'admin') {
                    throw HttpError::forbidden();
                }
            }

            $isWrite = !in_array($req->method, ['GET', 'HEAD', 'OPTIONS'], true);
            if ($isWrite && ($route['opts']['csrf'] ?? true)) {
                Csrf::verify($req);
            }

            if ($req->user !== null && $auth !== 'P') {
                // Giới hạn chung 300 request / phút / người (02 §6.3).
                RateLimit::enforce('api:' . $req->user['id'], 300, 60);
            }

            [$class, $method] = $route['handler'];
            $out = (new $class())->$method($req);
            $res = $out instanceof Response ? $out : Response::json($out);
            return self::cors($req, $res);
        } catch (HttpError $e) {
            return self::cors($req, Response::error($e));
        }
    }

    private static function finalize(Response $res): Response
    {
        $res->headers['X-Content-Type-Options'] ??= 'nosniff';
        $res->headers['Cache-Control'] ??= 'no-store';
        return $res;
    }

    private static function corsOrigin(Request $req): ?string
    {
        $origins = (array) Config::get('cors_origins', []);
        $origin = $req->header('origin');
        return $origins !== [] && $origin !== '' && in_array($origin, $origins, true) ? $origin : null;
    }

    /** Chỉ dùng khi triển khai tách 2 domain (02 §11). */
    private static function cors(Request $req, Response $res): Response
    {
        $origin = self::corsOrigin($req);
        if ($origin !== null) {
            $res->headers['Access-Control-Allow-Origin'] = $origin;
            $res->headers['Access-Control-Allow-Credentials'] = 'true';
            $res->headers['Access-Control-Allow-Headers'] = 'Content-Type, X-CSRF-Token, If-None-Match';
            $res->headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
            $res->headers['Vary'] = 'Origin';
        }
        return $res;
    }
}
