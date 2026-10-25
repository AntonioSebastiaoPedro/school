<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Assessment\Domain\AssessmentRules;
use App\Modules\Schools\Models\AssessmentRuleSet;
use App\Modules\Schools\Models\GradeLevelSubject;
use App\Modules\Schools\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CurriculumController extends Controller
{
    public function subjects(): JsonResponse
    {
        return response()->json(['data' => Subject::query()->orderBy('code')->get()]);
    }

    public function storeSubject(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('subjects', 'code')->where('institution_id', $tenant->id())],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['sometimes', Rule::in(['core', 'optional'])],
            'credits' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json(['data' => Subject::create($data)->fresh()], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['grade_level_id' => ['nullable', 'integer', 'between:0,9']]);

        $rows = GradeLevelSubject::query()
            ->with('subject')
            ->when($request->query('grade_level_id') !== null, fn ($q) => $q->where('grade_level_id', (int) $request->query('grade_level_id')))
            ->orderBy('grade_level_id')->orderBy('display_order')
            ->get();

        return response()->json(['data' => $rows->groupBy('grade_level_id')]);
    }

    public function sync(Request $request, int $gradeLevelId, CurrentTenant $tenant): JsonResponse
    {
        abort_unless($gradeLevelId >= 0 && $gradeLevelId <= 9, 404);

        $data = $request->validate([
            'subjects' => ['required', 'array'],
            'subjects.*.subject_id' => ['required', 'integer', 'distinct', Rule::exists('subjects', 'id')->where('institution_id', $tenant->id())],
            'subjects.*.weekly_hours' => ['required', 'integer', 'min:1'],
            'subjects.*.is_active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $gradeLevelId) {
            foreach ($data['subjects'] as $order => $row) {
                GradeLevelSubject::updateOrCreate(
                    ['grade_level_id' => $gradeLevelId, 'subject_id' => $row['subject_id']],
                    ['weekly_hours' => $row['weekly_hours'], 'is_active' => $row['is_active'] ?? true, 'display_order' => $order + 1],
                );
            }
        });

        Audit::record('curriculum.update', 'grade_level', $gradeLevelId, $tenant->id());

        return $this->index(new Request(['grade_level_id' => $gradeLevelId]));
    }

    public function rules(): JsonResponse
    {
        return response()->json(['data' => AssessmentRuleSet::query()->where('is_active', true)->firstOrFail()->rules]);
    }

    /** Nova versão das regras de avaliação; a anterior fica guardada e inactiva. */
    public function putRules(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'mt_divisor_mode' => ['sometimes', Rule::in(['present', 'fixed3'])],
            'weights.year' => ['sometimes', 'numeric', 'between:0,1'],
            'weights.exam' => ['sometimes', 'numeric', 'between:0,1'],
            'nuclear_subjects' => ['sometimes', 'array'],
            'nuclear_subjects.*' => ['string', 'max:10'],
            'strict.approve_max_negatives' => ['sometimes', 'integer', 'min:0'],
            'strict.resit_max_negatives' => ['sometimes', 'integer', 'min:0'],
            'lenient.approve_max_negatives' => ['sometimes', 'integer', 'min:0'],
            'lenient.fail_if_all_nuclear_negative' => ['sometimes', 'boolean'],
            'attendance_limit' => ['nullable', 'numeric', 'between:0,100'],
            'dropout_enabled' => ['sometimes', 'boolean'],
        ]);

        $rules = AssessmentRules::fromArray($data)->toArray();

        $version = DB::transaction(function () use ($rules, $request) {
            $next = (int) AssessmentRuleSet::query()->max('version') + 1;
            AssessmentRuleSet::query()->where('is_active', true)->update(['is_active' => false]);

            return AssessmentRuleSet::create(['version' => $next, 'rules' => $rules, 'is_active' => true, 'created_by' => $request->user()->id]);
        });

        Audit::record('assessment_rules.update', 'assessment_rules', $version->id, $tenant->id(), after: $rules);

        return response()->json(['data' => $version->rules, 'version' => $version->version], 201);
    }
}
