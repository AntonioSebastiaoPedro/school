<?php

namespace App\Modules\Schools\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Municipality;
use App\Modules\Schools\Models\SchoolLink;
use App\Modules\Schools\Models\SchoolProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolProfileController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => SchoolProfile::query()->firstOrFail()]);
    }

    public function update(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'school_code' => ['nullable', 'string', 'max:100', Rule::unique('school_profiles', 'school_code')->ignore($tenant->id(), 'institution_id')],
            'ex_code' => ['nullable', 'string', 'max:100'],
            'decree' => ['nullable', 'string', 'max:100'],
            'director_name' => ['required', 'string', 'max:255'],
            'director_gender' => ['required', Rule::in(['M', 'F'])],
            'director_phone' => ['nullable', 'string', 'max:40'],
            'is_complex' => ['sometimes', 'boolean'],
            'regime' => ['required', Rule::in(['public', 'private', 'mixed'])],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'education_office_id' => ['nullable', 'integer', 'exists:education_offices,id'],
            'district' => ['nullable', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'active_trimester' => ['sometimes', 'integer', 'between:1,3'],
        ]);

        if (! empty($data['municipality_id'])) {
            // A província deriva sempre do município: não aceitamos combinações incoerentes.
            $data['province_id'] = Municipality::findOrFail($data['municipality_id'])->province_id;
        }

        $profile = SchoolProfile::query()->firstOrFail();
        $profile->update($data);

        Audit::record('school_profile.update', 'school_profile', $profile->institution_id, $tenant->id());

        return response()->json(['data' => $profile]);
    }

    public function links(): JsonResponse
    {
        return response()->json(['data' => SchoolLink::query()->get()]);
    }

    /** Escola certificadora por grupo de nível (escolas privadas e mistas). */
    public function putLink(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'level_group' => ['required', Rule::in(['primary', 'lower_secondary'])],
            'public_institution_id' => ['nullable', 'integer', Rule::exists('school_profiles', 'institution_id')->where('regime', 'public'), Rule::notIn([$tenant->id()])],
            'certifying_school_name' => ['nullable', 'required_without:public_institution_id', 'string', 'max:255'],
            'certifying_director_name' => ['nullable', 'required_without:public_institution_id', 'string', 'max:255'],
            'certifying_director_gender' => ['nullable', 'required_without:public_institution_id', Rule::in(['M', 'F'])],
            'certifying_school_code' => ['nullable', 'string', 'max:100'],
            'certifying_ex_code' => ['nullable', 'string', 'max:100'],
            'certifying_district' => ['nullable', 'string', 'max:100'],
            'certifying_decree' => ['nullable', 'string', 'max:100'],
        ]);

        $link = SchoolLink::updateOrCreate(['level_group' => $data['level_group']], $data);

        return response()->json(['data' => $link]);
    }
}
