# Plataforma de Competições + Overlays para OBS

SaaS multi-organizador para gestão de campeonatos e transmissão ao vivo: do cadastro das equipes à súmula em tempo real, do portal público aos overlays para o OBS.
Esportes: **futebol, futsal, vôlei e basquete**. Cobrança automática ainda está fora do MVP (recursos liberados por organização).

**Stack:** Laravel 13 (PHP 8.4) · Inertia + React + TypeScript (SSR no portal) · PostgreSQL 17 · Redis · Reverb (WebSocket) · Horizon · S3 (S3Mock local, R2 em produção) · Docker.

## O que a plataforma faz

| Área | O que tem |
|---|---|
| **Competições** | Competições, edições com regulamento parametrizável (pontos, desempates, cartões/suspensão, W.O., substituições), divisões e fases; tabela de pontos corridos, grupos e mata-mata (ida e volta, byes, pênaltis); sorteio por arrasto; agenda com avisos de conflito; inscrição online com link pessoal e aprovação no painel; carteirinha com QR; relatórios (CSV). |
| **Jogo e súmula** | Escalação (inclusive pelo gestor, com esquema tático), eventos de súmula somente de acréscimo, resultado rápido, pênaltis, relógio, faltas, pedidos de tempo, rodízio/líbero do vôlei, relógio de posse do basquete; **fila offline** no app do mesário (PWA); súmulas enviadas depois do jogo (leitura, revisão e conferência); PDFs oficiais; **assinatura digital** por link único; **fechamento da súmula** com assinaturas mínimas. |
| **Transmissão (OBS)** | Canais com URL fixa por tela: placar completo, lower thirds, escalação (vários layouts e animações), tabela, patrocinadores, telas extras (pré-jogo, comparativo, resumo, chaveamento, estatísticas), overlays de campeonato, overlays livres (Builder), cenas, fontes próprias; **Modo operador**, **controle remoto** para celular, monitores PGM/PVW e OBS; controle externo por API. |
| **Portal público** | Home, competição, edição, equipe, jogo ao vivo (SSR + SEO + JSON-LD), estatísticas, busca, feed, locais, calendário `.ics`, widgets embutíveis, avisos push, galeria de mídia, **perfil público do atleta** (opt-in, só maiores de 18 anos). |
| **Pessoas e acesso** | Papéis e **perfis de acesso** com exceções e auditoria; painel do **gestor de equipe**; **mercado de jogadores livres**; perfil só de **mesário** com painel do operador; **2FA (TOTP)**; termos e privacidade versionados. |
| **Plataforma** | Pacotes e recursos por organização, domínios próprios e marca própria, status page, landing page com medição anônima, documentação interna das APIs (OpenAPI/Postman), central de ajuda com busca, **registro de atividades** (auditoria no banco) e de acesso a documentos pessoais. |
| **Segurança e LGPD** | CPF só como hash; documentos pessoais em disco privado com URL assinada; CSP com nonce (relatório por padrão), verificação dos arquivos enviados (PDF ativo, código em imagem, ClamAV opcional), isolamento entre organizações testado, dados de menores nunca públicos. Detalhes em [`docs/17-seguranca.md`](docs/17-seguranca.md). |

Quase tudo é **parametrizável** (por regulamento, por organização ou por variável de ambiente): [`docs/10-parametrizacao.md`](docs/10-parametrizacao.md).

## Documentação
- **Estado atual e pendências (leia primeiro):** [`docs/09-status-e-pendencias.md`](docs/09-status-e-pendencias.md)
- Plano de ação: [`docs/04-plano-de-acao.md`](docs/04-plano-de-acao.md) · arquitetura: [`docs/02-arquitetura.md`](docs/02-arquitetura.md) · decisões (ADRs): [`docs/adr/`](docs/adr/)
- OBS e overlays: [`docs/06-guia-obs.md`](docs/06-guia-obs.md) · API pública: [`docs/07-api-publica.md`](docs/07-api-publica.md) · tempo real e push: [`docs/08-validacao-tempo-real.md`](docs/08-validacao-tempo-real.md)
- Landing e status page: [`docs/14-landing-page-e-status-page.md`](docs/14-landing-page-e-status-page.md) · performance: [`docs/15-otimizacoes-e-performance.md`](docs/15-otimizacoes-e-performance.md)
- Produção: [`docs/16-deploy-producao.md`](docs/16-deploy-producao.md) · segurança: [`docs/17-seguranca.md`](docs/17-seguranca.md)
- Ambiente publicado nesta máquina (Docker Swarm local + Cloudflare Tunnel, `.\swarm.ps1 update`): [`docs/18-ambiente-local-swarm-tunnel.md`](docs/18-ambiente-local-swarm-tunnel.md)

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

### O que dá para testar
Entre em http://localhost:8080/login (`admin@plataforma.test` / `password`). Depois de `make fresh` há dados de demonstração.

- **Competição de ponta a ponta:** cadastre equipes e estádios, crie competição, edição (regulamento), divisões e fases, inscreva as equipes (ou abra a **inscrição online**), gere a tabela, sorteie, agende, escale e lance a **súmula** (Painel > Mesário abre os jogos do dia). Ao encerrar, **feche a súmula** no Painel do operador.
- **Transmissão:** em Painel > Transmissão crie um canal, ponha um jogo no ar e copie as URLs para o OBS (veja o [guia](docs/06-guia-obs.md)); experimente o **Modo operador** e o **Controle remoto** (`/painel/broadcast/controle`) no celular.
- **Portal público:** http://localhost:8080 mostra as edições publicadas; o jogo ao vivo atualiza por WebSocket com polling de contingência.
- **Gestor, mercado e atleta:** convide um gestor em Equipes > editar; o mercado de jogadores livres e a leitura de súmulas são recursos **sob demanda** (libere em `/plataforma`).
- **Plataforma (Super Admin):** `php artisan platform:make-admin <e-mail>` e abra `/plataforma` (organizações, pacotes, status page, contatos da landing, documentação das APIs).
- **Parâmetros:** Painel > Parâmetros, Comunicações e Portal público ajustam o comportamento sem mexer em código.

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
Guia completo em [`docs/16-deploy-producao.md`](docs/16-deploy-producao.md); infraestrutura em [`docs/03-infra-cicd.md`](docs/03-infra-cicd.md), [`docker-compose.prod.yml`](docker-compose.prod.yml) e [`.env.production.example`](.env.production.example). O primeiro administrador nasce por `php artisan platform:create-admin <e-mail> --platform` (o cadastro público fica fechado).
O CI (`.github/workflows/ci.yml`) roda Pint, Larastan, Pest, build do front, a fila offline no Chromium, tempo real/push de verdade e publica as imagens no GHCR a cada merge na `main`; `.github/workflows/security.yml` audita as dependências.

## Contribuindo (humanos e IAs)
Leia [`AGENTS.md`](AGENTS.md): convenções, escopo por módulo e checklist antes do PR.
