<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Services\EnrollmentService;
use App\Modules\Schools\Services\RenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'active', 'cancelled', 'completed', 'dropped', 'transferred'])]]);

        $page = Enrollment::query()
            ->with(['student:id,student_number,first_name,last_name,gender', 'section:id,name,shift'])
            ->when($request->query('school_year_id'), fn ($q, $v) => $q->where('school_year_id', $v))
            ->when($request->query('section_id'), fn ($q, $v) => $q->where('section_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('section_id')->orderBy('roll_number')
            ->paginate(50);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function store(Request $request, EnrollmentService $service, CurrentTenant $tenant): JsonResponse
    {
        $institutionId = $tenant->id();
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('institution_id', $institutionId);

        $data = $request->validate([
            'student_id' => ['required', 'integer', $exists('students')],
            'school_year_id' => ['required', 'integer', $exists('school_years')],
            'grade_level_id' => ['required', 'integer', 'between:0,9'],
            'section_id' => ['required', 'integer', $exists('sections')],
            'enrollment_type' => ['sometimes', Rule::in(['new', 'renewal', 'transfer_in'])],
            'origin_school' => ['nullable', 'string', 'max:255'],
            'is_repeater' => ['sometimes', 'boolean'],
            'foreign_language' => ['nullable', Rule::in(['english', 'french'])],
        ]);

        [$enrollment, $warnings] = $service->enroll($data);
        Audit::record('enrollment.create', 'enrollment', $enrollment->id, $institutionId);

        return response()->json(['data' => $enrollment, 'warnings' => $warnings], 201);
    }

    public function decision(Request $request, int $id, EnrollmentService $service, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'cancel', 'complete'])],
            'reason' => ['nullable', 'string'],
        ]);

        $enrollment = $service->decide(Enrollment::query()->findOrFail($id), $data['decision']);
        Audit::record("enrollment.{$data['decision']}", 'enrollment', $enrollment->id, $tenant->id(), after: ['status' => $enrollment->status, 'reason' => $data['reason'] ?? null]);

        return response()->json(['data' => $enrollment]);
    }

    public function transfer(Request $request, int $id, EnrollmentService $service, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'other_school_name' => ['required', 'string', 'max:255'],
            'guide_number' => ['nullable', 'string', 'max:80'],
            'transfer_date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        $enrollment = $service->transferOut(Enrollment::query()->findOrFail($id), $data);
        Audit::record('enrollment.transfer', 'enrollment', $enrollment->id, $tenant->id(), after: $data);

        return response()->json(['data' => $enrollment]);
    }

    public function bulkRenew(Request $request, RenewalService $renewals, CurrentTenant $tenant): JsonResponse
    {
        $institutionId = $tenant->id();
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('institution_id', $institutionId);

        $data = $request->validate([
            'from_school_year_id' => ['required', 'integer', $exists('school_years')],
            'to_school_year_id' => ['required', 'integer', 'different:from_school_year_id', Rule::exists('school_years', 'id')->where('institution_id', $institutionId)->whereNot('status', 'closed')],
            'section_map' => ['required', 'array', 'min:1'],
            'section_map.*.from_section_id' => ['required', 'integer', $exists('sections')],
            'section_map.*.to_section_id' => ['required', 'integer', $exists('sections')],
        ]);

        $summary = $renewals->renew($data['from_school_year_id'], $data['to_school_year_id'], $data['section_map']);
        Audit::record('enrollment.bulk_renew', 'school_year', $data['to_school_year_id'], $institutionId, after: ['created' => $summary['created'], 'skipped' => count($summary['skipped'])]);

        return response()->json($summary);
    }
}
