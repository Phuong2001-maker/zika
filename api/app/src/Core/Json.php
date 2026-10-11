<?php
declare(strict_types=1);

namespace Zika\Core;

/** Mã hoá/giải mã JSON thống nhất + đổi tên khoá snake_case ⇄ camelCase (04 §1). */
final class Json
{
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function decode(?string $json, mixed $default = null): mixed
    {
        if ($json === null || $json === '') {
            return $default;
        }
        try {
            return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $default;
        }
    }

    /** Đổi khoá của mảng (đệ quy) sang camelCase; danh sách giữ nguyên chỉ số. */
    public static function camel(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            $out[is_string($k) ? self::toCamel($k) : $k] = self::camel($v);
        }
        return $out;
    }

    /** Đổi khoá của mảng (đệ quy) sang snake_case. */
    public static function snake(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            $out[is_string($k) ? self::toSnake($k) : $k] = self::snake($v);
        }
        return $out;
    }

    public static function toCamel(string $key): string
    {
        return lcfirst(str_replace('_', '', ucwords($key, '_')));
    }

    public static function toSnake(string $key): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
    }
}
