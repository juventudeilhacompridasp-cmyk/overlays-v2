# 02 — Arquitetura (v2, decisões em `adr/0001-decisoes-iniciais.md`)

Contexto: SaaS multi-organizador, futebol primeiro, overlays para OBS como diferencial, custo de infra mínimo, desenvolvimento com várias IAs.
Referência funcional: `01-mapeamento-plataforma.md` (painel do organizador ainda não mapeado).

## 1. Stack
| Camada | Escolha | Motivo |
|---|---|---|
| Back-end | Laravel 12, PHP 8.4, monolito modular | Domínio do time; filas, policies, scheduler, testes |
| Front (painel + portal) | React + TypeScript + Inertia.js (Vite), **SSR no portal público** | Um deploy, sem API duplicada, SEO no portal |
| Overlays | Bundle React separado (`resources/js/overlays`), estático, sem login | Leve, fundo transparente, URL com token |
| Tempo real | Laravel Reverb + Echo | WebSocket nativo; escala via Redis |
| Banco | PostgreSQL 17 | JSONB para regras/eventos; integridade |
| Cache/filas | Redis 7 + Horizon | |
| Arquivos | S3-compatível (R2 em prod, MinIO local) | Ver §5 |
| UI | Tailwind + shadcn/ui, TanStack Query onde houver SPA | |

API: rotas `/api/v1` (Sanctum, OpenAPI via Scramble) ficam no mesmo projeto; só são publicadas quando houver app móvel ou integração.
Se a API virar produto próprio, extrai-se depois (monolito modular facilita).

## 2. Multi-tenancy
Tenant = Organização. Banco único, `organization_id` em todas as tabelas de domínio, global scope + policies. Subdomínio por organização
(`liga.dominio.com.br`) e domínio próprio opcional. **Recursos liberados por organização** (feature flags/pacote) em vez de cobrança no MVP.

## 3. Domínio (futebol)
Organização → Competição → Edição → **Especificação** (categoria × sexo × fase) → Grupos → Partidas → Eventos.
Pessoa única (papéis atleta/dirigente/árbitro, histórico por equipe/edição). Regulamento parametrizável (pontos, desempates em ordem, cartões/suspensão,
substituições, W.O.). `SportRuleset` por esporte. IDs ULID. Documentos e dados de menores não públicos por padrão (LGPD).

## 4. Partidas e tempo real (núcleo do diferencial)
- `match_events` **somente de acréscimo** (gol, cartão, substituição, início/fim de período); placar e classificação são projeções. Desfazer = evento compensatório (auditável).
- Estado ao vivo da partida em Redis; overlay carrega o snapshot ao conectar e recebe deltas; reconecta e pede snapshot de novo.
- Cronômetro calculado no cliente a partir de timestamp do servidor (sem tick por segundo).
- Canais públicos por token de overlay (`overlay.{token}`), canal privado para o painel do operador.
- Overlays: placar, cronômetro, escalação, lower-thirds (gol/cartão/substituição), tabela, artilharia, próximo jogo; temas por organização (JSON).
- Futuro: controle de cenas via OBS WebSocket / Bitfocus Companion / Stream Deck.

## 5. Arquivos
- **Dois buckets:** público (imagens, via CDN `cdn.dominio.com.br`) e privado (RG, CPF, atestados, ficha de saúde — somente URLs assinadas temporárias).
- Laravel Flysystem (driver `s3`). **R2 é compatível com S3**: o código é idêntico; muda `AWS_ENDPOINT`, chaves e `AWS_USE_PATH_STYLE_ENDPOINT`.
  Escolha do R2 vs AWS S3: R2 não cobra saída de dados (egress); o S3 cobra. Se o time preferir S3, funciona sem alterar código (custo maior).
- Variantes WebP (64/256/1024 px) geradas por job no upload; caminho `org/{id}/{tipo}/{ulid}/{variante}.webp`; cache imutável.
- Retenção/LGPD: apagar documentos privados após o prazo definido; exclusão de conta remove arquivos.

## 6. Módulos
`Identity` · `Tenancy` (+ pacotes/flags) · `Competitions` · `Teams/People` · `Matches` (eventos, súmula, disciplina) · `Rules` ·
`Standings` · `Registration` · `Portal` (público) · `Broadcast/Overlays` · `Media` · `Reports`. Cobrança (`Billing`) entra depois.

## 7. Qualidade e trabalho com várias IAs
Contratos antes do código (ADRs, migrations, OpenAPI); um módulo = uma pasta = uma branch/PR; Pest, PHPStan 8, Pint, ESLint/tsc, Vitest, Playwright no CI;
`AGENTS.md` na raiz com convenções, comandos e regras de domínio; humano revisa migrations, policies e LGPD.
