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

## Makefile

`make help` lista tudo. Os essenciais:

| Comando | O que faz |
|---|---|
| `make setup` | Primeira instalação: `.env`, contentores, dependências, migrations e dados de demonstração |
| `make up` / `make down` | Arranca / para o desenvolvimento (API, Postgres, Vite) |
| `make test` | Pest (sempre na base `school_test`) + Vitest + tipos |
| `make lint` / `make format` | Pint, tipos, schema e OpenAPI |
| `make artisan cmd="..."` / `make composer cmd="..."` / `make npm cmd="..."` | Comandos dentro dos contentores |
| `make psql` / `make shell` / `make logs` | Base de dados, shell e registos |
| `make migrations` | Regenera as migrations a partir do `schema.sql` |
| `make deploy` | Cópia de segurança, build, migrations, catálogos e `optimize` (produção) |
| `make backup` / `make restore file=...` | Cópia e restauro da base de produção |

## Desenvolvimento

```bash
make setup      # API em http://localhost:18480 · frontend em http://localhost:18481
```

Utilizadores de demonstração (só desenvolvimento): `director@demo.ao` e `admin@demo.ao`, palavra-passe `password`. As portas e o resto da configuração estão em `.env` (modelo em `.env.example`).

## Produção

`compose.yml` (base) + `compose.prod.yml`: `app` (php-fpm), `nginx` (frontend compilado + proxy da API) e `postgres`. O nginx só escuta em `127.0.0.1:HTTP_PORT`: ponha à frente um proxy com TLS (Caddy, nginx). No servidor: copie `.env.example` para `.env` e ajuste (`APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `DB_PASSWORD`, `APP_URL`, `FRONTEND_URL` e `SANCTUM_STATEFUL_DOMAINS` com o domínio, `SESSION_SECURE_COOKIE=true`), depois `make deploy`. Os comandos `artisan` de produção correm como `www-data` (`make prod-artisan cmd=...`).

## Testes

```bash
make test
```

Os testes de feature recusam correr fora de `APP_ENV=testing` ou numa base cujo nome não termine em `_test`.

## Decisões de regra a validar com o Ministério da Educação

Ver `docs/regras-ensino.md` §9. Em particular: **o recurso só existe na 6.ª e na 9.ª classe**; nas restantes, mais de 2 negativas (ou LP e MAT ambas negativas) reprovam directamente. Todos os parâmetros vivem em `assessment_rules` (por escola, versionados).
