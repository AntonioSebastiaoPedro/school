<?php

use App\Modules\Finance\Money;

it('converte para cêntimos sem erros de vírgula flutuante', function () {
    expect(Money::cents('0.10') + Money::cents('0.20'))->toBe(30)
        ->and(Money::cents('70000.5'))->toBe(7000050)
        ->and(Money::cents(45000))->toBe(4500000)
        ->and(Money::cents(19.99))->toBe(1999)
        ->and(Money::cents('-5.25'))->toBe(-525);
});

it('formata com duas casas', function () {
    expect(Money::format(7000050))->toBe('70000.50')
        ->and(Money::format(5))->toBe('0.05')
        ->and(Money::format(-525))->toBe('-5.25');
});

it('recusa valores que não são monetários', function () {
    expect(fn () => Money::cents('12,5'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::cents('1.234'))->toThrow(InvalidArgumentException::class);
});
