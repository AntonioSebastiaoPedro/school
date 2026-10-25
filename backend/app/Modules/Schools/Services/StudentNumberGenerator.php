<?php

namespace App\Modules\Schools\Services;

use Illuminate\Support\Facades\DB;

/**
 * Nº de processo: maior número puramente numérico da instituição + 1, com 6 dígitos.
 * Números com letras ou traços (importações, p. ex. 002-9B-040) são ignorados no cálculo.
 * Tem de correr dentro de uma transacção (usa um lock consultivo por instituição).
 */
class StudentNumberGenerator
{
    public function next(int $institutionId): string
    {
        DB::select('select pg_advisory_xact_lock(?, 1)', [$institutionId]);

        $max = DB::table('students')
            ->where('institution_id', $institutionId)
            ->whereRaw("student_number ~ '^[0-9]+\$'")
            ->max(DB::raw('student_number::bigint'));

        return str_pad((string) (((int) $max) + 1), 6, '0', STR_PAD_LEFT);
    }
}
