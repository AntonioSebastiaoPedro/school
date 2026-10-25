<?php

namespace App\Modules\Finance\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Platform\Models\Institution;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\SchoolProfile;
use App\Modules\Schools\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    /** Dados do recibo prontos para apresentar ou imprimir. O PDF fica para uma fase posterior. */
    public function show(int $id, CurrentTenant $tenant): JsonResponse
    {
        $receipt = Receipt::query()->with(['payment.allocations.charge'])->findOrFail($id);
        $payment = $receipt->payment;
        $student = Student::query()->find($payment->student_id);
        $institution = Institution::query()->find($tenant->id());

        $payer = DB::table('student_guardian as sg')
            ->join('guardians as g', fn ($j) => $j->on('g.id', '=', 'sg.guardian_id')->on('g.institution_id', '=', 'sg.institution_id'))
            ->where('sg.institution_id', $tenant->id())->where('sg.student_id', $payment->student_id)
            ->orderByDesc('sg.is_financial_responsible')->orderByDesc('sg.is_primary')
            ->first(['g.first_name', 'g.last_name']);

        return response()->json(['data' => [
            'receipt_code' => $receipt->receipt_code,
            'status' => $receipt->status,
            'void_reason' => $receipt->void_reason,
            'issued_at' => $receipt->issued_at,
            'institution' => ['name' => $institution->name, 'tax_identifier' => $institution->tax_identifier, 'municipality' => $institution->municipality, 'province' => $institution->province],
            'student' => ['name' => trim($student->first_name.' '.$student->last_name), 'student_number' => $student->student_number],
            'payer' => $payer ? trim($payer->first_name.' '.$payer->last_name) : null,
            'payment' => $payment->only(['payment_code', 'amount', 'method', 'reference', 'paid_at', 'status']),
            'operator' => User::query()->whereKey($payment->recorded_by)->value('name'),
            'items' => $payment->allocations->map(fn ($a) => ['description' => $a->charge->description, 'amount' => $a->amount])->values(),
            'school' => SchoolProfile::query()->first(['school_code'])?->only('school_code'),
        ]]);
    }
}
