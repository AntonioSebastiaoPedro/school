# Regras do ensino angolano — Iniciação, Ensino Primário e I Ciclo

Fonte normativa interna para modelo de dados, API e testes. Deriva de `regras-ensino-primeiro-e-1-ciclo.md` (comportamento da plataforma anterior) com as correcções decididas para o novo sistema. Nada aqui substitui o regulamento oficial do Ministério da Educação; os pontos por validar estão na secção 9.

Âmbito: classe 0 (Iniciação), 1.ª–6.ª (Ensino Primário), 7.ª–9.ª (I Ciclo do Ensino Secundário). O II Ciclo (10.ª–13.ª) está fora do âmbito.

## 1. Catálogo de classes (`grade_levels`, global)

| Classe | Código | Etapa | Idade mín.–máx. | Escala | Mínimo positivo | Exame nacional | Regime |
|---|---|---|---|---|---|---|---|
| 0 | INIT | `preschool` | 5–6 | sem notas | — | não | `none` |
| 1–5 | 1ST…5TH | `primary` | 6–10 … 10–11 | 0–10 | 5 | não | `lenient` |
| 6 | 6TH | `primary` | 11–12 | 0–10 | 5 | **sim** | `strict` |
| 7–8 | 7TH, 8TH | `lower_secondary` | 12–13, 13–14 | 0–20 | 10 | não | `lenient` |
| 9 | 9TH | `lower_secondary` | 14–15 | 0–20 | 10 | **sim** | `strict` |

Idades: 1.ª 6–7, 2.ª 7–8, 3.ª 8–9, 4.ª 9–10, 5.ª 10–11. A progressão é sempre `grade_number + 1`.
Designação nos documentos: Iniciação e Primário → "Ensino Primário"; 7.ª–9.ª → "I Ciclo".

## 2. Currículo por classe

Créditos 1, excepto LP e MAT (2). "Nuclear" para a regra de aprovação são apenas **LP e MAT**.

| Código | Disciplina | Classes | h/sem. | Tipo |
|---|---|---|---|---|
| LP | Língua Portuguesa | 1–9 | 5 | obrigatória |
| MAT | Matemática | 1–9 | 5 | obrigatória |
| EM | Estudo do Meio | 1–4 | 3 | obrigatória |
| CN | Ciências da Natureza | 5–6 | 3 | obrigatória |
| BIO, FIS, QUI | Biologia, Física, Química | 7–9 | 3 | obrigatória |
| HIST, GEO | História, Geografia | 5–9 | 2 | obrigatória |
| EMC | Educação Moral e Cívica | 1–9 | 2 | obrigatória |
| EMP | Educação Manual e Plástica | 1–6 | 2 | obrigatória |
| MUS | Educação Musical | 1–6 | 2 | obrigatória |
| EF | Educação Física | 1–9 | 2 | obrigatória |
| EL | Educação Laboral | 7–9 | 2 | obrigatória |
| LE | Língua Estrangeira (Inglês/Francês) | 5–9 | 3 | obrigatória |
| EMPR | Empreendedorismo | 8–9 | 2 | opcional |
| LOA | Línguas de Origem Africana | 5–9 | 2 | opcional |

Iniciação (só classe 0, todas obrigatórias, sem notas): CL Comunicação Linguística (3), RM Representação Matemática (3), EP Expressão Plástica (2), MFS Meio Físico e Social (2), PJ Psicomotricidade e Jogos (2), HD Histórias Dramáticas (1).

Decisão nova: a Língua Estrangeira guarda o idioma escolhido (`enrollment_subject_choices`: Inglês ou Francês).

## 3. Avaliação

- Três trimestres. `grades.term` 1–3; `0` é a nota anual (exame e recurso).
- Tipos: `mac` (Avaliação Contínua), `npp` (Prova do Professor), `npt` (Prova Trimestral), `exam` (NE, anual), `resit` (NER, anual).
- Valor: `0 ≤ nota ≤ máximo da classe`, 2 casas decimais. **0 ou vazio significa "sem nota"**, nunca negativa.
- Só se lançam notas a alunos matriculados na turma e no ano. Iniciação: erro "Na Iniciação não se lançam notas.".
- Pauta fechada (turma, ano, período 0–3) recusa gravação; só a direcção abre e fecha.

### Fórmulas (valores em `assessment_rules`)

| Resultado | Fórmula | Arredondamento |
|---|---|---|
| MT | média dos componentes **presentes** (> 0) entre MAC, NPP, NPT; sem componentes, 0 | 1 casa |
| MFD | média dos MT preenchidos (> 0) | 1 casa |
| MFA sem exame | `round(MFD)` | inteiro |
| MFA com exame, só NE | `round(0,4 × MFD + 0,6 × NE)` | inteiro |
| MFA com exame, com NER | `round(NER)` | inteiro |
| MFA com exame, sem NE | `round(MFD)` | inteiro |
| Média do certificado | média das classificações > 0 do histórico | inteiro |

Correcção face ao sistema anterior: o MT deixa de usar divisor fixo 3 (uma só nota 12 dá MT 12,0, não 4,0). Configurável por `mt_divisor_mode` (`present` por omissão, `fixed3`).

`grade_averages` materializa por matrícula e disciplina: `term1_avg`, `term2_avg`, `term3_avg`, `year_avg`, `exam`, `resit`, `final_avg`. Recalculada a cada gravação; só guarda valores positivos.

Nota por extenso: "Cinco Valores" a "Vinte Valores". Fora de 5–20 devolve "-".

## 4. Aprovação, reprovação e recurso

Decisão a partir das classificações finais (MFA) por disciplina. Valores 0 são ignorados; sem nenhuma nota > 0 o resultado é **Pendente**. Negativa = MFA < nota mínima da classe. Resultados: `pending`, `promoted`, `resit`, `failed`, `dropped`.

**Uma só definição de Aprovado**: `PromotionRules`, usada na pauta, no termo, no assistente de promoção e no fecho do ano.

### Regime estrito — 6.ª e 9.ª (classes de exame)

| Negativas | Resultado |
|---|---|
| 0 | Aprovado |
| 1–2 | Recurso |
| ≥ 3 | Reprovado |

### Regime brando — 1.ª–5.ª, 7.ª e 8.ª (sem exame)

**Não há recurso.**

| Negativas | Resultado |
|---|---|
| 0–2 | Aprovado, excepto se LP e MAT são ambas negativas → Reprovado |
| ≥ 3 | Reprovado |

Decisão do projecto: o "Recurso" que o sistema anterior atribuía nestas classes passa a Reprovado, porque **só a 6.ª e a 9.ª têm exame de recurso**. Ponto a validar (secção 9).

### Exame de recurso (só 6.ª e 9.ª)

- Um só recurso por disciplina (NER). O NER substitui a classificação da disciplina.
- Resultado após recurso: **Aprovado** se zero negativas, senão **Reprovado** (não há segunda oportunidade). O fecho do ano usa esta mesma função, de modo que o estado gravado coincide com o da pauta.
- Não existe estado `resit` nem pauta de recurso fora da 6.ª e 9.ª.

### Iniciação
Sem avaliação; todos transitam (resultado `promoted` automático no fecho).

### Assiduidade e desistência
Estados `present`, `absent`, `late`, `justified`; só `absent` conta como falta injustificada. Não há limite de faltas nem reprovação por faltas por omissão (`attendance_limit` desligado). Desistência desligada por omissão (`dropout.enabled = false`).

## 5. Fecho do ano e transição

Exige as pautas dos trimestres 1–3 fechadas. Por turma: obter notas finais, aplicar `PromotionRules` (via `OutcomeResolver`, a mesma da pauta final), gravar `enrollment_results` (`status`, `reason`, `failed_subjects`) e `historical_grades`. Promoção: aprovado vai para a classe seguinte; reprovado repete; em recurso aguarda o resultado do NER. A renovação em massa cria matrículas novas (`enrollment_type = renewal`, `previous_enrollment_id`) sem nunca substituir uma matrícula existente e valida a classe de destino e a idade.

## 6. Dados do aluno e filiação

- Género `M`/`F` (decide a redacção filho/filha, nascido/a, aluno/a).
- Nº de processo (`student_number`): gerado, 6 dígitos, `max(maior número puramente numérico do tenant, incluindo eliminados) + 1`, nunca abaixo do contador inicial; único por tenant. Números com letras ou traços (importados) são ignorados no cálculo.
- Nº de ordem (`roll_number`): atribuído automaticamente à matrícula (o seguinte da turma); `POST /sections/{id}/renumber` reordena alfabeticamente antes da pauta.
- Documento: `birth_record`, `personal_card`, `identity_card`, `passport`; nunca obrigatório (comum no Primário). BI: `^[0-9]{9}[A-Z]{2}[0-9]{3}$` após normalizar (sem espaços, pontos, hífens; maiúsculas). Emissor por omissão: BI → Direcção Nacional de Identificação; Cédula/Assento → Conservatória do Registo Civil; Passaporte → Serviço de Migração e Estrangeiros.
- Naturalidade: país, província ⊂ país, município ⊂ província; no estrangeiro, província e município ficam vazios.
- Idade: `birth_date` anterior a hoje; idade mínima **e máxima** da classe validadas na matrícula (a máxima é aviso, não bloqueio, configurável).
- Nomes: guardados por extenso, sem forçar Title Case (preserva "dos Santos", "de", "da").
- **Filiação** estruturada: pai e mãe (nome obrigatório de pelo menos um) e encarregado de educação como entidade própria (`guardians`) com parentesco, documento, telefones, e-mail, profissão, morada e responsável financeiro. Contacto de emergência estruturado (nome, parentesco, telefone). Um único ponto de formatação: "filho/a de A e de B", só A, só B, ou pontos; igual em todos os documentos.
- Estados do aluno: `active`, `inactive`, `transferred`, `graduated`, `archived`. A transferência tem fluxo próprio (`student_transfers`: origem, destino, guia, data, motivo).

## 7. Escola, turma, ano lectivo

- Perfil da escola (`school_profiles`, 1:1 com a instituição): código, ex-código, decreto de criação, director (nome, género, telefone), complexo escolar (decide "do/no" vs "da/na"), regime `public`/`private`/`mixed`, província, município, direcção municipal, distrito, comuna, trimestre activo, logótipo.
- Escola certificadora (`school_links`): escolas `private`/`mixed` não assinam certificados; assina uma escola pública, por grupo de nível (`primary`, `lower_secondary`), da plataforma ou com dados manuais. Todos os dados do certificado (nome, código, ex-código, decreto, director) vêm da certificadora, sem mistura.
- Sem valores fixos no código ("Luanda", "Maianga", nomes de visto): tudo vem do perfil da escola e da direcção municipal.
- Turma (`sections`): nome único por tenant, ano e classe; turno `morning`/`afternoon`/`night`; capacidade (default 40, editável, **verificada na matrícula**; cruza com a capacidade da sala).
- Ano lectivo: nome `AAAA/AAAA`, `ends_on ≥ starts_on`, um único `active` por tenant, sem sobreposição de datas; ao activar um novo ano o anterior passa a `closed`.

## 8. Documentos oficiais (modelo de dados nesta entrega; PDF depois)

Declaração (1.ª–9.ª), Certificado (6.ª e 9.ª, 3.º trimestre, só aprovados em lote), Termo de Frequência e Exame (6.ª e 9.ª), Pauta trimestral/final (A3), Pauta de recurso (só 6.ª e 9.ª), Atestado (Iniciação). Cada emissão regista número, livro e folha geridos pelo sistema (`document_sequences`, `issued_documents`).

## 9. Pendências a validar com o Ministério da Educação

1. Regime brando sem recurso (decisão actual) e se 3+ negativas reprovam sempre nas classes sem exame.
2. Papel de LP e MAT: dupla negativa reprova nas classes sem exame.
3. Regime da 7.ª e 8.ª (brando) e da 9.ª (estrito).
4. Peso 40/60 entre ano e exame; arredondamento ao inteiro mais próximo.
5. MT sobre componentes presentes (vs divisor fixo 3).
6. Limite de faltas e consequência; regra de desistência.
7. Idade máxima: aviso ou bloqueio.
8. Modelos oficiais e textos de declaração, certificado, termo e atestado; alíneas citadas do artigo 109.º da LBSEE 17/16.

Todos os valores acima vivem em `assessment_rules` (por tenant, versionada) para permitir ajuste sem alterar código.
