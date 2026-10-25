<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Enrollment;
use App\Modules\Schools\Models\GradeLevel;
use App\Modules\Schools\Models\Room;
use App\Modules\Schools\Models\SchoolYear;
use App\Modules\Schools\Models\Section;
use App\Modules\Schools\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Anos lectivos, salas e turmas. */
class StructureController extends Controller
{
    public function gradeLevels(): JsonResponse
    {
        return response()->json(['data' => GradeLevel::query()->orderBy('grade_number')->get()]);
    }

    public function years(): JsonResponse
    {
        return response()->json(['data' => SchoolYear::query()->orderByDesc('starts_on')->get()]);
    }

    public function storeYear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'regex:/^[0-9]{4}\/[0-9]{4}$/'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], ['name.regex' => 'O ano lectivo deve ter o formato AAAA/AAAA (ex.: 2026/2027).']);

        [$from, $to] = array_map('intval', explode('/', $data['name']));
        if ($to !== $from + 1) {
            throw ValidationException::withMessages(['name' => 'O ano lectivo deve abranger anos consecutivos (ex.: 2026/2027).']);
        }

        // Nome repetido ou datas sobrepostas são recusados pela base de dados (409).
        return response()->json(['data' => DB::transaction(fn () => SchoolYear::create($data))->fresh()], 201);
    }

    /** Activa o ano; o ano activo anterior passa a fechado. */
    public function activateYear(int $id, CurrentTenant $tenant): JsonResponse
    {
        $year = SchoolYear::query()->findOrFail($id);

        if ($year->status !== 'planned' && $year->status !== 'active') {
            return response()->json(['message' => 'Só um ano planeado pode ser activado.'], 409);
        }

        DB::transaction(function () use ($year) {
            SchoolYear::query()->where('status', 'active')->whereKeyNot($year->id)->update(['status' => 'closed']);
            $year->update(['status' => 'active']);
        });

        Audit::record('school_year.activate', 'school_year', $year->id, $tenant->id());

        return response()->json(['data' => $year->fresh()]);
    }

    public function rooms(): JsonResponse
    {
        return response()->json(['data' => Room::query()->orderBy('name')->get()]);
    }

    public function storeRoom(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('rooms', 'name')->where('institution_id', $tenant->id())],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['data' => DB::transaction(fn () => Room::create($data))->fresh()], 201);
    }

    public function sections(Request $request): JsonResponse
    {
        $sections = Section::query()
            ->when($request->query('school_year_id'), fn ($q, $v) => $q->where('school_year_id', $v))
            ->when($request->query('grade_level_id') !== null, fn ($q) => $q->where('grade_level_id', (int) $request->query('grade_level_id')))
            ->withCount(['enrollments as enrolled' => fn ($q) => $q->whereIn('status', EnrollmentService::OCCUPYING)])
            ->orderBy('grade_level_id')->orderBy('name')
            ->get();

        return response()->json(['data' => $sections]);
    }

    public function storeSection(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $institutionId = $tenant->id();

        $data = $request->validate([
            'school_year_id' => ['required', 'integer', Rule::exists('school_years', 'id')->where('institution_id', $institutionId)->whereNot('status', 'closed')],
            'grade_level_id' => ['required', 'integer', 'between:0,9'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:50'],
            'shift' => ['required', Rule::in(['morning', 'afternoon', 'night'])],
            'capacity' => ['sometimes', 'integer', 'min:1'],
        ]);

        if (! empty($data['room_id']) && isset($data['capacity'])) {
            $roomCapacity = Room::query()->findOrFail($data['room_id'])->capacity;

            if ($data['capacity'] > $roomCapacity) {
                throw ValidationException::withMessages(['capacity' => "A capacidade da turma excede a da sala ({$roomCapacity})."]);
            }
        }

        return response()->json(['data' => DB::transaction(fn () => Section::create($data))->fresh()], 201);
    }

    /** Renumera a turma por ordem alfabética (nº de ordem da pauta). */
    public function renumber(int $id, EnrollmentService $enrollments): JsonResponse
    {
        $section = Section::query()->findOrFail($id);
        $enrollments->renumber($section);

        return response()->json(['data' => Enrollment::query()->where('section_id', $section->id)->orderBy('roll_number')->pluck('roll_number', 'id')]);
    }
}
