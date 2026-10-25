# Modelo de dados — SaaS Educativo Angola

## 1. Decisões fundamentais

1. **Multi-tenancy por base partilhada e isolamento lógico**: os dados institucionais têm `institution_id`.
2. **Tenant derivado no servidor**: o tenant activo é obtido da associação autenticada utilizador-instituição. O frontend não define autoridade.
3. **Integridade entre tenants**: usar FKs compostas `(institution_id, related_id)` onde ambas as entidades pertencem a uma instituição.
4. **Pessoas, contas e papéis são conceitos distintos**: uma pessoa pode ser aluno/formando sem login; um encarregado pode ter login; um utilizador pode pertencer a mais de uma instituição.
5. **Escola e formação são domínios diferentes**: `enrollments` não é a mesma coisa que `training_enrollments`.
6. **Financeiro é partilhado**: cobranças, pagamentos e alocações suportam os dois módulos.
7. **Registos financeiros não são apagados fisicamente**: corrigir por estorno/anulação auditável.
8. **Planos e pagamentos do SaaS são separados** dos pagamentos educativos dos clientes.

## 2. Entidades e responsabilidades

### Plataforma
- `institutions`: organização cliente, tipo (`school`, `training_center`, futuramente `both`) e estado de aprovação/activação.
- `institution_applications`: pedido de adesão, dados submetidos e decisões administrativas.
- `plans`: oferta comercial, preço e limites.
- `plan_features`: funcionalidades/limites configuráveis do plano.
- `subscriptions`: período comercial de uma instituição e estado.
- `subscription_payments`: registos manuais dos pagamentos à plataforma.

### Identidade
- `users`: identidade de autenticação.
- `institution_user`: associação de utilizador à instituição e papel primário.
- A autorização fina pode usar Policies/Gates e Spatie Permission; os papéis não substituem a verificação do tenant.

### Catálogos globais (sem tenant)
- `countries`, `provinces`, `municipalities`, `education_offices`: geografia de Angola. Província e município de um aluno são validados por FK composta contra o país e a província.
- `grade_levels`: classes 0–9 com etapa, idades, escala (`max_score`, `pass_mark`), `has_national_exam` e `assessment_regime` (`none`, `lenient`, `strict`). Semeada no próprio `schema.sql`. Escala e nota mínima vivem aqui, nunca espalhadas pelo código.

### Escola (por tenant)
- `school_profiles`: perfil 1:1 de uma instituição de tipo `school` ou `both` (FK composta `(institution_id, institution_type)`). Código, ex-código, decreto, director, regime público/privado/misto, complexo, localização, trimestre activo. Centros de formação não têm perfil escolar.
- `school_links`: escola certificadora por grupo de nível (`primary`, `lower_secondary`) para escolas privadas e mistas; aponta para outra instituição ou guarda os dados manuais.
- `assessment_rules`: parâmetros de avaliação versionados (JSONB, uma versão activa), ver `regras-ensino.md`.
- `school_years` (nome `AAAA/AAAA`, `EXCLUDE` contra sobreposição de datas, um único `active`), `rooms`, `sections` (turma: ano, classe, sala, turno, capacidade).
- `subjects` e `grade_level_subjects`: catálogo e currículo por classe, copiados do modelo oficial na aprovação da escola e ajustáveis depois.

### Alunos e encarregados
- `students`: cadastro independente da matrícula. Género `M`/`F`, `student_number` (nº de processo gerado, único por tenant), naturalidade, documento opcional (BI com formato validado por `CHECK`), `father_name`/`mother_name` como filiação de recurso, contacto de emergência estruturado.
- `guardians` e `student_guardian`: encarregado como entidade própria. `relationship` (`father`, `mother`, `other`) com índice único que impede dois pais ou duas mães por aluno; `is_financial_responsible`, `is_emergency_contact`.
- A filiação impressa vem de `student_guardian` (pai/mãe) e, na falta, dos campos `father_name`/`mother_name`. Há um único ponto de formatação.

### Matrículas e avaliação
- `enrollments`: ano, classe, turma, `roll_number` (nº de ordem, único por turma), `enrollment_type` (`new`, `renewal`, `transfer_in`), `previous_enrollment_id`, `is_repeater`. FK composta `(section_id, school_year_id, grade_level_id, institution_id)` garante que a turma é do mesmo ano e da mesma classe da matrícula.
- `enrollment_subject_choices`: escolhas por matrícula, nomeadamente Inglês ou Francês na Língua Estrangeira.
- `student_transfers`: origem, destino, guia e data.
- `grades`: `term` 0–3 e `kind` (`mac`, `npp`, `npt`, `exam`, `resit`) coerentes por `CHECK`. O valor máximo por classe e a ausência de notas na Iniciação são validados no serviço, porque dependem da classe da matrícula.
- `grade_averages`: médias materializadas por matrícula e disciplina (T1–T3, ano, exame, recurso, final). Recalculadas a cada gravação; não são fonte de verdade, apenas cache de leitura derivada de `grades`.
- `enrollment_results`: resultado do ano (`pending`, `promoted`, `resit`, `failed`, `dropped`). `resit` só ocorre em classes com exame nacional.
- `historical_grades`: classificação final por aluno, classe e disciplina; alimenta certificados.
- `grade_sheet_locks`: pauta fechada por turma e período.
- `exams`: nº de exame, sala e estado (só 6.ª e 9.ª).
- `attendance_records`: estados `present`, `absent`, `late`, `justified`; justificação obrigatória quando `justified`.

### Formação
- `courses`, `course_editions`, `training_enrollments`: inalterados.

### Financeiro e documentos
- `charges`: obrigação/cobrança emitida.
- `payments`: recebimento registado e confirmado segundo a política da instituição.
- `payment_allocations`: distribuição de um pagamento por uma ou mais cobranças.
- `receipts`: documento emitido para um pagamento.
- `cash_registers`: sessão de caixa.
- `certificates`: certificados de conclusão de formação.
- `document_sequences` e `issued_documents`: numeração, livro e folha dos documentos oficiais escolares (declaração, certificado, termo, atestado).
- `documents`: metadados de ficheiros privados.
- `audit_logs`: histórico de operações relevantes.

## 3. Regras de integridade recomendadas

- Unicidade de `institution_id + code` para nº de processo (`student_number`), matrícula, curso, recibo e certificado.
- Unicidade de matrícula por `institution_id + student_id + school_year_id`; a renovação cria nova linha com `previous_enrollment_id` e nunca substitui a anterior.
- Um só ano lectivo `active` e sem sobreposição de datas por instituição.
- A turma de uma matrícula pertence ao mesmo ano e classe (FK composta).
- No máximo um pai e uma mãe por aluno.
- Unicidade de inscrição por `institution_id + course_edition_id + student_id`.
- FKs compostas para evitar associar uma turma de uma instituição a um aluno de outra.
- Pagamento e cobrança devem pertencer ao mesmo tenant; `payment_allocations` valida ambos.
- `amount > 0` para cobranças e pagamentos; estornos registam-se como transacções explícitas ou registos de reversão, conforme política financeira.
- A soma das alocações confirmadas não pode exceder o montante do pagamento nem o saldo devido das cobranças. Esta regra precisa de transacção e locking no serviço; um `CHECK` não consegue validar somas entre linhas.
- Certificados emitidos são imutáveis quanto ao seu conteúdo essencial; anulação deve manter o registo e o motivo.
- O acesso público de verificação usa `verification_code` aleatório, não a PK sequencial.
- Não usar `cascade delete` em pagamentos, recibos, matrículas históricas ou certificados.

## 4. Índices sugeridos

- Todas as FKs devem ter índices quando a consulta/planeamento os justificar; PostgreSQL não cria automaticamente índices no lado referenciador.
- `students(institution_id, status)`, `enrollments(institution_id, school_year_id, classroom_id, status)`.
- `charges(institution_id, status, due_date)`, `payments(institution_id, paid_at)`.
- `attendance_records(institution_id, session_date, classroom_id)`.
- `institution_applications(status, submitted_at)`, `subscriptions(institution_id, status)`.
- Índices únicos para códigos de recibo e certificado por instituição.
- Índice parcial para registos activos pode ser considerado após medir consultas reais.

## 5. Isolamento multi-tenant

A API deve:
1. autenticar o utilizador;
2. resolver a instituição activa a partir da associação validada;
3. verificar que a instituição está aprovada/operacional e que a subscrição permite a operação;
4. carregar recursos sempre dentro do tenant;
5. autorizar a acção via Policy;
6. escrever auditoria para operações críticas.

Exemplo conceptual: `Student::query()->where('institution_id', $tenantId)->findOrFail($id)`. Não carregar primeiro por ID global e só depois confiar nos dados do cliente.

Como defesa adicional, avaliar PostgreSQL Row-Level Security. Se utilizada, configurar o contexto da sessão/transacção com cuidado para não vazar tenant entre conexões reutilizadas do pool.

## 6. Decisões pendentes antes da migração definitiva

- Pontos de `regras-ensino.md` §9 por validar com o Ministério da Educação (regime brando sem recurso, peso 40/60, MT sobre componentes presentes, faltas, desistência, idade máxima, modelos oficiais).
- Geração em PDF dos documentos oficiais escolares (o modelo de dados já a suporta).
- Política de emissão fiscal/numeração dos recibos.
- Política de crédito/adiantamento e alocação automática de pagamentos.
- Política de desconto, bolsa, isenção, mora e estorno.
- Se uma instituição pode ter várias unidades.
- Política de retenção e exportação após fim da subscrição.
