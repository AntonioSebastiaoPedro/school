<?php

use App\Models\User;
use App\Modules\Platform\Models\Institution;
use App\Modules\Platform\Models\InstitutionApplication;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\GradeLevelSubject;
use App\Modules\Schools\Models\SchoolProfile;
use App\Modules\Schools\Models\Subject;
use Illuminate\Support\Facades\Notification;

function schoolApplication(array $overrides = []): array
{
    return [
        'institution_type' => 'school',
        'proposed_name' => 'Escola Primária 1',
        'applicant_name' => 'Maria Pedro',
        'applicant_email' => 'maria@escola.ao',
        'applicant_phone' => '923000000',
        'school_profile' => ['director_name' => 'Maria Pedro', 'director_gender' => 'F', 'regime' => 'private', 'school_code' => '1234'],
        ...$overrides,
    ];
}

it('aceita pedidos públicos de adesão sem conceder acesso operacional', function () {
    $this->postJson('/api/v1/institution-applications', schoolApplication())->assertCreated();

    expect(InstitutionApplication::count())->toBe(1)
        ->and(Institution::count())->toBe(0)
        ->and(User::count())->toBe(0);
});

it('exige o perfil escolar para escolas', function () {
    $this->postJson('/api/v1/institution-applications', schoolApplication(['school_profile' => null]))
        ->assertUnprocessable();
});

it('só o administrador SaaS decide pedidos', function () {
    $application = InstitutionApplication::create([...collect(schoolApplication())->except('school_profile')->all(), 'school_profile' => schoolApplication()['school_profile']]);

    $this->actingAs(User::factory()->create(), 'web')
        ->postJson("/api/v1/admin/institution-applications/{$application->id}/decision", ['decision' => 'approve'])
        ->assertForbidden();
});

it('aprova o pedido: cria instituição inactiva, proprietário, perfil, currículo e regras', function () {
    Notification::fake();
    $admin = User::factory()->platformAdmin()->create();
    $this->postJson('/api/v1/institution-applications', schoolApplication());
    $application = InstitutionApplication::first();

    loginAs($admin)
        ->postJson("/api/v1/admin/institution-applications/{$application->id}/decision", ['decision' => 'approve'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $institution = Institution::first();
    expect($institution->approval_status)->toBe('approved')
        ->and($institution->operational_status)->toBe('inactive');

    $owner = User::where('email', 'maria@escola.ao')->first();
    expect(DB::table('institution_user')->where(['institution_id' => $institution->id, 'user_id' => $owner->id, 'role' => 'owner'])->exists())->toBeTrue();

    app(CurrentTenant::class)->runAs($institution->id, function () {
        expect(SchoolProfile::first()->regime)->toBe('private')
            ->and(Subject::count())->toBe(17 + 6)
            ->and(GradeLevelSubject::where('grade_level_id', 0)->count())->toBe(6)
            ->and(GradeLevelSubject::where('grade_level_id', 1)->count())->toBe(7)   // LP, MAT, EM, EMC, EMP, MUS, EF
            ->and(GradeLevelSubject::where('grade_level_id', 9)->count())->toBe(13); // 11 obrigatórias + EMPR + LOA
    });

    expect(DB::table('assessment_rules')->where(['institution_id' => $institution->id, 'is_active' => true])->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'application.approve')->count())->toBe(1);

    // Aprovada não significa activa (RN-003): sem subscrição, o acesso operacional continua recusado.
    loginAs($owner)->getJson('/api/v1/school-profile')->assertForbidden();
});

it('não decide duas vezes o mesmo pedido', function () {
    $admin = User::factory()->platformAdmin()->create();
    $this->postJson('/api/v1/institution-applications', schoolApplication());
    $id = InstitutionApplication::first()->id;

    loginAs($admin)->postJson("/api/v1/admin/institution-applications/{$id}/decision", ['decision' => 'reject'])->assertOk();
    loginAs($admin)->postJson("/api/v1/admin/institution-applications/{$id}/decision", ['decision' => 'approve'])->assertStatus(409);
});

it('a subscrição activa a instituição aprovada e o limite do plano é aplicado', function () {
    $admin = User::factory()->platformAdmin()->create();
    $this->postJson('/api/v1/institution-applications', schoolApplication());
    $id = InstitutionApplication::first()->id;
    loginAs($admin)->postJson("/api/v1/admin/institution-applications/{$id}/decision", ['decision' => 'approve'])->assertOk();
    $institution = Institution::first();

    $plan = loginAs($admin)->postJson('/api/v1/admin/plans', [
        'name' => 'Básico', 'code' => 'basic', 'price' => 15000, 'billing_interval' => 'monthly',
        'features' => [['feature_key' => 'max_active_students', 'numeric_limit' => 1]],
    ])->assertCreated()->json('data.id');

    loginAs($admin)->postJson("/api/v1/admin/institutions/{$institution->id}/subscriptions", ['plan_id' => $plan])->assertCreated();

    expect($institution->fresh()->operational_status)->toBe('active');

    $owner = User::where('email', 'maria@escola.ao')->first();
    loginAs($owner)->getJson('/api/v1/school-profile')->assertOk();

    loginAs($admin)->postJson("/api/v1/admin/institutions/{$institution->id}/suspend", ['reason' => 'Falta de pagamento'])->assertOk();
    loginAs($owner)->getJson('/api/v1/school-profile')->assertForbidden();
});
