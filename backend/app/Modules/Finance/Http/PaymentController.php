<?php

namespace App\Modules\Finance\Http;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'status' => ['nullable', Rule::in(['confirmed', 'reversed'])]]);

        $page = Payment::query()
            ->with('receipt:id,payment_id,receipt_code,status')
            ->when($request->query('student_id'), fn ($q, $v) => $q->where('student_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->where('paid_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->where('paid_at', '<', date('Y-m-d', strtotime($v.' +1 day'))))
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => Payment::query()->with(['allocations', 'receipt'])->findOrFail($id)]);
    }

    public function store(Request $request, PaymentService $payments, CurrentTenant $tenant): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        abort_if($key === '' || strlen($key) > 120, 422, 'O cabeçalho Idempotency-Key é obrigatório (máx. 120 caracteres).');

        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('institution_id', $tenant->id())],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'method' => ['required', Rule::in(['cash', 'card_terminal', 'bank_transfer', 'deposit', 'other_manual'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.charge_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        [$payment, $replayed] = $payments->register($data, $key, $request->user()->id);

        return response()->json(['data' => $payment, 'replayed' => $replayed], $replayed ? 200 : 201);
    }

    public function reverse(Request $request, int $id, PaymentService $payments): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return response()->json(['data' => $payments->reverse(Payment::query()->findOrFail($id), $data['reason'], $request->user()->id)]);
    }
}
