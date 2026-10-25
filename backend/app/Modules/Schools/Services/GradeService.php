<?php

namespace App\Modules\Schools\Services;

use App\Modules\Schools\Assessment\Domain\GradeCalculator;
use App\Modules\Schools\Assessment\GradeAverageRecalculator;
use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Models\Grade;
use App\Modules\Schools\Models\GradeLevel;
use App\Modules\Schools\Models\GradeSheetLock;
use App\Modules\Schools\Models\Section;
use App\Modules\Schools\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeService
{
    /** Alunos a quem se lançam notas: matriculados e confirmados na turma. */
    public const GRADED_STATUSES = ['active', 'completed'];

    public function __construct(
        private readonly GradeAverageRecalculator $averages,
        private readonly AssessmentRulesProvider $rules,
    ) {}

    /** @return array<string, mixed> */
    public function grid(Section $section, Subject $subject, int $term): array
    {
        $level = GradeLevel::findOrFail($section->grade_level_id);
        $this->assertGradable($level, $subject);

        $enrollments = $this->roster($section);
        $grades = Grade::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('subject_id', $subject->id)
            ->where('term', $term)
            ->get()
            ->groupBy('enrollment_id');

        $calculator = new GradeCalculator($this->rules->current());

        $rows = $enrollments->map(function ($e) use ($grades, $calculator, $term) {
            $byKind = ($grades[$e->id] ?? collect())->pluck('value', 'kind');

            return [
                'enrollment_id' => $e->id,
                'roll_number' => $e->roll_number,
                'student_name' => $e->student->first_name.' '.$e->student->last_name,
                'mac' => $byKind['mac'] ?? null,
                'npp' => $byKind['npp'] ?? null,
                'npt' => $byKind['npt'] ?? null,
                'mt' => $term > 0 ? $this->positive($calculator->mt($byKind['mac'] ?? null, $byKind['npp'] ?? null, $byKind['npt'] ?? null)) : null,
                'exam' => $byKind['exam'] ?? null,
                'resit' => $byKind['resit'] ?? null,
            ];
        })->values();

        return [
            'scale' => ['max_score' => $level->max_score, 'pass_mark' => $level->pass_mark],
            'locked' => GradeSheetLock::query()->where('section_id', $section->id)->where('term', $term)->exists(),
            'rows' => $rows,
        ];
    }

    /**
     * Grava notas. Aceita vírgula decimal; 0 ou vazio apaga a nota (sem nota).
     *
     * @param  list<array{enrollment_id:int, kind:string, value:mixed}>  $entries
     */
    public function save(Section $section, Subject $subject, int $term, array $entries, ?int $userId): int
    {
        $level = GradeLevel::findOrFail($section->grade_level_id);
        $this->assertGradable($level, $subject);

        if ($term === 0 && ! $level->has_national_exam) {
            throw ValidationException::withMessages(['term' => 'O exame e o recurso só existem na 6.ª e na 9.ª classe.']);
        }

        if (GradeSheetLock::query()->where('section_id', $section->id)->where('term', $term)->exists()) {
            throw new ConflictException('A pauta deste período está fechada.');
        }

        $allowedKinds = $term === 0 ? ['exam', 'resit'] : ['mac', 'npp', 'npt'];
        $roster = $this->roster($section)->keyBy('id');
        $scale = $level->scale();
        $institutionId = $section->institution_id;

        return DB::transaction(function () use ($entries, $allowedKinds, $roster, $scale, $subject, $term, $userId, $level, $institutionId) {
            $affected = [];

            foreach ($entries as $i => $entry) {
                if (! in_array($entry['kind'], $allowedKinds, true)) {
                    throw ValidationException::withMessages(["entries.{$i}.kind" => 'Tipo de nota inválido para este período.']);
                }

                if (! $roster->has($entry['enrollment_id'])) {
                    throw ValidationException::withMessages(["entries.{$i}.enrollment_id" => 'O aluno não está matriculado nesta turma.']);
                }

                $value = $this->normalize($entry['value'] ?? null);
                $key = ['enrollment_id' => $entry['enrollment_id'], 'subject_id' => $subject->id, 'term' => $term, 'kind' => $entry['kind']];

                if ($value === null) {
                    Grade::query()->where($key)->delete();
                } elseif (! $scale->accepts($value)) {
                    throw ValidationException::withMessages(["entries.{$i}.value" => "A nota deve estar entre 0 e {$scale->max}."]);
                } else {
                    Grade::updateOrCreate($key, ['value' => $value, 'recorded_by' => $userId]);
                }

                $affected[$entry['enrollment_id']] = true;
            }

            foreach (array_keys($affected) as $enrollmentId) {
                $this->averages->recalculate($institutionId, $enrollmentId, $subject->id, $level);
            }

            return count($affected);
        });
    }

    /** @return Collection<int, Enrollment> */
    public function roster(Section $section)
    {
        return Enrollment::query()
            ->with('student:id,first_name,last_name')
            ->where('section_id', $section->id)
            ->whereIn('status', self::GRADED_STATUSES)
            ->orderBy('roll_number')
            ->get();
    }

    private function assertGradable(GradeLevel $level, Subject $subject): void
    {
        if (! $level->hasGrades()) {
            throw ValidationException::withMessages(['section_id' => 'Na Iniciação não se lançam notas.']);
        }

        $offered = DB::table('grade_level_subjects')
            ->where(['institution_id' => $subject->institution_id, 'grade_level_id' => $level->id, 'subject_id' => $subject->id, 'is_active' => true])
            ->exists();

        if (! $offered) {
            throw ValidationException::withMessages(['subject_id' => "A disciplina {$subject->code} não é leccionada nesta classe."]);
        }
    }

    /** "7,5" → 7.5; vazio, null e 0 significam sem nota; arredonda a 2 casas. */
    private function normalize(mixed $raw): ?float
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        $text = is_string($raw) ? str_replace(',', '.', trim($raw)) : $raw;

        if (! is_numeric($text)) {
            throw ValidationException::withMessages(['entries' => 'Nota inválida.']);
        }

        $value = round((float) $text, 2);

        return $value == 0.0 ? null : $value;
    }

    private function positive(float $v): ?float
    {
        return $v > 0 ? $v : null;
    }
}
