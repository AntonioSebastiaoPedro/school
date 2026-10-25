# Plano de implementação técnica

Regras do ensino angolano: `docs/regras-ensino.md`. Modelo: `database/schema.sql` (fonte única; as migrations são geradas por `tools/build_migrations.py`).

## Sprint 0 — Preparação
- Repositório com `backend/` (Laravel, PHP, Pest, Pint) e `frontend/` (React + TypeScript + Vite, Vitest).
- PostgreSQL via Docker Compose; ambientes local, staging e produção.
- Linter, testes e CI; variáveis de ambiente; versões fixadas e documentadas.

## Sprint 1 — Identidade e tenant
- Autenticação Sanctum (sessão e cookie seguro).
- Associação utilizador-instituição e middleware `ResolveTenant` (o tenant vem da associação, nunca do frontend).
- Trait `BelongsToTenant` com global scope; Policies; testes de acesso cruzado.
- Convites e papéis.

## Sprint 2 — Adesão, planos e perfil escolar
- Pedido público de adesão, painel de decisão, criação da instituição aprovada.
- Na aprovação de uma escola: criar `school_profiles`, provisionar currículo (`subjects`, `grade_level_subjects`) e `assessment_rules` v1.
- Planos, limites, subscrições e pagamentos manuais da subscrição (separados do financeiro educativo).

## Sprint 3 — Cadastro e matrículas escolares
- Seeders de geografia de Angola; `grade_levels` 0–9 já semeados pelo esquema.
- Alunos (nº de processo gerado, BI validado, idade) e encarregados (filiação estruturada).
- Anos lectivos (um activo, sem sobreposição), salas, turmas com capacidade.
- Matrículas: nº de ordem automático, validação de idade e capacidade, renovação em massa sem substituição, transferência.
- Domínio de avaliação (`GradeScale`, `GradeCalculator`, `PromotionRules`, `GradeInWords`) com testes antes da camada HTTP.

## Sprint 3b — Notas e pautas
- Grelha de notas MAC/NPP/NPT e NE/NER (só 6.ª e 9.ª); Iniciação sem notas.
- Recálculo de `grade_averages`; pautas trimestral, final e de recurso (JSON); fecho e abertura de pautas.
- Fecho do ano por turma com `enrollment_results` e `historical_grades`; resultado igual ao da pauta.

## Sprint 4 — Financeiro (feito, excepto caixa)
- Cobranças (individuais e por turma), pagamentos manuais com alocação total, idempotência com fila por chave, recibos numerados, estornos autorizados e resumo financeiro.
- Por fazer: sessão de caixa e reconciliação (`cash_registers`), descontos e isenções, mora, PDF do recibo.

## Sprint 5 — Presenças, portal, documentos
- Presenças (escola e formação), portal de encarregados, certificados de formação.
- Documentos oficiais escolares em PDF (declaração, certificado, termo, atestado, pauta A3) sobre `issued_documents`.

## Sprint 6 — Piloto
- Testes de autorização, isolamento, concorrência financeira, backup/restore, carga e segurança.
- Validação das pendências de `regras-ensino.md` §9 com o Ministério da Educação.

## Definition of Done por endpoint
- Validação de entrada.
- Autorização e tenant aplicados no backend.
- Respostas de sucesso e erros documentadas.
- Testes de sucesso, validação, não autorizado e tenant cruzado.
- Transacção e idempotência quando há efeitos financeiros.
- Auditoria nas operações críticas.
- OpenAPI actualizado.
