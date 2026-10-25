<?php

namespace App\Modules\Schools\Assessment\Domain;

/**
 * Única definição de "Aprovado" do sistema (pauta, termo, promoção e fecho do ano).
 *
 * Regime estrito (classes com exame nacional, 6.ª e 9.ª): 0 negativas aprova,
 * 1–2 dão recurso, 3 ou mais reprovam. Depois do recurso, aprova quem não tem
 * negativas e reprova os restantes.
 *
 * Regime brando (restantes classes): não há recurso. Até 2 negativas aprova,
 * excepto se as disciplinas nucleares (LP e MAT) forem todas negativas; mais
 * negativas reprovam.
 */
final class PromotionRules
{
    public function __construct(private readonly AssessmentRules $rules = new AssessmentRules) {}

    /**
     * @param  'none'|'lenient'|'strict'  $regime
     * @param  array<string, int|float|null>  $finals  classificação final por código de disciplina
     * @param  bool  $afterResit  o aluno já fez exame de recurso (só relevante no regime estrito)
     */
    public function decide(string $regime, ?int $passMark, array $finals, bool $afterResit = false): YearOutcome
    {
        if ($regime === 'none') {
            return YearOutcome::Promoted;
        }

        $graded = array_filter($finals, fn ($v) => $v !== null && $v > 0);

        if ($graded === [] || $passMark === null) {
            return YearOutcome::Pending;
        }

        $negatives = count(array_filter($graded, fn ($v) => $v < $passMark));

        return $regime === 'strict'
            ? $this->strict($negatives, $afterResit)
            : $this->lenient($negatives, $graded, $passMark);
    }

    public function negatives(?int $passMark, array $finals): int
    {
        if ($passMark === null) {
            return 0;
        }

        return count(array_filter($finals, fn ($v) => $v !== null && $v > 0 && $v < $passMark));
    }

    private function strict(int $negatives, bool $afterResit): YearOutcome
    {
        if ($negatives <= $this->rules->strictApproveMaxNegatives) {
            return YearOutcome::Promoted;
        }

        if ($afterResit || $negatives > $this->rules->strictResitMaxNegatives) {
            return YearOutcome::Failed;
        }

        return YearOutcome::Resit;
    }

    /** @param array<string, int|float|null> $graded */
    private function lenient(int $negatives, array $graded, int $passMark): YearOutcome
    {
        if ($negatives > $this->rules->lenientApproveMaxNegatives) {
            return YearOutcome::Failed;
        }

        if ($this->rules->lenientFailIfAllNuclearNegative && $this->allNuclearNegative($graded, $passMark)) {
            return YearOutcome::Failed;
        }

        return YearOutcome::Promoted;
    }

    /** @param array<string, int|float|null> $graded */
    private function allNuclearNegative(array $graded, int $passMark): bool
    {
        $nuclear = $this->rules->nuclearSubjects;

        foreach ($nuclear as $code) {
            if (! isset($graded[$code]) || $graded[$code] >= $passMark) {
                return false;
            }
        }

        return $nuclear !== [];
    }
}
