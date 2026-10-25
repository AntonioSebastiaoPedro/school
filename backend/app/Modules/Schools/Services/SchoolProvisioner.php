<?php

namespace App\Modules\Schools\Services;

use App\Modules\Platform\Models\Institution;
use App\Modules\Schools\Assessment\Domain\AssessmentRules;
use App\Modules\Schools\Models\AssessmentRuleSet;
use App\Modules\Schools\Models\Municipality;
use App\Modules\Schools\Models\SchoolProfile;

/** Dados iniciais de uma escola aprovada: perfil, currículo e regras de avaliação v1. Corre no contexto do tenant. */
class SchoolProvisioner
{
    public function __construct(private readonly CurriculumProvisioner $curriculum) {}

    /** @param array<string, mixed> $profile */
    public function provision(Institution $institution, array $profile): void
    {
        $municipalityId = $profile['municipality_id'] ?? null;
        $provinceId = $municipalityId ? Municipality::find($municipalityId)?->province_id : ($profile['province_id'] ?? null);

        SchoolProfile::create([
            'institution_id' => $institution->id,
            'institution_type' => $institution->institution_type,
            'school_code' => $profile['school_code'] ?? null,
            'ex_code' => $profile['ex_code'] ?? null,
            'decree' => $profile['decree'] ?? null,
            'director_name' => $profile['director_name'] ?? $institution->name,
            'director_gender' => $profile['director_gender'] ?? 'M',
            'director_phone' => $profile['director_phone'] ?? null,
            'is_complex' => $profile['is_complex'] ?? false,
            'regime' => $profile['regime'] ?? 'public',
            'province_id' => $provinceId,
            'municipality_id' => $municipalityId,
        ]);

        $this->curriculum->provision();

        AssessmentRuleSet::create([
            'version' => 1,
            'rules' => (new AssessmentRules)->toArray(),
            'is_active' => true,
        ]);
    }
}
