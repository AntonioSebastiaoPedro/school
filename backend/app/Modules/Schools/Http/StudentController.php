<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Http\Requests\StudentRequest;
use App\Modules\Schools\Models\Student;
use App\Modules\Schools\Services\StudentService;
use App\Modules\Schools\Support\Filiation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(['active', 'inactive', 'transferred', 'graduated', 'archived'])]]);

        $page = Student::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $search) {
                $term = '%'.mb_strtolower($search).'%';
                $q->where(fn ($w) => $w
                    ->whereRaw('lower(first_name || \' \' || last_name) like ?', [$term])
                    ->orWhere('student_number', 'like', $term)
                    ->orWhere('document_number', 'like', strtoupper($term)));
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    public function store(StudentRequest $request, StudentService $students, CurrentTenant $tenant): JsonResponse
    {
        $student = $students->create($request->validated());
        Audit::record('student.create', 'student', $student->id, $tenant->id());

        return response()->json(['data' => $student->fresh()], 201);
    }

    public function show(int $id): JsonResponse
    {
        $student = Student::query()->findOrFail($id);

        return response()->json(['data' => [...$student->toArray(), 'filiation' => Filiation::of($student)]]);
    }

    public function update(StudentRequest $request, int $id, StudentService $students, CurrentTenant $tenant): JsonResponse
    {
        $student = $students->update(Student::query()->findOrFail($id), $request->validated());
        Audit::record('student.update', 'student', $student->id, $tenant->id());

        return response()->json(['data' => $student->fresh()]);
    }
}
