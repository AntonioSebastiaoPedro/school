<?php

namespace App\Modules\Schools\Services;

use App\Modules\Schools\Assessment\Domain\YearOutcome;
use App\Modules\Schools\Assessment\OutcomeResolver;
use App\Modules\Schools\Models\GradeLevel;
use App\Modules\Schools\Models\GradeSheetLock;
use App\Modules\Schools\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Fecho do ano de uma turma: decide cada matrícula pelo mesmo OutcomeResolver da pauta final,
 * grava enrollment_results e historical_grades.
 *
 * Idempotente: quem já tem resultado definitivo (aprovado, reprovado, desistente) não é
 * alterado, salvo `recompute`. Quem ficou em recurso é reavaliado ao voltar a fechar, depois
 * de lançadas as notas de recurso.
 */
class YearClosingService
{
    public function __construct(
        private readonly OutcomeResolver $outcomes,
        private readonly GradeService $grades,
    ) {}

    /** @return list<array{enrollment_id:int, status:string, failed_subjects:int, reason:?string}> */
    public function close(Section $section, bool $recompute = false): array
    {
        $level = GradeLevel::findOrFail($section->grade_level_id);

        $this->assertSheetsLocked($section, $level);

        return DB::transaction(function () use ($section, $level, $recompute) {
            $institutionId = $section->institution_id;
            $roster = $this->grades->roster($section);
            $ids = $roster->pluck('id')->all();

            $finals = $level->hasGrades() ? $this->outcomes->finals($institutionId, $level->id, $ids) : [];
            $resitTakers = $level->hasGrades() ? $this->outcomes->resitTakers($institutionId, $ids) : [];
            $existing = DB::table('enrollment_results')->where('institution_id', $institutionId)->whereIn('enrollment_id', $ids)->get()->keyBy('enrollment_id');
            $subjects = DB::table('subjects')->where('institution_id', $institutionId)->pluck('id', 'code');

            $results = [];

            foreach ($roster as $enrollment) {
                $current = $existing[$enrollment->id] ?? null;

                if ($current !== null && ! $recompute && ! in_array($current->status, ['pending', 'resit'], true)) {
                    $results[] = $this->payload($enrollment->id, $current->status, $current->failed_subjects, $current->reason);

                    continue;
                }

                $enrollmentFinals = $finals[$enrollment->id] ?? [];

                // Iniciação: todos transitam, sem regras de notas.
                $outcome = $level->hasGrades()
                    ? $this->outcomes->decide($level, $enrollmentFinals, isset($resitTakers[$enrollment->id]))
                    : YearOutcome::Promoted;

                $failed = $level->hasGrades() ? $this->outcomes->negatives($level, $enrollmentFinals) : 0;
                $reason = $outcome === YearOutcome::Pending ? 'Sem notas lançadas' : null;

                DB::table('enrollment_results')->updateOrInsert(
                    ['institution_id' => $institutionId, 'enrollment_id' => $enrollment->id],
                    ['status' => $outcome->value, 'reason' => $reason, 'failed_subjects' => $failed, 'decided_at' => now()],
                );

                if (in_array($outcome, [YearOutcome::Promoted, YearOutcome::Failed, YearOutcome::Dropped], true)) {
                    $enrollment->forceFill(['status' => $outcome === YearOutcome::Dropped ? 'dropped' : 'completed'])->save();
                    $this->writeHistory($institutionId, $enrollment, $level, $enrollmentFinals, $subjects);
                }

                $results[] = $this->payload($enrollment->id, $outcome->value, $failed, $reason);
            }

            return $results;
        });
    }

    /** O fecho só é consistente se os trimestres estiverem fechados, para as notas não mudarem depois. */
    private function assertSheetsLocked(Section $section, GradeLevel $level): void
    {
        if (! $level->hasGrades()) {
            return;
        }

        $locked = GradeSheetLock::query()->where('section_id', $section->id)->pluck('term')->all();
        $open = array_values(array_diff([1, 2, 3], $locked));

        if ($open !== []) {
            throw new ConflictException('Feche as pautas dos trimestres '.implode(', ', $open).' antes de fechar o ano.');
        }
    }

    /** @param array<string, int> $finals */
    private function writeHistory(int $institutionId, $enrollment, GradeLevel $level, array $finals, $subjects): void
    {
        foreach ($finals as $code => $value) {
            DB::table('historical_grades')->updateOrInsert(
                ['institution_id' => $institutionId, 'student_id' => $enrollment->student_id, 'grade_level_id' => $level->id, 'subject_id' => $subjects[$code]],
                ['school_year_id' => $enrollment->school_year_id, 'final_value' => $value, 'source' => 'closing'],
            );
        }
    }

    private function payload(int $enrollmentId, string $status, int $failed, ?string $reason): array
    {
        return ['enrollment_id' => $enrollmentId, 'status' => $status, 'failed_subjects' => $failed, 'reason' => $reason];
    }
}
