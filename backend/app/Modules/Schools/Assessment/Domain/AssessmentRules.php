<?php

namespace App\Modules\Schools\Assessment\Domain;

/**
 * Parâmetros de avaliação de uma escola. Os valores por omissão são os de
 * docs/regras-ensino.md; cada escola pode ter uma versão própria (assessment_rules).
 */
final class AssessmentRules
{
    /**
     * @param  list<string>  $nuclearSubjects
     */
    public function __construct(
        public readonly string $mtDivisorMode = 'present',
        public readonly float $yearWeight = 0.4,
        public readonly float $examWeight = 0.6,
        public readonly array $nuclearSubjects = ['LP', 'MAT'],
        public readonly int $strictApproveMaxNegatives = 0,
        public readonly int $strictResitMaxNegatives = 2,
        public readonly int $lenientApproveMaxNegatives = 2,
        public readonly bool $lenientFailIfAllNuclearNegative = true,
        public readonly ?float $attendanceLimit = null,
        public readonly bool $dropoutEnabled = false,
    ) {}

    /** @param array<string, mixed> $data estrutura do OpenAPI (components/schemas/AssessmentRules) */
    public static function fromArray(array $data): self
    {
        $d = new self;

        return new self(
            mtDivisorMode: $data['mt_divisor_mode'] ?? $d->mtDivisorMode,
            yearWeight: (float) ($data['weights']['year'] ?? $d->yearWeight),
            examWeight: (float) ($data['weights']['exam'] ?? $d->examWeight),
            nuclearSubjects: $data['nuclear_subjects'] ?? $d->nuclearSubjects,
            strictApproveMaxNegatives: (int) ($data['strict']['approve_max_negatives'] ?? $d->strictApproveMaxNegatives),
            strictResitMaxNegatives: (int) ($data['strict']['resit_max_negatives'] ?? $d->strictResitMaxNegatives),
            lenientApproveMaxNegatives: (int) ($data['lenient']['approve_max_negatives'] ?? $d->lenientApproveMaxNegatives),
            lenientFailIfAllNuclearNegative: (bool) ($data['lenient']['fail_if_all_nuclear_negative'] ?? $d->lenientFailIfAllNuclearNegative),
            attendanceLimit: isset($data['attendance_limit']) ? (float) $data['attendance_limit'] : null,
            dropoutEnabled: (bool) ($data['dropout_enabled'] ?? $d->dropoutEnabled),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mt_divisor_mode' => $this->mtDivisorMode,
            'weights' => ['year' => $this->yearWeight, 'exam' => $this->examWeight],
            'nuclear_subjects' => $this->nuclearSubjects,
            'strict' => [
                'approve_max_negatives' => $this->strictApproveMaxNegatives,
                'resit_max_negatives' => $this->strictResitMaxNegatives,
            ],
            'lenient' => [
                'approve_max_negatives' => $this->lenientApproveMaxNegatives,
                'fail_if_all_nuclear_negative' => $this->lenientFailIfAllNuclearNegative,
            ],
            'attendance_limit' => $this->attendanceLimit,
            'dropout_enabled' => $this->dropoutEnabled,
        ];
    }
}
