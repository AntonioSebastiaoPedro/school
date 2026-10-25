<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\DB;

/** Registo de auditoria das operações críticas (RF-105, RF-106). */
class Audit
{
    public static function record(
        string $action,
        string $resourceType,
        int|string|null $resourceId,
        ?int $institutionId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        $request = request();

        DB::table('audit_logs')->insert([
            'institution_id' => $institutionId,
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : (string) $resourceId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'before_data' => $before === null ? null : json_encode($before),
            'after_data' => $after === null ? null : json_encode($after),
        ]);
    }
}
