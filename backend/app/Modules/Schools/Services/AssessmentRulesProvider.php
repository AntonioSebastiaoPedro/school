<?php

namespace App\Modules\Schools\Services;

use App\Modules\Schools\Assessment\Domain\AssessmentRules;
use App\Modules\Schools\Models\AssessmentRuleSet;

/** Regras de avaliação activas da instituição corrente (ou os valores por omissão). */
class AssessmentRulesProvider
{
    public function current(): AssessmentRules
    {
        $set = AssessmentRuleSet::query()->where('is_active', true)->first();

        return $set === null ? new AssessmentRules : AssessmentRules::fromArray($set->rules);
    }
}
