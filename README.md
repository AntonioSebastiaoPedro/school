# Plataforma de Gestão Educativa (Angola)

SaaS multi-instituição para escolas (Iniciação, Ensino Primário e I Ciclo, classes 0–9) e centros de formação.
Laravel 13 (API) + React/TypeScript (Vite) + PostgreSQL.

- Requisitos: [`requisitos.md`](requisitos.md)
- Regras do ensino angolano (fonte normativa): [`saas_educativo_angola_artifacts/docs/regras-ensino.md`](saas_educativo_angola_artifacts/docs/regras-ensino.md)
- Modelo de dados: [`database/schema.sql`](saas_educativo_angola_artifacts/database/schema.sql) é a fonte única; as migrations em `backend/database/migrations` são **geradas** com `python3 saas_educativo_angola_artifacts/tools/build_migrations.py`.
- Contrato da API: [`api/openapi.yaml`](saas_educativo_angola_artifacts/api/openapi.yaml)

## Estado

Implementado: fundação, identidade e tenant (sessão Sanctum, `X-Institution-Id` validado contra a associação), adesão e aprovação de instituições, planos e subscrições, perfil escolar, currículo oficial, alunos e encarregados, anos/salas/turmas, matrículas (idade, capacidade, renovação em massa, transferência), notas, pautas (trimestral, final e de recurso em JSON), fecho do ano.

Fora desta entrega: financeiro, portal dos encarregados, certificados de formação, PDF dos documentos oficiais (declaração, certificado, termo, atestado, pauta A3), piloto. Os municípios de Angola não vêm carregados: preencher `backend/database/seeders/data/municipalities.csv` com a divisão oficial (colunas `province,municipality`).

## Arranque local

```bash
docker compose up -d db                       # PostgreSQL em localhost:55432 (bases school e school_test)
cd backend
./php.sh composer install                     # PHP 8.4 com pdo_pgsql, dentro do contentor
./php.sh php artisan migrate:fresh --seed --seeder=DemoSeeder
docker compose up php                         # API em http://localhost:8000
cd ../frontend && npm install && npm run dev  # http://localhost:5173 (proxy para a API)
```

Utilizadores de demonstração (só desenvolvimento): `director@demo.ao` e `admin@demo.ao`, palavra-passe `password`.

## Testes

```bash
cd backend && ./php.sh vendor/bin/pest        # corre sempre na base school_test
cd frontend && npm test
```

Os testes de feature recusam correr numa base cujo nome não termine em `_test`.

## Decisões de regra a validar com o Ministério da Educação

Ver `docs/regras-ensino.md` §9. Em particular: **o recurso só existe na 6.ª e na 9.ª classe**; nas restantes, mais de 2 negativas (ou LP e MAT ambas negativas) reprovam directamente. Todos os parâmetros vivem em `assessment_rules` (por escola, versionados).
