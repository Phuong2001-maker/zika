<?php
declare(strict_types=1);

namespace Zika\Core;

use GdImage;

/**
 * Nhận tệp tải lên (02 §6.4): kiểm định dạng bằng finfo, ảnh mở bằng GD rồi lưu lại WebP (xoá EXIF),
 * tên tệp = sha1(nội dung) để nội dung đổi thì URL đổi (cache an toàn).
 */
final class Upload
{
    public const IMAGE_MAX = 5 * 1024 * 1024;
    public const IMAGE_MAX_PIXELS = 24_000_000;
    public const AUDIO_MAX = 2 * 1024 * 1024;
    public const STROKE_MAX = 200 * 1024;

    /** Cạnh dài tối đa và kiểu cắt theo loại ảnh. */
    private const IMAGE_KINDS = [
        // Ảnh đại diện hiện to nhất 72px: 192px vẫn nét trên màn 2–3x mà mỗi tệp chỉ vài KB.
        'avatar'   => ['max' => 192, 'square' => true, 'dir' => 'avatar'],
        'shopee'   => ['max' => 400, 'square' => true, 'dir' => 'img'],
        'qr'       => ['max' => 800, 'square' => false, 'dir' => 'qr'],
        'logo'     => ['max' => 512, 'square' => false, 'dir' => 'img'],
        'lesson'   => ['max' => 1600, 'square' => false, 'dir' => 'img'],
        'dialogue' => ['max' => 1600, 'square' => false, 'dir' => 'img'],
        'feedback' => ['max' => 1600, 'square' => false, 'dir' => 'img'],
    ];

    /**
     * @param array<string,mixed>|null $file một phần tử của $_FILES
     * @return array{path:string,url:string,width:int,height:int}
     */
    public static function image(?array $file, string $kind): array
    {
        $cfg = self::IMAGE_KINDS[$kind] ?? self::IMAGE_KINDS['lesson'];
        $bytes = self::read($file, self::IMAGE_MAX);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new HttpError(415, 'BAD_FILE', 'Chỉ nhận ảnh JPG, PNG hoặc WebP.');
        }
        // GD giải nén cả ảnh vào RAM (~4 byte/điểm): ảnh 6000×4000 đã ~100MB, vượt memory_limit
        // của hosting chia sẻ. Đọc kích thước từ phần đầu tệp và chặn trước khi giải nén.
        $kt = @getimagesizefromstring($bytes);
        if ($kt === false || $kt[0] * $kt[1] > self::IMAGE_MAX_PIXELS) {
            throw new HttpError(413, 'TOO_LARGE', 'Ảnh quá lớn (tối đa khoảng 24 megapixel).');
        }
        $src = @imagecreatefromstring($bytes);
        if (!$src instanceof GdImage) {
            throw new HttpError(415, 'BAD_FILE', 'Không đọc được ảnh này.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        [$sx, $sy, $sw, $sh] = [0, 0, $w, $h];
        if ($cfg['square']) {
            $side = min($w, $h);
            [$sx, $sy, $sw, $sh] = [intdiv($w - $side, 2), intdiv($h - $side, 2), $side, $side];
        }
        $scale = min(1, $cfg['max'] / max($sw, $sh));
        $dw = max(1, (int) round($sw * $scale));
        $dh = max(1, (int) round($sh * $scale));
        $dst = imagecreatetruecolor($dw, $dh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);
        ob_start();
        // Ảnh đại diện nhỏ, 80 đã đủ mịn; các loại khác giữ 85 cho chữ trên ảnh bài học khỏi nhoè.
        imagewebp($dst, null, $kind === 'avatar' ? 80 : 85);
        $out = (string) ob_get_clean();
        $stored = self::store($out, $cfg['dir'], 'webp');
        return $stored + ['width' => $dw, 'height' => $dh];
    }

    /** @param array<string,mixed>|null $file @return array{path:string,url:string} */
    public static function audio(?array $file): array
    {
        $bytes = self::read($file, self::AUDIO_MAX);
        if (!self::isMp3($bytes)) {
            throw new HttpError(415, 'BAD_FILE', 'Chỉ nhận tệp âm thanh MP3.');
        }
        return self::store($bytes, 'audio', 'mp3');
    }

    /**
     * finfo đoán nhầm một số MP3 hợp lệ (MPEG-2, khung đầu toàn số 0) thành application/octet-stream,
     * nên nó nói không thì xét thêm đầu tệp: thẻ ID3, hoặc khung MPEG Layer III (11 bit đồng bộ, layer = 01).
     */
    public static function isMp3(string $bytes): bool
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (in_array($mime, ['audio/mpeg', 'audio/mp3', 'audio/mpeg3'], true) || str_starts_with($bytes, 'ID3')) {
            return true;
        }
        return strlen($bytes) > 4 && ord($bytes[0]) === 0xFF && (ord($bytes[1]) & 0xE6) === 0xE2;
    }

    /** Thêm cách đọc vào khoá để một chữ đa âm không dùng nhầm file của âm khác. */
    public static function ttsHash(string $text, ?string $reading = null): string
    {
        $py = mb_strtolower((string) preg_replace('/\s+/u', '', Str::nfc($reading ?? '')));
        return sha1(trim($text) . ($py !== '' ? "\n" . $py : ''));
    }

    /** File Kokoro theo chữ + pinyin (nếu có); sinh lại thì ghi đè cùng chỗ.
     * @param array<string,mixed>|null $file @return array{path:string,url:string}
     */
    public static function tts(?array $file, string $text, string $voice, ?string $reading = null): array
    {
        $bytes = self::read($file, self::AUDIO_MAX);
        if (!self::isMp3($bytes)) {
            throw new HttpError(415, 'BAD_FILE', 'Chỉ nhận tệp âm thanh MP3.');
        }
        $hash = self::ttsHash($text, $reading);
        $rel = 'tts/' . $voice . '/' . substr($hash, 0, 2) . '/' . $hash . '.mp3';
        $abs = Config::uploadsDir($rel);
        if (!is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0755, true);
        }
        file_put_contents($abs, $bytes, LOCK_EX);
        $url = Config::uploadsUrl($rel);
        return ['path' => $url, 'url' => $url];
    }

    /** Dữ liệu nét chữ (Hanzi Writer): JSON có strokes, medians. @param array<string,mixed>|null $file @return array<string,mixed> */
    public static function strokeJson(?array $file): array
    {
        $bytes = self::read($file, self::STROKE_MAX);
        $data = Json::decode($bytes, null);
        if (!is_array($data) || !isset($data['strokes'], $data['medians']) || !is_array($data['strokes']) || !is_array($data['medians'])) {
            throw new HttpError(415, 'BAD_FILE', 'Tệp nét chữ phải là JSON có khoá strokes và medians.');
        }
        return $data;
    }

    /** @param array<string,mixed>|null $file */
    private static function read(?array $file, int $max): string
    {
        if ($file === null || !isset($file['tmp_name'], $file['error'])) {
            throw HttpError::invalid('Hãy chọn tệp để tải lên.');
        }
        if ((int) $file['error'] === UPLOAD_ERR_INI_SIZE || (int) $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new HttpError(413, 'TOO_LARGE');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw HttpError::invalid('Tải tệp lên chưa thành công, thử lại nhé.');
        }
        $tmp = (string) $file['tmp_name'];
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            throw new HttpError(415, 'BAD_FILE');
        }
        if ((int) ($file['size'] ?? filesize($tmp)) > $max || filesize($tmp) > $max) {
            throw new HttpError(413, 'TOO_LARGE', 'Tệp quá lớn (tối đa ' . round($max / 1048576, 1) . ' MB).');
        }
        return (string) file_get_contents($tmp);
    }

    /** @return array{path:string,url:string} */
    public static function store(string $bytes, string $dir, string $ext): array
    {
        $hash = sha1($bytes);
        $rel = $dir . '/' . substr($hash, 0, 2) . '/' . $hash . '.' . $ext;
        $abs = Config::uploadsDir($rel);
        if (!is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0755, true);
        }
        if (!is_file($abs)) {
            file_put_contents($abs, $bytes, LOCK_EX);
        }
        $url = Config::uploadsUrl($rel);
        return ['path' => $url, 'url' => $url];
    }
}
