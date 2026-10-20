# 17 — Segurança: o que protege hoje, o que verificar e o que falta

Foco: a plataforma está **pública na internet** (Cloudflare Tunnel a partir de uma máquina própria) e guarda dados de atletas,
inclusive menores (LGPD). Este documento separa (1) o que o código já garante e os testes que o travam, (2) a lista de verificação
da máquina e do túnel, e (3) o que ainda falta.

## 0. Antes de tudo (5 minutos)
1. **Qual stack está exposta?** O túnel deve apontar para a stack de PRODUÇÃO (`docker-compose.prod.yml`), nunca para o `docker-compose.yml` de desenvolvimento. O de desenvolvimento tem `APP_DEBUG=true` (páginas de erro mostram código e variáveis), Postgres publicado na porta 5433, S3Mock sem autenticação na 9000 e Mailpit. Se esse foi o caso, trate como incidente: troque `APP_KEY`, senhas do banco e tokens, e revise o que foi enviado.
2. **Nenhuma porta publicada.** `docker ps --format '{{.Names}} {{.Ports}}'` não deve mostrar `0.0.0.0:...->` em nenhum serviço da stack de produção (o túnel só faz conexão de SAÍDA). Confira também no host: `ss -tlnp` e o firewall.
3. **Primeiro administrador** pelo comando, nunca pela tela `/register` (a stack de produção a fecha com `ALLOW_FIRST_USER_SETUP=false`):
   `docker exec -it <app> php artisan platform:create-admin voce@exemplo.com.br --platform`

## 1. O que o código garante (e o teste que o trava)
| Proteção | Onde | Teste |
|---|---|---|
| IP do visitante não é forjável (`X-Forwarded-For`): só proxies da rede PRIVADA são confiáveis, nunca `*` | `App\Support\TrustedProxies`, `bootstrap/app.php` (`TRUSTED_PROXIES`) | `ForgedIpTest`, `TrustedProxiesTest` |
| Bloqueio de login (5 tentativas por e-mail + IP) e limites por IP valem de verdade (dependem do item acima) | `LoginRequest`, `PlatformLimits` | `ForgedIpTest` |
| Cabeçalhos: `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS (só HTTPS), anti-clickjacking | `SecurityHeaders`, `FrameOptions` | `SecurityHeadersTest` |
| Cookie de sessão `Secure` (HTTPS) e sessão cifrada no Redis | `config/session.php`, `docker-compose.prod.yml` | — (configuração) |
| Ninguém "toma" a instalação chegando primeiro em `/register` | `platform.first_user_setup`, `platform:create-admin` | `FirstAdminSetupTest` |
| Áreas restritas (painel, gestor, atleta, plataforma, perfil) fechadas a anônimos; painel/plataforma/atleta fechados ao gestor e ao atleta; plataforma fechada a quem não é Super Admin — **em todas as rotas, inclusive as futuras** | middlewares `auth`, `panel.member`, `platform.admin`... | `RouteProtectionTest` |
| Uma organização nunca vê dados de outra: todo modelo com `organization_id` usa `BelongsToOrganization` | trait + escopo global | `TenantIsolationTest` |
| CPF só como HMAC; documentos e fotos de pessoas só em disco privado, por link assinado e temporário (`no-store`, CSP `sandbox`) | `Cpf::hash`, `UploadDisks`, `ServeFile` | testes de Registro, Cartões e Imagens |
| SQL: consultas parametrizadas (os `whereRaw` usam binding e curingas escapados); XSS: o React escapa e o único `dangerouslySetInnerHTML` é o JSON-LD com `safeJsonLd` | revisão do código | `JsonLd`/portal |
| Dependências sem falhas conhecidas (`composer audit`, `npm audit`) | `.github/workflows/security.yml` (a cada mudança do lock e toda segunda) | CI |
| Tokens longos e aleatórios (canal do overlay 40, chave de controle 64, link de inscrição 40); a chave de controle só é guardada como SHA-256 | `Str::random` (CSPRNG) | testes de Overlay/Registro |

## 2. Máquina e túnel (Cloudflare Tunnel)
**Túnel**
- Rode o `cloudflared` **dentro da stack** (`docker compose --profile tunnel up -d`, token em `CLOUDFLARE_TUNNEL_TOKEN`): sem porta de entrada aberta no roteador nem no host. Hostnames no painel Zero Trust → Tunnels: site → `http://nginx:80`; `ws.dominio` → `http://reverb:8080`.
- Proteja o token do túnel como senha (quem o tem publica qualquer coisa no seu domínio). Se vazar, apague e recrie o túnel.
- **Cloudflare Access** (Zero Trust, grátis até 50 usuários) na frente das áreas administrativas: use ao menos `/plataforma*` e, se os operadores forem poucos e conhecidos, também `/painel*` e `/login`. É uma segunda porta (e-mail + código/Google) antes mesmo de chegar ao Laravel.
- **WAF/Rate limiting** da Cloudflare: regra de limite em `/login`, `/forgot-password`, `/produto/contato` e `/status/inscrever` (ex.: 10 req/min por IP) e *Bot Fight Mode*. SSL/TLS em *Full (strict)* (com túnel o trecho até o app é interno), *Always Use HTTPS* e TLS mínimo 1.2.
- Não coloque as telas de OBS (`/overlay/...`) atrás do Access: o OBS não faz login. Elas já são protegidas pelo token secreto da URL.

**Máquina (host)**
- Sistema atualizado e com atualizações automáticas de segurança; disco **criptografado** (os documentos dos atletas estão nele); usuário comum para o dia a dia, nunca `root` remoto; SSH desligado ou só por chave e só na rede local.
- Firewall do host negando toda entrada (o túnel não precisa de nenhuma); `ufw default deny incoming`. Cuidado: o Docker publica portas passando por cima do `ufw` — por isso a regra "nenhuma porta publicada".
- Docker: não exponha o socket (`/var/run/docker.sock`) a nenhum container; **Portainer** só na rede local/VPN, nunca no túnel público; imagens fixadas por versão quando possível; `docker system prune` com cuidado (nunca `-v`).
- Máquina doméstica = disponibilidade doméstica: queda de energia/internet derruba o site. No-break, "não suspender", e backup **fora** da máquina (a pasta `/srv/data/backups` no mesmo disco não protege de roubo/falha). Cifre o backup antes de enviar (`rclone crypt`).
- Segredos (`APP_KEY`, `DOCUMENT_HASH_KEY`, senhas, token do túnel) num cofre (ex.: gerenciador de senhas) e fora do repositório. `DOCUMENT_HASH_KEY` nunca muda.

## 3. Como verificar de fora (faça depois de qualquer mudança)
```bash
curl -sI https://SEU-DOMINIO/ | grep -iE 'strict-transport|x-content-type|referrer-policy|permissions-policy|x-frame|content-security'
curl -s  https://SEU-DOMINIO/up -o /dev/null -w '%{http_code}\n'          # 200
curl -s  https://SEU-DOMINIO/register -o /dev/null -w '%{http_code}\n'     # 403 (cadastro fechado)
curl -s  https://SEU-DOMINIO/horizon -o /dev/null -w '%{http_code}\n'      # 403/404, nunca 200
curl -s  https://SEU-DOMINIO/.env -o /dev/null -w '%{http_code}\n'         # 404
curl -s  https://SEU-DOMINIO/plataforma -o /dev/null -w '%{http_code}\n'   # 302 (login) ou 404, nunca 200
```
E de dentro da máquina: `docker ps --format '{{.Names}}\t{{.Ports}}'` (sem `0.0.0.0`) e `ss -tlnp` (só o que você reconhece).
Teste de força bruta: 6 logins errados seguidos com o mesmo e-mail devem bloquear (mensagem de "muitas tentativas").

## 4. Resposta a incidente (resumo)
1. Derrube o acesso: `docker compose --profile tunnel stop cloudflared` (o site sai do ar; o resto fica de pé).
2. Preserve logs (`docker compose logs --no-color > incidente.log`) antes de reiniciar qualquer coisa.
3. Troque `APP_KEY` (invalida sessões), senhas, `CLOUDFLARE_TUNNEL_TOKEN` e as chaves de controle dos overlays (Painel → Transmissão → renovar).
4. Se dados pessoais foram expostos: a LGPD exige comunicar a ANPD e os titulares em prazo razoável; registre o que, quando e quantos. Menores pedem atenção redobrada.
5. Restaure de backup conhecido se houve alteração de dados (`docs/16`, seção 6).

## 5. O que ainda NÃO existe (próximos passos, por prioridade)
1. **Autenticação em dois fatores** para administradores (hoje só senha; o Cloudflare Access cobre parte disso).
2. **CSP completo** (`Content-Security-Policy` com nonce): hoje só `frame-ancestors`. Exige mapear scripts inline do Inertia/SSR, Reverb (`wss:`), players de vídeo (YouTube/Vimeo/Instagram) e fontes; começar em `Report-Only`.
3. **Antivírus/análise** dos arquivos enviados (hoje: tipo e tamanho validados; imagens são reencodadas, o que já descarta a maioria dos ataques; PDFs/planilhas de súmula são lidos, não executados).
4. **Registro de auditoria de leitura** de documentos pessoais (quem abriu o quê). Hoje só há auditoria de mudança de acesso.
5. **Teste de intrusão** por terceiro antes de abrir cadastros em massa, e *threat model* por módulo.
6. Cabeçalho `Cross-Origin-Opener-Policy`, `Report-To`/NEL e monitoramento de erros (Sentry) com remoção de dados pessoais.
