<?php

namespace App\Modules\Schools\Assessment\Domain;

/**
 * Escala de uma classe: 0–10 (nota mínima 5) até à 6.ª, 0–20 (mínima 10) da 7.ª à 9.ª.
 * Na Iniciação não há escala (max e passMark nulos).
 */
final class GradeScale
{
    public function __construct(
        public readonly ?int $max,
        public readonly ?int $passMark,
    ) {}

    public function hasGrades(): bool
    {
        return $this->max !== null;
    }

    public function accepts(float $value): bool
    {
        return $this->hasGrades() && $value >= 0 && $value <= $this->max;
    }

    /** 0 é "sem nota" e nunca negativa. */
    public function isNegative(float $value): bool
    {
        return $this->hasGrades() && $value > 0 && $value < $this->passMark;
    }

    public function isPositive(float $value): bool
    {
        return $this->hasGrades() && $value >= $this->passMark;
    }
}
