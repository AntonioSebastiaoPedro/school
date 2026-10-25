<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Models\Institution;
use App\Modules\Platform\Models\InstitutionApplication;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Services\SchoolProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Cria a instituição aprovada, o proprietário e, para escolas, o perfil, currículo e regras. */
class InstitutionProvisioner
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly SchoolProvisioner $schools,
    ) {}

    public function approve(InstitutionApplication $application, User $reviewer): Institution
    {
        return DB::transaction(function () use ($application, $reviewer) {
            $institution = Institution::create([
                'name' => $application->proposed_name,
                'slug' => $this->uniqueSlug($application->proposed_name),
                'institution_type' => $application->institution_type,
                'approval_status' => 'approved',
                'operational_status' => 'inactive',
                'email' => $application->applicant_email,
                'phone' => $application->applicant_phone,
                'tax_identifier' => $application->tax_identifier,
                'province' => $application->province,
                'municipality' => $application->municipality,
                'approved_at' => now(),
                'approved_by' => $reviewer->id,
            ]);

            $owner = User::firstOrCreate(
                ['email' => $application->applicant_email],
                ['name' => $application->applicant_name, 'password' => Str::random(40), 'phone' => $application->applicant_phone],
            );

            DB::table('institution_user')->insert([
                'institution_id' => $institution->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'invited_by' => $reviewer->id,
                'joined_at' => now(),
            ]);

            if ($institution->isSchool()) {
                $this->tenant->runAs($institution->id, fn () => $this->schools->provision(
                    $institution,
                    $application->school_profile ?? [],
                ));
            }

            // O proprietário define a sua palavra-passe através da ligação de recuperação.
            Password::sendResetLink(['email' => $owner->email]);

            return $institution;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'instituicao';
        $slug = $base;

        for ($i = 2; Institution::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
