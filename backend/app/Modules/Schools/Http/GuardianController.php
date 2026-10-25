<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Guardian;
use App\Modules\Schools\Models\Student;
use App\Modules\Schools\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuardianController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Guardian::query()->orderBy('last_name')->orderBy('first_name')->paginate(25)->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'first_name' => StudentService::normalizeName($request->input('first_name')),
            'last_name' => StudentService::normalizeName($request->input('last_name')),
        ]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'required_with:document_number', Rule::in(['birth_record', 'personal_card', 'identity_card', 'passport'])],
            'document_number' => ['nullable', 'required_with:document_type', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_alt' => ['nullable', 'string', 'max:40'],
            'profession' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string'],
        ]);

        return response()->json(['data' => Guardian::create($data)], 201);
    }

    /** Associa o encarregado ao aluno. No máximo um pai e uma mãe por aluno. */
    public function attach(Request $request, int $studentId, CurrentTenant $tenant): JsonResponse
    {
        $institutionId = $tenant->id();

        $data = $request->validate([
            'guardian_id' => ['required', 'integer', Rule::exists('guardians', 'id')->where('institution_id', $institutionId)],
            'relationship' => ['sometimes', Rule::in(['father', 'mother', 'other'])],
            'relationship_label' => ['nullable', 'string', 'max:60'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_financial_responsible' => ['sometimes', 'boolean'],
            'is_emergency_contact' => ['sometimes', 'boolean'],
            'can_view_finance' => ['sometimes', 'boolean'],
            'can_view_academic' => ['sometimes', 'boolean'],
        ]);

        $student = Student::query()->findOrFail($studentId);
        $relation = ['institution_id' => $institutionId, 'student_id' => $student->id, 'guardian_id' => $data['guardian_id']];

        if (DB::table('student_guardian')->where($relation)->exists()) {
            return response()->json(['message' => 'O encarregado já está associado a este aluno.'], 409);
        }

        $relationship = $data['relationship'] ?? 'other';

        if (in_array($relationship, ['father', 'mother'], true) && DB::table('student_guardian')
            ->where(['institution_id' => $institutionId, 'student_id' => $student->id, 'relationship' => $relationship])->exists()) {
            return response()->json(['message' => 'O aluno já tem '.($relationship === 'father' ? 'um pai' : 'uma mãe').' associado(a).'], 409);
        }

        DB::transaction(function () use ($data, $relation, $relationship, $institutionId, $student) {
            if (! empty($data['is_primary'])) {
                DB::table('student_guardian')->where(['institution_id' => $institutionId, 'student_id' => $student->id])->update(['is_primary' => false]);
            }

            DB::table('student_guardian')->insert([...$relation, ...$data, 'relationship' => $relationship]);
        });

        return response()->json(['data' => $relation + ['relationship' => $relationship]], 201);
    }
}
