<?php

use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Models\Student;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->school = makeSchool('Escola A');
    $this->secretary = makeMember($this->school, 'secretary');
    $this->year = makeYear($this->school);
    $this->api = fn () => asMember($this->secretary, $this->school);
});

function enroll(object $t, Student $student, $section, array $extra = []): TestResponse
{
    return ($t->api)()->postJson('/api/v1/enrollments', enrollmentPayload($student, $t->year, $section, $extra));
}

it('matricula com código, nº de ordem sequencial e estado pendente', function () {
    $section = makeSection($this->school, $this->year, 1);

    $a = enroll($this, makeStudent($this->school), $section)->assertCreated();
    $b = enroll($this, makeStudent($this->school), $section)->assertCreated();

    $a->assertJsonPath('data.status', 'pending')->assertJsonPath('data.roll_number', 1)->assertJsonPath('data.enrollment_code', 'M2026-000001');
    $b->assertJsonPath('data.roll_number', 2)->assertJsonPath('data.enrollment_code', 'M2026-000002');
});

it('recusa matrícula duplicada no mesmo ano', function () {
    $section = makeSection($this->school, $this->year, 1);
    $student = makeStudent($this->school);

    enroll($this, $student, $section)->assertCreated();
    enroll($this, $student, makeSection($this->school, $this->year, 1, 'B'))->assertStatus(409);
});

it('valida a idade mínima da classe e avisa na idade máxima', function () {
    $first = makeSection($this->school, $this->year, 1);

    enroll($this, makeStudent($this->school, age: 5), $first)->assertUnprocessable()->assertJsonValidationErrors('student_id');
    enroll($this, makeStudent($this->school, age: 6), $first)->assertCreated()->assertJsonPath('warnings', []);

    $old = enroll($this, makeStudent($this->school, age: 12), $first)->assertCreated();
    expect($old->json('warnings.0'))->toContain('idade máxima');
});

it('aceita aluno sem data de nascimento', function () {
    enroll($this, makeStudent($this->school, ['birth_date' => null]), makeSection($this->school, $this->year, 3))->assertCreated();
});

it('matricula na Iniciação aos 5 anos', function () {
    enroll($this, makeStudent($this->school, age: 5), makeSection($this->school, $this->year, 0))->assertCreated();
});

it('recusa matrícula quando a turma está cheia', function () {
    $section = makeSection($this->school, $this->year, 1, capacity: 2);

    enroll($this, makeStudent($this->school), $section)->assertCreated();
    enroll($this, makeStudent($this->school), $section)->assertCreated();
    enroll($this, makeStudent($this->school), $section)->assertStatus(409);
});

it('matrículas canceladas libertam a vaga', function () {
    $section = makeSection($this->school, $this->year, 1, capacity: 1);
    $id = enroll($this, makeStudent($this->school), $section)->json('data.id');

    ($this->api)()->postJson("/api/v1/enrollments/{$id}/decision", ['decision' => 'cancel'])->assertOk();
    enroll($this, makeStudent($this->school), $section)->assertCreated();
});

it('exige que a turma seja do mesmo ano e classe', function () {
    $section = makeSection($this->school, $this->year, 2);

    ($this->api)()->postJson('/api/v1/enrollments', [...enrollmentPayload(makeStudent($this->school), $this->year, $section), 'grade_level_id' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('section_id');
});

it('recusa matrículas em ano lectivo fechado', function () {
    $closed = makeYear($this->school, '2024/2025', '2024-09-01', '2025-07-31', 'closed');
    $section = makeSection($this->school, $closed, 1);

    ($this->api)()->postJson('/api/v1/enrollments', enrollmentPayload(makeStudent($this->school), $closed, $section))->assertUnprocessable();
});

it('guarda o idioma da Língua Estrangeira só nas classes onde existe', function () {
    $fifth = makeSection($this->school, $this->year, 5);
    $first = makeSection($this->school, $this->year, 1);

    enroll($this, makeStudent($this->school, age: 10), $fifth, ['foreign_language' => 'french'])->assertCreated();
    expect(DB::table('enrollment_subject_choices')->where('choice_code', 'french')->count())->toBe(1);

    enroll($this, makeStudent($this->school), $first, ['foreign_language' => 'english'])->assertUnprocessable()->assertJsonValidationErrors('foreign_language');
    expect(Enrollment::withoutGlobalScopes()->count())->toBe(1); // a matrícula recusada não deixa rasto
});

it('aprova e conclui seguindo as transições permitidas', function () {
    $id = enroll($this, makeStudent($this->school), makeSection($this->school, $this->year, 1))->json('data.id');
    $decide = fn (string $d) => ($this->api)()->postJson("/api/v1/enrollments/{$id}/decision", ['decision' => $d]);

    $decide('complete')->assertStatus(409);
    $decide('approve')->assertOk()->assertJsonPath('data.status', 'active');
    $decide('approve')->assertStatus(409);
    $decide('complete')->assertOk()->assertJsonPath('data.status', 'completed');
    $decide('cancel')->assertStatus(409);
});

it('regista a transferência de saída com guia e actualiza o aluno', function () {
    $student = makeStudent($this->school);
    $id = enroll($this, $student, makeSection($this->school, $this->year, 1))->json('data.id');
    ($this->api)()->postJson("/api/v1/enrollments/{$id}/decision", ['decision' => 'approve']);

    ($this->api)()->postJson("/api/v1/enrollments/{$id}/transfer", ['other_school_name' => 'Escola 9', 'guide_number' => 'G-55', 'transfer_date' => '2026-11-01'])
        ->assertOk()->assertJsonPath('data.status', 'transferred');

    expect(DB::table('student_transfers')->where(['direction' => 'out', 'guide_number' => 'G-55'])->exists())->toBeTrue()
        ->and(DB::table('students')->where('id', $student->id)->value('status'))->toBe('transferred');
});

it('regista a transferência de entrada com a escola de origem', function () {
    enroll($this, makeStudent($this->school), makeSection($this->school, $this->year, 1), ['enrollment_type' => 'transfer_in', 'origin_school' => 'Escola 3'])->assertCreated();

    expect(DB::table('student_transfers')->where(['direction' => 'in', 'other_school_name' => 'Escola 3'])->exists())->toBeTrue();
});

it('renumera a turma por ordem alfabética', function () {
    $section = makeSection($this->school, $this->year, 1);
    $zeca = enroll($this, makeStudent($this->school, ['first_name' => 'Zeca']), $section)->json('data.id');
    $ana = enroll($this, makeStudent($this->school, ['first_name' => 'Ana']), $section)->json('data.id');
    $cancelled = enroll($this, makeStudent($this->school, ['first_name' => 'Bia']), $section)->json('data.id');
    ($this->api)()->postJson("/api/v1/enrollments/{$cancelled}/decision", ['decision' => 'cancel']);

    ($this->api)()->postJson("/api/v1/sections/{$section->id}/renumber")->assertOk();

    expect(DB::table('enrollments')->where('id', $ana)->value('roll_number'))->toBe(1)
        ->and(DB::table('enrollments')->where('id', $zeca)->value('roll_number'))->toBe(2)
        ->and(DB::table('enrollments')->where('id', $cancelled)->value('roll_number'))->toBeNull();
});

it('só a secretaria matricula; o professor só consulta', function () {
    $teacher = makeMember($this->school, 'teacher');
    $section = makeSection($this->school, $this->year, 1);
    $student = makeStudent($this->school);

    asMember($teacher, $this->school)->postJson('/api/v1/enrollments', enrollmentPayload($student, $this->year, $section))->assertForbidden();
    asMember($teacher, $this->school)->getJson('/api/v1/enrollments')->assertOk();
});

it('isola matrículas e turmas entre instituições', function () {
    $other = makeSchool('Escola B');
    $otherYear = makeYear($other);
    $otherSection = makeSection($other, $otherYear, 1);
    $otherStudent = makeStudent($other);
    $mine = makeSection($this->school, $this->year, 1);

    // aluno, turma e ano de outra instituição não são aceites como referência
    ($this->api)()->postJson('/api/v1/enrollments', enrollmentPayload($otherStudent, $this->year, $mine))->assertUnprocessable()->assertJsonValidationErrors('student_id');
    ($this->api)()->postJson('/api/v1/enrollments', enrollmentPayload(makeStudent($this->school), $this->year, $otherSection))->assertUnprocessable();
    ($this->api)()->postJson('/api/v1/enrollments', enrollmentPayload(makeStudent($this->school), $otherYear, $mine))->assertUnprocessable();

    $foreign = asMember(makeMember($other, 'secretary'), $other)->postJson('/api/v1/enrollments', enrollmentPayload($otherStudent, $otherYear, $otherSection))->assertCreated()->json('data.id');
    ($this->api)()->postJson("/api/v1/enrollments/{$foreign}/decision", ['decision' => 'approve'])->assertNotFound();
    ($this->api)()->getJson('/api/v1/enrollments')->assertJsonCount(0, 'data');
    ($this->api)()->getJson('/api/v1/sections')->assertJsonCount(1, 'data');
});

describe('renovação em massa', function () {
    function closedYearWith(object $t, int $grade, string $outcome, array $studentAttrs = [], int $age = 10): Enrollment
    {
        static $section = [];
        $key = $grade;
        $section[$key] ??= null;
        $section = makeSection($t->school, $t->year, $grade, 'R'.uniqid());
        $student = makeStudent($t->school, $studentAttrs, $age);
        $e = inTenant($t->school, fn () => Enrollment::create([
            'student_id' => $student->id, 'school_year_id' => $t->year->id, 'grade_level_id' => $grade, 'section_id' => $section->id,
            'enrollment_code' => 'X'.uniqid(), 'status' => 'completed',
        ]));
        DB::table('enrollment_results')->insert(['institution_id' => $t->school->id, 'enrollment_id' => $e->id, 'status' => $outcome]);

        return $e;
    }

    it('promove aprovados, repete reprovados e salta recursos e pendentes', function () {
        $next = makeYear($this->school, '2027/2028', '2027-09-01', '2028-07-31', 'planned');
        $promoted = closedYearWith($this, 4, 'promoted');
        $failed = closedYearWith($this, 4, 'failed');
        $resit = closedYearWith($this, 4, 'resit');
        $pending = closedYearWith($this, 4, 'pending');

        // as quatro matrículas estão em turmas de origem diferentes: junta-as numa só para o mapa
        $from = makeSection($this->school, $this->year, 4, 'ORIGEM');
        Enrollment::withoutGlobalScopes()->whereIn('id', [$promoted->id, $failed->id, $resit->id, $pending->id])->update(['section_id' => $from->id]);

        $fifth = makeSection($this->school, $next, 5);
        $fourth = makeSection($this->school, $next, 4);

        $payload = fn ($to) => ['from_school_year_id' => $this->year->id, 'to_school_year_id' => $next->id, 'section_map' => [['from_section_id' => $from->id, 'to_section_id' => $to->id]]];

        // destino da 5.ª: só o aprovado passa; os restantes não pertencem a essa classe
        $r = ($this->api)()->postJson('/api/v1/enrollments/bulk-renew', $payload($fifth))->assertOk()->json();
        expect($r['created'])->toBe(1)
            ->and(collect($r['skipped'])->pluck('enrollment_id')->sort()->values()->all())->toBe(collect([$failed->id, $resit->id, $pending->id])->sort()->values()->all());

        $new = Enrollment::withoutGlobalScopes()->where('student_id', $promoted->student_id)->where('school_year_id', $next->id)->first();
        expect($new->enrollment_type)->toBe('renewal')->and($new->previous_enrollment_id)->toBe($promoted->id)->and($new->is_repeater)->toBeFalse();

        // destino da 4.ª: o reprovado repete
        $r = ($this->api)()->postJson('/api/v1/enrollments/bulk-renew', $payload($fourth))->json();
        expect($r['created'])->toBe(1);
        $repeat = Enrollment::withoutGlobalScopes()->where('student_id', $failed->student_id)->where('school_year_id', $next->id)->first();
        expect($repeat->grade_level_id)->toBe(4)->and($repeat->is_repeater)->toBeTrue();
    });

    it('nunca substitui uma matrícula existente no ano de destino', function () {
        $next = makeYear($this->school, '2027/2028', '2027-09-01', '2028-07-31', 'planned');
        $e = closedYearWith($this, 2, 'promoted');
        $target = makeSection($this->school, $next, 3);
        $existing = inTenant($this->school, fn () => Enrollment::create([
            'student_id' => $e->student_id, 'school_year_id' => $next->id, 'grade_level_id' => 3, 'section_id' => $target->id,
            'enrollment_code' => 'PREVIO', 'status' => 'active',
        ]));

        $r = ($this->api)()->postJson('/api/v1/enrollments/bulk-renew', [
            'from_school_year_id' => $this->year->id, 'to_school_year_id' => $next->id,
            'section_map' => [['from_section_id' => $e->section_id, 'to_section_id' => $target->id]],
        ])->assertOk()->json();

        expect($r['created'])->toBe(0)->and($r['skipped'])->toHaveCount(1)
            ->and(Enrollment::withoutGlobalScopes()->find($existing->id)->enrollment_code)->toBe('PREVIO');
    });

    it('valida a idade e dá o 1.º ciclo por concluído na 9.ª', function () {
        $next = makeYear($this->school, '2027/2028', '2027-09-01', '2028-07-31', 'planned');
        $ninth = closedYearWith($this, 9, 'promoted', age: 15);
        $r = ($this->api)()->postJson('/api/v1/enrollments/bulk-renew', [
            'from_school_year_id' => $this->year->id, 'to_school_year_id' => $next->id,
            'section_map' => [['from_section_id' => $ninth->section_id, 'to_section_id' => makeSection($this->school, $next, 9)->id]],
        ])->assertOk()->json();

        expect($r['graduated'])->toBe(1)->and($r['created'])->toBe(0)
            ->and(DB::table('students')->where('id', $ninth->student_id)->value('status'))->toBe('graduated');
    });

    it('exige turmas dos anos indicados', function () {
        $next = makeYear($this->school, '2027/2028', '2027-09-01', '2028-07-31', 'planned');
        $e = closedYearWith($this, 1, 'promoted');

        ($this->api)()->postJson('/api/v1/enrollments/bulk-renew', [
            'from_school_year_id' => $next->id, 'to_school_year_id' => $this->year->id,
            'section_map' => [['from_section_id' => $e->section_id, 'to_section_id' => $e->section_id]],
        ])->assertUnprocessable();
    });
});
