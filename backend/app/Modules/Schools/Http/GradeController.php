<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\GradeSheetLock;
use App\Modules\Schools\Models\Section;
use App\Modules\Schools\Models\Subject;
use App\Modules\Schools\Services\GradeService;
use App\Modules\Schools\Services\GradeSheetService;
use App\Modules\Schools\Services\YearClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    public function grid(Request $request, GradeService $grades, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('institution_id', $tenant->id())],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('institution_id', $tenant->id())],
            'term' => ['required', 'integer', 'between:0,3'],
        ]);

        return response()->json($grades->grid(
            Section::query()->findOrFail($data['section_id']),
            Subject::query()->findOrFail($data['subject_id']),
            $data['term'],
        ));
    }

    public function save(Request $request, GradeService $grades, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('institution_id', $tenant->id())],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('institution_id', $tenant->id())],
            'term' => ['required', 'integer', 'between:0,3'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.enrollment_id' => ['required', 'integer'],
            'entries.*.kind' => ['required', Rule::in(['mac', 'npp', 'npt', 'exam', 'resit'])],
            'entries.*.value' => ['nullable'],
        ]);

        $saved = $grades->save(
            Section::query()->findOrFail($data['section_id']),
            Subject::query()->findOrFail($data['subject_id']),
            $data['term'],
            $data['entries'],
            $request->user()->id,
        );

        return response()->json(['saved' => $saved]);
    }

    public function sheet(Request $request, int $sectionId, GradeSheetService $sheets): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['term', 'final', 'resit'])],
            'term' => ['nullable', 'integer', 'between:1,3'],
        ]);

        return response()->json($sheets->build(Section::query()->findOrFail($sectionId), $data['type'], $data['term'] ?? null));
    }

    public function lock(Request $request, int $sectionId, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'term' => ['required', 'integer', 'between:0,3'],
            'locked' => ['required', 'boolean'],
        ]);

        $section = Section::query()->findOrFail($sectionId);
        $key = ['section_id' => $section->id, 'term' => $data['term']];

        if ($data['locked']) {
            GradeSheetLock::query()->firstOrCreate($key, ['locked_by' => $request->user()->id]);
        } else {
            GradeSheetLock::query()->where($key)->delete();
        }

        Audit::record($data['locked'] ? 'grade_sheet.lock' : 'grade_sheet.unlock', 'section', $section->id, $tenant->id(), after: $data);

        return response()->json(['data' => ['term' => $data['term'], 'locked' => $data['locked']]]);
    }

    public function closeYear(Request $request, int $id, YearClosingService $closing, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate(['recompute' => ['sometimes', 'boolean']]);

        $section = Section::query()->findOrFail($id);
        $results = $closing->close($section, $data['recompute'] ?? false);

        Audit::record('section.close_year', 'section', $section->id, $tenant->id(), after: ['students' => count($results)]);

        return response()->json(['data' => $results]);
    }
}
