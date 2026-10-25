<?php

namespace App\Modules\Schools\Support;

use App\Modules\Schools\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Único ponto de formatação da filiação, igual em todos os documentos:
 * "filho/a de A e de B", só A, só B, ou pontos quando não há dados.
 */
class Filiation
{
    public const BLANK = '..................................';

    public static function of(Student $student): string
    {
        $parents = DB::table('student_guardian')
            ->join('guardians', fn ($j) => $j->on('guardians.id', '=', 'student_guardian.guardian_id')->on('guardians.institution_id', '=', 'student_guardian.institution_id'))
            ->where('student_guardian.institution_id', $student->institution_id)
            ->where('student_guardian.student_id', $student->id)
            ->whereIn('student_guardian.relationship', ['father', 'mother'])
            ->get(['student_guardian.relationship', 'guardians.first_name', 'guardians.last_name'])
            ->keyBy('relationship');

        $name = fn (string $rel, ?string $fallback) => isset($parents[$rel])
            ? trim($parents[$rel]->first_name.' '.$parents[$rel]->last_name)
            : self::clean($fallback);

        return self::format($student->gender, $name('father', $student->father_name), $name('mother', $student->mother_name));
    }

    public static function format(?string $gender, ?string $father, ?string $mother): string
    {
        $word = match ($gender) {
            'M' => 'filho',
            'F' => 'filha',
            default => 'filho/a',
        };

        $names = array_values(array_filter([$father, $mother], fn ($n) => $n !== null && $n !== ''));

        return match (count($names)) {
            0 => "{$word} de ".self::BLANK,
            1 => "{$word} de {$names[0]}",
            default => "{$word} de {$names[0]} e de {$names[1]}",
        };
    }

    private static function clean(?string $name): ?string
    {
        $name = $name === null ? null : trim($name);

        return $name === '' ? null : $name;
    }
}
