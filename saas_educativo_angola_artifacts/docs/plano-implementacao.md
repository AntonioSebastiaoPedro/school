# Plano de implementação técnica

## Sprint 0 — Preparação
- Criar repositório e configurar PHP/Laravel, PostgreSQL, React + TypeScript + Vite.
- Definir ambientes local, staging e produção.
- Configurar formatter/linter, testes, CI e variáveis de ambiente.
- Fixar versões e documentar setup.

## Sprint 1 — Identidade e tenant
- Implementar autenticação Sanctum.
- Criar associação utilizador-instituição.
- Implementar resolução do tenant activo.
- Criar Policies e testes de acesso cruzado.
- Definir fluxo de convite e atribuição de papéis.

## Sprint 2 — Adesão e planos
- Pedido público de adesão.
- Painel de revisão e decisão.
- Criação/activação de instituição aprovada.
- CRUD de planos, limites e subscrições.
- Pagamentos manuais da subscrição SaaS, separados do financeiro educativo.

## Sprint 3 — Cadastro e matrículas
- Alunos/formandos e encarregados.
- Anos lectivos, classes e turmas.
- Matrículas com estados e histórico.
- Cursos, edições e inscrições.
- Validações de capacidade e duplicação.

## Sprint 4 — Financeiro
- Cobranças e pagamentos.
- Alocações de pagamento.
- Transacções e idempotência.
- Recibos, estornos e auditoria.
- Relatórios e reconciliação básica de caixa.

## Sprint 5 — Presenças e experiências prioritárias
- Presenças para escola e formação.
- Portal de encarregados.
- Certificados e verificação pública.
- Documentos privados e exportações.

## Sprint 6 — Preparação do piloto
- Testes de autorização e isolamento.
- Testes de concorrência financeira.
- Testes de backup/restore.
- Testes de carga e segurança.
- Formação de 2–3 instituições piloto.
- Recolha de feedback e correção antes da venda ampla.

## Definition of Done por endpoint
- Validação de entrada.
- Autorização e tenant aplicados no backend.
- Respostas de sucesso e erros documentadas.
- Teste de sucesso, validação, não autorizado e tenant cruzado.
- Transacção e idempotência quando há efeitos financeiros.
- Registo de auditoria para operações críticas.
- OpenAPI actualizado.
