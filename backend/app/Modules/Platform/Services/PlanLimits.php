<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Limites do plano aplicados no backend (RF-020, RN-006). Sem limite definido, não há restrição. */
class PlanLimits
{
    public function limit(int $institutionId, string $featureKey): ?int
    {
        $subscription = Subscription::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latest('id')
            ->first();

        if ($subscription === null) {
            return null;
        }

        return DB::table('plan_features')
            ->where('plan_id', $subscription->plan_id)
            ->where('feature_key', $featureKey)
            ->value('numeric_limit');
    }

    public function assertCanAdd(int $institutionId, string $featureKey, int $current, int $adding = 1): void
    {
        $limit = $this->limit($institutionId, $featureKey);

        if ($limit !== null && $current + $adding > $limit) {
            throw ValidationException::withMessages([
                $featureKey => "O plano contratado permite no máximo {$limit}. Altere o plano para continuar.",
            ]);
        }
    }
}
