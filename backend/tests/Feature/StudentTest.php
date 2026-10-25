<?php

use App\Modules\Schools\Models\Student;
use Database\Seeders\GeographySeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(GeographySeeder::class);
    $this->school = makeSchool('Escola A');
    $this->director = makeMember($this->school);
});

function newStudent(array $overrides = []): array
{
    return ['first_name' => 'Ana', 'last_name' => 'dos Santos', 'gender' => 'F', ...$overrides];
}

it('gera o nº de processo com 6 dígitos e ignora números importados com letras ou traços', function () {
    makeStudent($this->school, ['student_number' => '002-9B-040']);

    $first = asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent())->assertCreated()->json('data.student_number');
    $second = asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['first_name' => 'Rui']))->json('data.student_number');

    expect($first)->toBe('000001')->and($second)->toBe('000002');
});

it('continua a numeração a partir do maior número puramente numérico', function () {
    makeStudent($this->school, ['student_number' => '000010']);

    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent())
        ->assertCreated()->assertJsonPath('data.student_number', '000011');
});

it('guarda os nomes por extenso, sem forçar maiúsculas', function () {
    asMember($this->director, $this->school)
        ->postJson('/api/v1/students', newStudent(['first_name' => '  Maria   da  Conceição ', 'last_name' => 'dos Santos']))
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Maria da Conceição')
        ->assertJsonPath('data.last_name', 'dos Santos');
});

it('assume Angola como país de nascimento e nacionalidade', function () {
    $angola = DB::table('countries')->where('iso2', 'AO')->value('id');

    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent())
        ->assertCreated()
        ->assertJsonPath('data.birth_country_id', $angola)
        ->assertJsonPath('data.nationality_id', $angola);
});

it('exige género M ou F', function () {
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['gender' => 'X']))->assertUnprocessable()->assertJsonValidationErrors('gender');
    asMember($this->director, $this->school)->postJson('/api/v1/students', ['first_name' => 'A', 'last_name' => 'B'])->assertUnprocessable()->assertJsonValidationErrors('gender');
});

it('normaliza e valida o B.I. e preenche o emissor por omissão', function () {
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent([
        'document_type' => 'identity_card', 'document_number' => '006.151.112-la 041',
    ]))->assertCreated()
        ->assertJsonPath('data.document_number', '006151112LA041')
        ->assertJsonPath('data.document_issuer', 'Direcção Nacional de Identificação');

    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent([
        'document_type' => 'identity_card', 'document_number' => '123',
    ]))->assertUnprocessable()->assertJsonValidationErrors('document_number');
});

it('aceita aluno sem documento e recusa tipo sem número', function () {
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent())->assertCreated()->assertJsonPath('data.document_type', null);

    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['document_type' => 'passport']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');
});

it('recusa documento repetido na mesma escola, mas aceita-o noutra', function () {
    $doc = ['document_type' => 'personal_card', 'document_number' => 'C-123'];
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent($doc))->assertCreated();
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent($doc))->assertUnprocessable()->assertJsonValidationErrors('document_number');

    $other = makeSchool('Escola B');
    asMember(makeMember($other), $other)->postJson('/api/v1/students', newStudent($doc))->assertCreated();
});

it('exige data de nascimento anterior a hoje', function () {
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['birth_date' => now()->toDateString()]))
        ->assertUnprocessable()->assertJsonValidationErrors('birth_date');
});

it('valida a naturalidade: município da província, província do país', function () {
    $luanda = DB::table('provinces')->where('name', 'Luanda')->value('id');
    $benguela = DB::table('provinces')->where('name', 'Benguela')->value('id');
    $viana = DB::table('municipalities')->insertGetId(['province_id' => $luanda, 'name' => 'Viana']);

    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['province_id' => $luanda, 'municipality_id' => $viana]))->assertCreated();

    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['province_id' => $benguela, 'municipality_id' => $viana]))
        ->assertUnprocessable()->assertJsonValidationErrors('municipality_id');

    $portugal = DB::table('countries')->where('iso2', 'PT')->value('id');
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['birth_country_id' => $portugal, 'province_id' => $luanda]))
        ->assertUnprocessable()->assertJsonValidationErrors('province_id');
});

it('actualiza o aluno sem alterar o nº de processo', function () {
    $student = makeStudent($this->school, ['student_number' => '000007']);

    asMember($this->director, $this->school)->patchJson("/api/v1/students/{$student->id}", ['first_name' => 'Novo', 'student_number' => '999999'])
        ->assertOk()->assertJsonPath('data.first_name', 'Novo')->assertJsonPath('data.student_number', '000007');
});

it('mostra a filiação formatada num só sítio', function () {
    $student = makeStudent($this->school, ['gender' => 'F', 'father_name' => 'João Pedro', 'mother_name' => null]);

    asMember($this->director, $this->school)->getJson("/api/v1/students/{$student->id}")
        ->assertOk()->assertJsonPath('data.filiation', 'filha de João Pedro');
});

it('pesquisa por nome, nº de processo e documento', function () {
    makeStudent($this->school, ['first_name' => 'Zeferino', 'last_name' => 'Gonga', 'student_number' => '000555']);
    makeStudent($this->school, ['first_name' => 'Outra', 'last_name' => 'Pessoa']);

    asMember($this->director, $this->school)->getJson('/api/v1/students?search=zefer')->assertOk()->assertJsonCount(1, 'data');
    asMember($this->director, $this->school)->getJson('/api/v1/students?search=000555')->assertOk()->assertJsonCount(1, 'data');
});

it('só quem tem permissão cria alunos', function () {
    $teacher = makeMember($this->school, 'teacher');

    asMember($teacher, $this->school)->postJson('/api/v1/students', newStudent())->assertForbidden();
    asMember($teacher, $this->school)->getJson('/api/v1/students')->assertOk();
});

it('aplica o limite de alunos activos do plano', function () {
    $plan = DB::table('plans')->insertGetId(['name' => 'P', 'code' => 'p', 'price' => 1, 'billing_interval' => 'monthly']);
    DB::table('plan_features')->insert(['plan_id' => $plan, 'feature_key' => 'max_active_students', 'numeric_limit' => 1]);
    DB::table('subscriptions')->insert(['institution_id' => $this->school->id, 'plan_id' => $plan, 'status' => 'active']);

    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent())->assertCreated();
    asMember($this->director, $this->school)->postJson('/api/v1/students', newStudent(['first_name' => 'Outro']))
        ->assertUnprocessable()->assertJsonValidationErrors('max_active_students');
});

it('isola alunos entre instituições', function () {
    $other = makeSchool('Escola B');
    $foreign = makeStudent($other, ['first_name' => 'Alheio']);
    makeStudent($this->school, ['first_name' => 'Meu']);

    asMember($this->director, $this->school)->getJson("/api/v1/students/{$foreign->id}")->assertNotFound();
    asMember($this->director, $this->school)->patchJson("/api/v1/students/{$foreign->id}", ['first_name' => 'X'])->assertNotFound();
    asMember($this->director, $this->school)->getJson('/api/v1/students')->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Meu');
    expect(inTenant($other, fn () => Student::count()))->toBe(1);
});

it('associa encarregados: no máximo um pai e uma mãe, e filiação a partir deles', function () {
    $student = makeStudent($this->school, ['gender' => 'M']);
    $api = fn () => asMember($this->director, $this->school);

    $pai = $api()->postJson('/api/v1/guardians', ['first_name' => 'Carlos', 'last_name' => 'Neto'])->assertCreated()->json('data.id');
    $mae = $api()->postJson('/api/v1/guardians', ['first_name' => 'Rosa', 'last_name' => 'Neto'])->json('data.id');
    $outro = $api()->postJson('/api/v1/guardians', ['first_name' => 'Outro', 'last_name' => 'Pai'])->json('data.id');

    $api()->postJson("/api/v1/students/{$student->id}/guardians", ['guardian_id' => $pai, 'relationship' => 'father', 'is_primary' => true])->assertCreated();
    $api()->postJson("/api/v1/students/{$student->id}/guardians", ['guardian_id' => $mae, 'relationship' => 'mother'])->assertCreated();
    $api()->postJson("/api/v1/students/{$student->id}/guardians", ['guardian_id' => $outro, 'relationship' => 'father'])->assertStatus(409);
    $api()->postJson("/api/v1/students/{$student->id}/guardians", ['guardian_id' => $pai])->assertStatus(409);

    $api()->getJson("/api/v1/students/{$student->id}")->assertJsonPath('data.filiation', 'filho de Carlos Neto e de Rosa Neto');
});

it('não associa encarregados de outra instituição', function () {
    $other = makeSchool('Escola B');
    $foreignGuardian = asMember(makeMember($other), $other)->postJson('/api/v1/guardians', ['first_name' => 'X', 'last_name' => 'Y'])->json('data.id');
    $student = makeStudent($this->school);

    asMember($this->director, $this->school)->postJson("/api/v1/students/{$student->id}/guardians", ['guardian_id' => $foreignGuardian])
        ->assertUnprocessable()->assertJsonValidationErrors('guardian_id');
});
