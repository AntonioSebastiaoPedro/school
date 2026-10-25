<?php

use App\Modules\Schools\Assessment\Domain\AssessmentRules;
use App\Modules\Schools\Assessment\Domain\GradeInWords;
use App\Modules\Schools\Assessment\Domain\PromotionRules;
use App\Modules\Schools\Assessment\Domain\YearOutcome;

beforeEach(fn () => $this->rules = new PromotionRules);

it('promove todos na Iniciação sem olhar a notas', function () {
    expect($this->rules->decide('none', null, []))->toBe(YearOutcome::Promoted);
});

it('devolve pendente sem nenhuma nota positiva', function () {
    expect($this->rules->decide('lenient', 5, ['LP' => 0, 'MAT' => null]))->toBe(YearOutcome::Pending);
});

it('regime brando: LP=5 e MAT=5 na 4.ª aprova', function () {
    expect($this->rules->decide('lenient', 5, ['LP' => 5, 'MAT' => 5, 'EM' => 7]))->toBe(YearOutcome::Promoted);
});

it('regime brando: LP e MAT negativas reprovam, sem recurso', function () {
    expect($this->rules->decide('lenient', 5, ['LP' => 4, 'MAT' => 4, 'EM' => 7]))->toBe(YearOutcome::Failed);
});

it('regime brando: 8.ª com LP=8, MAT=8 e BIO=12 reprova; LP=8, MAT=12, BIO=8 aprova', function () {
    expect($this->rules->decide('lenient', 10, ['LP' => 8, 'MAT' => 8, 'BIO' => 12]))->toBe(YearOutcome::Failed)
        ->and($this->rules->decide('lenient', 10, ['LP' => 8, 'MAT' => 12, 'BIO' => 8]))->toBe(YearOutcome::Promoted);
});

it('regime brando: três ou mais negativas reprovam e nunca dão recurso', function () {
    $finals = ['LP' => 8, 'MAT' => 12, 'BIO' => 8, 'FIS' => 8, 'QUI' => 12];

    expect($this->rules->decide('lenient', 10, $finals))->toBe(YearOutcome::Failed);
});

it('regime estrito: 0 negativas aprova, 1 a 2 dão recurso, 3 ou mais reprovam', function () {
    $ok = ['LP' => 6, 'MAT' => 7, 'CN' => 8];
    $one = ['LP' => 4, 'MAT' => 7, 'CN' => 8];
    $two = ['LP' => 4, 'MAT' => 3, 'CN' => 8];
    $three = ['LP' => 4, 'MAT' => 3, 'CN' => 2];

    expect($this->rules->decide('strict', 5, $ok))->toBe(YearOutcome::Promoted)
        ->and($this->rules->decide('strict', 5, $one))->toBe(YearOutcome::Resit)
        ->and($this->rules->decide('strict', 5, $two))->toBe(YearOutcome::Resit)
        ->and($this->rules->decide('strict', 5, $three))->toBe(YearOutcome::Failed);
});

it('regime estrito: depois do recurso aprova sem negativas e reprova nos restantes', function () {
    expect($this->rules->decide('strict', 10, ['LP' => 10, 'MAT' => 12], afterResit: true))->toBe(YearOutcome::Promoted)
        ->and($this->rules->decide('strict', 10, ['LP' => 8, 'MAT' => 12], afterResit: true))->toBe(YearOutcome::Failed);
});

it('ignora disciplinas com valor 0 ao contar negativas', function () {
    expect($this->rules->decide('strict', 5, ['LP' => 0, 'MAT' => 7]))->toBe(YearOutcome::Promoted);
});

it('lê os limites da configuração da escola', function () {
    $custom = new PromotionRules(AssessmentRules::fromArray([
        'lenient' => ['approve_max_negatives' => 1, 'fail_if_all_nuclear_negative' => false],
    ]));

    expect($custom->decide('lenient', 5, ['LP' => 4, 'MAT' => 7, 'EM' => 4]))->toBe(YearOutcome::Failed)
        ->and($custom->decide('lenient', 5, ['LP' => 4, 'MAT' => 7, 'EM' => 6]))->toBe(YearOutcome::Promoted);
});

it('escreve a nota por extenso de 5 a 20', function () {
    expect(GradeInWords::of(5))->toBe('Cinco Valores')
        ->and(GradeInWords::of(20))->toBe('Vinte Valores')
        ->and(GradeInWords::of(4))->toBe('-')
        ->and(GradeInWords::of(0))->toBe('-');
});
