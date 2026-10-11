<?php
declare(strict_types=1);

namespace Zika\Services;

use Throwable;
use Zika\Core\Config;
use Zika\Core\Db;
use Zika\Core\Json;
use Zika\Core\Log;

/**
 * Dữ liệu nét chữ Hán cho Hanzi Writer (Make Me a Hanzi), tải về lưu ở uploads/hanzi (04 §10.5).
 * 牛 (U+725B) → uploads/hanzi/72/725b.json
 */
final class HanziDataService
{
    private const CDN = 'https://cdn.jsdelivr.net/npm/hanzi-writer-data@2.0/';

    public static function hex(string $char): string
    {
        return dechex((int) mb_ord($char, 'UTF-8'));
    }

    public static function relPath(string $char): string
    {
        $hex = self::hex($char);
        return 'hanzi/' . substr($hex, 0, 2) . '/' . $hex . '.json';
    }

    /** Thử tải dữ liệu nét cho 1 chữ (theo id bảng characters). Trả true nếu thành công. */
    public static function fetch(int $charId): bool
    {
        $c = Db::one('SELECT id, hanzi FROM characters WHERE id = ?', [$charId]);
        if ($c === null) {
            return false;
        }
        $char = (string) $c['hanzi'];
        try {
            $ch = curl_init(self::CDN . rawurlencode($char) . '.json');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($body === false || $status !== 200) {
                throw new \RuntimeException("HTTP $status " . curl_error($ch));
            }
            $data = Json::decode((string) $body, null);
            if (!is_array($data) || !isset($data['strokes']) || !is_array($data['strokes'])) {
                throw new \RuntimeException('Dữ liệu nét không hợp lệ');
            }
            self::save($charId, $char, (string) $body, count($data['strokes']), 'ok');
            return true;
        } catch (Throwable $e) {
            Log::warn('hanzi.fetch', ['char' => $char, 'error' => $e->getMessage()]);
            Db::exec("UPDATE characters SET stroke_data_status = 'missing' WHERE id = ? AND stroke_data_status <> 'custom'", [$charId]);
            return false;
        }
    }

    /** Lưu tệp nét (tải tự động hoặc admin tải lên). */
    public static function save(int $charId, string $char, string $json, int $strokes, string $status): void
    {
        $rel = self::relPath($char);
        $abs = Config::uploadsDir($rel);
        if (!is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0755, true);
        }
        file_put_contents($abs, $json, LOCK_EX);
        Db::exec(
            'UPDATE characters SET stroke_data_status = ?, stroke_data_path = ?, strokes = IF(? > 0, ?, strokes), updated_at = NOW() WHERE id = ?',
            [$status, Config::uploadsUrl($rel), $strokes, $strokes, $charId],
        );
    }

    /** URL cho trình duyệt, kèm ?v= để PWA không giữ bản cũ (02 §6.4). */
    public static function url(?string $path, ?string $updatedAt): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $v = $updatedAt !== null ? strtotime($updatedAt) : 0;
        return $path . '?v=' . $v;
    }
}
