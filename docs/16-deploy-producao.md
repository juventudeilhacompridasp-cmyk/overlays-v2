# 16 — Colocando em produção (1 servidor, banco e arquivos no próprio disco)

> **Site público = leia também `docs/17-seguranca.md`** (túnel Cloudflare, firewall, Cloudflare Access, verificação e resposta a incidente).

Roteiro para subir a stack de `docker-compose.prod.yml` num servidor só (VPS), com **Postgres no próprio servidor** e os
**arquivos enviados no disco do servidor** (`STORAGE_DRIVER=local`). Visão geral da topologia: `docs/03-infra-cicd.md`.

## 0. O que você precisa antes
- Um servidor Linux com Docker + Docker Compose (recomendado: 4 vCPU, 8 GB de RAM, 80 GB de disco; mínimo viável 2 vCPU / 4 GB).
- Um domínio com DNS que você controla (ex.: `exemplo.com.br`). Registros `A` para o IP do servidor: `exemplo.com.br` (ou `app.`) e **`ws.exemplo.com.br`** (WebSocket do tempo real). Se for usar subdomínios por organização, também `*.exemplo.com.br`.
- Um proxy com HTTPS na frente. O roteiro usa o **Nginx Proxy Manager** (NPM) numa stack própria; a rede `proxy` precisa existir: `docker network create proxy`.
- Acesso às imagens: a CI publica `ghcr.io/<dono>/overlays-v2/{app,nginx,backup}:main` (GHCR). Se o repositório for privado, crie um token do GitHub (`read:packages`) e cadastre o registro `ghcr.io` no Portainer (ou `docker login ghcr.io`).
- Um e-mail SMTP (convites, avisos, inscrição). Sem ele o sistema roda, mas nenhum e-mail chega (`MAIL_MAILER=log`).

## 1. Onde ficam os arquivos (STORAGE_DRIVER=local)
Tudo vai para o volume Docker `storage` (montado em `/var/www/html/storage` pelo app, horizon, scheduler, reverb e ssr):

| Pasta (dentro do volume) | O que guarda | Como sai |
|---|---|---|
| `app/uploads/public` | escudos, logotipos da organização, patrocinadores, banners, imagens das artes, fontes próprias | `https://SEU-DOMINIO/files/...` — servido direto pelo nginx, cache de 1 ano (o nome muda a cada envio) |
| `app/uploads/private` | fotos de pessoas, documentos da inscrição, súmulas enviadas | **só** por link assinado e temporário da aplicação (`/private-files/...`, `no-store`); o nginx nunca expõe |
| `logs`, `framework` | logs e caches | interno |

- O volume fica em `/var/lib/docker/volumes/<stack>_storage/_data` no servidor. **Nunca rode `docker compose down -v`** (apaga o volume).
- Os nomes dos discos no código continuam `s3` e `s3-private` (são nomes fixos); o motor é o `STORAGE_DRIVER`.
- Mais tarde, para ir ao R2/S3: preencha `AWS_*`, copie os arquivos (`rclone copy .../uploads/public r2:bucket-publico` e `.../private r2:bucket-privado`) e troque `STORAGE_DRIVER=s3`. A troca **não migra sozinha**.

## 2. Variáveis (Portainer → Stack → Environment variables)
Use `.env.production.example` como lista. Obrigatórias:

| Variável | Valor |
|---|---|
| `IMAGE_APP`, `IMAGE_NGINX`, `IMAGE_BACKUP` | as três imagens do GHCR (`...:main`; para travar uma versão use `:<sha>`) |
| `APP_URL` | `https://exemplo.com.br` (exatamente como o público acessa) |
| `APP_KEY` | `docker run --rm IMAGE_APP php artisan key:generate --show` (guarde; trocar invalida sessões e dados cifrados) |
| `DB_PASSWORD` | senha forte do Postgres |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | valores aleatórios (`openssl rand -hex 16` / `-hex 32`) |
| `REVERB_PUBLIC_HOST` | `ws.exemplo.com.br` (porta 443, https) |
| `DOCUMENT_HASH_KEY` | aleatória (`openssl rand -hex 32`) e **nunca mais trocar** (é a chave do hash do CPF) |
| `MAIL_*` | seu SMTP e `MAIL_FROM_ADDRESS` |

Já vêm com padrão certo para este cenário: `STORAGE_DRIVER=local`, `FILESYSTEM_DISK=local`, backup em `/srv/data/backups`, banco em `/srv/data/postgres`.
Opcionais: `PLATFORM_BASE_DOMAIN` (subdomínios por organização), `VAPID_*` (push, `php artisan webpush:keys`), `ANTHROPIC_API_KEY` (IA sob demanda), `PLATFORM_CONTACT_EMAIL` e `PLATFORM_LANDING_ROOT` (landing na raiz).

Crie as pastas do host antes: `sudo mkdir -p /srv/data/postgres /srv/data/backups`.

## 3. Subir a stack
1. Portainer → *Stacks → Add stack* → cole o conteúdo de `docker-compose.prod.yml` (ou use *Repository* apontando para o repositório) → preencha as variáveis → *Deploy*.
2. A ordem é automática: Postgres e Redis ficam saudáveis → `migrate` roda as migrações → `app` → `nginx`, `horizon`, `scheduler`, `reverb`, `ssr`, `backup`.
3. Confira que todos estão `running` (o `migrate` termina em `exited (0)`, é normal).

## 4. Proxy (Nginx Proxy Manager)
- **Host do site:** `exemplo.com.br` (e `www`, se quiser) → encaminha para `nginx` porta `80`; SSL Let's Encrypt, *Force SSL*, HTTP/2. Aba *Advanced*: `client_max_body_size 25m;` (uploads de imagem/súmula).
- **Host do WebSocket:** `ws.exemplo.com.br` → `reverb` porta `8080` com **Websockets Support** ligado; SSL.
- Subdomínios por organização: um host `*.exemplo.com.br` → `nginx:80`, com certificado curinga (desafio DNS no provedor) e `PLATFORM_BASE_DOMAIN=exemplo.com.br`.
- Atrás da Cloudflare: proxy laranja liga no site; o WebSocket funciona na 443. Domínios de clientes (opcional) são cadastrados à mão em *Plataforma → organização → domínios*.

## 5. Primeiro acesso
1. Crie o primeiro administrador **pelo comando** (a stack de produção fecha o `/register` com `ALLOW_FIRST_USER_SETUP=false`, para ninguém assumir o sistema chegando primeiro; ver `docs/17-seguranca.md`):
   `docker exec -it <container-app> php artisan platform:create-admin voce@exemplo.com.br --platform`
   (a senha é pedida sem eco, mínimo 12 caracteres com letras e números; `--platform` também dá acesso à área `/plataforma`).
2. Entre em `https://exemplo.com.br/login`. Em *Painel → Parâmetros / Portal público / Termos e privacidade* ajuste a organização; crie os demais acessos em *Painel → Usuários*.
3. Validações: `docker exec <app> php artisan realtime:check` (Reverb) e, se usar push, `php artisan push:check`. A página `/up` deve responder 200 e `/status` mostrar os componentes.

## 6. Backup e restauração
- O serviço `backup` roda todo dia (03h UTC): `pg_dump` em `/srv/data/backups/pg` (retém 14 dias) e **cópia aditiva** dos arquivos enviados em `/srv/data/backups/files` (nunca apaga no destino).
- **Isso protege de engano e de falha do software, não da perda do servidor.** Faça também snapshot do VPS e, assim que puder, aponte `BACKUP_REMOTE`/`BACKUP_FILES_REMOTE` para um bucket (ex.: `r2:bucket/pg`, `r2:bucket/files`) preenchendo `BACKUP_*`.
- Restaurar o banco: `docker exec -i <postgres> pg_restore -U overlays -d overlays --clean --if-exists < db-AAAAMMDD-HHMMSS.dump`.
- Restaurar arquivos: copie `/srv/data/backups/files` de volta para `uploads` dentro do volume (`docker run --rm -v <stack>_storage:/s -v /srv/data/backups/files:/b alpine cp -a /b/. /s/app/uploads/`) e ajuste o dono: `chown -R 82:82 /s/app/uploads` (usuário `www-data` da imagem).
- Teste a restauração uma vez por mês.

## 7. Atualizar e voltar atrás
- Atualizar: *Stack → Update the stack* com *Re-pull image* (ou `docker compose pull && docker compose up -d`). O `migrate` roda antes do app; migrações são compatíveis com a versão anterior (expand/contract).
- Voltar: troque as três imagens para o `:<sha>` anterior (a CI publica `:main` e `:<sha>` de cada merge) e atualize a stack. Migrações já aplicadas não voltam sozinhas.

## 8. Operação do dia a dia
- Disco: monitore o uso (`df -h`, `docker system df`); logs dos containers já giram (10 MB × 3). O volume `storage` cresce com as imagens e documentos enviados.
- Firewall: só 22 (por chave), 80 e 443; Postgres e Redis ficam só na rede interna `internal`.
- Fila e tempo real: *Horizon* roda no container `horizon`; se o placar ao vivo parar, veja `reverb` e o host `ws.`. O OBS continua funcionando por polling mesmo sem o WebSocket.
- Segredos: nunca no repositório; guarde `APP_KEY` e `DOCUMENT_HASH_KEY` num cofre.

## 9. Problemas comuns
| Sintoma | Causa provável |
|---|---|
| Imagens/escudos quebrados | `/files` não chega ao nginx do app (host do proxy aponta para outro serviço) ou `APP_URL` diferente do endereço acessado (ajuste `FILES_PUBLIC_URL`) |
| Foto/documento "expirado" ou 404 ao abrir | link assinado venceu (10 min/30 min) ou o relógio do servidor está errado (`timedatectl`) |
| Erro 413 ao enviar arquivo | falta `client_max_body_size 25m;` no host do site no NPM |
| Tempo real não conecta | host `ws.` sem *Websockets Support*, `REVERB_PUBLIC_HOST` errado ou porta/esquema divergentes |
| `migrate` fica reiniciando | `DB_PASSWORD` diferente do que o Postgres já guardou no `/srv/data/postgres` (a senha só vale na criação do banco) |
| 502 do nginx | container `app` reiniciando — veja o log (`APP_KEY` vazia é o campeão) |
