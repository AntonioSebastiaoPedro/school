DC_DEV  = docker compose -f compose.yml -f compose.dev.yml
DC_PROD = docker compose -f compose.yml -f compose.prod.yml
ART     = saas_educativo_angola_artifacts
# O ambiente do contentor (.env) sobrepõe-se ao phpunit.xml, por isso os testes recebem o seu explicitamente.
TEST_ENV = -e APP_ENV=testing -e DB_DATABASE=school_test -e SESSION_DRIVER=array -e CACHE_STORE=array -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync -e BCRYPT_ROUNDS=4

.DEFAULT_GOAL := help
.PHONY: help setup up down restart build logs ps artisan composer npm shell psql test test-backend test-frontend lint format \
        fresh migrations schema-check openapi-lint prod-up prod-down prod-logs prod-ps prod-artisan deploy backup restore

help: ## Mostra os comandos disponíveis
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / {printf "  \033[1m%-16s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ---------- Desenvolvimento ----------
setup: ## Primeira instalação: .env, contentores, dependências, migrations e dados de demonstração
	@test -f .env || cp .env.example .env
	@grep -q '^APP_KEY=base64' .env || sed -i "s#^APP_KEY=.*#APP_KEY=base64:$$(openssl rand -base64 32)#" .env
	@cp .env backend/.env
	$(DC_DEV) up -d --build postgres app nginx
	$(DC_DEV) exec app composer install
	$(DC_DEV) exec app php artisan migrate:fresh --seed --seeder=DemoSeeder --force
	$(DC_DEV) up -d node
	@echo "API: http://localhost:$$(grep ^HTTP_PORT .env | cut -d= -f2)  ·  Frontend: http://localhost:$$(grep ^VITE_HOST_PORT .env | cut -d= -f2)"
	@echo "Demonstração: director@demo.ao e admin@demo.ao, palavra-passe: password"

up: ## Arranca os contentores de desenvolvimento
	$(DC_DEV) up -d
down: ## Para os contentores de desenvolvimento
	$(DC_DEV) down
restart: ## Reinicia os contentores
	$(DC_DEV) restart
build: ## Reconstrói as imagens de desenvolvimento
	$(DC_DEV) build
logs: ## Segue os registos (make logs s=app para um serviço)
	$(DC_DEV) logs -f $(s)
ps: ## Estado dos contentores
	$(DC_DEV) ps
artisan: ## Corre o artisan: make artisan cmd="migrate:status"
	$(DC_DEV) exec app php artisan $(cmd)
composer: ## Corre o composer: make composer cmd="require x/y"
	$(DC_DEV) exec app composer $(cmd)
npm: ## Corre o npm no frontend: make npm cmd="install x"
	$(DC_DEV) exec node npm $(cmd)
shell: ## Shell no contentor da aplicação
	$(DC_DEV) exec app sh
psql: ## Consola da base de dados de desenvolvimento
	$(DC_DEV) exec postgres sh -c 'psql -U "$$POSTGRES_USER" "$$POSTGRES_DB"'
fresh: ## Recria a base de desenvolvimento com os dados de demonstração
	$(DC_DEV) exec app php artisan migrate:fresh --seed --seeder=DemoSeeder --force

# ---------- Testes e qualidade ----------
test: test-backend test-frontend ## Todos os testes
test-backend: ## Pest, sempre na base school_test
	$(DC_DEV) exec $(TEST_ENV) app vendor/bin/pest $(args)
test-frontend: ## Vitest e verificação de tipos
	$(DC_DEV) exec node sh -c "npx tsc -b && npm test"
lint: ## Pint (verificação), tipos do frontend, schema e OpenAPI
	$(DC_DEV) exec app vendor/bin/pint --test
	$(DC_DEV) exec node npx tsc -b
	$(MAKE) schema-check openapi-lint
format: ## Formata o PHP com o Pint
	$(DC_DEV) exec app vendor/bin/pint

# ---------- Esquema e contrato ----------
migrations: ## Regenera as migrations a partir do schema.sql
	python3 $(ART)/tools/build_migrations.py
schema-check: ## Falha se as migrations não estiverem em dia com o schema.sql
	python3 $(ART)/tools/build_migrations.py --check
openapi-lint: ## Valida o OpenAPI
	npx --yes @redocly/cli@latest lint $(ART)/api/openapi.yaml --max-problems 1000 --format=stylish | tee /tmp/openapi-lint.txt; ! grep -q " error " /tmp/openapi-lint.txt

# ---------- Produção ----------
prod-up: ## Arranca a stack de produção (sem reconstruir)
	$(DC_PROD) up -d
prod-down: ## Para a stack de produção
	$(DC_PROD) down
prod-logs: ## Segue os registos de produção (make prod-logs s=app)
	$(DC_PROD) logs -f $(s)
prod-ps: ## Estado dos contentores de produção
	$(DC_PROD) ps
prod-artisan: ## Artisan em produção: make prod-artisan cmd="about"
	$(DC_PROD) exec -u www-data app php artisan $(cmd)
deploy: ## Cópia de segurança, build, migrations e optimize
	./scripts/deploy.sh
backup: ## Cópia da base de dados de produção em backups/
	./scripts/backup.sh
restore: ## Restaura uma cópia: make restore file=backups/db-AAAA-MM-DD-HHMM.sql.gz
	./scripts/restore.sh $(file)
