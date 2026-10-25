# Regras do Ensino Primário e do 1.º Ciclo — documento-base

Documento de referência para construir um novo sistema. Descreve **o que a plataforma actual faz hoje**, extraído do código, das configurações e das vistas de impressão. Onde o código é ambíguo, incoerente ou tem erros, isso está assinalado em **⚠** e reunido na secção 10.

- **Âmbito:** Iniciação (classe 0), Ensino Primário (1.ª–6.ª) e 1.º Ciclo do Ensino Secundário (7.ª–9.ª).
- **Fora do âmbito:** 2.º Ciclo (10.ª–13.ª). Existe no enum e nas regras, mas não tem classes nem disciplinas no catálogo.
- **Referências:** `ficheiro:linha` relativos à raiz do repositório.
- **Nada aqui é regulamento oficial.** São as regras implementadas. Os pontos a validar com o regulamento do Ministério da Educação estão na secção 11.

---

## 1. Glossário e siglas

| Sigla | Significado | Onde existe |
|---|---|---|
| MAC | Avaliação Contínua | nota lançada, por trimestre |
| NPP | Prova do Professor | nota lançada, por trimestre |
| NPT (PT nas pautas) | Prova Trimestral | nota lançada, por trimestre |
| MT | Média do Trimestre | calculada |
| MFD | Média Final da Disciplina (média dos trimestres) | calculada |
| NE | Nota de Exame (nacional) | nota lançada, anual |
| NER (NR nas pautas) | Nota do Exame de Recurso | nota lançada, anual |
| MFA (MF) | Média Final Anual / classificação final da disciplina | calculada |
| MEC | Média de Exame de Classe (rótulo da pauta em classes sem exame) | só rótulo |
| Turma | `section` no código | |
| Classe | `level` no código | |
| Nº de processo | `students.student_number` | |
| Nº de ordem / "nº" | `enrollments.roll_number` | |
| Escola certificadora | escola pública que assina certificados de escolas privadas ou mistas | |

---

## 2. Estrutura das classes

Fonte: `config/curriculum.php:13-24`, `app/Enums/EducationStage.php`.

| Classe (`grade_number`) | Código | Nome | Etapa | Idade mín.–máx. | Escala | Mínimo positivo | Exame nacional |
|---|---|---|---|---|---|---|---|
| 0 | INIT | Iniciação | `preschool` | 5–6 | **sem notas** | — | não |
| 1 | 1ST | 1.ª | `primary` | 6–7 | 0–10 | 5 | não |
| 2 | 2ND | 2.ª | `primary` | 7–8 | 0–10 | 5 | não |
| 3 | 3RD | 3.ª | `primary` | 8–9 | 0–10 | 5 | não |
| 4 | 4TH | 4.ª | `primary` | 9–10 | 0–10 | 5 | não |
| 5 | 5TH | 5.ª | `primary` | 10–11 | 0–10 | 5 | não |
| 6 | 6TH | 6.ª | `primary` | 11–12 | 0–10 | 5 | **sim** |
| 7 | 7TH | 7.ª | `lower_secondary` | 12–13 | 0–20 | 10 | não |
| 8 | 8TH | 8.ª | `lower_secondary` | 13–14 | 0–20 | 10 | não |
| 9 | 9TH | 9.ª | `lower_secondary` | 14–15 | 0–20 | 10 | **sim** |

Regras:

- A escala vem das colunas `levels.max_score` e `levels.pass_mark`. Se não vierem preenchidas, deriva-se de `grade_number`: até à 6.ª, 10/5; da 7.ª em diante, 20/10 (`app/Models/Level.php:21-22,51-54`).
- Nomes da etapa (`EducationStage.php:14-22`): "Iniciação", "Ensino Primário", "Primeiro Ciclo do Ensino Secundário".
- Designação nos certificados (`EducationStage.php:31-38`): Iniciação e Primário usam **"Ensino Primário"**; o 1.º Ciclo usa **"I Ciclo"**.
- A progressão é sempre `grade_number + 1` (`EnrollmentService.php:47`). Não há saltos de classe.
- **⚠** `min_age` e `max_age` existem no catálogo, mas só `min_age` é validado, e só no cadastro do aluno (secção 6.1).

---

## 3. Currículo por classe

Fonte: `config/curriculum.php:27-51`, semeado por `CurriculumService::provisionSchool` (`CurriculumService.php:132-165`). A tabela `level_subject` é a fonte única por escola e pode ser ajustada.

### 3.1 Disciplinas

Créditos: 1 em todas, excepto LP e MAT (2). "Obrig." = categoria `core` (obrigatória).

| Código | Disciplina | Classes | h/semana | Tipo |
|---|---|---|---|---|
| LP | Língua Portuguesa | 1–9 | 5 | obrig. |
| MAT | Matemática | 1–9 | 5 | obrig. |
| EM | Estudo do Meio | 1–4 | 3 | obrig. |
| CN | Ciências da Natureza | 5–6 | 3 | obrig. |
| BIO | Biologia | 7–9 | 3 | obrig. |
| FIS | Física | 7–9 | 3 | obrig. |
| QUI | Química | 7–9 | 3 | obrig. |
| HIST | História | 5–9 | 2 | obrig. |
| GEO | Geografia | 5–9 | 2 | obrig. |
| EMC | Educação Moral e Cívica | 1–9 | 2 | obrig. |
| EMP | Educação Manual e Plástica | 1–6 | 2 | obrig. |
| MUS | Educação Musical | 1–6 | 2 | obrig. |
| EF | Educação Física | 1–9 | 2 | obrig. |
| EL | Educação Laboral | 7–9 | 2 | obrig. |
| LE | Língua Estrangeira (Inglês/Francês) | 5–9 | 3 | obrig. |
| EMPR | Empreendedorismo | 8–9 | 2 | opcional |
| LOA | Línguas de Origem Africana | 5–9 | 2 | opcional |

### 3.2 Resumo por classe

| Classe | Disciplinas |
|---|---|
| 1.ª–4.ª | LP, MAT, EM, EMC, EMP, MUS, EF |
| 5.ª–6.ª | LP, MAT, CN, HIST, GEO, EMC, EMP, MUS, EF, LE (+ opcional LOA) |
| 7.ª | LP, MAT, BIO, FIS, QUI, HIST, GEO, EMC, EF, EL, LE (+ opcional LOA) |
| 8.ª–9.ª | as da 7.ª (+ opcionais EMPR e LOA) |

### 3.3 Iniciação

Seis áreas, todas obrigatórias e só da classe 0 (`curriculum.php:28-33`):

| Código | Área | h/semana |
|---|---|---|
| CL | Comunicação Linguística | 3 |
| RM | Representação Matemática | 3 |
| EP | Expressão Plástica | 2 |
| MFS | Meio Físico e Social | 2 |
| PJ | Psicomotricidade e Jogos | 2 |
| HD | Histórias Dramáticas | 1 |

A Iniciação **não tem notas nem pautas**: `EducationStage::hasGrades()` é falso (`EducationStage.php:25-28`) e lançar notas dá o erro "Na Iniciação não se lançam notas." (`GradeService.php:250-252`, `GradeGridService.php:44-49`).

### 3.4 Observações

- "Obrigatória" (categoria `core`) **não é** o mesmo que "nuclear" na regra de aprovação. Nuclear são só **LP e MAT** (`config/assessment.php:34`).
- **⚠** A Língua Estrangeira não regista qual (Inglês ou Francês). O dado não existe no sistema.
- Todas as disciplinas com nota contam igual nas negativas, incluindo EF, EMC, EL e as opcionais.

---

## 4. Avaliação e cálculo das notas

Fonte única: `app/Domain/Assessment/GradeCalculator.php` e `GradeScale.php`. Valores em `config/assessment.php`.

### 4.1 Períodos e tipos de nota

- Três trimestres. Na tabela `grades`, `term` 1–3 é o trimestre e **0 é a nota anual** (`database/migrations/2026_11_01_000010_create_assessment_tables.php:11,19`).
- Não há datas por trimestre. O trimestre activo da escola é `schools.active_trimester` (1–3).

| Tipo (`GradeKind`) | Sigla | Período |
|---|---|---|
| `continuous` | MAC | trimestre |
| `test_1` | NPP | trimestre |
| `test_2` | NPT | trimestre |
| `exam` | NE | 0 (anual) |
| `resit` | NER | 0 (anual) |

- `grades.value` é `decimal(5,2)`. Chave única: `(student_id, subject_id, academic_year_id, term, kind)`.
- A grelha aceita vírgula decimal e arredonda a 2 casas ao gravar.
- Validação: `0 ≤ nota ≤ máximo da classe` (10 até à 6.ª, 20 depois). Mensagem "A nota deve estar entre 0 e {max}".
- **0 ou vazio significa "sem nota"**, nunca negativa (`GradeCalculator.php:9`, `GradeScale.php:29`).
- Só se lançam notas a alunos matriculados na turma e no ano.
- Pauta fechada (`GradeSheetLock` por turma, ano e período, 0 a 3) recusa gravação. Quem fecha e reabre é a direcção.

### 4.2 Fórmulas

| Resultado | Fórmula | Arredondamento |
|---|---|---|
| **MT** | `(MAC + NPP + NPT) / 3`. Componente em falta conta 0 enquanto houver pelo menos uma nota > 0. Sem notas, 0. | 1 casa decimal |
| **MFD** | média dos MT já preenchidos (> 0). Trimestre vazio não entra no divisor. | 1 casa decimal |
| **MFA** classe **sem** exame | `round(MFD)` | inteiro |
| **MFA** classe **com** exame, só NE | `round(0,4 × MFD + 0,6 × NE)` | inteiro |
| **MFA** classe com exame, com NER | `round(NER)` (o recurso substitui a nota) | inteiro |
| **MFA** classe com exame, sem NE | `round(MFD)` | inteiro |
| **Média do certificado** | média das classificações > 0 das classes do histórico | inteiro |

Pesos: `year` 0,4 e `exam` 0,6 (`config/assessment.php:24-27`). Componentes por trimestre: 3.

Exemplo: numa classe sem exame, MAC 8, NPP 6, NPT 7 dá MT = 7,0. Uma só nota de 12 dá MT = 4,0 (12/3), porque o divisor é fixo.

### 4.3 Escala e nota por extenso

- `GradeScale`: `accepts` = 0 ≤ v ≤ máximo; `isNegative` = 0 < v < passMark; `isPositive` = v ≥ passMark.
- Nota por extenso (`GradeInWords.php`): "Cinco Valores" a "Vinte Valores". Fora de 5–20 devolve "-". As negativas do primário (1–4) ficam, por isso, sem extenso. É coerente com "só recebe certificado quem tem média positiva".

### 4.4 Valores materializados

`grade_averages` guarda por aluno, disciplina e ano: `term1_avg`, `term2_avg`, `term3_avg`, `year_avg`, `exam`, `resit`, `final_avg`. Só grava valores positivos (0 vira `null`). É recalculado a cada gravação de nota.

---

## 5. Aprovação, reprovação e recurso

Fonte: `app/Domain/Assessment/PromotionRules.php`, `config/assessment.php:33-48`.

### 5.1 Princípios

- A decisão parte das **classificações finais (MFA)** por disciplina. Valores 0 são ignorados. Sem nenhuma nota > 0, o resultado é **Pendente**.
- **Negativa** = MFA < nota mínima da classe (5 no primário, 10 no 1.º Ciclo).
- Resultados possíveis (`YearOutcome`): `PENDING` (Pendente), `PROMOTED` (Aprovado), `RESIT` (Recurso), `FAILED` (Reprovado), `DROPPED` (Desistente).

### 5.2 Regime estrito: 6.ª e 9.ª (classes de exame)

| Negativas | Resultado |
|---|---|
| 0 | Aprovado |
| 1 a 2 | Recurso |
| 3 ou mais | Reprovado |

### 5.3 Regime brando: 1.ª a 5.ª, 7.ª e 8.ª

| Negativas | Resultado |
|---|---|
| 0 a 2 | Aprovado, **excepto** se LP e MAT são ambas negativas (então Recurso) |
| 3 a 4 | Recurso |
| 5 ou mais | Reprovado |

- A regra LP+MAT só dispara com exactamente 2 negativas, porque só se verifica com `negativas ≤ 2` e exige as duas.
- Casos de teste (`tests/Feature/PromotionRulesTest.php`): classe 4 com LP=5 e MAT=5 → Aprovado; LP=4 e MAT=4 → Recurso; classe 8 com LP=8, MAT=8, BIO=12 → Recurso; LP=8, MAT=12, BIO=8 → Aprovado.

### 5.4 Quadro por classe

| Classe | Regime | Mínimo | Escala | Exame nacional |
|---|---|---|---|---|
| Iniciação | sem avaliação, todos transitam | — | — | não |
| 1.ª–5.ª | brando | 5 | 0–10 | não |
| 6.ª | estrito | 5 | 0–10 | sim |
| 7.ª e 8.ª | brando | 10 | 0–20 | não |
| 9.ª | estrito | 10 | 0–20 | sim |

### 5.5 Exame nacional

- Classes com exame no âmbito: **6.ª e 9.ª** (`config/assessment.php:12`, que também tem 12.ª e 13.ª).
- Tabela `exams`: `exam_number` (único por aluno, nível e ano), `exam_room`, `status` (`registered`, `absent`, `present`, `graded`) e `grade`. Os números são gerados em sequência por classe (`ExamService.php:66-91`).
- **⚠** A nota que entra nas médias vem de `grades` com `kind = exam`. A coluna `exams.grade` não é lida pelas fórmulas.

### 5.6 Exame de recurso

- Um só exame de recurso por disciplina (`GradeKind::RESIT`, NER). Não há outra época especial.
- A pauta de recurso (`GradeService::getSectionGradeSheetResit`, linhas 700-795) mostra MFD, NE, MFA antes, NER e MFA depois.
- Estado após recurso na pauta: **Aprovado** se zero negativas, senão **Reprovado**. O comentário do código diz que o recurso "já é a última oportunidade" e que não há "Admitido a exame".

### 5.7 Fecho do ano e transição

`YearTransitionService::closeYear` (linhas 33-135), por turma:

1. Obtém as notas finais por aluno e disciplina.
2. Aplica `PromotionRules::decide`.
3. Iniciação: marca todos como **Aprovado**, sem regras.
4. Grava `enrollment_results` com `status`, `reason` ("Sem notas lançadas" se Pendente) e `failed_subjects` (nº de negativas).
5. Grava `historical_grades` com a nota final de cada disciplina (chave: aluno, nível, disciplina).

Promoção (`EnrollmentService::getPromotionCandidates`): aprovado vai para a classe seguinte, os restantes repetem. A rematrícula em massa cria matrículas `active` no novo ano.

### 5.8 Assiduidade

- Estados: `PRESENT`, `ABSENT`, `LATE`, `JUSTIFIED`. Só `ABSENT` conta como falta injustificada.
- Taxa de faltas = `ABSENT / dias com chamada × 100` (1 casa decimal).
- **Não há limite de faltas nem reprovação por faltas.** A taxa é só informativa.

### 5.9 Desistência

Desligada (`dropout.enabled = false`). O estado `DROPPED` existe mas nenhum código o atribui. O próprio ficheiro de configuração diz que a regra "nunca foi confirmada".

---

## 6. Dados a recolher

Todos os campos abaixo existem no sistema actual. A coluna "Docs" indica que documentos os consomem: **D** declaração, **C** certificado, **T** termo, **P** pauta, **A** atestado (Iniciação), **L** lista de turma.

### 6.1 Aluno (`students`)

| Campo | Tipo | Obrig. | Validação / regra | Docs |
|---|---|---|---|---|
| `student_number` (nº de processo) | texto | não (gerado) | único por escola; ver 6.1.1 | T, L |
| `first_name` | texto ≤ 255 | **sim** | normalizado para Title Case | D, C, T, A |
| `last_name` | texto ≤ 255 | **sim** | idem | D, C, T, A |
| `gender` | `M` / `F` | **sim** | decide filho/filha, nascido/a, aluno/a, portador/a | D, C, T, A |
| `birth_date` | data | não | antes de hoje; idade mínima da classe | D, C, T, A, L |
| `father_name` | texto ≤ 255 | não | ver 6.2 | D, C, T, A |
| `mother_name` | texto ≤ 255 | não | ver 6.2 | D, C, T, A |
| `birth_country_id` | país | não | por omissão Angola | D, A |
| `nationality_id` | país | não | por omissão Angola; o gentílico vem de `countries.nationality` | C |
| `province_id` | província | não | tem de pertencer ao país de nascimento | D, C, T, A |
| `municipality_id` | município | não | tem de pertencer à província | D, C, T, A |
| `document_type` | enum | não | `birth_record`, `personal_card`, `identity_card`, `passport` | D, C, T |
| `document_number` | texto ≤ 50 | não | único por escola; formato só no BI | D, C, T, L |
| `identity_number` | texto | não | espelho do BI; só se o tipo for BI | C, L |
| `document_issuer` | texto ≤ 255 | não | local de emissão | D, C, T |
| `document_issue_date` | data | não | até hoje | D, C, T |
| `contact_phone` | texto ≤ 20 | não | sem formato | L |
| `emergency_contact` | texto ≤ 20 | não | só um número, sem nome nem parentesco | — |
| `address` | texto | não | | — |
| `photo` | imagem | não | jpeg, png, jpg, gif; máx. 2 MB | — |
| `status` | enum | sim (default `active`) | `active`, `inactive`, `transferred`, `graduated` | L |
| `medical_info` | JSON | não | estrutura livre, sem ecrã | — |
| `section_id` | turma | **sim, só no cadastro** | obriga a matricular ao cadastrar | — |

#### 6.1.1 Número de processo

- Se vazio, é gerado: `max(maior nº puramente numérico da escola, incluindo apagados) + 1`, nunca abaixo do contador inicial da escola (`school_counters.initial_student_number`, ≥ 1), com 6 dígitos e zeros à esquerda (`StudentService.php:216-226`).
- Números com letras ou traços (de importações, como `002-9B-040`) são ignorados no cálculo.
- Único por escola, incluindo alunos apagados.

#### 6.1.2 Idade mínima

`StudentAgeRule`: `birth_date->age < level.min_age` falha com "O aluno deve ter no mínimo {min} anos para a {classe}." Usa a classe da turma escolhida. Sem data de nascimento, a regra é ignorada. `max_age` nunca é validado.

#### 6.1.3 Documento de identificação

| Tipo | Formato | Emissor por omissão (se vazio) |
|---|---|---|
| Bilhete de Identidade (BI) | `^[0-9]{9}[A-Z]{2}[0-9]{3}$`, ex.: `006151112LA041` | Direcção Nacional de Identificação |
| Cédula Pessoal | texto livre ≤ 50 | Conservatória do Registo Civil |
| Assento de Nascimento | texto livre ≤ 50 | Conservatória do Registo Civil |
| Passaporte | texto livre ≤ 50 | Serviço de Migração e Estrangeiros |

- O BI é normalizado antes de validar (tira espaços, pontos e hífens; maiúsculas). As 2 letras são o código da província de emissão, mas não são validadas contra lista.
- Tipo sem número resulta em `document_type = null`: "aluno sem documento, comum no primário". O documento nunca é obrigatório.
- Redacção nos documentos: "do B.I", "da Cédula Pessoal", "do Passaporte", "do Assento de Nascimento" (`IdentityDocumentType::withArticle()`).
- Existe consulta opcional a um serviço externo de BI que pré-preenche nome, nascimento, género, pais, data e local de emissão. É só ajuda.

### 6.2 Filiação (pais e encarregado)

Esta é a parte do cadastro mais sensível para os documentos oficiais, porque a filiação aparece em **todos** eles.

#### Como o sistema guarda hoje

| Dado | Campo | Regra |
|---|---|---|
| Nome do pai | `students.father_name` | opcional, texto ≤ 255 |
| Nome da mãe | `students.mother_name` | opcional, texto ≤ 255 |
| Telefone de contacto | `students.contact_phone` | opcional, ≤ 20, sem formato |
| Contacto de emergência | `students.emergency_contact` | opcional, ≤ 20, sem nome nem parentesco |
| Morada | `students.address` | opcional |

**Não existe entidade de encarregado de educação.** Não há profissão, BI, telemóvel ou e-mail do encarregado, grau de parentesco, responsável financeiro, nem encarregado distinto dos pais. O "Portal do Encarregado de Educação" consta como lacuna em `analise_sistema_escolar.txt:17`.

#### Como a filiação é impressa

| Documento | Redacção | Se vazio |
|---|---|---|
| Declaração | "filho/a de {pai} e de {mãe}" | linha de pontos `..................................` |
| Certificado 6.ª | "filho (a) de {pai} e de {mãe}" | **fica em branco**, sem pontos (usa `??` em vez de `?:`) |
| Certificado 9.ª | idem, com `_______` | `_______` |
| Termo 6.ª e 9.ª | "Filho(a) de: {pai} e de {mãe}." | vazio |
| Atestado | "filho/a de …" | pontos |

#### O que o novo sistema deve recolher (recomendado)

Para satisfazer os documentos actuais basta `father_name` e `mother_name`. Para o novo sistema, recomenda-se acrescentar, **marcando claramente o que é obrigatório e o que é opcional**:

- **Pai e mãe** (obrigatório nome de pelo menos um): nome completo, estado (vivo ou falecido, se relevante para o documento), profissão, telefone.
- **Encarregado de educação** (entidade própria): nome completo, parentesco, tipo e nº de documento, telefone principal e alternativo, e-mail, profissão, morada, indicação se é o responsável financeiro.
- **Contactos de emergência**: nome, parentesco e telefone, em vez de um número solto.
- **Regra de redacção**: um único ponto de formatação da filiação ("filho/a de A e de B", só A, só B, ou omitido), com a mesma regra de campo vazio em todos os documentos.
- **Formato**: nomes por extenso, sem forçar Title Case (o actual estraga "dos Santos" e "de"; ver secção 10).

### 6.3 Escola (`schools`)

#### Dados da escola

| Campo | Tipo | Obrig. | Regra | Onde aparece |
|---|---|---|---|---|
| `name` | texto ≤ 255 | **sim** | | todos os documentos |
| `code` (nº da escola) | texto ≤ 100 | não | único | D, C, T, A, "Nº {code}" |
| `ex_code` (ex-código) | texto ≤ 100 | não | no registo ≤ 100, na edição ≤ 50 | D, C ("Ex. {ex_code}") |
| `decree` (decreto executivo de criação) | texto ≤ 100 | não | | D, C ("criada sob Decreto Executivo nº …") |
| `director_name` | texto ≤ 255 | **sim** | | assinatura em todos |
| `director_gender` | `M` / `F` | **sim** | decide "Director/Directora", "O/A Director/a da Escola" | todos |
| `director_phone` | texto ≤ 20 | **sim** | | — |
| `is_complex` (complexo escolar) | bool | sim | decide "do/no" (complexo) ou "da/na" (escola) | D, C, A |
| `regime` | `public`, `private`, `mixed` | **sim** | no registo vem como 1, 2, 3 | decide a escola certificadora |
| `email` | e-mail ≤ 100 | **sim** | único | contacto |
| `phone` | texto ≤ 20 | não | | — |
| `address` | texto ≤ 255 | **sim** | | lista de turma |
| `province_id` | província | **sim** | | D ("Governo da Província de …") |
| `municipality_id` | município | **sim** | | D, T |
| `district`, `commune` | texto ≤ 100 | não | | T, L |
| `education_office_id` | direcção municipal | automático | atribuída pelo município | D ("Direcção Municipal da Educação de …") |
| `active_trimester` | 1–3 | sim (default 1) | | habilita certificados e termos (só no 3.º) |
| `status` | `active`, `inactive`, `suspended` | sim | nasce `inactive` até aprovação | — |

Também se recolhe, no registo da escola, o administrador: nome, e-mail (único) e palavra-passe (mín. 6).

**⚠** Não existem: NIF, alvará, logótipo da escola, lista de classes leccionadas, turnos configuráveis por escola.

#### Escola certificadora (escolas privadas e mistas)

Escolas `private` ou `mixed` não assinam os certificados: quem assina é uma escola pública que as certifica, **por nível** (`Ensino Primário` ou `I Ciclo`). Tabela `school_links`:

| Campo | Obrig. |
|---|---|
| `public_school_id` (se a certificadora está na plataforma) | alternativa aos campos abaixo |
| `certifying_school_name` | sim, se não houver `public_school_id` |
| `certifying_school_director_name` | sim, idem |
| `certifying_school_director_gender` | sim, idem |
| `certifying_school_code`, `_ex_code`, `_district`, `_decree` | não |
| `level` | **sim** (`Ensino Primário` ou `I Ciclo`) |

`School::getCertifyingSchoolForLevel` (`School.php:184-211`) devolve a escola pública ligada, ou um objecto virtual com os dados manuais, quando o regime é privado ou misto. Caso contrário devolve a própria escola.

**⚠** O certificado da 9.ª mistura os dois: director da certificadora, mas nome, código, ex-código e decreto da escola original. Isso é incoerente para escolas privadas.

#### Direcção municipal (`education_offices`)

Uma por município (`municipality_id` único). Campos: `name`, `head_name`, `head_gender`, `head_email`, `head_phone`, `general_head_name`, `general_head_email`, `general_head_phone`, `province_id`, `municipality_id`, `district`. O `head_name` e o `head_gender` assinam o atestado da Iniciação.

#### Geografia

`countries` (nome, gentílico, ISO de 2 letras; Angola por omissão), `provinces` (por país), `municipalities` (por província). Para quem nasce no estrangeiro, província e município ficam vazios.

### 6.4 Ano lectivo (`academic_years`)

| Campo | Regra |
|---|---|
| `name` | texto ≤ 50, único por escola. Ex.: `2025/2026`. Sem validação de formato. |
| `start_date`, `end_date` | `end ≥ start`. Se faltarem no cadastro, assume hoje e hoje + 1 ano. |
| `status` | `planned`, `active`, `inactive`, `completed`, `cancelled` (só `active` e `completed` são usados) |
| `settings` | `{"trimesters":[1,2,3]}` |

Regras: ao criar um ano, os anteriores passam a `completed`. Não se apaga o ano activo nem se desactiva o único ano. Sem validação de sobreposição.

### 6.5 Turma, sala, turno

| Entidade | Campos e regras |
|---|---|
| Turma (`sections`) | `name` (≤ 50 na BD), `level_id`, `room_id`, `academic_year_id`, `shift` (`morning` Manhã, `afternoon` Tarde, `night` Noite), `capacity` (default 40, **não editável**). Única por escola, ano, classe e nome. |
| Sala (`rooms`) | `name`, `capacity` (default 40, ≥ 1), `type`, `status` |

A capacidade não é verificada na matrícula. Só os relatórios municipais detectam turmas acima da capacidade.

### 6.6 Matrícula (`enrollments`)

| Campo | Regra |
|---|---|
| `student_id`, `school_id`, `section_id`, `academic_year_id` | obrigatórios; **única por aluno e ano** |
| `roll_number` | nº de ordem na pauta. **⚠** nunca é atribuído em produção. |
| `enrollment_date` | data da matrícula |
| `status` | `active`, `completed`, `dropped`, `transferred` |
| `observations` | texto |

Resultado do ano em `enrollment_results` (secção 5.7). **⚠** Não há campos de escola de proveniência, classe anterior, repetente, tipo de matrícula (nova ou renovação) nem transferência.

### 6.7 Professor e atribuições

Professor (`teachers`): `employee_number`, `identity_number` (únicos por escola), `first_name`, `last_name`, `gender`, `birth_date`, `phone`, `email`, `address`, `qualification`, `specializations`, `hire_date`, `contract_type` (`permanent`, `temporary`, `substitute`), `status`. Atribuição à turma (`teacher_assignments`): turma, disciplina, ano, `type` (`subject` ou `director` de turma), datas. Os professores não aparecem nos documentos emitidos.

### 6.8 Assiduidade

Um registo por aluno, turma e dia: `status`, `justification` (obrigatória para `JUSTIFIED`, ≤ 500). Não se regista em data futura nem fora do ano lectivo, e só para matrículas activas.

---

## 7. Documentos emitidos

Permissões: `view_certificates` consulta; `manage_certificates` emite (director, subdirector administrativo, secretaria). O professor recebe 403.

### 7.1 Quadro geral

| Documento | Classes | Formato | Condição principal |
|---|---|---|---|
| Declaração (com ou sem notas) | 1.ª–9.ª | A4 vertical | matrícula elegível |
| Certificado | **6.ª e 9.ª** | A4 vertical | 3.º trimestre; média positiva (individual dispensa) |
| Termo de Frequência e Exame | 6.ª e 9.ª (+ genérico) | A4 | 3.º trimestre; sem verificação de aprovação |
| Pauta trimestral e final | 1.ª–9.ª | A3 horizontal | classe com notas |
| Pauta de recurso | classes de exame | A3 horizontal | |
| Atestado | só Iniciação | A4 horizontal | 3.º trimestre |

Não existem: boletim, cartão de aluno, caderneta, nem folha de recurso separada da pauta de recurso.

### 7.2 Cabeçalho institucional por documento

| Documento | Linhas |
|---|---|
| Declaração | emblema; República de Angola; Ministério da Educação; Governo da Província de {escola.província}; Direcção Municipal da Educação de {escola.município} (ou "Administração Municipal" se não houver direcção); {escola} Nº {código} |
| Certificado 6.ª | emblema; República de Angola; Ministério da Educação; Ensino Primário |
| Certificado 9.ª | emblema; República de Angola; Ministério da Educação; I Ciclo do Ensino Secundário |
| Termo | República de Angola; Governo da Província de {província ou LUANDA}; Direcção Municipal da Educação de {município ou LUANDA}; (Área do Ensino Geral). Sem "Ministério". |
| Pauta | visto; emblema; República de Angola; Ministério da Educação; {escola} |
| Atestado | emblema; República de Angola; Ministério da Educação; Educação Pré-Escolar |

### 7.3 Declaração

**Condições**

- Etapa tem de ser Primário ou 1.º Ciclo. A Iniciação e o 2.º Ciclo dão 404.
- "Com notas" exige pelo menos uma nota lançada, senão "Este aluno não tem notas lançadas neste ano lectivo. Emita a declaração sem notas."
- **Não exige aprovação.**
- Verbo: **"Concluiu"** só se for "com notas" **e** existir `EnrollmentResult` `PROMOTED`. Caso contrário, **"Frequentou"** (sem notas diz sempre "Frequentou").

**Texto por variante**

1. **Primário com notas:** director "do/da {escola} Nº {código} [Ex. {ex}] - {município}[, criada sob Decreto Executivo nº {decreto}]". Depois, "Declara-se que {nome}, filho/a de {pai} e de {mãe}, nascido/a aos {data}, natural de {município}, Município de {município}, Província de {província}, [portador/a {documento} nº …], aluno/a matriculado/a na {escola}. {Frequentou|Concluiu} a {N}.ª Classe no Ano Lectivo de {ano}[ com aproveitamento], tendo as seguintes classificações:" e tabela "Disciplinas | {N}.ª Classe" com "{valor} Valores".
2. **Primário sem notas:** igual, mas termina em "Frequentou a {N}.ª Classe no Ano Lectivo de {ano}, na {escola}[, na sala nº {sala}], Período: {turno}, Turma: {turma}."
3. **1.º Ciclo (com ou sem notas):** abre "Para os efeitos legais declara-se que {nome}, …", depois "{Frequentou|Concluiu} na {escola}, no Ano Lectivo {ano}, a {N}.ª Classe[ e sob o nº {nº de ordem}], turma: {turma}[, sala nº {sala}], Período: {turno}[. Com as seguintes classificações:]".
4. **Fecho:** "Por ser verdade[ e me ter sido solicitado], passou-se a presente declaração que vai ser assinada[ por mim] e autenticada com o carimbo a óleo em uso nesta Instituição de Ensino."

**Campos e origem**

| Campo no documento | Origem |
|---|---|
| Nome | `first_name + last_name` |
| Género | `gender` |
| Filiação | `father_name`, `mother_name` (senão pontos) |
| Nascimento | `birth_date` por extenso |
| Naturalidade (Angola) | `municipality.name` (duas vezes) e `province.name` |
| Naturalidade (estrangeiro) | `birthCountry.name` se há país e não há província |
| Documento | tipo, `document_number` ou `identity_number`, emissor, data (só se houver tipo e número) |
| Classe, ano, turma, sala, turno, nº | `level`, `academicYear.name`, `section` (nome, sala, turno), `roll_number` |
| Escola | `name`, `code`, `ex_code`, `decree`, `director_name`, `director_gender`, `is_complex`, município |
| Notas | `media_final` de cada disciplina, `(int) round`, na ordem da classe |

**Local, data e assinatura:** "{escola}, em {província}, aos {data de emissão por extenso}". Assina só "O Director da Escola" (ou "A Directora").

### 7.4 Certificado

**Condições**

- Só aparece no 3.º trimestre (`schools.active_trimester = 3`).
- **Individual:** emite-se mesmo sem média positiva ("decisão da secretaria"). Exige matrícula no ano corrente.
- **Por turma ou selecção:** só alunos aprovados (média geral ≥ mínimo da classe: 5 na 6.ª, 10 na 9.ª).
- Vista: 9.ª usa `pdf_nona`; qualquer outra classe usa `pdf` (6.ª, Primário).
- A escola que assina é a certificadora (secção 6.3).

**Histórico (notas por ciclo)**

| Certificado | Classes lidas | Fonte |
|---|---|---|
| 6.ª (Primário) | 2.ª, 4.ª e 6.ª | `historical_grades` |
| 9.ª (1.º Ciclo) | 7.ª, 8.ª e 9.ª | `historical_grades` |

As notas das classes anteriores vêm de `historical_grades`, que têm de existir. Alimentam-se do fecho do ano ou das pautas de histórico lançadas à mão (classes 2, 4, 7 e 8). Por disciplina: média das classes com nota > 0 (inteiro), e por extenso. Média geral = média das médias por disciplina.

**Texto da 6.ª classe**

- a) "{director}, Director/a do/da {escola} Nº {código} Ex. {ex} - **Maianga**, criada sob Decreto Executivo nº {decreto}."
- Identificação: "Certifica que {nome}, filho (a) de {pai} e de {mãe}, nascido (a) aos {data}, natural de {município}, Município de {município}, Província de {província}, nacionalidade {nacionalidade ou Angolana}, portador/a {documento} nº …, {emissor} aos {data}."
- Conclusão: "Concluiu no Ano Lectivo {ano}, o ENSINO PRIMÁRIO, conforme o disposto na alínea b) do artigo 109.º da LBSEE 17/16 de 7 de Outubro, com a Média Final de {média} valores, obtida nas seguintes classificações por ciclos de aprendizagem:"
- Tabela: Disciplina | I Ciclo (2.ª) | II Ciclo (4.ª) | III Ciclo (6.ª) | Média final | Média por extenso. Células cinzentas fixas: CN, História, Geografia e EMC nas 2.ª e 4.ª; Estudo do Meio na 6.ª.
- Rodapé: "consta no livro de registo nº ....../{ano}, folha ......" **em branco**. "Conferido por" em branco e "O Director".

**Texto da 9.ª classe**

- Igual na estrutura, com "I CICLO DO ENSINO SECUNDÁRIO GERAL", alínea **c)** do artigo 109.º e colunas 7.ª, 8.ª, 9.ª.
- Rodapé preenchido: livro `{código da escola}/{ano}`, folha = **número de exame com 4 dígitos**.
- "Educação Manual e Plástica" aparece como "Educação Visual e Plástica".

**Ordem das disciplinas na tabela da 6.ª:** LP, MAT, EM, CN, HIST, GEO, EMC, EMP, MUS, EF, LOA, LE; as desconhecidas vão para o fim.

### 7.5 Termo de Frequência e Exame

**Condições:** 3.º trimestre; turmas das classes 6, 9, 12 e 13 (lista fixa no controlador). **Não verifica aprovação**, emite para todos.

| | 6.ª classe | 9.ª classe | Genérico |
|---|---|---|---|
| Título | Termo de Frequência e Exame da 6.ª Classe | idem da 9.ª | Termo de Aproveitamento |
| Cabeçalho | ano, classe, sala, nº de processo, escola, município | idem; "Nr. de Exame" | escola, ano, nome, turma |
| Texto | "O EXAMINANDO … Natural de …, nascido(a) no dia …, Portador(a) …. Filho(a) de: … e de …. Fez exame da 6.ª Classe que foi considerado(a) Transita/Não Transita…" | idem, "9.ª Classe" | — |
| Tabela | 2.ª e 4.ª (MFD); 6.ª (MT1, MT2, MT3, MFD, NE, MFA, NER); Média Final | I, II, III trimestre (MAC, NPP, NPT, MT, Fal); MFD, NE, MFA, NR | Disciplina, MT1, MT2, MT3, MFD |
| Assinaturas | Director/a da Escola; Júri (Presidente, 1.º e 2.º Vogal) | Encarregado de Educação; Director de Turma; A Direcção | — |

**"Transita":** média global (`global_avg = round(Σ MFA / n, 1)`) ≥ nota mínima (5 ou 10). Não usa `PromotionRules`.

### 7.6 Pautas

- **Trimestral:** por disciplina MAC, NPP, PT, MT (vermelho abaixo do mínimo). **Final:** MFD, NE (classes de exame) ou MEC, MFA, NR, mais "Observação" = resultado do ano (Aprovado, Recurso, Reprovado, Pendente).
- Colunas fixas: Nr., Nome Completo, por disciplina.
- Assinaturas trimestral: Subdirector Pedagógico, Professor, Director. Final: Conselho de Notas, Professor, Director.
- **Pauta de recurso:** por disciplina MFD, NE, MFA, NR; observação = Aprovado ou Reprovado. Assinam Corpo do Júri, Subdirector Pedagógico e Director.
- Não há pauta para a Iniciação.
- A estatística (aprovados, reprovados, média por disciplina) é calculada mas **não é impressa**.

### 7.7 Atestado (fronteira: Iniciação)

Só para classe sem notas, A4 horizontal, no 3.º trimestre. Texto: "{director}, … atesta que: {nome}, filho/a de …, nascido/a aos …, natural de …, concluiu com êxito a Educação Pré-Escolar, em {província}, aos {data}." Assinam o director da escola e o director municipal (`educationOffice.head_name`).

### 7.8 Impressão em lote

Tipos: `attestation`, `certificate`, `term`, `grade_sheet`, `resit_sheet`. Máximo 2 jobs activos por escola, blocos de 10 alunos, ficheiro final expira em 7 dias. A declaração é **sempre individual e síncrona**.

---

## 8. Matriz documento × dado

Legenda: ● usado; ○ só em alguns casos.

| Dado | Declaração | Certificado | Termo | Pauta | Atestado | Lista de turma |
|---|---|---|---|---|---|---|
| Nome completo | ● | ● | ● | ● | ● | ● |
| Género | ● | ● | ● | | ● | ● |
| Data de nascimento | ● | ● | ● | | ● | ● |
| **Nome do pai** | ● | ● | ● | | ● | |
| **Nome da mãe** | ● | ● | ● | | ● | |
| País de nascimento | ○ | | | | ○ | |
| Nacionalidade | | ● | | | | |
| Província e município de naturalidade | ● | ● | ● | | ● | |
| Tipo e nº de documento | ● | ● | ● | | | ● |
| Emissor e data de emissão | ● | ● | ● | | | |
| Nº de processo | | | ● | | | ● |
| Nº de ordem | ○ (1.º Ciclo) | | | ● | | |
| Contacto | | | | | | ● |
| **Escola: nome, código, ex-código** | ● | ● | ● | ● | ● | ● |
| **Escola: decreto** | ● | ● | | | | |
| **Escola: director e género** | ● | ● | ● | ● | ● | ● |
| **Escola: complexo (do/no)** | ● | ● | | | ● | |
| **Escola: província e município** | ● | | ● | ● | ● | |
| **Escola: direcção municipal** | ● | | ● | | ● | |
| **Escola certificadora (privadas)** | | ● | | | | |
| Classe, turma, turno, sala | ● | ● | ● | ● | ● | ● |
| Ano lectivo | ● | ● | ● | ● | | ● |
| Notas finais por disciplina | ○ | ● | ● | ● | | |
| Histórico de classes anteriores | | ● | ○ | | | |
| Nº de exame | | ○ (9.ª, folha) | | | | |
| Resultado do ano | ○ ("Concluiu") | | | ● | | |

---

## 9. Regras transversais de dados

- **Isolamento:** todas as tabelas de escola têm `school_id`. Nenhuma escola vê dados de outra.
- **Soft delete** em alunos, matrículas, turmas, etc. Apagar um aluno apaga em cascata matrículas, notas e registos.
- **Normalização de nomes:** Title Case automático em `first_name` e `last_name` do aluno.
- **Unicidade por escola:** nº de processo, nº de documento, nº do BI, nome do ano lectivo, nome da turma (por classe e ano), matrícula (aluno e ano).
- **Datas:** `birth_date` antes de hoje; emissão do documento até hoje; datas dos documentos impressas são sempre as do dia da emissão.
- **Estados do aluno:** `transferred` é só etiqueta, **sem fluxo de transferência** (sem guia, origem, destino ou data).

---

## 10. Lacunas e incoerências do sistema actual

Para decidir no novo sistema. Os itens 1 a 6 mexem com regras de negócio; os restantes são erros ou dívidas técnicas.

### Regras

1. **Duas definições de "Aprovado".** A pauta e o fecho do ano usam `PromotionRules` (contagem de negativas). O assistente de promoção e o "Transita" do termo usam média global ≥ mínimo (`GradeService.php:534`). O mesmo aluno pode ser sugerido como aprovado e ficar em Recurso ou Reprovado.
2. **Recurso após o fecho do ano.** O `final_avg` já inclui o NER e o `closeYear` volta a aplicar o regime estrito, devolvendo Recurso para 1–2 negativas. A pauta de recurso devolve Reprovado com qualquer negativa. O resultado gravado pode divergir do da pauta.
3. **Recurso nas classes sem exame.** O regime brando também gera Recurso (3–4 negativas, ou LP+MAT), mas a lista de recursos só procura classes de exame (`GradeService.php:592-615`). Alunos em Recurso nas 1.ª–5.ª, 7.ª e 8.ª não têm ecrã.
4. **MT com divisor fixo 3.** Uma única nota de 12 dá MT 4,0. O sistema antigo usava só os componentes presentes.
5. **Zero é "sem nota".** Um 0 lançado não conta como negativa.
6. **Regime 7.ª–9.ª** (brando na 7.ª e 8.ª, estrito na 9.ª) está marcado em `docs/modules/assessment.md` como a validar com quem conhece o regulamento.

### Dados e cadastro

7. **Sem entidade de encarregado de educação** nem dados do encarregado (secção 6.2).
8. **Title Case estraga nomes** como "dos Santos" ou partículas "de", "da".
9. **`max_age` nunca é validado**; `min_age` só se houver data de nascimento. A rematrícula em massa não valida idade.
10. **`roll_number` nunca é atribuído** em produção, mas pautas, atestados e declarações dependem dele.
11. **Capacidade da turma** não é recolhida nem verificada; não há cruzamento com a da sala. A turma não valida existência de classe, sala e ano, nem unicidade (erro cru da BD).
12. **Ano lectivo:** datas opcionais na UI mas obrigatórias na BD; a edição pode gravar `null`; sem validação de nome nem de sobreposição.
13. **`StudentRecord`:** pedido aceita `title`, `academic` e `other`, mas a BD só tem `medical` e `disciplinary`, sem `title`; `created_by` (obrigatório) não é preenchido.
14. **`TeacherAssignment`:** `type`, `start_date` e `end_date` fora do `$fillable`, impossibilitando registar director de turma. Foto do professor sem coluna.
15. **`UpdateSchoolRequest`** torna anuláveis campos obrigatórios na BD (`director_phone`, `address`) e não valida a unicidade do e-mail.
16. **Rematrícula em massa** usa `updateOrCreate` e substitui em silêncio uma matrícula existente, sem validar a classe de destino.
17. **Classes do 2.º Ciclo** não existem no catálogo; novos níveis exigem `grade_number` de 1 a 13 (exclui a Iniciação).

### Documentos

18. **Termo:** `$academicYear->year` não existe (só `name`), logo o "Ano Lectivo" sai vazio; `{{ $student->municipality }}`, `province` e `school->municipality` imprimem o modelo e não o nome; colunas 2.ª e 4.ª da 6.ª sempre "-"; coluna "Fal" da 9.ª sempre "-"; "Nr. de Exame" usa o nº de processo e não o nº de exame; `TermoController` pode falhar com turma nula. O `TermService` parece código morto.
19. **Valores fixos no código:** "Luanda" (certificados, termo 6.ª, pautas), "- Maianga" (certificado 6.ª), "Distrito da Maianga / Município de Luanda / Província de Luanda" e o nome do visto ("Antónia de Castro Cortez") nas pautas. Declaração e atestado usam os dados da escola.
20. **Cabeçalhos inconsistentes** entre documentos (secção 7.2).
21. **Certificado:** 12.ª e 13.ª aparecem na lista mas caem no modelo "Ensino Primário"; "livro" e "folha" em branco na 6.ª; `generateSelected` não valida a turma; mistura de `$school` com `$originalSchool` na 9.ª.
22. **Pautas:** a pauta final de qualquer classe diz "Pauta da classe de exame"; coluna NR sempre vazia; `section->shift->name` devolve vazio (shift é texto, o Período nunca aparece); a lista de classes de exame está duplicada na vista; `exportPdf` não aplica a restrição de trimestre activo do `show`.
23. **Declaração:** `scale` é enviado à vista mas não usado; disciplinas sem lançamento são omitidas sem aviso.
24. **Lista de classes de exame duplicada** em `config/assessment.php`, `ExamService.php:12`, `GradeService.php:332,599`, `TermoController` e na vista da pauta.
25. **Numeração de documentos:** não há número de documento nem livro de registo gerido. `SchoolCounter` só controla o nº de processo.

---

## 11. Recomendações para o novo sistema

**Replicar**

- Cálculo central num só módulo (`GradeCalculator` + `PromotionRules`), com os valores em configuração.
- Escala e nota mínima como atributos da classe, nunca como números espalhados.
- Iniciação sem notas e fecho com resultado automático.
- Escola certificadora por nível para escolas privadas e mistas.
- Fallbacks de campos em falta em todos os documentos (pontos ou traços), decididos uma só vez.

**Corrigir**

- Uma só definição de "Aprovado" (a de `PromotionRules`), usada na pauta, no termo, no assistente de promoção e no fecho.
- Tratar o recurso por classe (também nas sem exame) e definir o estado depois do recurso de forma única.
- Eliminar valores fixos (local, município, nomes) e ler tudo da escola e da direcção municipal.
- Entidade própria de encarregado de educação, com filiação estruturada e redacção única.
- Número de documento e registo de livro e folha geridos pelo sistema.
- Atribuir `roll_number` automaticamente (ordem alfabética por omissão).
- Validar idade mínima e máxima, capacidade da turma, sobreposição de anos e datas do ano.
- Fluxo de transferência (escola de origem e destino, guia, data).
- Guardar o Inglês ou Francês escolhido.

**Perguntas a validar com o regulamento oficial**

1. Regime brando ou estrito na 7.ª e 8.ª, e na 9.ª.
2. Número máximo de negativas por classe e o papel de LP e MAT em cada uma.
3. O exame de recurso e o que acontece após ele (classes com e sem exame nacional).
4. Limite de faltas e consequência para a aprovação.
5. Regra de desistência.
6. Peso 40/60 entre ano e exame, e se o arredondamento é sempre para o inteiro mais próximo.
7. Modelo do certificado da 6.ª e da 9.ª e as alíneas legais citadas (artigo 109.º da LBSEE 17/16).
8. Texto oficial da declaração, do termo e do atestado.

---

## 12. Mapa de ficheiros-fonte

| Tema | Ficheiros |
|---|---|
| Regras de avaliação | `config/assessment.php`, `app/Domain/Assessment/{GradeCalculator,GradeScale,PromotionRules,YearOutcome,GradeAverageRecalculator,GradeInWords}.php` |
| Currículo | `config/curriculum.php`, `app/Services/CurriculumService.php`, `app/Models/Level.php`, `app/Enums/EducationStage.php` |
| Notas e pautas | `app/Services/{GradeService,GradeGridService,GradeSheetLockService,HistoryGradeSheetService}.php`, `app/Enums/GradeKind.php` |
| Matrícula e fecho | `app/Services/{EnrollmentService,YearTransitionService,ExamService,AcademicYearService}.php` |
| Cadastro | `app/Models/{Student,School,Enrollment,Section,Room,Teacher,AcademicYear,EducationOffice,SchoolLink}.php`, `app/Http/Requests/School/*`, `app/Http/Requests/Concerns/ValidatesIdentityDocument.php`, `app/Rules/{StudentAgeRule,AngolanIdentityCard}.php`, `app/Services/Identity/IdentityCardService.php`, `app/Enums/IdentityDocumentType.php` |
| Documentos | `app/Http/Controllers/Web/{Certificate,Declaration,Termo,Grades,Attestation,PrintJob}Controller.php`, `app/Services/Printing/**`, `resources/views/school/reports/**`, `resources/views/school/grades/**` |
| Migrations | `database/migrations/2026_11_01_0000*` |
| Testes | `tests/Feature/{PromotionRulesTest,PreschoolTest,DeclarationTest}.php`, `tests/Unit/Assessment/GradeCalculatorTest.php` |
| Documentação existente | `docs/modules/{assessment,curriculum,certificates-and-exams,enrollment-and-year-transition,printing}.md`, `docs/database.md` |
