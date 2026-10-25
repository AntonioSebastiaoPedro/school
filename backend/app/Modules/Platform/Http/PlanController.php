<?php

namespace App\Modules\Platform\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Plan::query()->with('features')->orderBy('price')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $plan = DB::transaction(function () use ($data) {
            $plan = Plan::create(collect($data)->except('features')->all());
            $plan->features()->createMany($data['features'] ?? []);

            return $plan;
        });

        Audit::record('plan.create', 'plan', $plan->id);

        return response()->json(['data' => $plan->load('features')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::query()->findOrFail($id);
        $data = $this->validated($request, $plan->id);

        DB::transaction(function () use ($plan, $data) {
            $plan->update(collect($data)->except('features')->all());

            if (array_key_exists('features', $data)) {
                $plan->features()->delete();
                $plan->features()->createMany($data['features']);
            }
        });

        Audit::record('plan.update', 'plan', $plan->id);

        return response()->json(['data' => $plan->fresh('features')]);
    }

    private function validated(Request $request, ?int $ignore = null): array
    {
        $required = $ignore === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:100'],
            'code' => [$required, 'string', 'max:60', Rule::unique('plans', 'code')->ignore($ignore)],
            'description' => ['nullable', 'string'],
            'price' => [$required, 'numeric', 'min:0'],
            'billing_interval' => [$required, Rule::in(['monthly', 'quarterly', 'yearly'])],
            'is_active' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*.feature_key' => ['required', 'string', 'max:100', 'distinct'],
            'features.*.enabled' => ['nullable', 'boolean'],
            'features.*.numeric_limit' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
