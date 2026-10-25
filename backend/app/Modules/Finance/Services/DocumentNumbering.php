<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;

/** Numeração sequencial por instituição, tipo e ano, atómica (sem lacunas nem repetições em concorrência). */
class DocumentNumbering
{
    public function next(int $institutionId, string $type, ?int $year = null): int
    {
        $year ??= (int) now()->format('Y');

        return (int) DB::selectOne(
            'insert into document_sequences (institution_id, document_type, year, last_number) values (?, ?, ?, 1)
             on conflict (institution_id, document_type, year) do update set last_number = document_sequences.last_number + 1
             returning last_number',
            [$institutionId, $type, $year],
        )->last_number;
    }
}
