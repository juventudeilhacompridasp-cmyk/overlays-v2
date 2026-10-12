# Atalhos do ambiente local (Docker). Rode `make help`.
UID := $(shell id -u)
GID := $(shell id -g)
export UID GID

DC  := docker compose
APP := $(DC) exec app

.DEFAULT_GOAL := help

help: ## Lista os comandos
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

setup: ## Primeira execução: .env, containers, dependências, chave, banco
	@[ -f .env ] || cp .env.example .env
	$(DC) up -d --build
	$(APP) composer install
	$(APP) php artisan key:generate
	$(APP) php artisan migrate --seed --force
	@echo ""
	@echo "Pronto! App: http://localhost:8080  |  Login: admin@plataforma.test / password"
	@echo "Mailpit: http://localhost:8025  |  S3 local: http://localhost:9000"

up: ## Sobe os containers
	@[ -f .env ] || cp .env.example .env
	$(DC) up -d --build

down: ## Para os containers
	$(DC) down

restart: ## Reinicia os containers
	$(DC) restart

logs: ## Acompanha os logs (make logs s=app)
	$(DC) logs -f --tail=100 $(s)

shell: ## Terminal dentro do container da aplicação
	$(APP) sh

fresh: ## Recria o banco com dados de demonstração
	$(APP) php artisan migrate:fresh --seed --force

migrate: ## Roda as migrations
	$(APP) php artisan migrate

test: ## Testes (Pest)
	$(APP) php artisan test

lint: ## Verifica estilo (Pint)
	$(APP) vendor/bin/pint --test

fix: ## Corrige estilo (Pint)
	$(APP) vendor/bin/pint

stan: ## Análise estática (Larastan)
	$(APP) vendor/bin/phpstan analyse --memory-limit=1G

types: ## Checagem de tipos do front (tsc)
	$(DC) exec vite npx tsc --noEmit

.PHONY: help setup up down restart logs shell fresh migrate test lint fix stan types
