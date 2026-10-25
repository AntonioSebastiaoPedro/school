<?php

use App\Modules\Schools\Support\Filiation;

it('formata a filiação com os dois pais', function () {
    expect(Filiation::format('M', 'A', 'B'))->toBe('filho de A e de B')
        ->and(Filiation::format('F', 'A', 'B'))->toBe('filha de A e de B');
});

it('formata com um só progenitor', function () {
    expect(Filiation::format('F', 'A', null))->toBe('filha de A')
        ->and(Filiation::format('M', null, 'B'))->toBe('filho de B');
});

it('usa pontos quando não há dados, igual em todos os documentos', function () {
    expect(Filiation::format('M', null, null))->toBe('filho de '.Filiation::BLANK)
        ->and(Filiation::format(null, '', ''))->toBe('filho/a de '.Filiation::BLANK);
});
