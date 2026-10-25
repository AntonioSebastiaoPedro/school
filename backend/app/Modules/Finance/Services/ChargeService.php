<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Money;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Services\ConflictException;
use Illuminate\Support\Facades\DB;

class ChargeService
{
    /** Estados em que uma cobrança aceita pagamentos. */
    public const PAYABLE = ['open', 'partially_paid'];

    public function __construct(private readonly CurrentTenant $tenant, private readonly DocumentNumbering $numbering) {}

    /** Valor já liquidado por pagamentos confirmados (estornados não contam), em cêntimos. */
    public function paidCents(int $chargeId): int
    {
        $sum = DB::table('payment_allocations as pa')
            ->join('payments as p', fn ($j) => $j->on('p.id', '=', 'pa.payment_id')->on('p.institution_id', '=', 'pa.institution_id'))
            ->where('pa.institution_id', $this->tenant->id())
            ->where('pa.charge_id', $chargeId)
            ->where('p.status', 'confirmed')
            ->sum('pa.amount');

        return Money::cents((string) ($sum ?: '0'));
    }

    public function outstandingCents(Charge $charge): int
    {
        return Money::cents($charge->amount) - $this->paidCents($charge->id);
    }

    /** Recalcula o estado a partir das alocações confirmadas. Anuladas e rascunhos não mudam. */
    public function refreshStatus(Charge $charge): Charge
    {
        if (in_array($charge->status, ['void', 'written_off', 'draft'], true)) {
            return $charge;
        }

        $paid = $this->paidCents($charge->id);
        $status = match (true) {
            $paid >= Money::cents($charge->amount) => 'paid',
            $paid > 0 => 'partially_paid',
            default => 'open',
        };

        if ($status !== $charge->status) {
            $charge->update(['status' => $status]);
        }

        return $charge;
    }

    /** @param array<string, mixed> $data */
    public function create(Enrollment $enrollment, array $data, ?int $userId): Charge
    {
        return DB::transaction(function () use ($enrollment, $data, $userId) {
            DB::select('select pg_advisory_xact_lock(?, 3)', [$this->tenant->id()]);

            return Charge::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'code' => $this->nextCode(),
                'description' => $data['description'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'due_date' => $data['due_date'] ?? null,
                'status' => 'open',
                'created_by' => $userId,
            ]);
        });
    }

    /** Gera a mesma cobrança para todos os alunos activos de uma turma, sem repetir a que já existe. */
    public function createForSection(int $sectionId, array $data, ?int $userId): array
    {
        $enrollments = Enrollment::query()->where('section_id', $sectionId)->whereIn('status', ['active', 'completed'])->get();
        $created = 0;
        $skipped = 0;

        foreach ($enrollments as $enrollment) {
            $exists = Charge::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('category', $data['category'])
                ->where('description', $data['description'])
                ->whereNot('status', 'void')
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $this->create($enrollment, $data, $userId);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Anula uma cobrança que ainda não recebeu pagamentos confirmados. */
    public function void(Charge $charge): Charge
    {
        if ($charge->status === 'void') {
            throw new ConflictException('A cobrança já está anulada.');
        }

        if ($this->paidCents($charge->id) > 0) {
            throw new ConflictException('A cobrança tem pagamentos confirmados: estorne-os primeiro.');
        }

        $charge->update(['status' => 'void']);

        return $charge;
    }

    private function nextCode(): string
    {
        return sprintf('CB-%s-%06d', now()->format('Y'), $this->numbering->next($this->tenant->id(), 'charge'));
    }
}
