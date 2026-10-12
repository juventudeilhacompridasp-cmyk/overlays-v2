# Arquitetura proposta (rascunho v0)

> Status: baseada nas decisões do projeto (SaaS multi-organizador, foco inicial em futebol,
> overlays para OBS, WebSocket, Docker). O mapeamento do site de referência
> (gerenciadordecompeticoes.com.br) ainda está pendente e vai refinar o domínio.

## 1. Stack

| Camada | Escolha | Motivo |
|---|---|---|
| Back-end | Laravel 12 (PHP 8.4) | Domínio do time; ecossistema completo (filas, policies, Cashier, Horizon) |
| Tempo real | Laravel Reverb (protocolo Pusher) + Laravel Echo | WebSocket nativo, escala horizontal via Redis, sem serviço externo |
| Front-end painel | React + TypeScript + Inertia.js + Vite | Um só deploy, sem API duplicada, SSR opcional |
| Overlays OBS | App React separado e leve (Vite) em rota `/overlay/{token}/{tipo}` | Fundo transparente, sem login, assina canal WebSocket público por token |
| Banco | PostgreSQL 17 | Integridade, JSONB (configs de regras por esporte), índices parciais, RLS opcional |
| Cache/filas/pub-sub | Redis 7 | Cache, Horizon, broadcast do Reverb |
| Arquivos | S3 compatível (MinIO local, Cloudflare R2/Backblaze em prod) | Escudos, fotos, súmulas PDF |
| Proxy | Traefik (ou Caddy) | TLS automático, roteamento por subdomínio (multi-tenant) |

Alternativa descartada: Node/NestJS. Não compensa trocar a linguagem do time; Reverb resolve o WebSocket.

## 2. Multi-tenancy (SaaS)

- Tenant = **Organização** (federação/liga/produtor). Banco único, coluna `organization_id` em todas as tabelas de domínio, com global scope + policies.
- Subdomínio por organização (`liga.plataforma.com.br`) e domínio próprio opcional.
- Planos/assinatura: Laravel Cashier (Stripe) e/ou Asaas/Pagar.me (PIX/boleto no Brasil).

## 3. Domínio inicial (futebol) — a validar com o mapeamento

Organização → Competição → Edição/Temporada → Fases (pontos corridos, grupos, mata-mata, misto)
→ Rodadas → Partidas. Clubes, Atletas, Comissão, Árbitros, Estádios, Súmula
(gols, cartões, substituições, escalação), Classificação, Artilharia, Disciplina/suspensões,
Regulamento (critérios de desempate configuráveis), Portal público, Inscrição online.

**Preparado para outros esportes:** interface `SportRuleset` (futebol, futsal, vôlei, basquete)
define eventos de partida, pontuação, desempates e formato do placar. Eventos de partida
armazenados como `match_events` (tipo + payload JSONB) para servir todos os esportes.

## 4. Overlays para OBS

- Cada overlay é uma URL com token (Browser Source no OBS, 1920x1080, fundo transparente).
- Tipos iniciais: placar, escalação, lower-third de gol/cartão/substituição, tabela, artilharia, próximo jogo, cronômetro.
- Operação: **painel de controle da transmissão** (operador dispara/oculta cada elemento) → evento no Reverb → overlay atualiza em tempo real.
- Cronômetro calculado no cliente a partir de timestamp do servidor (evita enviar ticks).
- Temas por organização (cores, logos, fontes) em JSON.

## 5. Módulos do monolito (Laravel modular)

`Identity` · `Tenancy/Billing` · `Competitions` · `Teams/Athletes` · `Matches/Scoresheet` ·
`Standings` · `Registration` · `Public Portal` · `Broadcast/Overlays` · `Media` · `Reports`.
Monolito modular primeiro; extrair serviços só se houver necessidade comprovada.

## 6. Qualidade e trabalho com múltiplas IAs

- Contratos primeiro: OpenAPI/ADRs em `docs/adr/`, migrations e policies revisadas por humano.
- Um módulo = uma branch/PR; cada IA recebe um escopo de pasta (sem editar fora dele).
- Pest + PHPStan(level 8) + Pint + ESLint/tsc + Vitest + Playwright (e2e/overlays) obrigatórios no CI.
- `CLAUDE.md`/`AGENTS.md` na raiz com convenções, comandos e regras de domínio.
