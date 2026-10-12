# 03 — Infra local, CI/CD e produção

## 1. Ambiente local (Docker Compose)
Serviços: `app` (PHP-FPM+Nginx, imagem serversideup/php 8.4), `queue` (Horizon), `scheduler`, `reverb` (WebSocket :8080),
`web` (Vite dev/HMR), `postgres:17`, `redis:7`, `minio` (S3), `mailpit` (e-mail), `traefik` (opcional: `*.localhost`).
- Um comando: `make up` (alias de `docker compose up -d`), `make test`, `make fresh` (migrate:fresh --seed com competição de demonstração).
- Seeders realistas: 1 organização, 1 competição de 12 equipes, jogos, súmulas e eventos — serve para overlays e testes e2e.
- Subdomínios locais: `liga.localhost`, `overlay.localhost`; `.env.example` completo; nenhum segredo no repositório.
- Dev Container (`.devcontainer`) para as IAs e pessoas terem o mesmo ambiente.

## 2. Pipeline CI (GitHub Actions)
1. **PR:** Pint, PHPStan nível 8, Pest (com Postgres/Redis em services), ESLint, `tsc`, Vitest, build do front, `composer audit`/`npm audit`, Trivy na imagem.
2. **E2E:** Playwright contra `docker compose` (inclui teste visual dos overlays com fundo transparente).
3. **Merge em `main`:** build multi-stage, push para **GHCR** com tags `sha` e `main`; deploy automático em **staging**.
4. **Tag `v*`:** promove a mesma imagem para **produção** (aprovação manual no environment do GitHub).
Regras: branch protegida, PR obrigatório, status checks obrigatórios, Dependabot/Renovate, CODEOWNERS por módulo.

## 3. Produção
- **Orquestração recomendada para começar:** Docker Compose/Swarm gerenciado pelo **Portainer** (você já conhece) — stacks versionadas no Git, webhook de redeploy chamado pelo CI.
- **Proxy/TLS:** Traefik com Let's Encrypt (wildcard via DNS-01 para subdomínios dos tenants) atrás do Cloudflare.
- **Topologia mínima (1–2 VPS):** Traefik · app (2 réplicas) · horizon · scheduler · reverb · postgres · redis. Evoluir: Postgres gerenciado, Redis separado, app em N nós.
- **Migrations:** job one-shot antes de subir novas réplicas (`php artisan migrate --force`), com estratégia expand/contract para zero downtime.
- **Backups:** `pg_dump`/WAL-G diário + contínuo para R2/B2, teste de restauração mensal; versionamento no bucket de mídia.
- **Observabilidade:** Sentry (erros), Uptime Kuma (disponibilidade), Prometheus+Grafana+Loki ou Better Stack; métricas do Reverb (conexões, mensagens).
- **Segurança:** segredos no Portainer/GitHub Environments (ou SOPS), usuário não-root nos containers, rate-limit na API pública, Cloudflare WAF.
- **Ambientes:** local → staging → produção, bancos separados; staging com dados sintéticos.

## 4. Alternativas ao Portainer (para decidir depois do MVP)
| Opção | Quando escolher |
|---|---|
| **Portainer + Compose** | Menor atrito agora; você já domina |
| **Dokploy / Coolify** | PaaS self-hosted: deploy por Git, previews por PR, SSL e bancos prontos; ótimo para equipe pequena |
| **Kubernetes (k3s)** | Só quando precisar de muitos nós/autoscale; custo operacional alto |
| **Laravel Cloud / Forge** | Menos DevOps, mais custo; bom se não quiser operar servidores |
Recomendação: começar com Portainer+Traefik e, se o deploy por PR/preview fizer falta, migrar para Dokploy sem mudar as imagens.

## 5. Escala do tempo real (overlays)
Reverb com `REVERB_SCALING_ENABLED=true` (Redis pub/sub) permite várias instâncias; canais públicos por token de overlay.
Meta inicial: 5 mil conexões simultâneas por nó; teste de carga com k6 antes do lançamento.
