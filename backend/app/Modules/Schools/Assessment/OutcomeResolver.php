<?php

namespace App\Modules\Schools\Assessment;

use App\Modules\Schools\Assessment\Domain\PromotionRules;
use App\Modules\Schools\Assessment\Domain\YearOutcome;
use App\Modules\Schools\Models\GradeLevel;
use App\Modules\Schools\Services\AssessmentRulesProvider;
use Illuminate\Support\Facades\DB;

/**
 * Único caminho que decide o resultado de uma matrícula. A pauta final, o fecho do ano e a
 * renovação passam todos por aqui, por isso nunca divergem (corrige a dupla definição de
 * "Aprovado" do sistema anterior).
 */
class OutcomeResolver
{
    public function __construct(private readonly AssessmentRulesProvider $rules) {}

    /**
     * Classificações finais por código de disciplina, só das disciplinas activas no currículo da classe.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, array<string, int>> matrícula => [código => nota final]
     */
    public function finals(int $institutionId, int $gradeLevelId, array $enrollmentIds): array
    {
        $rows = DB::table('grade_averages as ga')
            ->join('subjects as s', fn ($j) => $j->on('s.id', '=', 'ga.subject_id')->on('s.institution_id', '=', 'ga.institution_id'))
            ->join('grade_level_subjects as gls', fn ($j) => $j->on('gls.subject_id', '=', 'ga.subject_id')->on('gls.institution_id', '=', 'ga.institution_id'))
            ->where('ga.institution_id', $institutionId)
            ->where('gls.grade_level_id', $gradeLevelId)
            ->where('gls.is_active', true)
            ->whereIn('ga.enrollment_id', $enrollmentIds)
            ->whereNotNull('ga.final_avg')
            ->get(['ga.enrollment_id', 's.code', 'ga.final_avg']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->enrollment_id][$row->code] = (int) $row->final_avg;
        }

        return $out;
    }

    /** @param list<int> $enrollmentIds @return array<int, bool> matrícula => já fez recurso */
    public function resitTakers(int $institutionId, array $enrollmentIds): array
    {
        $ids = DB::table('grades')
            ->where(['institution_id' => $institutionId, 'kind' => 'resit'])
            ->where('value', '>', 0)
            ->whereIn('enrollment_id', $enrollmentIds)
            ->distinct()->pluck('enrollment_id')->all();

        return array_fill_keys($ids, true);
    }

    /** @param array<string, int> $finals */
    public function decide(GradeLevel $level, array $finals, bool $afterResit): YearOutcome
    {
        return (new PromotionRules($this->rules->current()))->decide(
            $level->assessment_regime,
            $level->pass_mark,
            $finals,
            $afterResit,
        );
    }

    /** @param array<string, int> $finals */
    public function negatives(GradeLevel $level, array $finals): int
    {
        return (new PromotionRules($this->rules->current()))->negatives($level->pass_mark, $finals);
    }
}
