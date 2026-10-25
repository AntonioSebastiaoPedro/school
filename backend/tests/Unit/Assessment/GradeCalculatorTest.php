<?php

use App\Modules\Schools\Assessment\Domain\AssessmentRules;
use App\Modules\Schools\Assessment\Domain\GradeCalculator;
use App\Modules\Schools\Assessment\Domain\GradeScale;

it('calcula o MT com os três componentes', function () {
    expect((new GradeCalculator)->mt(8, 6, 7))->toBe(7.0);
});

it('calcula o MT sobre os componentes presentes', function () {
    $calc = new GradeCalculator;

    expect($calc->mt(12, null, null))->toBe(12.0)
        ->and($calc->mt(8, 0, 6))->toBe(7.0)
        ->and($calc->mt(null, null, null))->toBe(0.0);
});

it('suporta o divisor fixo 3 do sistema antigo por configuração', function () {
    $calc = new GradeCalculator(new AssessmentRules(mtDivisorMode: 'fixed3'));

    expect($calc->mt(12, null, null))->toBe(4.0);
});

it('calcula o MFD só com os trimestres preenchidos', function () {
    $calc = new GradeCalculator;

    expect($calc->mfd([7.0, 8.0, null]))->toBe(7.5)
        ->and($calc->mfd([0.0, null, null]))->toBe(0.0);
});

it('arredonda a MFA ao inteiro em classes sem exame', function () {
    expect((new GradeCalculator)->mfa(7.5, null, null, false))->toBe(8);
});

it('combina 40 por cento do ano e 60 por cento do exame nas classes de exame', function () {
    // 0,4 × 8 + 0,6 × 6 = 6,8 → 7
    expect((new GradeCalculator)->mfa(8.0, 6, null, true))->toBe(7);
});

it('usa o NER no lugar da classificação quando existe recurso', function () {
    expect((new GradeCalculator)->mfa(8.0, 3, 6, true))->toBe(6);
});

it('usa a MFD quando a classe de exame ainda não tem NE', function () {
    expect((new GradeCalculator)->mfa(6.4, null, null, true))->toBe(6);
});

it('ignora NE e NER em classes sem exame nacional', function () {
    expect((new GradeCalculator)->mfa(6.0, 9, 9, false))->toBe(6);
});

it('calcula a média do certificado só com classificações positivas', function () {
    expect((new GradeCalculator)->certificateAverage([10, 12, 0, null, 14]))->toBe(12);
});

it('respeita a escala da classe', function () {
    $primary = new GradeScale(10, 5);

    expect($primary->accepts(10))->toBeTrue()
        ->and($primary->accepts(10.5))->toBeFalse()
        ->and($primary->accepts(-1))->toBeFalse()
        ->and($primary->isNegative(4))->toBeTrue()
        ->and($primary->isNegative(0))->toBeFalse()
        ->and($primary->isPositive(5))->toBeTrue();
});

it('não tem escala na Iniciação', function () {
    $preschool = new GradeScale(null, null);

    expect($preschool->hasGrades())->toBeFalse()
        ->and($preschool->accepts(5))->toBeFalse();
});
