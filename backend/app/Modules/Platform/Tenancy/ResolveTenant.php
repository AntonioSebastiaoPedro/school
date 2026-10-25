<?php

namespace App\Modules\Platform\Tenancy;

use App\Modules\Platform\Models\Institution;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Escolhe a instituição activa a partir do cabeçalho X-Institution-Id, mas só
 * a aceita se o utilizador autenticado tiver associação activa a ela e se a
 * instituição estiver aprovada e operacional (RN-001, RN-004, RN-011).
 */
class ResolveTenant
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $requested = $request->header('X-Institution-Id');

        $query = DB::table('institution_user')->where('user_id', $user->id)->where('status', 'active');

        $membership = $requested !== null
            ? (ctype_digit((string) $requested) ? (clone $query)->where('institution_id', (int) $requested)->first() : null)
            : ((clone $query)->count() === 1 ? $query->first() : null);

        if ($membership === null) {
            return response()->json([
                'message' => 'Instituição activa em falta ou sem acesso. Envie X-Institution-Id.',
            ], 403);
        }

        $institution = Institution::query()->find($membership->institution_id);

        if ($institution === null || $institution->approval_status !== 'approved' || $institution->operational_status !== 'active') {
            return response()->json(['message' => 'A instituição não está activa.'], 403);
        }

        $this->tenant->set($institution->id, $membership->role);

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
