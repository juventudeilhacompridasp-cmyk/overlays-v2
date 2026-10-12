# Plataforma de Competições + Overlays para OBS

SaaS multi-organizador para gestão de campeonatos (futebol primeiro) com placar/overlays em tempo real para transmissões.
Documentação de produto e arquitetura em [`docs/`](docs/) — comece por [`docs/04-plano-de-acao.md`](docs/04-plano-de-acao.md).

**Stack:** Laravel 13 (PHP 8.4) · Inertia + React + TypeScript (SSR) · PostgreSQL 17 · Redis · Reverb (WebSocket) · Horizon · S3 (S3Mock local, R2 em produção) · Docker.

## Rodar localmente

Pré-requisitos: Docker (com Compose v2), Git e Make.

```bash
git clone https://github.com/juventudeilhacompridasp-cmyk/overlays-v2.git
cd overlays-v2
make setup      # primeira vez: sobe tudo, instala dependências, gera chave, migra e popula o banco
```

| O quê | Endereço |
|---|---|
| Aplicação | http://localhost:8080 — login `admin@plataforma.test` / `password` |
| Vite (HMR) | http://localhost:5173 |
| Reverb (WebSocket) | `ws://localhost:8081` |
| Mailpit (e-mails de teste) | http://localhost:8025 |
| S3 local (arquivos) | http://localhost:9000 — buckets `overlays-public` e `overlays-private` (sem painel; credenciais ignoradas) |
| Postgres | `localhost:5433` — usuário/senha/banco em `.env` |

**Windows (PowerShell):** o `make` não existe no Windows; use o script equivalente: `.\dev.ps1 setup`, `.\dev.ps1 up`, `.\dev.ps1 test`…
(se o PowerShell bloquear: `Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass`). Para melhor desempenho, clone o projeto dentro do WSL2
(ex.: `\\wsl$\Ubuntu\home\<usuario>`) e use o `make` de lá, com o Docker Desktop integrado ao WSL.

Subdomínios de organizações funcionam em `*.localhost` (ex.: `http://liga.localhost:8080`).

### Comandos do dia a dia
`make up` · `make down` · `make logs s=app` · `make shell` · `make fresh` (recria o banco com dados de demonstração) ·
`make test` · `make lint` / `make fix` (Pint) · `make stan` (Larastan) · `make types` (tsc) · `make help`.

### O que já dá para testar
**Painel do organizador:** entre em http://localhost:8080/login (`admin@plataforma.test` / `password`) e use o menu lateral:
cadastre **equipes** e **estádios**, crie uma **competição**, uma **edição** (com o regulamento: pontuação, ordem dos desempates, cartões, W.O., substituições),
as **divisões**, as **fases** e **inscreva as equipes**. Dentro de cada fase (**Jogos e classificação**): **gere a tabela** (pontos corridos em turno único ou ida e volta, ou fase de grupos com distribuição em serpentina),
**agende** cada jogo (data, hora e estádio), **lance o resultado** (ou W.O.) e acompanhe a **classificação** atualizar na hora; dá para **reabrir** um jogo para corrigir.
Ainda não há atletas/elenco, súmula detalhada (gols e cartões por atleta) nem mata-mata.

**Pelo terminal** (dados de demonstração e testes):
```bash
make fresh      # (Windows: .\dev.ps1 fresh) recria o banco com as migrations atuais
make demo       # (Windows: .\dev.ps1 demo)  cria um campeonato de demonstração e imprime a classificação e os suspensos
make test       # (Windows: .\dev.ps1 test)  roda todos os testes (motor de regras, classificação, disciplina...)
```
O `demo` cria 6 equipes, 15 jogos com gols, cartões e um W.O., e mostra a tabela calculada pelo motor de regras e quem está suspenso por cartões.
Para explorar os dados direto no banco: Postgres em `localhost:5433` (usuário/senha/banco no `.env`).

### Testar uma branch/PR
```bash
git fetch origin && git checkout <branch> && git pull
make up && make fresh      # se houver migrations novas
```
Se o `package.json` ou o `composer.json` mudaram: `docker compose exec app composer install` (o container `vite` roda `npm install` sozinho ao subir).

## Produção
Ver [`docs/03-infra-cicd.md`](docs/03-infra-cicd.md), [`docker-compose.prod.yml`](docker-compose.prod.yml) e [`.env.production.example`](.env.production.example).
O CI (`.github/workflows/ci.yml`) roda Pint, Larastan, Pest, build do front e publica as imagens no GHCR a cada merge na `main`.

## Contribuindo (humanos e IAs)
Leia [`AGENTS.md`](AGENTS.md): convenções, escopo por módulo e checklist antes do PR.
