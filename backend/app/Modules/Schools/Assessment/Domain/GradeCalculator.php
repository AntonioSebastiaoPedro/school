<?php

namespace App\Modules\Schools\Assessment\Domain;

/**
 * Cálculo de MT, MFD e MFA. 0 ou null significa "sem nota".
 */
final class GradeCalculator
{
    public function __construct(private readonly AssessmentRules $rules = new AssessmentRules) {}

    /** Média do trimestre (1 casa decimal). Sem nenhuma nota, 0. */
    public function mt(?float $mac, ?float $npp, ?float $npt): float
    {
        $present = $this->positives([$mac, $npp, $npt]);

        if ($present === []) {
            return 0.0;
        }

        $divisor = $this->rules->mtDivisorMode === 'fixed3' ? 3 : count($present);

        return round(array_sum($present) / $divisor, 1);
    }

    /**
     * Média final da disciplina: média dos MT preenchidos (1 casa decimal).
     *
     * @param  list<float|null>  $mts
     */
    public function mfd(array $mts): float
    {
        $present = $this->positives($mts);

        return $present === [] ? 0.0 : round(array_sum($present) / count($present), 1);
    }

    /**
     * Classificação final anual (inteiro).
     * Só as classes com exame nacional usam NE e NER; nas outras, round(MFD).
     */
    public function mfa(float $mfd, ?float $ne, ?float $ner, bool $hasNationalExam): int
    {
        if ($hasNationalExam) {
            if ($ner !== null && $ner > 0) {
                return (int) round($ner);
            }
            if ($ne !== null && $ne > 0) {
                return (int) round($this->rules->yearWeight * $mfd + $this->rules->examWeight * $ne);
            }
        }

        return (int) round($mfd);
    }

    /**
     * Média do certificado: média das classificações > 0 (inteiro).
     *
     * @param  list<int|float|null>  $finals
     */
    public function certificateAverage(array $finals): int
    {
        $present = $this->positives($finals);

        return $present === [] ? 0 : (int) round(array_sum($present) / count($present));
    }

    /**
     * @param  list<int|float|null>  $values
     * @return list<float>
     */
    private function positives(array $values): array
    {
        return array_values(array_map('floatval', array_filter($values, fn ($v) => $v !== null && $v > 0)));
    }
}
