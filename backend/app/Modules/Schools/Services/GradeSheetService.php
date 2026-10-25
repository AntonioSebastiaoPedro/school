<?php

namespace App\Modules\Schools\Services;

use App\Modules\Schools\Assessment\Domain\GradeCalculator;
use App\Modules\Schools\Assessment\Domain\YearOutcome;
use App\Modules\Schools\Assessment\OutcomeResolver;
use App\Modules\Schools\Models\Grade;
use App\Modules\Schools\Models\GradeLevel;
use App\Modules\Schools\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pautas trimestral, final e de recurso (JSON; o PDF A3 fica para fase posterior). */
class GradeSheetService
{
    public function __construct(
        private readonly GradeService $grades,
        private readonly OutcomeResolver $outcomes,
        private readonly AssessmentRulesProvider $rules,
    ) {}

    /** @return array<string, mixed> */
    public function build(Section $section, string $type, ?int $term): array
    {
        $level = GradeLevel::findOrFail($section->grade_level_id);

        if (! $level->hasGrades()) {
            abort(404, 'A Iniciação não tem pauta.');
        }

        if ($type === 'resit' && ! $level->has_national_exam) {
            abort(404, 'Só a 6.ª e a 9.ª classe têm pauta de recurso.');
        }

        if ($type === 'term' && ($term === null || $term < 1 || $term > 3)) {
            throw ValidationException::withMessages(['term' => 'Indique o trimestre (1 a 3).']);
        }

        $subjects = DB::table('grade_level_subjects as gls')
            ->join('subjects as s', fn ($j) => $j->on('s.id', '=', 'gls.subject_id')->on('s.institution_id', '=', 'gls.institution_id'))
            ->where('gls.institution_id', $section->institution_id)
            ->where(['gls.grade_level_id' => $level->id, 'gls.is_active' => true])
            ->orderBy('gls.display_order')
            ->get(['s.id', 's.code', 's.name']);

        $roster = $this->grades->roster($section);
        $ids = $roster->pluck('id')->all();

        $rows = match ($type) {
            'term' => $this->termRows($roster, $subjects, $term),
            'final' => $this->finalRows($roster, $subjects, $level, $section),
            default => $this->resitRows($roster, $subjects, $level, $section),
        };

        return [
            'type' => $type,
            'term' => $term,
            'section' => ['id' => $section->id, 'name' => $section->name, 'grade_level_id' => $level->id],
            'scale' => ['max_score' => $level->max_score, 'pass_mark' => $level->pass_mark],
            'subjects' => $subjects->map(fn ($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->values(),
            'rows' => $rows,
        ];
    }

    private function termRows($roster, $subjects, int $term): array
    {
        $calculator = new GradeCalculator($this->rules->current());
        $grades = Grade::query()->whereIn('enrollment_id', $roster->pluck('id'))->where('term', $term)->get()->groupBy('enrollment_id');

        return $roster->map(function ($e) use ($subjects, $grades, $calculator) {
            $values = [];
            foreach ($subjects as $s) {
                $byKind = ($grades[$e->id] ?? collect())->where('subject_id', $s->id)->pluck('value', 'kind');
                $mt = $calculator->mt($byKind['mac'] ?? null, $byKind['npp'] ?? null, $byKind['npt'] ?? null);
                $values[$s->code] = [
                    'mac' => $byKind['mac'] ?? null, 'npp' => $byKind['npp'] ?? null, 'npt' => $byKind['npt'] ?? null,
                    'mt' => $mt > 0 ? $mt : null,
                ];
            }

            return $this->row($e, $values, null);
        })->values()->all();
    }

    private function finalRows($roster, $subjects, GradeLevel $level, Section $section): array
    {
        $averages = $this->averages($roster);
        $finals = $this->outcomes->finals($section->institution_id, $level->id, $roster->pluck('id')->all());
        $resitTakers = $this->outcomes->resitTakers($section->institution_id, $roster->pluck('id')->all());

        return $roster->map(function ($e) use ($subjects, $averages, $finals, $resitTakers, $level) {
            $values = [];
            foreach ($subjects as $s) {
                $a = $averages[$e->id][$s->id] ?? null;
                $values[$s->code] = [
                    'mfd' => $this->num($a?->year_avg),
                    'exam' => $level->has_national_exam ? $this->num($a?->exam) : null,
                    'mfa' => $this->num($a?->final_avg),
                    'resit' => $level->has_national_exam ? $this->num($a?->resit) : null,
                ];
            }

            $outcome = $this->outcomes->decide($level, $finals[$e->id] ?? [], isset($resitTakers[$e->id]));

            return $this->row($e, $values, $outcome);
        })->values()->all();
    }

    /** Pauta de recurso: só os alunos em recurso ou que já o fizeram. */
    private function resitRows($roster, $subjects, GradeLevel $level, Section $section): array
    {
        $ids = $roster->pluck('id')->all();
        $averages = $this->averages($roster);
        $finals = $this->outcomes->finals($section->institution_id, $level->id, $ids);
        $takers = $this->outcomes->resitTakers($section->institution_id, $ids);
        $calculator = new GradeCalculator($this->rules->current());

        return $roster->filter(function ($e) use ($finals, $takers, $level) {
            if (isset($takers[$e->id])) {
                return true;
            }

            // Sem recurso feito: está em recurso se, sem NER, a decisão for Recurso.
            return $this->outcomes->decide($level, $finals[$e->id] ?? [], false) === YearOutcome::Resit;
        })->map(function ($e) use ($subjects, $averages, $finals, $takers, $level, $calculator) {
            $values = [];
            $before = [];

            foreach ($subjects as $s) {
                $a = $averages[$e->id][$s->id] ?? null;
                $mfd = (float) ($a?->year_avg ?? 0);
                $mfaBefore = $mfd > 0 || $a?->exam ? $calculator->mfa($mfd, $a?->exam === null ? null : (float) $a->exam, null, true) : null;
                $before[$s->code] = $mfaBefore;
                $values[$s->code] = [
                    'mfd' => $this->num($a?->year_avg), 'exam' => $this->num($a?->exam), 'mfa_before' => $mfaBefore,
                    'resit' => $this->num($a?->resit), 'mfa_after' => $this->num($a?->final_avg),
                ];
            }

            $outcome = isset($takers[$e->id])
                ? $this->outcomes->decide($level, $finals[$e->id] ?? [], true)
                : YearOutcome::Resit;

            return $this->row($e, $values, $outcome);
        })->values()->all();
    }

    /** Os decimais chegam do PostgreSQL como texto; a API devolve números. */
    private function num(mixed $v): int|float|null
    {
        return $v === null ? null : $v + 0;
    }

    private function averages($roster): array
    {
        $rows = DB::table('grade_averages')->whereIn('enrollment_id', $roster->pluck('id'))->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->enrollment_id][$r->subject_id] = $r;
        }

        return $out;
    }

    private function row($enrollment, array $values, ?YearOutcome $outcome): array
    {
        return [
            'enrollment_id' => $enrollment->id,
            'roll_number' => $enrollment->roll_number,
            'student_name' => $enrollment->student->first_name.' '.$enrollment->student->last_name,
            'values' => $values,
            'outcome' => $outcome?->value,
            'outcome_label' => $outcome?->label(),
        ];
    }
}
