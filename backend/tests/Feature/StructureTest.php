<?php

use App\Modules\Schools\Models\SchoolYear;

beforeEach(function () {
    $this->school = makeSchool('Escola A');
    $this->director = makeMember($this->school);
    $this->api = fn () => asMember($this->director, $this->school);
});

it('lista as classes 0 a 9 com escala e regime', function () {
    $levels = ($this->api)()->getJson('/api/v1/grade-levels')->assertOk()->json('data');

    expect($levels)->toHaveCount(10)
        ->and($levels[0])->toMatchArray(['grade_number' => 0, 'stage' => 'preschool', 'max_score' => null, 'assessment_regime' => 'none'])
        ->and($levels[6])->toMatchArray(['grade_number' => 6, 'max_score' => 10, 'pass_mark' => 5, 'has_national_exam' => true, 'assessment_regime' => 'strict'])
        ->and($levels[8])->toMatchArray(['grade_number' => 8, 'max_score' => 20, 'pass_mark' => 10, 'has_national_exam' => false, 'assessment_regime' => 'lenient'])
        ->and($levels[9])->toMatchArray(['grade_number' => 9, 'has_national_exam' => true, 'assessment_regime' => 'strict']);
});

it('cria anos lectivos no formato AAAA/AAAA com anos consecutivos', function () {
    ($this->api)()->postJson('/api/v1/school-years', ['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31'])->assertCreated();
    ($this->api)()->postJson('/api/v1/school-years', ['name' => '2026-2027', 'starts_on' => '2028-09-01', 'ends_on' => '2029-07-31'])->assertUnprocessable();
    ($this->api)()->postJson('/api/v1/school-years', ['name' => '2026/2028', 'starts_on' => '2028-09-01', 'ends_on' => '2029-07-31'])->assertUnprocessable();
    ($this->api)()->postJson('/api/v1/school-years', ['name' => '2030/2031', 'starts_on' => '2031-01-01', 'ends_on' => '2030-01-01'])->assertUnprocessable();
});

it('recusa anos lectivos com datas sobrepostas ou nome repetido', function () {
    ($this->api)()->postJson('/api/v1/school-years', ['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31'])->assertCreated();

    ($this->api)()->postJson('/api/v1/school-years', ['name' => '2027/2028', 'starts_on' => '2027-07-01', 'ends_on' => '2028-07-31'])->assertStatus(409);
    ($this->api)()->postJson('/api/v1/school-years', ['name' => '2026/2027', 'starts_on' => '2028-09-01', 'ends_on' => '2029-07-31'])->assertStatus(409);
});

it('activa um ano de cada vez e fecha o anterior', function () {
    $a = makeYear($this->school, '2025/2026', '2025-09-01', '2026-07-31', 'active');
    $b = makeYear($this->school, '2026/2027', '2026-09-01', '2027-07-31', 'planned');

    ($this->api)()->postJson("/api/v1/school-years/{$b->id}/activate")->assertOk()->assertJsonPath('data.status', 'active');

    inTenant($this->school, function () use ($a, $b) {
        expect(SchoolYear::find($a->id)->status)->toBe('closed')
            ->and(SchoolYear::where('status', 'active')->pluck('id')->all())->toBe([$b->id]);
    });

    ($this->api)()->postJson("/api/v1/school-years/{$a->id}/activate")->assertStatus(409);
});

it('cria turmas únicas por ano, classe e nome', function () {
    $year = makeYear($this->school);
    $payload = ['school_year_id' => $year->id, 'grade_level_id' => 1, 'name' => 'A', 'shift' => 'morning'];

    ($this->api)()->postJson('/api/v1/sections', $payload)->assertCreated()->assertJsonPath('data.capacity', 40);
    ($this->api)()->postJson('/api/v1/sections', $payload)->assertStatus(409);
    ($this->api)()->postJson('/api/v1/sections', [...$payload, 'grade_level_id' => 2])->assertCreated();
    ($this->api)()->postJson('/api/v1/sections', [...$payload, 'name' => 'B', 'shift' => 'evening'])->assertUnprocessable();
});

it('recusa turmas em anos fechados e a capacidade acima da sala', function () {
    $closed = makeYear($this->school, '2024/2025', '2024-09-01', '2025-07-31', 'closed');
    ($this->api)()->postJson('/api/v1/sections', ['school_year_id' => $closed->id, 'grade_level_id' => 1, 'name' => 'A', 'shift' => 'morning'])->assertUnprocessable();

    $year = makeYear($this->school);
    $room = ($this->api)()->postJson('/api/v1/rooms', ['name' => 'Sala 1', 'capacity' => 30])->assertCreated()->json('data.id');
    ($this->api)()->postJson('/api/v1/sections', ['school_year_id' => $year->id, 'grade_level_id' => 1, 'name' => 'A', 'shift' => 'morning', 'room_id' => $room, 'capacity' => 35])
        ->assertUnprocessable()->assertJsonValidationErrors('capacity');
    ($this->api)()->postJson('/api/v1/sections', ['school_year_id' => $year->id, 'grade_level_id' => 1, 'name' => 'A', 'shift' => 'morning', 'room_id' => $room, 'capacity' => 30])->assertCreated();
});

it('o currículo provisionado corresponde ao oficial e é ajustável', function () {
    $first = ($this->api)()->getJson('/api/v1/curriculum?grade_level_id=1')->assertOk()->json('data.1');
    $codes = collect($first)->pluck('subject.code')->all();
    expect($codes)->toBe(['LP', 'MAT', 'EM', 'EMC', 'EMP', 'MUS', 'EF']);

    $subjects = collect(($this->api)()->getJson('/api/v1/subjects')->json('data'))->pluck('id', 'code');
    ($this->api)()->putJson('/api/v1/curriculum/1/subjects', ['subjects' => [
        ['subject_id' => $subjects['LP'], 'weekly_hours' => 6],
        ['subject_id' => $subjects['MAT'], 'weekly_hours' => 5],
        ['subject_id' => $subjects['EM'], 'is_active' => false, 'weekly_hours' => 3],
    ]])->assertOk();

    $rows = collect(($this->api)()->getJson('/api/v1/curriculum?grade_level_id=1')->json('data.1'));
    expect($rows->firstWhere('subject.code', 'LP')['weekly_hours'])->toBe(6)
        ->and($rows->firstWhere('subject.code', 'EM')['is_active'])->toBeFalse();
});

it('guarda regras de avaliação versionadas, com a anterior arquivada', function () {
    ($this->api)()->getJson('/api/v1/assessment-rules')->assertOk()->assertJsonPath('data.weights.exam', 0.6);

    ($this->api)()->putJson('/api/v1/assessment-rules', ['mt_divisor_mode' => 'fixed3', 'weights' => ['year' => 0.5, 'exam' => 0.5]])
        ->assertCreated()->assertJsonPath('version', 2);

    ($this->api)()->getJson('/api/v1/assessment-rules')->assertJsonPath('data.mt_divisor_mode', 'fixed3');
    expect(DB::table('assessment_rules')->where('institution_id', $this->school->id)->count())->toBe(2)
        ->and(DB::table('assessment_rules')->where('institution_id', $this->school->id)->where('is_active', true)->count())->toBe(1);
});

it('só a direcção altera as regras de avaliação', function () {
    $secretary = makeMember($this->school, 'secretary');

    asMember($secretary, $this->school)->putJson('/api/v1/assessment-rules', ['mt_divisor_mode' => 'fixed3'])->assertForbidden();
});

it('a escola privada indica a certificadora com dados completos ou uma escola pública', function () {
    $api = ($this->api);
    $api()->putJson('/api/v1/school-links', ['level_group' => 'primary'])->assertUnprocessable();

    $api()->putJson('/api/v1/school-links', [
        'level_group' => 'primary', 'certifying_school_name' => 'Escola Pública 7',
        'certifying_director_name' => 'Ana Lopes', 'certifying_director_gender' => 'F',
    ])->assertOk();

    $public = makeSchool('Pública', ['school_code' => '777']);
    $api()->putJson('/api/v1/school-links', ['level_group' => 'lower_secondary', 'public_institution_id' => $public->id])->assertOk();
    $api()->getJson('/api/v1/school-links')->assertJsonCount(2, 'data');

    $private = makeSchool('Privada', [], 'private');
    $api()->putJson('/api/v1/school-links', ['level_group' => 'primary', 'public_institution_id' => $private->id])->assertUnprocessable();
});
