<?php

namespace App\Modules\Schools\Services;

use App\Modules\Platform\Services\PlanLimits;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\Country;
use App\Modules\Schools\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentService
{
    /** Emissor por omissão quando o documento é indicado sem emissor. */
    private const DEFAULT_ISSUER = [
        'identity_card' => 'Direcção Nacional de Identificação',
        'personal_card' => 'Conservatória do Registo Civil',
        'birth_record' => 'Conservatória do Registo Civil',
        'passport' => 'Serviço de Migração e Estrangeiros',
    ];

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly StudentNumberGenerator $numbers,
        private readonly PlanLimits $limits,
    ) {}

    /** Nomes por extenso, sem forçar maiúsculas ("dos Santos" e "de" ficam como foram escritos). */
    public static function normalizeName(?string $name): ?string
    {
        $name = $name === null ? null : trim(preg_replace('/\s+/u', ' ', $name));

        return $name === '' ? null : $name;
    }

    /** BI: sem espaços, pontos ou hífens e em maiúsculas. Outros documentos: só aparado. */
    public static function normalizeDocumentNumber(?string $type, ?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        return $type === 'identity_card'
            ? strtoupper(preg_replace('/[\s.\-]/', '', $number))
            : trim($number);
    }

    public function create(array $data): Student
    {
        $institutionId = $this->tenant->id();

        return DB::transaction(function () use ($data, $institutionId) {
            $this->limits->assertCanAdd(
                $institutionId,
                'max_active_students',
                Student::query()->where('status', 'active')->count(),
            );

            $data = $this->withDefaults($data);
            $data['student_number'] = ($data['student_number'] ?? null) ?: $this->numbers->next($institutionId);

            return Student::create($data);
        });
    }

    public function update(Student $student, array $data): Student
    {
        $data = $this->withDefaults($data, $student);
        unset($data['student_number']);

        $student->update($data);

        return $student;
    }

    private function withDefaults(array $data, ?Student $existing = null): array
    {
        if ($existing === null) {
            $angola = Country::query()->where('iso2', 'AO')->value('id');
            $data['birth_country_id'] ??= $angola;
            $data['nationality_id'] ??= $angola;
        }

        $type = $data['document_type'] ?? $existing?->document_type;
        $hasDocument = $type !== null && ($data['document_number'] ?? $existing?->document_number) !== null;

        if ($hasDocument && empty($data['document_issuer']) && empty($existing?->document_issuer)) {
            $data['document_issuer'] = self::DEFAULT_ISSUER[$type];
        }

        return $data;
    }
}
