<?php

namespace App\Modules\Schools\Services;

use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Models\Section;
use App\Modules\Schools\Models\Student;
use Illuminate\Validation\ValidationException;

/**
 * Renovação em massa. Aprovados passam à classe seguinte, reprovados repetem; quem está
 * em recurso aguarda. Nunca substitui uma matrícula existente: o aluno já matriculado no
 * ano de destino é saltado e comunicado.
 */
class RenewalService
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /**
     * @param  list<array{from_section_id:int,to_section_id:int}>  $sectionMap
     * @return array{created:int, graduated:int, skipped:list<array{enrollment_id:int, reason:string}>}
     */
    public function renew(int $fromYearId, int $toYearId, array $sectionMap): array
    {
        $created = 0;
        $graduated = 0;
        $skipped = [];

        foreach ($sectionMap as $pair) {
            $from = Section::query()->findOrFail($pair['from_section_id']);
            $to = Section::query()->findOrFail($pair['to_section_id']);

            if ($from->school_year_id !== $fromYearId || $to->school_year_id !== $toYearId) {
                throw ValidationException::withMessages(['section_map' => 'As turmas não correspondem aos anos lectivos indicados.']);
            }

            $rows = Enrollment::query()
                ->with(['student', 'result'])
                ->where('section_id', $from->id)
                ->whereIn('status', ['active', 'completed'])
                ->get()
                ->sortBy(fn ($e) => mb_strtolower($e->student->first_name.' '.$e->student->last_name));

            foreach ($rows as $enrollment) {
                $status = $enrollment->result?->status;

                $reason = match ($status) {
                    null, 'pending' => 'Sem resultado do ano: feche o ano da turma primeiro.',
                    'resit' => 'Em recurso: aguardar o resultado do exame de recurso.',
                    'dropped' => 'Desistente.',
                    default => null,
                };

                if ($reason !== null) {
                    $skipped[] = ['enrollment_id' => $enrollment->id, 'reason' => $reason];

                    continue;
                }

                if ($status === 'promoted' && $enrollment->grade_level_id === 9) {
                    Student::query()->whereKey($enrollment->student_id)->update(['status' => 'graduated']);
                    $graduated++;

                    continue;
                }

                $target = $status === 'promoted' ? $enrollment->grade_level_id + 1 : $enrollment->grade_level_id;

                if ($to->grade_level_id !== $target) {
                    $skipped[] = ['enrollment_id' => $enrollment->id, 'reason' => "A turma de destino não é da classe esperada ({$target})."];

                    continue;
                }

                try {
                    $this->enrollments->enroll([
                        'student_id' => $enrollment->student_id,
                        'school_year_id' => $toYearId,
                        'grade_level_id' => $target,
                        'section_id' => $to->id,
                        'enrollment_type' => 'renewal',
                        'previous_enrollment_id' => $enrollment->id,
                        'is_repeater' => $status === 'failed',
                    ]);
                    $created++;
                } catch (ConflictException $e) {
                    $skipped[] = ['enrollment_id' => $enrollment->id, 'reason' => $e->getMessage()];
                } catch (ValidationException $e) {
                    $skipped[] = ['enrollment_id' => $enrollment->id, 'reason' => collect($e->errors())->flatten()->first()];
                }
            }
        }

        return ['created' => $created, 'graduated' => $graduated, 'skipped' => $skipped];
    }
}
