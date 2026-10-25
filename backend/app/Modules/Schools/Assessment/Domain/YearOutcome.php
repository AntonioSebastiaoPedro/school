<?php

namespace App\Modules\Schools\Assessment\Domain;

enum YearOutcome: string
{
    case Pending = 'pending';
    case Promoted = 'promoted';
    case Resit = 'resit';
    case Failed = 'failed';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Promoted => 'Aprovado',
            self::Resit => 'Recurso',
            self::Failed => 'Reprovado',
            self::Dropped => 'Desistente',
        };
    }
}
