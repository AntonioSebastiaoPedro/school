<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Money;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Services\ConflictException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly ChargeService $charges,
        private readonly DocumentNumbering $numbering,
    ) {}

    /**
     * Regista um pagamento confirmado, aloca-o às cobranças e emite o recibo, numa só transacção.
     * A mesma chave de idempotência com o mesmo pedido devolve o pagamento original (sem duplicar);
     * com um pedido diferente é um conflito (RN-028).
     *
     * @param  array{student_id:int, amount:string|int|float, method:string, reference?:?string, paid_at?:?string, notes?:?string, allocations:list<array{charge_id:int, amount:string|int|float}>}  $data
     * @return array{0: Payment, 1: bool} o pagamento e se foi uma repetição
     */
    public function register(array $data, string $idempotencyKey, ?int $userId): array
    {
        $hash = $this->hash($data);

        try {
            return DB::transaction(function () use ($data, $idempotencyKey, $hash, $userId) {
                // Pedidos com a mesma chave em simultâneo ficam em fila: o segundo só valida depois de o
                // primeiro terminar e, vendo a chave, recebe o pagamento original em vez de um erro.
                DB::select('select pg_advisory_xact_lock(?, hashtext(?))', [$this->tenant->id(), $idempotencyKey]);

                if ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first()) {
                    return $this->replay($existing, $hash);
                }

                return [$this->create($data, $idempotencyKey, $hash, $userId), false];
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first()) {
                return $this->replay($existing, $hash);
            }

            throw $e;
        }
    }

    /** Estorna um pagamento confirmado: fica registado, o recibo é anulado e os saldos reabrem. */
    public function reverse(Payment $payment, string $reason, ?int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $userId) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'confirmed') {
                throw new ConflictException('O pagamento já foi estornado ou não está confirmado.');
            }

            $payment->update(['status' => 'reversed', 'reversed_at' => now(), 'reversal_reason' => $reason]);
            Receipt::query()->where('payment_id', $payment->id)->update(['status' => 'void', 'void_reason' => "Estorno: {$reason}"]);

            $chargeIds = PaymentAllocation::query()->where('payment_id', $payment->id)->pluck('charge_id');
            Charge::query()->whereIn('id', $chargeIds)->lockForUpdate()->get()->each(fn (Charge $c) => $this->charges->refreshStatus($c));

            Audit::record('payment.reverse', 'payment', $payment->id, $this->tenant->id(), after: ['reason' => $reason, 'amount' => $payment->amount, 'by' => $userId]);

            return $payment->load(['allocations', 'receipt']);
        });
    }

    private function create(array $data, string $key, string $hash, ?int $userId): Payment
    {
        $institutionId = $this->tenant->id();
        $amount = Money::cents($data['amount']);
        $requested = collect($data['allocations'])->map(fn ($a) => ['charge_id' => (int) $a['charge_id'], 'cents' => Money::cents($a['amount'])]);

        if ($requested->pluck('charge_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['allocations' => 'Há cobranças repetidas na alocação.']);
        }

        // Bloqueia as cobranças enquanto se valida e aloca: dois pagamentos simultâneos não as podem ultrapassar.
        $charges = Charge::query()->whereIn('id', $requested->pluck('charge_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($requested->values() as $i => $alloc) {
            $charge = $charges[$alloc['charge_id']] ?? null;
            $field = "allocations.{$i}.amount";

            if ($charge === null || $charge->student_id !== (int) $data['student_id']) {
                throw ValidationException::withMessages(["allocations.{$i}.charge_id" => 'A cobrança não pertence a este aluno.']);
            }
            if (! in_array($charge->status, ChargeService::PAYABLE, true)) {
                throw ValidationException::withMessages(["allocations.{$i}.charge_id" => 'A cobrança não aceita pagamentos (estado '.$charge->status.').']);
            }
            if ($alloc['cents'] <= 0) {
                throw ValidationException::withMessages([$field => 'O valor alocado deve ser positivo.']);
            }
            $outstanding = $this->charges->outstandingCents($charge);
            if ($alloc['cents'] > $outstanding) {
                throw ValidationException::withMessages([$field => 'O valor alocado excede o saldo em dívida ('.Money::format($outstanding).' Kz).']);
            }
        }

        $allocated = $requested->sum('cents');
        if ($allocated !== $amount) {
            throw ValidationException::withMessages([
                'amount' => 'O valor recebido ('.Money::format($amount).' Kz) tem de ser totalmente alocado; alocado: '.Money::format($allocated).' Kz.',
            ]);
        }

        $year = (int) now()->format('Y');

        $payment = Payment::create([
            'student_id' => $data['student_id'],
            'payment_code' => sprintf('PG-%d-%06d', $year, $this->numbering->next($institutionId, 'payment', $year)),
            'amount' => Money::format($amount),
            'method' => $data['method'],
            'status' => 'confirmed',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'recorded_by' => $userId,
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($requested as $alloc) {
            PaymentAllocation::create(['payment_id' => $payment->id, 'charge_id' => $alloc['charge_id'], 'amount' => Money::format($alloc['cents'])]);
        }

        $charges->each(fn (Charge $c) => $this->charges->refreshStatus($c));

        Receipt::create([
            'payment_id' => $payment->id,
            'receipt_code' => sprintf('RC %d/%05d', $year, $this->numbering->next($institutionId, 'receipt', $year)),
            'issued_by' => $userId,
        ]);

        Audit::record('payment.create', 'payment', $payment->id, $institutionId, after: ['amount' => $payment->amount, 'method' => $payment->method]);

        return $payment->load(['allocations', 'receipt']);
    }

    /** @return array{0: Payment, 1: bool} */
    private function replay(Payment $existing, string $hash): array
    {
        if ($existing->request_hash !== $hash) {
            throw new ConflictException('A chave de idempotência já foi usada com um pedido diferente.');
        }

        return [$existing->load(['allocations', 'receipt']), true];
    }

    /** Identifica o pedido de forma estável: mesma chave com o mesmo conteúdo é uma repetição. */
    private function hash(array $data): string
    {
        $allocations = collect($data['allocations'])
            ->map(fn ($a) => [(int) $a['charge_id'], Money::cents($a['amount'])])
            ->sortBy(fn ($a) => $a[0])->values()->all();

        return hash('sha256', json_encode([
            (int) $data['student_id'], Money::cents($data['amount']), $data['method'], $data['reference'] ?? null,
            $data['paid_at'] ?? null, $data['notes'] ?? null, $allocations,
        ]));
    }
}
