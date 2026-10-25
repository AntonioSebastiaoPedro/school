<?php

namespace App\Modules\Platform\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Institution;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Models\SubscriptionPayment;
use App\Modules\Platform\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Subscrição comercial. A aprovação e a subscrição são estados independentes (RN-003):
 * aprovar não activa; activar exige instituição aprovada e subscrição vigente.
 */
class SubscriptionController extends Controller
{
    public function store(Request $request, int $institutionId): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'status' => ['sometimes', Rule::in(['trialing', 'active'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $institution = Institution::query()->findOrFail($institutionId);

        if ($institution->approval_status !== 'approved') {
            return response()->json(['message' => 'A instituição ainda não foi aprovada.'], 409);
        }

        $subscription = DB::transaction(function () use ($institution, $data) {
            Subscription::query()
                ->where('institution_id', $institution->id)
                ->whereIn('status', ['pending', 'trialing', 'active', 'past_due'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $subscription = Subscription::create([
                'institution_id' => $institution->id,
                'plan_id' => $data['plan_id'],
                'status' => $data['status'] ?? 'active',
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            $institution->update(['operational_status' => 'active']);

            return $subscription;
        });

        Audit::record('subscription.create', 'subscription', $subscription->id, $institution->id, after: $subscription->only(['plan_id', 'status']));

        return response()->json(['data' => $subscription->load('plan')], 201);
    }

    public function payment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'other_manual'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $subscription = Subscription::query()->findOrFail($id);

        $payment = SubscriptionPayment::create([
            ...$data,
            'institution_id' => $subscription->institution_id,
            'subscription_id' => $subscription->id,
            'status' => 'confirmed',
            'paid_at' => $data['paid_at'] ?? now(),
            'recorded_by' => $request->user()->id,
        ]);

        Audit::record('subscription.payment', 'subscription_payment', $payment->id, $subscription->institution_id, after: ['amount' => $data['amount']]);

        return response()->json(['data' => $payment], 201);
    }

    public function suspend(Request $request, int $institutionId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        $institution = Institution::query()->findOrFail($institutionId);
        $institution->update(['operational_status' => 'suspended']);

        Audit::record('institution.suspend', 'institution', $institution->id, $institution->id, after: ['reason' => $data['reason']]);

        return response()->json(['data' => $institution]);
    }

    public function reactivate(int $institutionId): JsonResponse
    {
        $institution = Institution::query()->findOrFail($institutionId);

        if ($institution->operational_status !== 'suspended') {
            return response()->json(['message' => 'A instituição não está suspensa.'], 409);
        }

        $institution->update(['operational_status' => 'active']);
        Audit::record('institution.reactivate', 'institution', $institution->id, $institution->id);

        return response()->json(['data' => $institution]);
    }
}
