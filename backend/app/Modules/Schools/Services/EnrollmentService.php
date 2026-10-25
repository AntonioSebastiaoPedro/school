<?php

namespace App\Modules\Schools\Services;

use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Models\GradeLevel;
use App\Modules\Schools\Models\SchoolYear;
use App\Modules\Schools\Models\Section;
use App\Modules\Schools\Models\Student;
use App\Modules\Schools\Models\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EnrollmentService
{
    /** Estados que ocupam vaga na turma. */
    public const OCCUPYING = ['pending', 'approved', 'active'];

    private const TRANSITIONS = [
        'approve' => ['from' => ['pending', 'approved'], 'to' => 'active'],
        'cancel' => ['from' => ['pending', 'approved', 'active'], 'to' => 'cancelled'],
        'complete' => ['from' => ['active'], 'to' => 'completed'],
        'transfer' => ['from' => ['active'], 'to' => 'transferred'],
    ];

    public function __construct(private readonly CurrentTenant $tenant) {}

    /**
     * Matricula o aluno. Valida ano, turma (mesmo ano e classe), duplicação, idade mínima e
     * capacidade. A idade máxima só gera aviso. O nº de ordem é o seguinte da turma.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Enrollment, 1: list<string>}
     */
    public function enroll(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $institutionId = $this->tenant->id();

            // Serializa matrículas da instituição: capacidade, nº de ordem e código sem corridas.
            DB::select('select pg_advisory_xact_lock(?, 2)', [$institutionId]);

            $student = Student::query()->findOrFail($data['student_id']);
            $year = SchoolYear::query()->findOrFail($data['school_year_id']);
            $section = Section::query()->findOrFail($data['section_id']);
            $level = GradeLevel::query()->findOrFail($data['grade_level_id']);

            if ($year->status === 'closed') {
                throw ValidationException::withMessages(['school_year_id' => 'O ano lectivo está fechado.']);
            }

            if ($section->school_year_id !== $year->id || $section->grade_level_id !== $level->id) {
                throw ValidationException::withMessages(['section_id' => 'A turma não pertence a este ano lectivo e classe.']);
            }

            if (Enrollment::query()->where('student_id', $student->id)->where('school_year_id', $year->id)->exists()) {
                throw new ConflictException('O aluno já tem matrícula neste ano lectivo.');
            }

            $warnings = $this->checkAge($student, $level, $year);

            $occupied = Enrollment::query()->where('section_id', $section->id)->whereIn('status', self::OCCUPYING)->count();
            if ($occupied >= $section->capacity) {
                throw new ConflictException("A turma {$section->name} já atingiu a capacidade ({$section->capacity}).");
            }

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'school_year_id' => $year->id,
                'grade_level_id' => $level->id,
                'section_id' => $section->id,
                'enrollment_code' => $this->nextCode($institutionId, $year),
                'roll_number' => (int) Enrollment::query()->where('section_id', $section->id)->max('roll_number') + 1,
                'enrollment_type' => $data['enrollment_type'] ?? 'new',
                'previous_enrollment_id' => $data['previous_enrollment_id'] ?? null,
                'origin_school' => $data['origin_school'] ?? null,
                'is_repeater' => $data['is_repeater'] ?? false,
                'status' => 'pending',
                'enrolled_at' => now()->toDateString(),
            ]);

            if (($data['enrollment_type'] ?? 'new') === 'transfer_in') {
                DB::table('student_transfers')->insert([
                    'institution_id' => $institutionId,
                    'student_id' => $student->id,
                    'enrollment_id' => $enrollment->id,
                    'direction' => 'in',
                    'other_school_name' => $data['origin_school'] ?? 'Não indicada',
                    'transfer_date' => now()->toDateString(),
                    'recorded_by' => request()->user()?->id,
                ]);
            }

            if (! empty($data['foreign_language'])) {
                $this->chooseForeignLanguage($enrollment, $level, $data['foreign_language']);
            }

            return [$enrollment->fresh(), $warnings];
        });
    }

    public function decide(Enrollment $enrollment, string $decision): Enrollment
    {
        $rule = self::TRANSITIONS[$decision] ?? throw new RuntimeException("Decisão desconhecida: {$decision}");

        if (! in_array($enrollment->status, $rule['from'], true)) {
            throw new ConflictException("Não é possível aplicar «{$decision}» a uma matrícula {$enrollment->status}.");
        }

        $enrollment->forceFill([
            'status' => $rule['to'],
            ...($decision === 'approve' ? ['approved_by' => request()->user()?->id, 'approved_at' => now()] : []),
        ])->save();

        return $enrollment;
    }

    /** Transferência para outra escola: regista a guia e retira o aluno da turma. */
    public function transferOut(Enrollment $enrollment, array $data): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $data) {
            $this->decide($enrollment, 'transfer');

            DB::table('student_transfers')->insert([
                'institution_id' => $enrollment->institution_id,
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'direction' => 'out',
                'other_school_name' => $data['other_school_name'],
                'guide_number' => $data['guide_number'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'reason' => $data['reason'] ?? null,
                'recorded_by' => request()->user()?->id,
            ]);

            Student::query()->whereKey($enrollment->student_id)->update(['status' => 'transferred']);

            return $enrollment->fresh();
        });
    }

    /** Nº de ordem por ordem alfabética do nome (apelido, nome). */
    public function renumber(Section $section): void
    {
        DB::transaction(function () use ($section) {
            DB::select('select pg_advisory_xact_lock(?, 2)', [$section->institution_id]);

            $ids = Enrollment::query()
                ->join('students', fn ($j) => $j->on('students.id', '=', 'enrollments.student_id')->on('students.institution_id', '=', 'enrollments.institution_id'))
                ->where('enrollments.section_id', $section->id)
                ->whereIn('enrollments.status', [...self::OCCUPYING, 'completed'])
                ->orderByRaw('lower(students.first_name), lower(students.last_name)')
                ->pluck('enrollments.id');

            // Liberta todos os números antes de os reatribuir (índice único por turma).
            Enrollment::query()->where('section_id', $section->id)->update(['roll_number' => null]);

            foreach ($ids as $i => $id) {
                Enrollment::query()->whereKey($id)->update(['roll_number' => $i + 1]);
            }
        });
    }

    /** @return list<string> avisos (idade máxima ultrapassada) */
    private function checkAge(Student $student, GradeLevel $level, SchoolYear $year): array
    {
        if ($student->birth_date === null) {
            return [];
        }

        // Idade na data de referência: hoje, ou o início do ano lectivo se este ainda não começou.
        $asOf = CarbonImmutable::parse(max(now()->toDateString(), $year->starts_on->toDateString()));
        $age = (int) floor($student->birth_date->diffInYears($asOf));

        if ($age < $level->min_age) {
            throw ValidationException::withMessages([
                'student_id' => "O aluno deve ter no mínimo {$level->min_age} anos para a {$level->name}.",
            ]);
        }

        return $age > $level->max_age
            ? ["O aluno tem {$age} anos, acima da idade máxima habitual ({$level->max_age}) da {$level->name}."]
            : [];
    }

    private function chooseForeignLanguage(Enrollment $enrollment, GradeLevel $level, string $choice): void
    {
        $subject = Subject::query()->where('code', 'LE')->first();

        $offered = $subject !== null && DB::table('grade_level_subjects')
            ->where(['institution_id' => $enrollment->institution_id, 'grade_level_id' => $level->id, 'subject_id' => $subject->id, 'is_active' => true])
            ->exists();

        if (! $offered) {
            throw ValidationException::withMessages(['foreign_language' => 'A Língua Estrangeira não é leccionada nesta classe.']);
        }

        DB::table('enrollment_subject_choices')->insert([
            'institution_id' => $enrollment->institution_id,
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'choice_code' => $choice,
        ]);
    }

    private function nextCode(int $institutionId, SchoolYear $year): string
    {
        $n = Enrollment::query()->where('school_year_id', $year->id)->count() + 1;

        return sprintf('M%s-%06d', substr($year->name, 0, 4), $n);
    }
}
