## 1. Pacote completo

## SaaS Educativo Angola

Pacote ZIP · Esquema SQL, OpenAPI, documentação e migration Laravel

Descarregar todos os artefactos (.zip)

## 2. Artefactos individuais

Modelo de dados PostgreSQL

Tabelas, relações, chaves estrangeiras, índices, estados e regras de integridade.

Abrir schema.sql

Contrato da API REST

OpenAPI 3.1, endpoints, parâmetros, corpos dos pedidos e respostas para o frontend React.

Abrir openapi.yaml

Especificação do modelo de dados

Explicação das entidades, decisões arquitecturais e isolamento entre instituições.

Abrir modelo-dados.md

Migration inicial Laravel

Estrutura inicial para instituições, pedidos de adesão, planos e subscrições.

Abrir migration PHP

Plano de implementação

Sequência de desenvolvimento, entregas por fase e critérios de conclusão.

Abrir plano-implementacao.md

## 3. O que já está contemplado

O esquema SQL inclui as principais estruturas de:

- Instituições, pedidos de adesão e aprovação.
- Planos, funcionalidades e subscrições.
- Utilizadores e associações institucionais.
- Alunos, encarregados e relações entre ambos.
- Anos lectivos, classes, turmas e matrículas.
- Cursos, edições e inscrições de formandos.
- Presenças.
- Cobranças, pagamentos, alocações e recibos.
- Caixas, certificados, documentos e auditoria.

O contrato OpenAPI contempla os principais fluxos de autenticação, adesão, matrículas, cursos, pagamentos, encarregados e certificados.

## 4. Atenção antes de começar a implementar

Este é um primeiro pacote técnico de referência, não uma aplicação completa pronta para produção.

Em particular:

- A migration Laravel incluída cobre apenas o núcleo inicial da plataforma; não contém ainda migrations completas para todas as entidades do esquema SQL.
- O esquema SQL deve ser revisto e validado num PostgreSQL de desenvolvimento antes de ser adoptado como base definitiva.
- O contrato OpenAPI é inicial e precisa de ser alinhado com as implementações reais, sobretudo nos fluxos de autorização e nas respostas financeiras.
- Ainda faltam migrations específicas para disciplinas, avaliações, atribuições de professores/formadores e algumas funcionalidades académicas.
- O isolamento por instituição e a consistência financeira devem ser demonstrados através de testes automatizados, não apenas pela estrutura das tabelas.

A minha recomendação: o próximo passo deve ser consolidar o modelo relacional e gerar o conjunto completo de migrations Laravel, incluindo as restrições compostas que impedem relações entre registos de instituições diferentes. Só depois avançaria para implementar os endpoints e as respectivas Policies.

Assim, o backend nasce com uma estrutura coerente, em vez de termos de corrigir o modelo de dados à medida que os módulos começam a crescer.