<?php
declare(strict_types=1);

namespace Zika\Core;

/**
 * Kiểm tra dữ liệu đầu vào theo từng trường, gom lỗi tiếng Việt rồi ném 422 VALIDATION một lần.
 *
 *   $v = new Validator($r->body);
 *   $name = $v->str('displayName', 'Tên hiển thị', min: 2, max: 60);
 *   $v->done();
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function present(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null;
    }

    public function raw(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /** Chuỗi đã trim + chuẩn hoá NFC. Không bắt buộc mà trống → ''. */
    public function str(string $key, string $label, bool $required = true, int $min = 0, ?int $max = null): string
    {
        $v = $this->data[$key] ?? null;
        if ($v !== null && !is_string($v) && !is_int($v) && !is_float($v)) {
            $this->errors[$key] = "$label không hợp lệ.";
            return '';
        }
        $s = Str::nfc(trim((string) ($v ?? '')));
        if ($s === '') {
            if ($required) {
                $this->errors[$key] = "Hãy nhập $label.";
            }
            return '';
        }
        $len = mb_strlen($s);
        if ($len < $min) {
            $this->errors[$key] = "$label cần ít nhất $min ký tự.";
        } elseif ($max !== null && $len > $max) {
            $this->errors[$key] = "$label dài tối đa $max ký tự.";
        }
        return $s;
    }

    /** Chuỗi có thể null (trống → null). */
    public function strOrNull(string $key, string $label, ?int $max = null): ?string
    {
        $s = $this->str($key, $label, false, 0, $max);
        return $s === '' ? null : $s;
    }

    public function email(string $key, string $label = 'Email', bool $required = true): string
    {
        $s = Str::lower($this->str($key, $label, $required, 0, 190));
        if ($s !== '' && filter_var($s, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$key] = 'Email không hợp lệ.';
        }
        return $s;
    }

    public function int(string $key, string $label, ?int $min = null, ?int $max = null, bool $required = true, ?int $default = null): ?int
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required && $default === null) {
                $this->errors[$key] = "Hãy nhập $label.";
            }
            return $default;
        }
        if (!is_int($v) && !(is_string($v) && preg_match('/^-?\d+$/', $v)) && !(is_float($v) && floor($v) === $v)) {
            $this->errors[$key] = "$label phải là số nguyên.";
            return $default;
        }
        $n = (int) $v;
        if ($min !== null && $n < $min || $max !== null && $n > $max) {
            $this->errors[$key] = match (true) {
                $min !== null && $max !== null => "$label phải từ $min đến $max.",
                $min !== null => "$label phải từ $min trở lên.",
                default => "$label tối đa $max.",
            };
        }
        return $n;
    }

    public function num(string $key, string $label, ?float $min = null, ?float $max = null, ?float $default = null): ?float
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($default === null) {
                $this->errors[$key] = "Hãy nhập $label.";
            }
            return $default;
        }
        if (!is_numeric($v)) {
            $this->errors[$key] = "$label phải là số.";
            return $default;
        }
        $n = (float) $v;
        if ($min !== null && $n < $min || $max !== null && $n > $max) {
            $this->errors[$key] = "$label phải từ " . Str::num($min ?? 0, 1) . ' đến ' . Str::num($max ?? 0, 1) . '.';
        }
        return $n;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->data[$key] ?? null;
        if ($v === null) {
            return $default;
        }
        return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** @param list<string> $allowed */
    public function in(string $key, string $label, array $allowed, ?string $default = null): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($default === null) {
                $this->errors[$key] = "Hãy chọn $label.";
            }
            return $default;
        }
        if (!is_string($v) || !in_array($v, $allowed, true)) {
            $this->errors[$key] = "$label không hợp lệ.";
            return $default;
        }
        return $v;
    }

    /** @return array<mixed> */
    public function arr(string $key, string $label, bool $required = true): array
    {
        $v = $this->data[$key] ?? null;
        if ($v === null) {
            if ($required) {
                $this->errors[$key] = "Thiếu $label.";
            }
            return [];
        }
        if (!is_array($v)) {
            $this->errors[$key] = "$label không hợp lệ.";
            return [];
        }
        return $v;
    }

    /** Danh sách số nguyên dương (ví dụ ids để sắp xếp). @return list<int> */
    public function ids(string $key, string $label = 'Danh sách'): array
    {
        $out = [];
        foreach ($this->arr($key, $label) as $v) {
            if (!is_numeric($v) || (int) $v <= 0) {
                $this->errors[$key] = "$label không hợp lệ.";
                return [];
            }
            $out[] = (int) $v;
        }
        return $out;
    }

    /** Ngày dạng YYYY-MM-DD hoặc null. */
    public function date(string $key, string $label, bool $required = false): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = "Hãy chọn $label.";
            }
            return null;
        }
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || !checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4))) {
            $this->errors[$key] = "$label không hợp lệ.";
            return null;
        }
        return $v;
    }

    /** Ngày giờ ISO 8601 hoặc “YYYY-MM-DD HH:MM” → chuỗi DATETIME theo giờ VN. */
    public function datetime(string $key, string $label, bool $required = false): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = "Hãy chọn $label.";
            }
            return null;
        }
        try {
            $d = new \DateTimeImmutable((string) $v, Clock::tz());
            return Clock::sql($d->setTimezone(Clock::tz()));
        } catch (\Exception) {
            $this->errors[$key] = "$label không hợp lệ.";
            return null;
        }
    }

    public function error(string $key, string $message): void
    {
        $this->errors[$key] ??= $message;
    }

    public function hasError(string $key): bool
    {
        return isset($this->errors[$key]);
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function done(): void
    {
        if ($this->errors !== []) {
            throw HttpError::validation($this->errors);
        }
    }
}
