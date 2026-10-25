<?php

namespace App\Modules\Finance\Http;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Money;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends Controller
{
    /**
     * Distingue o emitido, o recebido, o pendente e o estornado (RN-033). Emitido e recebido
     * respeitam o período; pendente e vencido são o retrato de hoje.
     */
    public function summary(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $id = $tenant->id();
        $to = date('Y-m-d', strtotime($data['to'].' +1 day'));

        $cents = fn ($v) => Money::cents((string) ($v ?: '0'));

        $issued = DB::table('charges')->where('institution_id', $id)->whereNotIn('status', ['void', 'draft'])
            ->where('issued_at', '>=', $data['from'])->where('issued_at', '<', $to)->sum('amount');
        $received = DB::table('payments')->where(['institution_id' => $id, 'status' => 'confirmed'])
            ->where('paid_at', '>=', $data['from'])->where('paid_at', '<', $to)->sum('amount');
        $reversed = DB::table('payments')->where(['institution_id' => $id, 'status' => 'reversed'])
            ->where('paid_at', '>=', $data['from'])->where('paid_at', '<', $to)->sum('amount');

        $open = DB::table('charges as c')
            ->where('c.institution_id', $id)->whereIn('c.status', ['open', 'partially_paid'])
            ->selectRaw("c.due_date, c.student_id, c.amount - coalesce((select sum(pa.amount) from payment_allocations pa join payments p on p.id = pa.payment_id and p.institution_id = pa.institution_id where pa.charge_id = c.id and p.status = 'confirmed'), 0) as outstanding")
            ->get();

        $today = now()->toDateString();
        $pending = $open->sum(fn ($r) => $cents($r->outstanding));
        $overdueRows = $open->filter(fn ($r) => $r->due_date !== null && $r->due_date < $today);

        return response()->json(['data' => [
            'period' => ['from' => $data['from'], 'to' => $data['to']],
            'issued' => Money::format($cents($issued)),
            'received' => Money::format($cents($received)),
            'reversed' => Money::format($cents($reversed)),
            'pending' => Money::format($pending),
            'overdue' => Money::format($overdueRows->sum(fn ($r) => $cents($r->outstanding))),
            'students_overdue' => $overdueRows->pluck('student_id')->unique()->count(),
        ]]);
    }
}
