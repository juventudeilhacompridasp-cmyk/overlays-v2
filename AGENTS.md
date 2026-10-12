# AGENTS.md — guia para quem desenvolve neste repositório (humanos e IAs)

## Contexto
SaaS de gestão de competições (futebol primeiro; depois futsal, vôlei, basquete) com overlays para OBS em tempo real.
Decisões em `docs/adr/`, arquitetura em `docs/02-arquitetura.md`, plano em `docs/04-plano-de-acao.md`, referência funcional em `docs/01-mapeamento-plataforma.md`.
Idioma: código em inglês (nomes de classes, colunas, rotas internas); textos de interface, commits, PRs e docs em português do Brasil.

## Stack
Laravel 13 / PHP 8.4 · Inertia + React + TypeScript (SSR no portal público) · PostgreSQL · Redis · Horizon · Reverb · S3 compatível (S3Mock local, R2 em produção).

## Comandos (sempre via Docker: `make ...`)
`make test` · `make lint` (Pint) · `make stan` (Larastan) · `make types` (tsc) · `make fresh` (banco com seed). Antes de abrir o PR todos devem passar.

## Regras de arquitetura
- Monolito modular: código de domínio em `app/Modules/<Modulo>` (Models, Enums, Actions, Policies, Events, Http). Módulos atuais: `Tenancy`, `Competitions`, `Teams`, `Matches`. Um módulo = um escopo de PR. Não edite outro módulo sem combinar um contrato (ADR).
- **Organização (multi-tenancy simplificada):** toda tabela de domínio tem `organization_id`; os modelos usam o trait `BelongsToOrganization` (escopo global + preenchimento automático via `CurrentOrganization`). Por enquanto existe só a organização padrão (`config('platform.default_organization_slug')`), definida pelo middleware `ResolveDefaultOrganization`; subdomínios, papéis e painel de administração ficam para depois. Em jobs/comandos, defina a organização com `CurrentOrganization::using(...)`.
- **IDs:** ULID em tabelas de domínio (nunca sequenciais expostos publicamente).
- **Eventos de jogo somente de acréscimo** (`game_events`, modelo `GameEvent`, que lança exceção em update/delete); placar e classificação são projeções. Desfazer = evento compensatório (`compensates_event_id`). O modelo de partida se chama `Game` (`Match` é palavra reservada do PHP).
- **Regras de competição são parâmetros** (pontos, desempates, cartões/suspensão), nunca valores fixos no código.
- **LGPD:** documentos pessoais só no disco `s3-private` com URL assinada; dados de menores e disciplina não são públicos por padrão.
- Arquivos públicos no disco `s3` (CDN); variantes WebP geradas por job.
- Tempo real: broadcast via Reverb; overlays carregam snapshot e recebem deltas; cronômetro calculado no cliente.
- Cobrança automática está **fora do MVP** (recursos liberados por organização).

## Qualidade
Testes Pest para toda regra de negócio (as regras de campeonato têm testes baseados em exemplos reais). PHPStan/Larastan (nível sobe gradualmente até 8). PRs pequenos, descrição em português, sem segredos no repositório.
