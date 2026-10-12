# Arquitetura proposta (rascunho v0)

> Status: v1. Baseada nas decisões do projeto (SaaS multi-organizador, futebol primeiro, overlays OBS,
> WebSocket, Docker) e no mapeamento do portal público de referência (`01-mapeamento-plataforma.md`).
> O painel do organizador ainda não foi mapeado.

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

## 7. Decisões derivadas do mapeamento
- Hierarquia: Organização → Competição → Edição → **Especificação** (categoria × sexo × fase) → Grupos → Partidas → Eventos.
- **Pessoa única** (CPF, criptografado/hash para busca) com papéis atleta/dirigente/árbitro e histórico por equipe/edição.
- Regulamento **parametrizável** (pontos, desempates em ordem, cartões/suspensão, substituições, W.O.), não código fixo.
- Menus/áreas do portal por **flags por organização**, aplicadas também no servidor.
- Portal com URLs reais e SSR (SEO/Open Graph), IDs não sequenciais (ULID), LGPD: dados de menores e disciplina não públicos por padrão.
- Tempo real por WebSocket (Reverb) com fallback de polling; o ticker "ao vivo" do portal usa o mesmo canal dos overlays.
- API pública versionada, documentada, com cache (60 s) e rate-limit.
