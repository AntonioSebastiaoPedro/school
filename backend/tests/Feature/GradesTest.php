<?php

use App\Modules\Schools\Models\Enrollment;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->school = makeSchool('Escola A');
    $this->director = makeMember($this->school);
    $this->year = makeYear($this->school);
    $this->api = fn (?object $user = null) => asMember($user ?? $this->director, $this->school);
});

/** Lança uma única nota (MAC do 1.º trimestre) igual à classificação final pretendida. */
function giveFinals(object $t, $section, Enrollment $e, array $finals, int $term = 1): void
{
    foreach ($finals as $code => $value) {
        ($t->api)()->putJson('/api/v1/grades', [
            'section_id' => $section->id, 'subject_id' => subjectId($t->school, $code), 'term' => $term,
            'entries' => [['enrollment_id' => $e->id, 'kind' => 'mac', 'value' => $value]],
        ])->assertOk();
    }
}

function lockTerms(object $t, $section, array $terms = [1, 2, 3]): void
{
    foreach ($terms as $term) {
        ($t->api)()->putJson("/api/v1/grade-sheets/{$section->id}/locks", ['term' => $term, 'locked' => true])->assertOk();
    }
}

function enrolledStudent(object $t, $section, array $studentAttrs = [], int $age = 10): Enrollment
{
    return makeActiveEnrollment($t->school, $t->year, $section, makeStudent($t->school, $studentAttrs, $age));
}

describe('lançamento de notas', function () {
    beforeEach(function () {
        $this->section = makeSection($this->school, $this->year, 4);
        $this->e = enrolledStudent($this, $this->section);
        $this->lp = subjectId($this->school, 'LP');
    });

    it('aceita vírgula decimal, arredonda a 2 casas e calcula o MT pelos componentes presentes', function () {
        ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $this->section->id, 'subject_id' => $this->lp, 'term' => 1,
            'entries' => [
                ['enrollment_id' => $this->e->id, 'kind' => 'mac', 'value' => '8,456'],
                ['enrollment_id' => $this->e->id, 'kind' => 'npp', 'value' => 6],
            ],
        ])->assertOk()->assertJsonPath('saved', 1);

        $row = ($this->api)()->getJson("/api/v1/grades?section_id={$this->section->id}&subject_id={$this->lp}&term=1")
            ->assertOk()->assertJsonPath('scale.max_score', 10)->assertJsonPath('scale.pass_mark', 5)->json('rows.0');

        expect($row['mac'])->toEqual(8.46)->and($row['npp'])->toEqual(6)->and($row['npt'])->toBeNull()->and($row['mt'])->toEqual(7.2);
    });

    it('0 ou vazio apaga a nota: sem nota nunca é negativa', function () {
        $save = fn ($value) => ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $this->section->id, 'subject_id' => $this->lp, 'term' => 1,
            'entries' => [['enrollment_id' => $this->e->id, 'kind' => 'mac', 'value' => $value]],
        ])->assertOk();

        $save(7);
        expect(DB::table('grades')->count())->toBe(1);
        $save(0);
        expect(DB::table('grades')->count())->toBe(0)->and(DB::table('grade_averages')->count())->toBe(0);
        $save(7);
        $save('');
        expect(DB::table('grades')->count())->toBe(0);
    });

    it('rejeita notas fora da escala da classe', function () {
        $save = fn ($value, $section = null, $subject = null) => ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => ($section ?? $this->section)->id, 'subject_id' => $subject ?? $this->lp, 'term' => 1,
            'entries' => [['enrollment_id' => ($section ? $this->e8 : $this->e)->id, 'kind' => 'mac', 'value' => $value]],
        ]);

        $this->section8 = makeSection($this->school, $this->year, 8);
        $this->e8 = enrolledStudent($this, $this->section8, age: 13);

        $save(10.5)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains($e['entries.0.value'][0], 'entre 0 e 10'));
        $save(10)->assertOk();
        $save(-1)->assertUnprocessable();
        $save('abc')->assertUnprocessable();
        $save(20.5, $this->section8)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains($e['entries.0.value'][0], 'entre 0 e 20'));
        $save(11, $this->section8)->assertOk();
    });

    it('não se lançam notas na Iniciação', function () {
        $init = makeSection($this->school, $this->year, 0);
        $e = enrolledStudent($this, $init, age: 5);

        ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $init->id, 'subject_id' => subjectId($this->school, 'CL'), 'term' => 1,
            'entries' => [['enrollment_id' => $e->id, 'kind' => 'mac', 'value' => 5]],
        ])->assertUnprocessable()->assertJsonPath('errors.section_id.0', 'Na Iniciação não se lançam notas.');

        ($this->api)()->getJson("/api/v1/grades?section_id={$init->id}&subject_id=".subjectId($this->school, 'CL').'&term=1')->assertUnprocessable();
    });

    it('só aceita disciplinas leccionadas na classe', function () {
        ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $this->section->id, 'subject_id' => subjectId($this->school, 'BIO'), 'term' => 1,
            'entries' => [['enrollment_id' => $this->e->id, 'kind' => 'mac', 'value' => 5]],
        ])->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    });

    it('exame e recurso só existem na 6.ª e na 9.ª', function () {
        ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $this->section->id, 'subject_id' => $this->lp, 'term' => 0,
            'entries' => [['enrollment_id' => $this->e->id, 'kind' => 'exam', 'value' => 5]],
        ])->assertUnprocessable()->assertJsonValidationErrors('term');

        // tipo e período têm de ser coerentes
        ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $this->section->id, 'subject_id' => $this->lp, 'term' => 1,
            'entries' => [['enrollment_id' => $this->e->id, 'kind' => 'exam', 'value' => 5]],
        ])->assertUnprocessable();
    });

    it('só se lançam notas a alunos matriculados e confirmados na turma', function () {
        $other = makeSection($this->school, $this->year, 4, 'B');
        $stranger = enrolledStudent($this, $other);
        $pending = makeActiveEnrollment($this->school, $this->year, $this->section, makeStudent($this->school), 'pending');

        foreach ([$stranger, $pending] as $e) {
            ($this->api)()->putJson('/api/v1/grades', [
                'section_id' => $this->section->id, 'subject_id' => $this->lp, 'term' => 1,
                'entries' => [['enrollment_id' => $e->id, 'kind' => 'mac', 'value' => 5]],
            ])->assertUnprocessable()->assertJsonValidationErrors('entries.0.enrollment_id');
        }
    });

    it('pauta fechada recusa gravação até ser reaberta', function () {
        $put = fn () => ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $this->section->id, 'subject_id' => $this->lp, 'term' => 1,
            'entries' => [['enrollment_id' => $this->e->id, 'kind' => 'mac', 'value' => 6]],
        ]);

        lockTerms($this, $this->section, [1]);
        $put()->assertStatus(409);
        ($this->api)()->getJson("/api/v1/grades?section_id={$this->section->id}&subject_id={$this->lp}&term=1")->assertJsonPath('locked', true);

        ($this->api)()->putJson("/api/v1/grade-sheets/{$this->section->id}/locks", ['term' => 1, 'locked' => false])->assertOk();
        $put()->assertOk();
    });

    it('o professor lança notas mas não fecha pautas; a secretaria nem lança', function () {
        $teacher = makeMember($this->school, 'teacher');
        $secretary = makeMember($this->school, 'secretary');
        $body = [
            'section_id' => $this->section->id, 'subject_id' => $this->lp, 'term' => 1,
            'entries' => [['enrollment_id' => $this->e->id, 'kind' => 'mac', 'value' => 6]],
        ];

        ($this->api)($teacher)->putJson('/api/v1/grades', $body)->assertOk();
        ($this->api)($teacher)->putJson("/api/v1/grade-sheets/{$this->section->id}/locks", ['term' => 1, 'locked' => true])->assertForbidden();
        ($this->api)($secretary)->putJson('/api/v1/grades', $body)->assertForbidden();
        ($this->api)($secretary)->getJson("/api/v1/grades?section_id={$this->section->id}&subject_id={$this->lp}&term=1")->assertOk();
    });

    it('não lê nem grava notas de turmas de outra instituição', function () {
        $other = makeSchool('Escola B');
        $otherYear = makeYear($other);
        $otherSection = makeSection($other, $otherYear, 4);
        $otherE = makeActiveEnrollment($other, $otherYear, $otherSection, makeStudent($other));

        ($this->api)()->getJson("/api/v1/grades?section_id={$otherSection->id}&subject_id={$this->lp}&term=1")->assertUnprocessable();
        ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $otherSection->id, 'subject_id' => $this->lp, 'term' => 1,
            'entries' => [['enrollment_id' => $otherE->id, 'kind' => 'mac', 'value' => 9]],
        ])->assertUnprocessable();
        ($this->api)()->getJson("/api/v1/grade-sheets/{$otherSection->id}?type=final")->assertNotFound();

        expect(DB::table('grades')->count())->toBe(0);
    });
});

describe('médias materializadas', function () {
    it('calcula MT, MFD e MFA a partir das notas', function () {
        $section = makeSection($this->school, $this->year, 4);
        $e = enrolledStudent($this, $section);
        $lp = subjectId($this->school, 'LP');

        $put = fn ($term, $kind, $value) => ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $section->id, 'subject_id' => $lp, 'term' => $term,
            'entries' => [['enrollment_id' => $e->id, 'kind' => $kind, 'value' => $value]],
        ])->assertOk();

        foreach ([['mac', 8], ['npp', 6], ['npt', 7]] as [$k, $v]) {
            $put(1, $k, $v);
        }   // MT1 = 7,0
        $put(2, 'mac', 5);                                                                // MT2 = 5,0

        $avg = (array) DB::table('grade_averages')->where(['enrollment_id' => $e->id, 'subject_id' => $lp])->first();

        expect((float) $avg['term1_avg'])->toBe(7.0)->and((float) $avg['term2_avg'])->toBe(5.0)->and($avg['term3_avg'])->toBeNull()
            ->and((float) $avg['year_avg'])->toBe(6.0)->and($avg['final_avg'])->toBe(6)->and($avg['exam'])->toBeNull();
    });

    it('nas classes de exame combina 40% do ano e 60% do exame; o recurso substitui', function () {
        $section = makeSection($this->school, $this->year, 6);
        $e = enrolledStudent($this, $section, age: 11);
        $lp = subjectId($this->school, 'LP');

        $put = fn ($term, $kind, $value) => ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $section->id, 'subject_id' => $lp, 'term' => $term,
            'entries' => [['enrollment_id' => $e->id, 'kind' => $kind, 'value' => $value]],
        ])->assertOk();
        $final = fn () => DB::table('grade_averages')->where(['enrollment_id' => $e->id, 'subject_id' => $lp])->value('final_avg');

        $put(1, 'mac', 8);       // MFD 8
        expect($final())->toBe(8);
        $put(0, 'exam', 6);      // 0,4×8 + 0,6×6 = 6,8
        expect($final())->toBe(7);
        $put(0, 'resit', 5);     // NER substitui
        expect($final())->toBe(5);
        $put(0, 'resit', 0);     // sem recurso volta ao valor anterior
        expect($final())->toBe(7);
    });

    it('usa as regras da escola, por exemplo o divisor fixo do sistema antigo', function () {
        ($this->api)()->putJson('/api/v1/assessment-rules', ['mt_divisor_mode' => 'fixed3'])->assertCreated();
        $section = makeSection($this->school, $this->year, 4);
        $e = enrolledStudent($this, $section);

        giveFinals($this, $section, $e, ['LP' => 12 / 2 * 1]);   // uma só nota de 6 → 6/3 = 2,0

        expect((float) DB::table('grade_averages')->where('enrollment_id', $e->id)->value('term1_avg'))->toBe(2.0);
    });
});

describe('fecho do ano', function () {
    it('classes sem exame: aprova, reprova (sem recurso) e deixa pendente quem não tem notas', function () {
        $section = makeSection($this->school, $this->year, 4);
        $approved = enrolledStudent($this, $section, ['first_name' => 'Aprovado']);
        $failed = enrolledStudent($this, $section, ['first_name' => 'Reprovado']);
        $pending = enrolledStudent($this, $section, ['first_name' => 'Pendente']);

        giveFinals($this, $section, $approved, ['LP' => 5, 'MAT' => 5, 'EM' => 7]);   // 0 negativas
        giveFinals($this, $section, $failed, ['LP' => 4, 'MAT' => 4, 'EM' => 7]);     // LP e MAT negativas

        // a pauta final mostra o resultado que o fecho vai gravar
        $sheet = collect(($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=final")->assertOk()->json('rows'))->keyBy('enrollment_id');
        expect($sheet[$approved->id]['values']['LP'])->toEqual(['mfd' => 5, 'exam' => null, 'mfa' => 5, 'resit' => null]);
        expect($sheet[$approved->id]['outcome'])->toBe('promoted')
            ->and($sheet[$failed->id]['outcome'])->toBe('failed')
            ->and($sheet[$pending->id]['outcome'])->toBe('pending');

        // sem as pautas dos trimestres fechadas o ano não fecha
        ($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->assertStatus(409);
        lockTerms($this, $section);

        $results = collect(($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->assertOk()->json('data'))->keyBy('enrollment_id');

        expect($results[$approved->id]['status'])->toBe('promoted')
            ->and($results[$failed->id]['status'])->toBe('failed')->and($results[$failed->id]['failed_subjects'])->toBe(2)
            ->and($results[$pending->id]['status'])->toBe('pending')->and($results[$pending->id]['reason'])->toBe('Sem notas lançadas');

        // o resultado gravado coincide, aluno a aluno, com o da pauta (uma só definição de Aprovado)
        foreach ($results as $id => $r) {
            expect($r['status'])->toBe($sheet[$id]['outcome']);
        }

        expect(Enrollment::withoutGlobalScopes()->find($approved->id)->status)->toBe('completed')
            ->and(Enrollment::withoutGlobalScopes()->find($pending->id)->status)->toBe('active')
            ->and(DB::table('historical_grades')->where('student_id', $approved->student_id)->count())->toBe(3)
            ->and(DB::table('historical_grades')->where(['student_id' => $approved->student_id, 'subject_id' => subjectId($this->school, 'LP')])->value('final_value'))->toBe(5);
    });

    it('fechar outra vez não altera resultados definitivos, salvo recompute', function () {
        $section = makeSection($this->school, $this->year, 4);
        $e = enrolledStudent($this, $section);
        giveFinals($this, $section, $e, ['LP' => 7, 'MAT' => 7]);
        lockTerms($this, $section);
        ($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->assertOk();

        ($this->api)()->putJson("/api/v1/grade-sheets/{$section->id}/locks", ['term' => 1, 'locked' => false]);
        giveFinals($this, $section, $e, ['LP' => 3, 'MAT' => 3]);
        ($this->api)()->putJson("/api/v1/grade-sheets/{$section->id}/locks", ['term' => 1, 'locked' => true]);

        $again = ($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->json('data.0.status');
        expect($again)->toBe('promoted');

        $recomputed = ($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year", ['recompute' => true])->json('data.0.status');
        expect($recomputed)->toBe('failed');
    });

    it('classes de exame: recurso para 1–2 negativas e estado único depois do recurso', function () {
        $section = makeSection($this->school, $this->year, 6);
        $ok = enrolledStudent($this, $section, ['first_name' => 'Aprovado'], 11);
        $resit = enrolledStudent($this, $section, ['first_name' => 'Recurso'], 11);
        $passes = enrolledStudent($this, $section, ['first_name' => 'Recupera'], 11);
        $dead = enrolledStudent($this, $section, ['first_name' => 'Reprovado'], 11);

        giveFinals($this, $section, $ok, ['LP' => 6, 'MAT' => 7, 'CN' => 8]);
        giveFinals($this, $section, $resit, ['LP' => 4, 'MAT' => 7, 'CN' => 8]);
        giveFinals($this, $section, $passes, ['LP' => 4, 'MAT' => 7, 'CN' => 8]);
        giveFinals($this, $section, $dead, ['LP' => 4, 'MAT' => 3, 'CN' => 2]);
        lockTerms($this, $section);

        $close = fn () => collect(($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->assertOk()->json('data'))->keyBy('enrollment_id');
        $first = $close();

        expect($first[$ok->id]['status'])->toBe('promoted')
            ->and($first[$resit->id]['status'])->toBe('resit')
            ->and($first[$passes->id]['status'])->toBe('resit')
            ->and($first[$dead->id]['status'])->toBe('failed');

        // a pauta de recurso inclui só quem está em recurso
        $sheet = ($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=resit")->assertOk()->json('rows');
        expect(collect($sheet)->pluck('enrollment_id')->sort()->values()->all())->toBe(collect([$resit->id, $passes->id])->sort()->values()->all());

        // quem está em recurso continua activo e sem histórico
        expect(Enrollment::withoutGlobalScopes()->find($resit->id)->status)->toBe('active')
            ->and(DB::table('historical_grades')->where('student_id', $resit->student_id)->count())->toBe(0);

        // recurso: um passa (NER 6 ≥ 5), outro não (NER 4)
        $nerFor = fn ($e, $v) => ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $section->id, 'subject_id' => subjectId($this->school, 'LP'), 'term' => 0,
            'entries' => [['enrollment_id' => $e->id, 'kind' => 'resit', 'value' => $v]],
        ])->assertOk();
        $nerFor($passes, 6);
        $nerFor($resit, 4);

        $sheetAfter = collect(($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=resit")->json('rows'))->keyBy('enrollment_id');
        expect($sheetAfter[$passes->id]['outcome'])->toBe('promoted')->and($sheetAfter[$resit->id]['outcome'])->toBe('failed');

        $second = $close();

        // o estado gravado depois do recurso é o da pauta de recurso
        expect($second[$passes->id]['status'])->toBe('promoted')
            ->and($second[$resit->id]['status'])->toBe('failed')
            ->and($second[$ok->id]['status'])->toBe('promoted')
            ->and($second[$dead->id]['status'])->toBe('failed')
            ->and(DB::table('historical_grades')->where(['student_id' => $passes->student_id, 'subject_id' => subjectId($this->school, 'LP')])->value('final_value'))->toBe(6);
    });

    it('9.ª classe: escala 0–20, mínimo 10 e regime estrito', function () {
        $section = makeSection($this->school, $this->year, 9);
        $e = enrolledStudent($this, $section, age: 14);

        giveFinals($this, $section, $e, ['LP' => 12, 'MAT' => 9, 'BIO' => 15]);   // uma negativa (9 < 10)
        lockTerms($this, $section);

        expect(($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->json('data.0.status'))->toBe('resit');
    });

    it('as regras da escola mudam o resultado sem alterar código', function () {
        ($this->api)()->putJson('/api/v1/assessment-rules', ['lenient' => ['approve_max_negatives' => 0]])->assertCreated();
        $section = makeSection($this->school, $this->year, 4);
        $e = enrolledStudent($this, $section);
        giveFinals($this, $section, $e, ['LP' => 4, 'MAT' => 7, 'EM' => 7]);   // 1 negativa; por omissão aprovaria
        lockTerms($this, $section);

        expect(($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->json('data.0.status'))->toBe('failed');
    });

    it('Iniciação: todos transitam, sem notas nem histórico, e a pauta não existe', function () {
        $section = makeSection($this->school, $this->year, 0);
        $e = enrolledStudent($this, $section, age: 5);

        ($this->api)()->postJson("/api/v1/sections/{$section->id}/close-year")->assertOk()->assertJsonPath('data.0.status', 'promoted');

        expect(Enrollment::withoutGlobalScopes()->find($e->id)->status)->toBe('completed')
            ->and(DB::table('historical_grades')->count())->toBe(0);
        ($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=final")->assertNotFound();
    });

    it('só a direcção fecha o ano', function () {
        $section = makeSection($this->school, $this->year, 4);

        ($this->api)(makeMember($this->school, 'secretary'))->postJson("/api/v1/sections/{$section->id}/close-year")->assertForbidden();
        ($this->api)(makeMember($this->school, 'teacher'))->postJson("/api/v1/sections/{$section->id}/close-year")->assertForbidden();
    });
});

describe('pautas', function () {
    it('a pauta trimestral mostra MAC, NPP, NPT e MT por disciplina', function () {
        $section = makeSection($this->school, $this->year, 4);
        $e = enrolledStudent($this, $section);
        ($this->api)()->putJson('/api/v1/grades', [
            'section_id' => $section->id, 'subject_id' => subjectId($this->school, 'LP'), 'term' => 2,
            'entries' => [['enrollment_id' => $e->id, 'kind' => 'mac', 'value' => 8], ['enrollment_id' => $e->id, 'kind' => 'npt', 'value' => 6]],
        ])->assertOk();

        $sheet = ($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=term&term=2")->assertOk();

        expect($sheet->json('subjects.*.code'))->toBe(['LP', 'MAT', 'EM', 'EMC', 'EMP', 'MUS', 'EF'])
            ->and($sheet->json('rows.0.values.LP'))->toEqual(['mac' => 8, 'npp' => null, 'npt' => 6, 'mt' => 7])
            ->and($sheet->json('rows.0.values.MAT.mt'))->toBeNull();

        ($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=term")->assertUnprocessable();
    });

    it('não há pauta de recurso fora da 6.ª e da 9.ª', function () {
        $section = makeSection($this->school, $this->year, 4);

        ($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=resit")->assertNotFound();
        ($this->api)()->getJson("/api/v1/grade-sheets/{$section->id}?type=final")->assertOk();
    });

    it('a renovação em massa usa os resultados do fecho do ano', function () {
        $next = makeYear($this->school, '2027/2028', '2027-09-01', '2028-07-31', 'planned');
        $from = makeSection($this->school, $this->year, 4);
        $pass = enrolledStudent($this, $from, ['first_name' => 'Passa']);
        $fail = enrolledStudent($this, $from, ['first_name' => 'Chumba']);
        giveFinals($this, $from, $pass, ['LP' => 7, 'MAT' => 7]);
        giveFinals($this, $from, $fail, ['LP' => 3, 'MAT' => 3]);
        lockTerms($this, $from);
        ($this->api)()->postJson("/api/v1/sections/{$from->id}/close-year")->assertOk();

        $fifth = makeSection($this->school, $next, 5);
        $r = ($this->api)()->postJson('/api/v1/enrollments/bulk-renew', [
            'from_school_year_id' => $this->year->id, 'to_school_year_id' => $next->id,
            'section_map' => [['from_section_id' => $from->id, 'to_section_id' => $fifth->id]],
        ])->assertOk()->json();

        expect($r['created'])->toBe(1)->and($r['skipped'])->toHaveCount(1);
    });
});
