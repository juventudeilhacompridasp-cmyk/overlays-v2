# 03 — Infra local, CI/CD e produção

## 1. Topologia de produção (1 VPS, tudo em Docker Compose)
```
Internet → Cloudflare (DNS, CDN, WAF) → Nginx Proxy Manager (80/443, TLS)
   ├─ app.dominio / *.dominio ─► nginx (estáticos + fastcgi) ─► php-fpm (app)
   ├─ ws.dominio              ─► reverb :8080  (WebSocket habilitado no host do NPM)
   └─ cdn.dominio             ─► Cloudflare R2 (bucket público)
Serviços internos: horizon (worker), scheduler, ssr (Node, Inertia SSR), postgres, redis, backup
```
| Serviço | Imagem/Função | Obs |
|---|---|---|
| `nginx` | nginx:alpine | serve `public/` e repassa `.php` ao fpm; recebe do NPM |
| `app` | php-fpm 8.4 (imagem do projeto) | |
| `horizon` | mesma imagem, `php artisan horizon` | fila de jobs (imagens, e-mails, projeções) |
| `scheduler` | mesma imagem, `schedule:work` | |
| `reverb` | mesma imagem, `reverb:start` | WebSocket |
| `ssr` | mesma imagem + Node, `inertia:start-ssr` | só o portal público; ~100–200 MB RAM |
| `postgres` | postgres:17 | volume bind em `/srv/data/postgres` |
| `redis` | redis:7-alpine | AOF opcional |
| `backup` | pg_dump + rclone | envia diário ao R2/B2 |
| `npm` | Nginx Proxy Manager (stack própria) | camada externa; já conhecida |
Extras opcionais: Portainer, Uptime Kuma.

**Dimensionamento inicial:** 4 vCPU / 8 GB / 80 GB NVMe (≈ €8–15/mês). Cloudflare em frente com cache de HTML público curto (30–60 s) reduz a carga.
ARM (Hetzner CAX) é mais barato, mas exige build multi-arch no CI; x86 evita atrito.

## 2. Banco no mesmo servidor — o que torna isso seguro
- Bind mount em disco dedicado/`/srv/data`; **nunca** `docker compose down -v`.
- Backup diário `pg_dump` **fora do servidor** (R2/B2) com retenção 14/30 dias; teste de restauração mensal; snapshot semanal do VPS.
- Parâmetros ajustados (`shared_buffers` ≈ 25% da RAM, `max_connections` baixo + pooling por Octane/fpm limitado).
- Limites de memória por container para o banco não ser morto pelo OOM; swap pequeno.
- Atualização major do Postgres é manual (dump/restore). Quando crescer: mover o banco para outro servidor/gerenciado sem mudar a aplicação.

## 3. Nginx Proxy Manager — pontos de atenção
- **Wildcard** `*.dominio.com.br` para os subdomínios dos tenants: certificado via DNS challenge (Cloudflare) no NPM; um Proxy Host com domínio `*.dominio`.
- Ativar **Websockets Support** no host do Reverb. Header `X-Forwarded-*` e `TRUSTED_PROXIES` no Laravel.
- Limitação: a configuração do NPM fica no banco/GUI (não versionada). Exportar backup do volume `npm`. Alternativa futura: Traefik (labels versionadas).
- Domínio próprio de cliente: cadastrar manualmente no NPM no início (automatizar depois, via API do NPM ou Caddy on-demand TLS).

## 4. Ambiente local
Compose com os mesmos serviços + `vite` (HMR), `s3`, `mailpit`. Comandos: `make up`, `make test`, `make fresh` (seed de competição demonstrativa
com 12 equipes, jogos e eventos para testar overlays). `.env.example` completo, Dev Container para pessoas e IAs.

## 5. CI/CD (GitHub Actions)
1. **PR:** Pint, PHPStan 8, Pest (Postgres/Redis em services), ESLint, tsc, Vitest, build Vite (+SSR), audits, Trivy.
2. **E2E:** Playwright (inclui overlays com fundo transparente).
3. **Merge em `main`:** build da imagem → GHCR (`sha`, `main`) → deploy em staging.
4. **Tag `v*`:** promove a mesma imagem para produção (aprovação manual).
5. **Deploy no VPS:** webhook do Portainer (stack com imagem nova) **ou** SSH `docker compose pull && up -d` + `migrate --force`; health-check e rollback para a tag anterior.
Branch protegida, Dependabot, CODEOWNERS por módulo. Migrations em expand/contract.

## 6. Observabilidade e segurança
Sentry (grátis), Uptime Kuma, logs com rotação (`json-file` max-size), métricas do Reverb. Segredos em variáveis do ambiente do Portainer / GitHub Environments;
containers sem root; firewall (apenas 22/80/443), SSH por chave, fail2ban, rate-limit nas rotas públicas, Cloudflare WAF.

## 7. Evolução
Mais carga → separar banco em outro VPS; depois 2 réplicas de app/reverb atrás do NPM/Traefik. Ferramentas alternativas de deploy (Dokploy/Coolify) só se faltar preview por PR.
