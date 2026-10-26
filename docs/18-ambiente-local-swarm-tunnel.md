# 18 — Ambiente local em Docker Swarm + Cloudflare Tunnel

> Leia isto antes de mexer em Docker, `.env`, portas ou deploy **nesta máquina**. Descreve como a plataforma está publicada hoje
> (Windows 10 + Docker Desktop, Swarm de nó único, exposta pela internet via Cloudflare Tunnel). Para produção em VPS veja
> `docs/16-deploy-producao.md` (`docker-compose.prod.yml`); este documento NÃO o substitui.

## 1. Visão geral

```
Visitante ──HTTPS──▶ Cloudflare ──túnel──▶ cloudflared (serviço do Windows) ──▶ localhost:8080  → nginx  → php-fpm (app)
                                                                           └──▶ localhost:8081  → reverb (WebSocket)
```

| Item | Valor |
|---|---|
| App público | `https://app.juventude.site` → `http://localhost:8080` |
| WebSocket público | `https://ws.juventude.site` → `http://localhost:8081` (WebSocket passa pelo túnel sem configuração extra) |
| Orquestração | Docker Swarm, nó único (`docker swarm init`), stack chamada `overlays` |
| Pasta do projeto | `C:\overlays-v2` (caminho curto de propósito: em caminhos longos o `git clone` falha com `'$GIT_DIR' too big`) |
| Shell | PowerShell 5.1 (Windows). Não há `make` |
| Banco | PostgreSQL 17 em volume externo `overlays-v2_pgdata` (herdado do ambiente `docker compose` antigo) |
| Arquivos enviados | `STORAGE_DRIVER=local`, volume `overlays_storage` (`storage/app/uploads`), servidos pelo nginx em `/files` |

O túnel (cloudflared) **não faz parte da stack**: roda como serviço no Windows, criado no painel Cloudflare Zero Trust
(*Networks → Tunnels*, túnel `overlays`, instalado com o token do painel). O token é segredo e não está em nenhum arquivo do repositório.
As duas rotas (*Public Hostnames*) são `app.juventude.site → HTTP localhost:8080` e `ws.juventude.site → HTTP localhost:8081`.

## 2. Arquivos desta instalação

| Arquivo | Função |
|---|---|
| `docker-stack.yml` | Definição da stack Swarm: serviços, limites de CPU/RAM, volumes, portas |
| `swarm.ps1` | Único ponto de entrada operacional (`init`, `update`, `status`, `logs`, `artisan`, `composer`, `shell`, `stats`, `rm`) |
| `docker/local/` | `Dockerfile`, `php.ini` e `entrypoint.sh` da imagem de EXECUÇÃO `overlays-app:runtime` (PHP-FPM + extensões + Composer + Node, **sem o código**) |
| `.env` | Configuração (não versionado). `swarm.ps1` o lê e o repassa à stack; o Laravel também o lê pela pasta montada |
| `monitoramento/` | Coleta de logs/acessos (`coletar.ps1`), `BUGS.md` (lista viva de bugs) e `RESUMO-ACESSOS.md` |
| `docker-compose.yml`, `dev.ps1`, `Makefile` | Ambiente de **desenvolvimento** antigo (Compose). **Não use junto com a stack** (ver §7) |

`docker-stack.yml`, `swarm.ps1`, `docker/local/` e `monitoramento/` são específicos desta máquina.

## 3. Como o código roda (sem rebuild de imagem)

O código do projeto é **montado por bind mount** (`C:/overlays-v2` → `/var/www/html`) em todos os serviços PHP e no nginx. Nada é
copiado para a imagem. Por isso, depois de um `git pull` **não se reconstrói imagem**:

- `vendor/` fica no volume `overlays_vendor` (instalado com `composer install --no-dev --optimize-autoloader`).
- `node_modules/` fica no volume `overlays_node_modules` (só usado no build do front, em container descartável `node:22-alpine`).
- `storage/` fica no volume `overlays_storage`, sobreposto à pasta do projeto.
- Front: `npm run build` gera `public/build` e `bootstrap/ssr` **na pasta do projeto** (compilação de ~10 s, não é build de imagem).
- O OPcache revalida arquivos a cada 2 s (`docker/local/php.ini`), então o php-fpm enxerga o código novo sem reiniciar.

A imagem `overlays-app:runtime` só é refeita se `docker/local/*` mudar (`.\swarm.ps1 update -Image` força).

## 4. Rotina de atualização (o que fazer depois de `git pull`)

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass   # só se o PowerShell bloquear scripts
cd C:\overlays-v2
.\swarm.ps1 update
```

`update` compara o commit atual com o último aplicado (`.swarm-last`, ignorado pelo Git) mais as alterações locais e faz só o necessário:

1. `composer install` — apenas se `composer.json`/`composer.lock` mudaram.
2. Build do front (`npm ci` só se `package*.json` mudou; depois `npm run build`) — apenas se mudou `resources/js`, `resources/css`, `package*.json` ou config do Vite/Tailwind/tsconfig.
3. `docker stack deploy` (idempotente: aplica mudanças de `docker-stack.yml`/`.env`).
4. `php artisan migrate --force` e `php artisan optimize` (recria os caches de config/rotas/views/eventos).
5. Reinicia os processos de longa duração: `horizon:terminate`, `reverb:restart`, `scheduler` (e `ssr` se o front mudou).

Opções: `-Full` (refaz tudo), `-Front`, `-Composer`, `-Image`. O código no ar é sempre o da branch/commit que está **checkado em `C:\overlays-v2`**.

Se o `git pull` reclamar do `package-lock.json` modificado: `git checkout package-lock.json`.

## 5. Limites de CPU e RAM (nó único, VM do Docker Desktop ≈ 7,7 GiB e 6 vCPUs)

Definidos em `deploy.resources` no `docker-stack.yml` (limits = teto; reservations = garantido ao agendar). Medidos em repouso:
app 55 MiB, horizon 115 MiB, reverb 40 MiB, ssr 28 MiB, scheduler 37 MiB, postgres 30 MiB, redis 7 MiB, nginx 9 MiB.

| Serviço | CPU máx. | RAM máx. | RAM reservada |
|---|---|---|---|
| app (php-fpm) | 2,0 | 1 GiB | 256 MiB |
| postgres | 2,0 | 1 GiB | 384 MiB |
| horizon | 1,0 | 768 MiB | 192 MiB |
| ssr | 1,0 | 384 MiB | 128 MiB |
| reverb | 0,75 | 256 MiB | 64 MiB |
| redis | 0,5 | 384 MiB | 64 MiB |
| nginx | 0,5 | 128 MiB | 32 MiB |
| scheduler / backup | 0,25 | 192 MiB | 64 MiB |

Soma dos tetos de RAM ≈ 4,2 GiB, deixando folga para o Windows e para o Grafana/Prometheus (projeto Compose separado em
`C:\Users\LIVE\Documents\monitoring`, não faz parte desta stack). O Postgres está ajustado ao teto (`shared_buffers=256MB`,
`max_connections=60`). Acompanhe com `.\swarm.ps1 stats` e ajuste os números no `docker-stack.yml`.

## 6. Operação do dia a dia

```powershell
.\swarm.ps1 status                 # serviços e tarefas
.\swarm.ps1 logs app               # logs de um serviço (nginx, horizon, reverb, ssr, scheduler, postgres, redis, backup)
.\swarm.ps1 artisan tinker         # php artisan dentro do container app
.\swarm.ps1 composer require x/y   # composer no volume vendor
.\swarm.ps1 shell                  # sh no container app
.\swarm.ps1 stats                  # CPU/RAM por container
```

- **Nomes dos containers:** o Swarm os chama `overlays_<serviço>.1.<id-da-tarefa>`; o sufixo não é configurável. O nome estável é o do
  serviço (`overlays_app`). Os containers levam o rótulo `com.docker.compose.project=overlays` só para o Docker Desktop agrupá-los.
- **Primeiro administrador / senha:** `docker-stack.yml` define `ALLOW_FIRST_USER_SETUP=false`; usuários novos nascem por
  `.\swarm.ps1 artisan platform:create-admin voce@exemplo.com --platform`. O seed de demonstração cria `admin@plataforma.test` —
  **troque a senha** (o site é público).
- **Plano da organização:** o painel "Transmissão"/overlays só existe no plano `pro` (recurso `overlays`). A organização `default`
  foi colocada em `pro`. Plano básico = sem overlays, e o menu some (403).
- **Backups:** o serviço `backup` grava dump do banco e cópia de `uploads` no volume `overlays_backups` (retenção 14 dias, às 3 h).
  Está no mesmo disco; para proteger de verdade, copie para fora ou use um remoto do rclone (`BACKUP_REMOTE`).
  Há também um dump manual feito antes da migração para Swarm em `C:\overlays-backups\`.
- **E-mail:** sem SMTP configurado (`MAIL_MAILER=log`), e-mails só vão para o log.

## 7. Armadilhas (já aconteceram — não repita)

1. **Nunca suba o ambiente antigo junto.** `docker compose up`, `.\dev.ps1 up/setup`, `make up` iniciam um SEGUNDO Postgres sobre o
   mesmo volume `overlays-v2_pgdata` (risco de corrupção), disputam portas e rodam `composer`/`artisan` com dependências de dev.
   Isso já derrubou o site. Se subir sem querer: `docker compose down` (**sem `-v`**).
2. **Não rode `composer install` (com dev), `artisan` nem `npm run dev` fora da stack.** O Vite em modo dev cria `public/hot`, e o
   site passa a apontar para `localhost:5173`, inacessível de fora. Se `public/hot` existir, apague-o.
3. **`bootstrap/cache/packages.php` e `services.php` são compartilhados (pasta montada).** Gerados com dependências de dev, apontam para
   classes ausentes (ex.: `Laravel\Pail\PailServiceProvider`) e o app dá erro 500/502. Correção:
   apague os dois arquivos e rode `.\swarm.ps1 artisan package:discover` e `.\swarm.ps1 artisan optimize`.
4. **Migration "pendente" com a coluna já existente** (execução interrompida): confira a coluna e registre a migration em `migrations`
   (batch = máximo + 1) em vez de reaplicá-la. Nunca `migrate:fresh` nem `down -v` (apagam dados).
5. **Dono dos volumes:** `vendor` e `storage` precisam pertencer ao uid 82 (`www-data`); o `swarm.ps1 update` corrige. Sintoma: `mkdir: can't create directory 'storage/framework/'`.
6. **Proxies confiáveis:** `TRUSTED_PROXIES` vazio = só rede privada (nginx/cloudflared). Não use `*` (permite forjar IP). O IP real do visitante vem do `X-Forwarded-For` do Cloudflare.
7. **PowerShell 5.1:** o stderr do `docker` (progresso de build) vira erro se `$ErrorActionPreference='Stop'`; `swarm.ps1` já trata isso (função `Dk`). Não use `&&`.
8. **Não reescreva `.env` à mão sem backup** (`.env.bak-local` guarda o original de desenvolvimento). Mudar variáveis `VITE_*` exige `-Front` (são embutidas no build).

## 8. `.env` desta instalação (chaves relevantes, sem valores secretos)

`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://app.juventude.site`, `SESSION_SECURE_COOKIE=true`,
`REVERB_PUBLIC_HOST=ws.juventude.site` / `REVERB_PUBLIC_PORT=443` / `REVERB_PUBLIC_SCHEME=https` (e os `VITE_REVERB_*` iguais),
`REVERB_APP_KEY/SECRET` e `DOCUMENT_HASH_KEY` aleatórios (**nunca troque a `DOCUMENT_HASH_KEY`**: invalida a busca por CPF),
`STORAGE_DRIVER=local`, `FILESYSTEM_DISK=local`, `OPEN_REGISTRATION=false`, `ALLOW_FIRST_USER_SETUP=false`, `HSTS_SECONDS=15552000`.
A senha do banco ainda é a do ambiente antigo (rede interna, sem porta publicada); vale trocar numa janela de manutenção.

## 9. Monitoramento

Uma tarefa agendada do Claude (`monitorar-plataforma-campeonatos`, a cada hora, só com o app Claude aberto) roda
`monitoramento\coletar.ps1` (somente leitura: estado da stack, acessos do nginx, erros 4xx/5xx, tentativas suspeitas, logs de erro,
jobs com falha) e mantém `monitoramento\BUGS.md` (bugs com ID, severidade e causa provável) e `RESUMO-ACESSOS.md`. Ela **não corrige nada**:
a lista serve para corrigirmos depois. Falhas de tarefas durante deploys/reinícios são ruído esperado.

## 10. Regras para IAs que operam aqui

- Use sempre `.\swarm.ps1`; não inicie o Compose antigo; não apague volumes (`overlays-v2_pgdata` tem o banco).
- Antes de qualquer ação que derrube o site (deploy, restart, mudança de portas ou volumes), avise: o site é público e usado ao vivo.
- Alterações no banco por fora da aplicação (plano, senha, registro de migration) só com autorização explícita do usuário.
- Segredos (token do túnel, `APP_KEY`, senhas) nunca vão para o repositório, para logs de chat ou para este documento.
- Mudanças em `docker-stack.yml`/`swarm.ps1` devem manter `deploy.resources` e a documentação deste arquivo coerentes.
