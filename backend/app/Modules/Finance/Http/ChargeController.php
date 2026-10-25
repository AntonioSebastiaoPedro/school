<?php

namespace App\Modules\Finance\Http;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Money;
use App\Modules\Finance\Services\ChargeService;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChargeController extends Controller
{
    private const STATUSES = ['draft', 'open', 'partially_paid', 'paid', 'void', 'written_off'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['due_before' => ['nullable', 'date']]);
        $statuses = array_filter(explode(',', (string) $request->query('status')));
        abort_if(array_diff($statuses, self::STATUSES) !== [], 422, 'Estado inválido.');

        $paid = DB::table('payment_allocations as pa')
            ->join('payments as p', fn ($j) => $j->on('p.id', '=', 'pa.payment_id')->on('p.institution_id', '=', 'pa.institution_id'))
            ->where('p.status', 'confirmed')
            ->whereColumn('pa.charge_id', 'charges.id')
            ->selectRaw('coalesce(sum(pa.amount), 0)');

        $page = Charge::query()
            ->select('charges.*')
            ->selectSub($paid, 'paid_amount')
            ->when($request->query('student_id'), fn ($q, $v) => $q->where('student_id', $v))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when($request->query('due_before'), fn ($q, $v) => $q->where('due_date', '<', $v))
            ->orderBy('due_date')->orderBy('id')
            ->paginate(100);

        $today = now()->toDateString();
        $students = Student::query()->whereIn('id', collect($page->items())->pluck('student_id'))->get(['id', 'student_number', 'first_name', 'last_name'])->keyBy('id');

        return response()->json([
            'data' => collect($page->items())->map(function (Charge $c) use ($today, $students) {
                $outstanding = Money::cents($c->amount) - Money::cents((string) $c->paid_amount);

                return [
                    ...$c->only(['id', 'student_id', 'enrollment_id', 'code', 'description', 'category', 'status']),
                    'student_name' => trim(($students[$c->student_id]->first_name ?? '').' '.($students[$c->student_id]->last_name ?? '')),
                    'student_number' => $students[$c->student_id]->student_number ?? null,
                    'amount' => $c->amount,
                    'paid' => Money::format(Money::cents((string) $c->paid_amount)),
                    'outstanding' => Money::format($c->status === 'void' ? 0 : $outstanding),
                    'due_date' => $c->due_date?->toDateString(),
                    'overdue' => in_array($c->status, ChargeService::PAYABLE, true) && $c->due_date !== null && $c->due_date->toDateString() < $today,
                ];
            })->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request, ChargeService $charges, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'integer', Rule::exists('enrollments', 'id')->where('institution_id', $tenant->id())],
            ...$this->rules(),
        ]);

        $charge = $charges->create(Enrollment::query()->findOrFail($data['enrollment_id']), $data, $request->user()->id);
        Audit::record('charge.create', 'charge', $charge->id, $tenant->id(), after: ['amount' => $charge->amount]);

        return response()->json(['data' => $charge->fresh()], 201);
    }

    public function bulk(Request $request, ChargeService $charges, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('institution_id', $tenant->id())],
            ...$this->rules(),
        ]);

        $summary = $charges->createForSection($data['section_id'], $data, $request->user()->id);
        Audit::record('charge.bulk_create', 'section', $data['section_id'], $tenant->id(), after: $summary);

        return response()->json($summary, 201);
    }

    public function void(int $id, ChargeService $charges, CurrentTenant $tenant): JsonResponse
    {
        $charge = $charges->void(Charge::query()->findOrFail($id));
        Audit::record('charge.void', 'charge', $charge->id, $tenant->id());

        return response()->json(['data' => $charge]);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:240'],
            'category' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
