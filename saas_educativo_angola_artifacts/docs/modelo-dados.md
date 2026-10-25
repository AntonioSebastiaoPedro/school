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

### Educação
- `students`: perfil educativo da pessoa.
- `guardians`: perfil do encarregado.
- `student_guardian`: relação validada entre encarregado e aluno.
- `school_years`, `school_classes`, `classrooms`, `subjects`: estrutura escolar.
- `enrollments`: matrícula do aluno num ano lectivo, classe e turma.
- `courses`, `course_editions`, `training_enrollments`: catálogo, edição e inscrição em formação.
- `attendance_records`: presenças por sessão, com vínculo à matrícula ou inscrição.
- `assessments`, `assessment_results`: avaliações e resultados, separados do registo de presenças.

### Financeiro e documentos
- `charges`: obrigação/cobrança emitida.
- `payments`: recebimento registado e confirmado segundo a política da instituição.
- `payment_allocations`: distribuição de um pagamento por uma ou mais cobranças.
- `receipts`: documento emitido para um pagamento.
- `cash_registers`: sessão de caixa.
- `certificates`: certificados de conclusão.
- `documents`: metadados de ficheiros privados.
- `audit_logs`: histórico de operações relevantes.

## 3. Regras de integridade recomendadas

- Unicidade de `institution_id + code` para códigos de aluno, matrícula, curso, recibo e certificado.
- Unicidade de matrícula por `institution_id + student_id + school_year_id`, excepto se o regulamento exigir múltiplas matrículas por programa.
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

- Identificação civil e campos obrigatórios exigidos por cada instituição.
- Regras oficiais de classes, períodos de avaliação e transição de ano.
- Política de emissão fiscal/numeração dos recibos.
- Política de crédito/adiantamento e alocação automática de pagamentos.
- Política de desconto, bolsa, isenção, mora e estorno.
- Se uma instituição pode ter várias unidades.
- Política de retenção e exportação após fim da subscrição.
