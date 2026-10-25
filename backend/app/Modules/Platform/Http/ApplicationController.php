<?php

namespace App\Modules\Platform\Http;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\InstitutionApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    /** Pedido público de adesão. Não concede acesso operacional (RN-001). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institution_type' => ['required', Rule::in(['school', 'training_center', 'both'])],
            'proposed_name' => ['required', 'string', 'max:180'],
            'applicant_name' => ['required', 'string', 'max:180'],
            'applicant_email' => ['required', 'email', 'max:254'],
            'applicant_phone' => ['required', 'string', 'max:40'],
            'tax_identifier' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'school_profile' => ['exclude_if:institution_type,training_center', 'required', 'array'],
            'school_profile.director_name' => ['required_with:school_profile', 'string', 'max:255'],
            'school_profile.director_gender' => ['required_with:school_profile', Rule::in(['M', 'F'])],
            'school_profile.regime' => ['required_with:school_profile', Rule::in(['public', 'private', 'mixed'])],
            'school_profile.is_complex' => ['sometimes', 'boolean'],
            'school_profile.school_code' => ['nullable', 'string', 'max:100'],
            'school_profile.ex_code' => ['nullable', 'string', 'max:100'],
            'school_profile.decree' => ['nullable', 'string', 'max:100'],
            'school_profile.director_phone' => ['nullable', 'string', 'max:40'],
            'school_profile.province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'school_profile.municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
        ]);

        $application = InstitutionApplication::create($data)->refresh();

        return response()->json(['data' => ['id' => $application->id, 'status' => $application->status]], 201);
    }
}
