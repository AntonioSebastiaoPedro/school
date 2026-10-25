<?php

namespace App\Modules\Schools\Assessment\Domain;

/** Nota por extenso. Só existe de 5 a 20; fora disso devolve "-". */
final class GradeInWords
{
    private const WORDS = [
        5 => 'Cinco', 6 => 'Seis', 7 => 'Sete', 8 => 'Oito', 9 => 'Nove', 10 => 'Dez',
        11 => 'Onze', 12 => 'Doze', 13 => 'Treze', 14 => 'Catorze', 15 => 'Quinze',
        16 => 'Dezasseis', 17 => 'Dezassete', 18 => 'Dezoito', 19 => 'Dezanove', 20 => 'Vinte',
    ];

    public static function of(int|float|null $grade): string
    {
        $n = (int) round((float) $grade);

        return isset(self::WORDS[$n]) ? self::WORDS[$n].' Valores' : '-';
    }
}
