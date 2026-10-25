<?php

namespace App\Modules\Schools\Assessment;

use App\Modules\Schools\Assessment\Domain\GradeCalculator;
use App\Modules\Schools\Models\GradeLevel;
use App\Modules\Schools\Services\AssessmentRulesProvider;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula a linha materializada de grade_averages de uma matrícula e disciplina a partir
 * de grades (a fonte de verdade). Só grava valores positivos; 0 vira NULL.
 */
class GradeAverageRecalculator
{
    public function __construct(private readonly AssessmentRulesProvider $rules) {}

    public function recalculate(int $institutionId, int $enrollmentId, int $subjectId, GradeLevel $level): void
    {
        $grades = DB::table('grades')
            ->where(['institution_id' => $institutionId, 'enrollment_id' => $enrollmentId, 'subject_id' => $subjectId])
            ->get(['term', 'kind', 'value']);

        $value = fn (int $term, string $kind) => $grades->first(fn ($g) => $g->term === $term && $g->kind === $kind)?->value;

        $calculator = new GradeCalculator($this->rules->current());

        $mts = [];
        foreach ([1, 2, 3] as $term) {
            $mts[$term] = $calculator->mt(
                $this->float($value($term, 'mac')),
                $this->float($value($term, 'npp')),
                $this->float($value($term, 'npt')),
            );
        }

        $mfd = $calculator->mfd(array_values($mts));
        $exam = $this->float($value(0, 'exam'));
        $resit = $this->float($value(0, 'resit'));
        $final = $calculator->mfa($mfd, $exam, $resit, $level->has_national_exam);

        $row = [
            'term1_avg' => $this->positive($mts[1]),
            'term2_avg' => $this->positive($mts[2]),
            'term3_avg' => $this->positive($mts[3]),
            'year_avg' => $this->positive($mfd),
            'exam' => $this->positive($exam),
            'resit' => $this->positive($resit),
            'final_avg' => $final > 0 ? $final : null,
            'updated_at' => now(),
        ];

        $key = ['institution_id' => $institutionId, 'enrollment_id' => $enrollmentId, 'subject_id' => $subjectId];

        if ($grades->isEmpty()) {
            DB::table('grade_averages')->where($key)->delete();

            return;
        }

        DB::table('grade_averages')->updateOrInsert($key, $row);
    }

    private function float(mixed $v): ?float
    {
        return $v === null ? null : (float) $v;
    }

    private function positive(?float $v): ?float
    {
        return $v !== null && $v > 0 ? $v : null;
    }
}
