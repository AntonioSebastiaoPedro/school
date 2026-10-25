<?php

use App\Modules\Finance\Models\Charge;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->school = makeSchool('Escola A');
    $this->finance = makeMember($this->school, 'finance');
    $this->year = makeYear($this->school);
    $this->section = makeSection($this->school, $this->year, 4);
    $this->student = makeStudent($this->school);
    $this->enrollment = makeActiveEnrollment($this->school, $this->year, $this->section, $this->student);
    $this->api = fn (?object $user = null) => asMember($user ?? $this->finance, $this->school);
});

function charge(object $t, string $amount = '45000.00', string $description = 'Propina · Setembro', ?string $due = '2026-09-10', ?object $enrollment = null): int
{
    return ($t->api)()->postJson('/api/v1/charges', [
        'enrollment_id' => ($enrollment ?? $t->enrollment)->id, 'description' => $description, 'category' => 'tuition', 'amount' => $amount, 'due_date' => $due,
    ])->assertCreated()->json('data.id');
}

function pay(object $t, array $allocations, string $amount, string $key = 'k-1', array $extra = [], ?object $user = null, ?object $student = null): TestResponse
{
    return ($t->api)($user)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/payments', [
        'student_id' => ($student ?? $t->student)->id, 'amount' => $amount, 'method' => 'card_terminal', 'reference' => 'TPA-1',
        'allocations' => collect($allocations)->map(fn ($v, $id) => ['charge_id' => $id, 'amount' => $v])->values()->all(), ...$extra,
    ]);
}

describe('cobranças', function () {
    it('cria cobranças com código sequencial e estado aberto', function () {
        $a = charge($this);
        $b = charge($this, description: 'Propina · Outubro');

        $rows = ($this->api)()->getJson("/api/v1/charges?student_id={$this->student->id}")->assertOk()->json('data');

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['code'])->toBe('CB-'.now()->format('Y').'-000001')
            ->and($rows[1]['code'])->toBe('CB-'.now()->format('Y').'-000002')
            ->and($rows[0]['student_name'])->toContain('Teste')->and($rows[0]['student_number'])->toBe($this->student->student_number)
            ->and($rows[0]['status'])->toBe('open')->and($rows[0]['outstanding'])->toBe('45000.00')->and($rows[0]['overdue'])->toBeTrue();
        expect($a)->not->toBe($b);
    });

    it('valida o valor: positivo e com no máximo duas casas', function () {
        foreach (['0', '-5', '10.999', 'abc'] as $bad) {
            ($this->api)()->postJson('/api/v1/charges', ['enrollment_id' => $this->enrollment->id, 'description' => 'x', 'category' => 'tuition', 'amount' => $bad])
                ->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
    });

    it('gera a mesma cobrança para toda a turma sem repetir', function () {
        makeActiveEnrollment($this->school, $this->year, $this->section, makeStudent($this->school));
        makeActiveEnrollment($this->school, $this->year, $this->section, makeStudent($this->school), 'pending'); // pendente fica de fora
        $body = ['section_id' => $this->section->id, 'description' => 'Propina · Novembro', 'category' => 'tuition', 'amount' => '45000.00', 'due_date' => '2026-11-10'];

        ($this->api)()->postJson('/api/v1/charges/bulk', $body)->assertCreated()->assertJson(['created' => 2, 'skipped' => 0]);
        ($this->api)()->postJson('/api/v1/charges/bulk', $body)->assertCreated()->assertJson(['created' => 0, 'skipped' => 2]);
    });

    it('anula só cobranças sem pagamentos confirmados', function () {
        $free = charge($this);
        $paid = charge($this, description: 'Outra');
        pay($this, [$paid => '45000.00'], '45000.00')->assertCreated();

        ($this->api)()->postJson("/api/v1/charges/{$free}/void")->assertOk()->assertJsonPath('data.status', 'void');
        ($this->api)()->postJson("/api/v1/charges/{$free}/void")->assertStatus(409);
        ($this->api)()->postJson("/api/v1/charges/{$paid}/void")->assertStatus(409);
    });
});

describe('pagamentos', function () {
    it('regista um pagamento parcial, aloca, emite recibo e mantém o saldo certo', function () {
        $sep = charge($this);
        $oct = charge($this, description: 'Propina · Outubro', due: '2026-10-10');

        $r = pay($this, [$sep => '45000.00', $oct => '25000.00'], '70000.00')->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.amount', '70000.00')->assertJsonPath('replayed', false);

        expect($r->json('data.payment_code'))->toBe('PG-'.now()->format('Y').'-000001')
            ->and($r->json('data.receipt.receipt_code'))->toBe('RC '.now()->format('Y').'/00001')
            ->and($r->json('data.allocations'))->toHaveCount(2);

        $rows = collect(($this->api)()->getJson("/api/v1/charges?student_id={$this->student->id}")->json('data'))->keyBy('id');
        expect($rows[$sep]['status'])->toBe('paid')->and($rows[$sep]['outstanding'])->toBe('0.00')
            ->and($rows[$oct]['status'])->toBe('partially_paid')->and($rows[$oct]['paid'])->toBe('25000.00')->and($rows[$oct]['outstanding'])->toBe('20000.00');

        // o restante liquida a cobrança
        pay($this, [$oct => '20000.00'], '20000.00', 'k-2')->assertCreated()->assertJsonPath('data.receipt.receipt_code', 'RC '.now()->format('Y').'/00002');
        expect(Charge::withoutGlobalScopes()->find($oct)->status)->toBe('paid');
    });

    it('exige que o valor recebido seja totalmente alocado', function () {
        $c = charge($this);

        pay($this, [$c => '30000.00'], '45000.00')->assertUnprocessable()->assertJsonValidationErrors('amount');
        pay($this, [$c => '45000.00'], '30000.00', 'k-x')->assertUnprocessable()->assertJsonValidationErrors('amount');
        expect(DB::table('payments')->count())->toBe(0);
    });

    it('não aloca mais do que o saldo da cobrança', function () {
        $c = charge($this);

        pay($this, [$c => '50000.00'], '50000.00')->assertUnprocessable()->assertJsonValidationErrors('allocations.0.amount');

        pay($this, [$c => '30000.00'], '30000.00', 'k-2')->assertCreated();
        pay($this, [$c => '20000.00'], '20000.00', 'k-3')->assertUnprocessable()->assertJsonValidationErrors('allocations.0.amount');
    });

    it('não paga cobranças de outro aluno, anuladas ou repetidas na alocação', function () {
        $other = makeStudent($this->school);
        $otherEnrollment = makeActiveEnrollment($this->school, $this->year, $this->section, $other);
        $foreign = charge($this, enrollment: $otherEnrollment);
        $mine = charge($this);
        $voided = charge($this, description: 'Anulada');
        ($this->api)()->postJson("/api/v1/charges/{$voided}/void");

        pay($this, [$foreign => '45000.00'], '45000.00')->assertUnprocessable()->assertJsonValidationErrors('allocations.0.charge_id');
        pay($this, [$voided => '45000.00'], '45000.00', 'k-2')->assertUnprocessable()->assertJsonValidationErrors('allocations.0.charge_id');
        ($this->api)()->withHeader('Idempotency-Key', 'k-3')->postJson('/api/v1/payments', [
            'student_id' => $this->student->id, 'amount' => '20.00', 'method' => 'cash',
            'allocations' => [['charge_id' => $mine, 'amount' => '10.00'], ['charge_id' => $mine, 'amount' => '10.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('allocations');
    });

    it('repetir o pedido com a mesma chave não duplica o pagamento', function () {
        $c = charge($this);

        $first = pay($this, [$c => '45000.00'], '45000.00', 'chave-unica')->assertCreated();
        $again = pay($this, [$c => '45000.00'], '45000.00', 'chave-unica')->assertOk()->assertJsonPath('replayed', true);

        expect($again->json('data.id'))->toBe($first->json('data.id'))
            ->and(DB::table('payments')->count())->toBe(1)
            ->and(DB::table('receipts')->count())->toBe(1)
            ->and(DB::table('payment_allocations')->count())->toBe(1);
    });

    it('a mesma chave com um pedido diferente é um conflito', function () {
        $c = charge($this);
        pay($this, [$c => '10000.00'], '10000.00', 'chave')->assertCreated();

        pay($this, [$c => '20000.00'], '20000.00', 'chave')->assertStatus(409);
        expect(DB::table('payments')->count())->toBe(1);
    });

    it('exige a chave de idempotência', function () {
        $c = charge($this);

        ($this->api)()->postJson('/api/v1/payments', ['student_id' => $this->student->id, 'amount' => '10.00', 'method' => 'cash', 'allocations' => [['charge_id' => $c, 'amount' => '10.00']]])
            ->assertUnprocessable();
    });

    it('usa cêntimos exactos: 0,10 + 0,20 liquida 0,30', function () {
        $c = charge($this, '0.30');

        pay($this, [$c => '0.10'], '0.10')->assertCreated();
        pay($this, [$c => '0.20'], '0.20', 'k-2')->assertCreated();

        expect(Charge::withoutGlobalScopes()->find($c)->status)->toBe('paid');
    });

    it('recusa data de pagamento no futuro e método desconhecido', function () {
        $c = charge($this);

        pay($this, [$c => '10.00'], '10.00', extra: ['paid_at' => now()->addDay()->toDateTimeString()])->assertUnprocessable()->assertJsonValidationErrors('paid_at');
        pay($this, [$c => '10.00'], '10.00', 'k-2', ['method' => 'cheque'])->assertUnprocessable()->assertJsonValidationErrors('method');
    });

    it('não há forma de apagar um pagamento confirmado', function () {
        $c = charge($this);
        $id = pay($this, [$c => '10.00'], '10.00')->json('data.id');

        ($this->api)()->deleteJson("/api/v1/payments/{$id}")->assertStatus(405);
        expect(DB::table('payments')->count())->toBe(1);
    });
});

describe('estornos', function () {
    it('estorna: anula o recibo, reabre as cobranças e mantém o histórico', function () {
        $c = charge($this);
        $p = pay($this, [$c => '45000.00'], '45000.00')->json('data');

        ($this->api)()->postJson("/api/v1/payments/{$p['id']}/reverse", ['reason' => 'Valor lançado ao aluno errado'])
            ->assertOk()->assertJsonPath('data.status', 'reversed')->assertJsonPath('data.receipt.status', 'void');

        $row = ($this->api)()->getJson("/api/v1/charges?student_id={$this->student->id}")->json('data.0');
        expect($row['status'])->toBe('open')->and($row['outstanding'])->toBe('45000.00')
            ->and(DB::table('payments')->count())->toBe(1)
            ->and(DB::table('receipts')->value('void_reason'))->toContain('Valor lançado ao aluno errado')
            ->and(DB::table('audit_logs')->where('action', 'payment.reverse')->count())->toBe(1);

        // a dívida pode voltar a ser paga
        pay($this, [$c => '45000.00'], '45000.00', 'k-2')->assertCreated();
    });

    it('exige motivo e não estorna duas vezes', function () {
        $c = charge($this);
        $id = pay($this, [$c => '10.00'], '10.00')->json('data.id');

        ($this->api)()->postJson("/api/v1/payments/{$id}/reverse", ['reason' => 'x'])->assertUnprocessable();
        ($this->api)()->postJson("/api/v1/payments/{$id}/reverse", ['reason' => 'Engano de valor'])->assertOk();
        ($this->api)()->postJson("/api/v1/payments/{$id}/reverse", ['reason' => 'Engano de valor'])->assertStatus(409);
    });

    it('o estorno de um pagamento não afecta os outros', function () {
        $c = charge($this);
        $first = pay($this, [$c => '10000.00'], '10000.00')->json('data.id');
        pay($this, [$c => '5000.00'], '5000.00', 'k-2')->assertCreated();

        ($this->api)()->postJson("/api/v1/payments/{$first}/reverse", ['reason' => 'Engano de valor'])->assertOk();

        $row = ($this->api)()->getJson('/api/v1/charges')->json('data.0');
        expect($row['status'])->toBe('partially_paid')->and($row['paid'])->toBe('5000.00')->and($row['outstanding'])->toBe('40000.00');
    });
});

describe('permissões e isolamento', function () {
    it('o caixa regista pagamentos mas não cria cobranças nem estorna', function () {
        $c = charge($this);
        $cashier = makeMember($this->school, 'cashier');

        $id = pay($this, [$c => '10.00'], '10.00', user: $cashier)->assertCreated()->json('data.id');
        ($this->api)($cashier)->postJson('/api/v1/charges', ['enrollment_id' => $this->enrollment->id, 'description' => 'x', 'category' => 'x', 'amount' => '1.00'])->assertForbidden();
        ($this->api)($cashier)->postJson("/api/v1/payments/{$id}/reverse", ['reason' => 'Engano de valor'])->assertForbidden();
        ($this->api)($cashier)->getJson('/api/v1/charges')->assertOk();
    });

    it('professores e secretaria não têm acesso ao financeiro', function () {
        foreach (['teacher', 'secretary'] as $role) {
            $user = makeMember($this->school, $role);
            ($this->api)($user)->getJson('/api/v1/charges')->assertForbidden();
            ($this->api)($user)->getJson('/api/v1/reports/finance/summary?from=2026-01-01&to=2026-12-31')->assertForbidden();
        }
    });

    it('isola cobranças, pagamentos e recibos entre instituições', function () {
        $c = charge($this);
        $paymentId = pay($this, [$c => '10.00'], '10.00')->json('data.id');
        $receiptId = DB::table('receipts')->value('id');

        $other = makeSchool('Escola B');
        $intruder = makeMember($other, 'finance');
        $api = fn () => asMember($intruder, $other);

        $api()->getJson('/api/v1/charges')->assertJsonCount(0, 'data');
        $api()->getJson('/api/v1/payments')->assertJsonCount(0, 'data');
        $api()->getJson("/api/v1/payments/{$paymentId}")->assertNotFound();
        $api()->getJson("/api/v1/receipts/{$receiptId}")->assertNotFound();
        $api()->postJson("/api/v1/payments/{$paymentId}/reverse", ['reason' => 'Tentativa indevida'])->assertNotFound();
        $api()->postJson("/api/v1/charges/{$c}/void")->assertNotFound();

        // nem referenciar alunos e cobranças alheios num pagamento próprio
        $mine = makeStudent($other);
        $api()->withHeader('Idempotency-Key', 'k')->postJson('/api/v1/payments', [
            'student_id' => $this->student->id, 'amount' => '10.00', 'method' => 'cash', 'allocations' => [['charge_id' => $c, 'amount' => '10.00']],
        ])->assertUnprocessable();
        $api()->withHeader('Idempotency-Key', 'k2')->postJson('/api/v1/payments', [
            'student_id' => $mine->id, 'amount' => '10.00', 'method' => 'cash', 'allocations' => [['charge_id' => $c, 'amount' => '10.00']],
        ])->assertUnprocessable();
    });

    it('a mesma chave de idempotência é independente por instituição', function () {
        $c = charge($this);
        pay($this, [$c => '10.00'], '10.00', 'partilhada')->assertCreated();

        $other = makeSchool('Escola B');
        $year = makeYear($other);
        $student = makeStudent($other);
        $enrollment = makeActiveEnrollment($other, $year, makeSection($other, $year, 4), $student);
        $user = makeMember($other, 'finance');
        $cid = asMember($user, $other)->postJson('/api/v1/charges', ['enrollment_id' => $enrollment->id, 'description' => 'P', 'category' => 'tuition', 'amount' => '10.00'])->json('data.id');

        asMember($user, $other)->withHeader('Idempotency-Key', 'partilhada')->postJson('/api/v1/payments', [
            'student_id' => $student->id, 'amount' => '10.00', 'method' => 'cash', 'allocations' => [['charge_id' => $cid, 'amount' => '10.00']],
        ])->assertCreated()->assertJsonPath('replayed', false);
    });
});

describe('recibo e relatório', function () {
    it('o recibo reúne escola, aluno, pagador, itens e operador', function () {
        $c = charge($this, description: 'Propina · Setembro');
        $guardian = ($this->api)(makeMember($this->school, 'secretary'))->postJson('/api/v1/guardians', ['first_name' => 'Domingos', 'last_name' => 'Cassoma'])->json('data.id');
        ($this->api)(makeMember($this->school, 'director'))->postJson("/api/v1/students/{$this->student->id}/guardians", ['guardian_id' => $guardian, 'relationship' => 'father', 'is_financial_responsible' => true]);
        pay($this, [$c => '45000.00'], '45000.00');

        $receipt = ($this->api)()->getJson('/api/v1/receipts/'.DB::table('receipts')->value('id'))->assertOk()->json('data');

        expect($receipt['institution']['name'])->toBe('Escola A')
            ->and($receipt['payer'])->toBe('Domingos Cassoma')
            ->and($receipt['items'])->toBe([['description' => 'Propina · Setembro', 'amount' => '45000.00']])
            ->and($receipt['payment']['method'])->toBe('card_terminal')
            ->and($receipt['operator'])->not->toBeNull()
            ->and($receipt['student']['name'])->toContain('Teste');
    });

    it('o relatório distingue emitido, recebido, estornado, pendente e vencido', function () {
        $sep = charge($this, '45000.00', 'Setembro', '2026-09-10');
        $oct = charge($this, '45000.00', 'Outubro', '2026-10-10');
        charge($this, '45000.00', 'Dezembro', '2099-12-10');
        $p1 = pay($this, [$sep => '45000.00'], '45000.00')->json('data.id');
        pay($this, [$oct => '25000.00'], '25000.00', 'k-2')->assertCreated();
        ($this->api)()->postJson("/api/v1/payments/{$p1}/reverse", ['reason' => 'Engano de valor']);

        $r = ($this->api)()->getJson('/api/v1/reports/finance/summary?from='.now()->subDay()->toDateString().'&to='.now()->toDateString())->assertOk()->json('data');

        expect($r['issued'])->toBe('135000.00')
            ->and($r['received'])->toBe('25000.00')
            ->and($r['reversed'])->toBe('45000.00')
            ->and($r['pending'])->toBe('110000.00')    // 45 000 (setembro, reaberta) + 20 000 + 45 000
            ->and($r['overdue'])->toBe('65000.00')     // setembro e o resto de outubro
            ->and($r['students_overdue'])->toBe(1);
    });
});
