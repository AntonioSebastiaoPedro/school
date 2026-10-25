<?php

use App\Models\User;
use App\Modules\Platform\Models\Institution;
use App\Modules\Schools\Models\Student;

it('recusa pedidos sem utilizador autenticado', function () {
    $this->getJson('/api/v1/school-profile')->assertUnauthorized();
});

it('devolve o perfil da instituição à qual o utilizador pertence', function () {
    $school = makeSchool('Escola A');
    $user = makeMember($school);

    asMember($user, $school)->getJson('/api/v1/school-profile')
        ->assertOk()
        ->assertJsonPath('data.director_name', 'Director Teste');
});

it('recusa uma instituição à qual o utilizador não pertence, mesmo com o id no cabeçalho', function () {
    $a = makeSchool('Escola A');
    $b = makeSchool('Escola B', ['director_name' => 'Director B']);
    $userA = makeMember($a);

    asMember($userA, $b)->getJson('/api/v1/school-profile')->assertForbidden();
});

it('exige X-Institution-Id quando o utilizador pertence a várias instituições', function () {
    $a = makeSchool('Escola A');
    $b = makeSchool('Escola B');
    $user = makeMember($a);
    DB::table('institution_user')->insert(['institution_id' => $b->id, 'user_id' => $user->id, 'role' => 'director', 'status' => 'active']);

    loginAs($user)->getJson('/api/v1/school-profile')->assertForbidden();
    asMember($user, $b)->getJson('/api/v1/school-profile')->assertOk();
});

it('usa a única instituição do utilizador quando o cabeçalho é omitido', function () {
    $school = makeSchool();
    $user = makeMember($school);

    loginAs($user)->getJson('/api/v1/school-profile')->assertOk();
});

it('recusa instituições não aprovadas, inactivas ou suspensas', function () {
    $school = makeSchool();
    $user = makeMember($school);

    foreach ([['operational_status', 'inactive'], ['operational_status', 'suspended'], ['approval_status', 'pending']] as [$field, $value]) {
        Institution::whereKey($school->id)->update(['operational_status' => 'active', 'approval_status' => 'approved', $field => $value]);
        asMember($user, $school)->getJson('/api/v1/school-profile')->assertForbidden();
    }
});

it('recusa associações revogadas', function () {
    $school = makeSchool();
    $user = makeMember($school);
    DB::table('institution_user')->where('user_id', $user->id)->update(['status' => 'revoked']);

    asMember($user, $school)->getJson('/api/v1/school-profile')->assertForbidden();
});

it('aplica o papel: o professor não altera o perfil da escola', function () {
    $school = makeSchool();
    $teacher = makeMember($school, 'teacher');
    $payload = ['director_name' => 'Outro', 'director_gender' => 'F', 'regime' => 'public'];

    asMember($teacher, $school)->putJson('/api/v1/school-profile', $payload)->assertForbidden();
    asMember(makeMember($school, 'director'), $school)->putJson('/api/v1/school-profile', $payload)->assertOk();
});

it('lê o contexto do tenant apenas dentro do pedido (falha fechada)', function () {
    makeSchool();

    expect(fn () => Student::query()->count())->toThrow(RuntimeException::class);
});

it('recusa o início de sessão vindo de uma origem que não é o frontend, em vez de falhar com 500', function () {
    $this->withHeader('Origin', 'https://outro-sitio.example')
        ->postJson('/api/v1/auth/login', ['email' => 'x@y.ao', 'password' => 'segredo123'])
        ->assertStatus(400);
});

it('inicia e termina sessão e lista as instituições do utilizador', function () {
    $school = makeSchool('Escola A');
    $user = User::factory()->create(['email' => 'dir@escola.ao', 'password' => 'segredo123']);
    DB::table('institution_user')->insert(['institution_id' => $school->id, 'user_id' => $user->id, 'role' => 'director', 'status' => 'active']);

    $this->withHeader('Origin', config('app.frontend_url'))
        ->postJson('/api/v1/auth/login', ['email' => 'dir@escola.ao', 'password' => 'errada'])
        ->assertUnprocessable();

    $this->withHeader('Origin', config('app.frontend_url'))
        ->postJson('/api/v1/auth/login', ['email' => 'dir@escola.ao', 'password' => 'segredo123'])
        ->assertNoContent();

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.institutions.0.institution_name', 'Escola A');
});
