<?php
declare(strict_types=1);

namespace Zika\Core;

/** Nhật ký thao tác quản trị (bảng audit_log, giữ 1 năm). */
final class Audit
{
    /** @param array<string,mixed>|null $detail */
    public static function log(Request $r, string $action, string $entity, ?int $entityId = null, ?array $detail = null): void
    {
        Db::insert('audit_log', [
            'user_id' => $r->user['id'] ?? null,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'detail' => $detail === null ? null : Json::encode($detail),
            'ip' => substr($r->ip, 0, 45),
        ]);
    }
}
