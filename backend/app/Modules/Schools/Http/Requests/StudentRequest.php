<?php

namespace App\Modules\Schools\Http\Requests;

use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Country;
use App\Modules\Schools\Services\StudentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['first_name', 'last_name', 'father_name', 'mother_name'] as $field) {
            if ($this->has($field)) {
                $merge[$field] = StudentService::normalizeName($this->input($field));
            }
        }

        if ($this->has('document_number')) {
            $merge['document_number'] = StudentService::normalizeDocumentNumber($this->input('document_type'), $this->input('document_number'));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $tenant = app(CurrentTenant::class)->id();
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        $country = $this->input('birth_country_id') ?? Country::query()->where('iso2', 'AO')->value('id');

        return [
            'student_number' => ['nullable', 'string', 'max:30', Rule::unique('students', 'student_number')->where('institution_id', $tenant)],
            'first_name' => [$required, 'string', 'max:255'],
            'last_name' => [$required, 'string', 'max:255'],
            'gender' => [$required, Rule::in(['M', 'F'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'nationality_id' => ['nullable', 'integer', 'exists:countries,id'],
            'province_id' => ['nullable', 'integer', 'required_with:municipality_id', Rule::exists('provinces', 'id')->where('country_id', $country)],
            'municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')->where('province_id', $this->input('province_id'))],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'required_with:document_number', Rule::in(['birth_record', 'personal_card', 'identity_card', 'passport'])],
            'document_number' => [
                'nullable', 'required_with:document_type', 'string', 'max:50',
                Rule::when($this->input('document_type') === 'identity_card', ['regex:/^[0-9]{9}[A-Z]{2}[0-9]{3}$/']),
                Rule::unique('students', 'document_number')->where('institution_id', $tenant)->ignore($this->route('id')),
            ],
            'document_issuer' => ['nullable', 'string', 'max:255'],
            'document_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }

    public function messages(): array
    {
        return [
            'document_number.regex' => 'O B.I. deve ter 9 dígitos, 2 letras e 3 dígitos (ex.: 006151112LA041).',
            'document_number.unique' => 'Já existe um aluno com este número de documento.',
            'birth_date.before' => 'A data de nascimento deve ser anterior a hoje.',
        ];
    }
}
