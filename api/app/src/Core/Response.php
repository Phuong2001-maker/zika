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

    /** Gửi một đoạn của file thay vì cả file (trả lời Range); null = cả file. */
    public ?int $fileOffset = null;
    public int $fileLength = 0;

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

    /**
     * File phát/tua được: trả lời header Range bằng 206 + đúng đoạn được hỏi. Range lạ (nhiều đoạn, sai cú pháp) thì
     * trả cả file với 200 — đúng chuẩn HTTP, trình duyệt vẫn phát được.
     * @param array<string,string> $headers
     */
    public static function fileRange(string $path, string $contentType, string $range, array $headers = []): self
    {
        $size = (int) filesize($path);
        $res = new self(200, '', $headers + [
            'Content-Type'   => $contentType,
            'Accept-Ranges'  => 'bytes',
            'Content-Length' => (string) $size,
        ], $path);
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) || $m[1] === '' && $m[2] === '') {
            return $res;
        }
        if ($m[1] === '') {
            $start = max(0, $size - (int) $m[2]);
            $end = $size - 1;
        } else {
            $start = (int) $m[1];
            $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
        }
        if ($start > $end || $start >= $size) {
            return new self(416, '', ['Content-Range' => "bytes */$size"]);
        }
        $res->status = 206;
        $res->headers['Content-Range'] = "bytes $start-$end/$size";
        $res->headers['Content-Length'] = (string) ($end - $start + 1);
        $res->fileOffset = $start;
        $res->fileLength = $end - $start + 1;
        return $res;
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
        if ($this->filePath !== null && $this->fileOffset !== null) {
            $fh = fopen($this->filePath, 'rb');
            fseek($fh, $this->fileOffset);
            for ($con = $this->fileLength; $con > 0 && !feof($fh); $con -= 65536) {
                echo fread($fh, min(65536, $con));
            }
            fclose($fh);
            return;
        }
        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }
        if ($this->status !== 204 && $this->status !== 304) {
            echo $this->body;
        }
    }
}
