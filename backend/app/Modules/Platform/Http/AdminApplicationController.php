<?php

namespace App\Modules\Platform\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\InstitutionApplication;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Services\InstitutionProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminApplicationController extends Controller
{
    private const FINAL = ['approved', 'rejected'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(['pending', 'under_review', 'approved', 'rejected', 'info_requested'])]]);

        $page = InstitutionApplication::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('submitted_at')
            ->paginate(25);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function decide(Request $request, int $id, InstitutionProvisioner $provisioner): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'request_info'])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $application = InstitutionApplication::query()->findOrFail($id);

        if (in_array($application->status, self::FINAL, true)) {
            return response()->json(['message' => 'O pedido já foi decidido.'], 409);
        }

        $status = ['approve' => 'approved', 'reject' => 'rejected', 'request_info' => 'info_requested'][$data['decision']];

        $institution = null;
        if ($data['decision'] === 'approve') {
            $institution = $provisioner->approve($application, $request->user());
            $application->institution_id = $institution->id;
        }

        $application->fill([
            'status' => $status,
            'review_notes' => $data['notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        Audit::record("application.{$data['decision']}", 'institution_application', $application->id, $institution?->id, after: ['status' => $status]);

        return response()->json(['data' => ['id' => $application->id, 'status' => $status, 'institution_id' => $institution?->id]]);
    }
}
