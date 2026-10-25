<?php

use App\Models\User;
use App\Modules\Platform\Models\Institution;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Models\SchoolYear;
use App\Modules\Schools\Models\Section;
use App\Modules\Schools\Models\Student;
use App\Modules\Schools\Models\Subject;
use App\Modules\Schools\Services\SchoolProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Escola aprovada e activa, com perfil, currículo e regras provisionados. */
function makeSchool(string $name = 'Escola Teste', array $profile = [], string $regime = 'public'): Institution
{
    $institution = Institution::create([
        'name' => $name,
        'slug' => str($name)->slug().'-'.uniqid(),
        'institution_type' => 'school',
        'approval_status' => 'approved',
        'operational_status' => 'active',
    ]);

    app(CurrentTenant::class)->runAs($institution->id, fn () => app(SchoolProvisioner::class)->provision(
        $institution,
        ['director_name' => 'Director Teste', 'director_gender' => 'M', 'regime' => $regime, ...$profile],
    ));

    return $institution;
}

/** Utilizador com um papel numa instituição. */
function makeMember(Institution $institution, string $role = 'director'): User
{
    $user = User::factory()->create();
    DB::table('institution_user')->insert([
        'institution_id' => $institution->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active',
    ]);

    return $user;
}

/** Pedido HTTP autenticado como `$user` na instituição indicada. */
function asMember(User $user, Institution $institution): TestCase
{
    return loginAs($user)->withHeader('X-Institution-Id', (string) $institution->id);
}

/** Autentica `$user` limpando o utilizador em cache nos guards (vários utilizadores no mesmo teste). */
function loginAs(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->actingAs($user, 'web');
}

/** Executa um bloco no contexto de uma instituição (preparação de dados nos testes). */
function inTenant(Institution $institution, callable $callback): mixed
{
    return app(CurrentTenant::class)->runAs($institution->id, $callback);
}

function makeYear(Institution $institution, string $name = '2026/2027', string $start = '2026-09-01', string $end = '2027-07-31', string $status = 'active'): SchoolYear
{
    return inTenant($institution, fn () => SchoolYear::create(['name' => $name, 'starts_on' => $start, 'ends_on' => $end, 'status' => $status]));
}

function makeSection(Institution $institution, SchoolYear $year, int $grade, string $name = 'A', int $capacity = 40): Section
{
    return inTenant($institution, fn () => Section::create([
        'school_year_id' => $year->id, 'grade_level_id' => $grade, 'name' => $name, 'shift' => 'morning', 'capacity' => $capacity,
    ]));
}

/** Aluno com a idade indicada (em anos) à data de hoje. */
function makeStudent(Institution $institution, array $attributes = [], int $age = 8): Student
{
    static $n = 0;
    $n++;

    return inTenant($institution, fn () => Student::create([
        'student_number' => sprintf('T%05d', $n),
        'first_name' => 'Aluno'.$n,
        'last_name' => 'Teste',
        'gender' => 'M',
        'birth_date' => now()->subYears($age)->subMonth()->toDateString(),
        ...$attributes,
    ]));
}

/** Corpo de um pedido de matrícula. */
function enrollmentPayload(Student $student, SchoolYear $year, Section $section, array $extra = []): array
{
    return [
        'student_id' => $student->id,
        'school_year_id' => $year->id,
        'grade_level_id' => $section->grade_level_id,
        'section_id' => $section->id,
        ...$extra,
    ];
}

/** Matrícula confirmada (activa) criada directamente, sem passar pela API. */
function makeActiveEnrollment(Institution $institution, SchoolYear $year, Section $section, Student $student, string $status = 'active'): Enrollment
{
    static $n = 0;
    $n++;

    return inTenant($institution, fn () => Enrollment::create([
        'student_id' => $student->id,
        'school_year_id' => $year->id,
        'grade_level_id' => $section->grade_level_id,
        'section_id' => $section->id,
        'enrollment_code' => 'E'.str_pad((string) $n, 6, '0', STR_PAD_LEFT).uniqid(),
        'roll_number' => (int) Enrollment::query()->where('section_id', $section->id)->max('roll_number') + 1,
        'status' => $status,
    ]));
}

function subjectId(Institution $institution, string $code): int
{
    return inTenant($institution, fn () => Subject::where('code', $code)->value('id'));
}
