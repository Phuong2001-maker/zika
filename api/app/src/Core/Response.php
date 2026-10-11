<?php
declare(strict_types=1);

namespace Zika\Core;

/** Response trả về trình duyệt. Controller có thể trả mảng (tự bọc thành { data }) hoặc đối tượng này. */
final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
        public ?string $filePath = null,
    ) {
    }

    /** @param array<string,mixed>|null $meta */
    public static function json(mixed $data, int $status = 200, ?array $meta = null): self
    {
        $payload = ['data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        return self::rawJson(Json::encode($payload), $status);
    }

    public static function rawJson(string $json, int $status = 200): self
    {
        return new self($status, $json, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function error(HttpError $e): self
    {
        return self::rawJson(Json::encode($e->toArray()), $e->status);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['Location' => $location, 'Cache-Control' => 'no-store']);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * CSV UTF-8 có BOM để Excel mở đúng tiếng Việt.
     * @param list<string>       $header
     * @param iterable<list<mixed>> $rows
     */
    public static function csv(string $filename, array $header, iterable $rows): self
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, array_map(static fn ($v) => $v === null ? '' : (string) $v, $row), ',', '"', '');
        }
        rewind($fh);
        $body = (string) stream_get_contents($fh);
        fclose($fh);
        return new self(200, $body, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    public static function file(string $path, string $contentType, string $downloadName): self
    {
        return new self(200, '', [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Content-Length'      => (string) filesize($path),
            'Cache-Control'       => 'no-store',
        ], $path);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        Cookie::flush();
        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }
        if ($this->status !== 204 && $this->status !== 304) {
            echo $this->body;
        }
    }
}
