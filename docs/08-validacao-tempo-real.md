# Validação do tempo real (Reverb) e dos avisos push

Este guia separa **o que a CI já prova sozinha** do que **só dá para confirmar num ambiente real** (HTTPS, proxy, navegadores).

## 1. O que a CI prova a cada PR

O job **"Tempo real e push"** (`.github/workflows/ci.yml`) roda `php artisan test --filter=EndToEnd` com `RUN_E2E=1`:

| Teste | O que acontece de verdade | Prova |
|---|---|---|
| `ReverbEndToEndTest` (placar) | sobe um `reverb:start`, liga um cliente WebSocket em Node (o mesmo `pusher-js` do front) ao canal público `games.{id}`, muda o placar do jogo no Laravel | `Game::updated` → `GameUpdated` → API do Reverb → WebSocket → o cliente recebe o retrato completo com o placar novo |
| `ReverbEndToEndTest` (diagnóstico) | roda `php artisan realtime:check` contra o servidor vivo e escuta `realtime.check` | o comando de diagnóstico passa e o evento de teste chega ao cliente |
| `WebPushEndToEndTest` | o `WebPushSender` real manda um push para um "serviço de push" local em Node | o cabeçalho VAPID tem assinatura ES256 válida para a chave pública configurada, `TTL` presente, `Content-Encoding: aes128gcm` e o corpo decifra (RFC 8291) para o JSON enviado |

Esses testes já acharam um defeito real: a biblioteca usava o formato antigo `aesgcm` por padrão, que o Safari/iOS não aceita; o envio agora força `aes128gcm`.

**Não coberto pela CI** (precisa de ambiente real): o proxy/Cloudflare com WebSocket, certificado HTTPS, os serviços de push de verdade (FCM da Google, Mozilla, Apple), a permissão e o service worker em cada navegador e o OBS.

## 2. Comandos de diagnóstico (rode no servidor)

```bash
docker compose exec app php artisan realtime:check   # Reverb: config, porta, evento de teste, endereço público
docker compose exec app php artisan push:check       # VAPID: chaves, assunto, HTTPS, recurso e pedidos guardados
```

Ambos terminam com código ≠ 0 quando algo está errado (servem em checagem de deploy).

## 3. Reverb em produção — passo a passo

1. **Variáveis** (`.env.production.example`): `REVERB_APP_ID/KEY/SECRET` e o endereço **público** `REVERB_PUBLIC_HOST` (ex.: `ws.exemplo.com.br`), `REVERB_PUBLIC_PORT=443`, `REVERB_PUBLIC_SCHEME=https`. Internamente o app fala com o container (`REVERB_HOST=reverb`, porta 8080, http).
2. **Proxy**: no Nginx Proxy Manager, `ws.exemplo.com.br → reverb:8080` com **Websockets Support** ligado e certificado. Na Cloudflare, o WebSocket precisa estar habilitado (padrão) e o registro com proxy (nuvem laranja) ou direto.
3. **Servidor**: `docker compose exec app php artisan realtime:check` deve terminar sem ✗. Se falhar em "não consegui conectar", o container `reverb` não está de pé ou fora da rede `internal`.
4. **Navegador**: abra `https://exemplo.com.br/jogos/{id}` com um jogo ao vivo, F12 → Rede → **WS**: deve existir uma conexão `101 Switching Protocols` para `wss://ws.exemplo.com.br/app/...`. Mude o placar na súmula e o número muda na página sem recarregar.
5. **OBS**: cole a URL do overlay (`/overlay/{token}/placar`) numa Fonte de Navegador; o placar e o relógio devem atualizar com o jogo. A tela tem contingência por polling: se o WebSocket cair, ela continua atualizando a cada poucos segundos.

| Sintoma | Causa provável |
|---|---|
| a página só atualiza ao recarregar / indicador "sem conexão" | WebSocket bloqueado: confira o passo 2 e o `REVERB_PUBLIC_*` |
| `realtime:check`: "conteúdo misto" | site em HTTPS com `REVERB_PUBLIC_SCHEME=http` |
| WS abre e fecha em loop | proxy sem "Websockets Support" ou timeout curto no proxy |
| o evento de teste é aceito mas nada chega ao navegador | `REVERB_APP_KEY` do navegador diferente do do servidor |
| eventos atrasados | fila parada: o Horizon precisa estar rodando |

## 4. Avisos push em produção — passo a passo

1. **HTTPS é obrigatório** (service worker e push só existem em contexto seguro; `localhost` serve para desenvolver).
2. **Chaves**: `docker compose exec app php artisan webpush:keys`, copie para `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` (a privada é segredo) e defina `VAPID_SUBJECT=mailto:contato@seudominio`. Trocar as chaves depois invalida os pedidos já feitos.
3. **Recurso**: o pacote **Completo** (ou liberação manual do recurso `push`) na organização, em `/plataforma`.
4. `docker compose exec app php artisan push:check` deve terminar sem ✗.
5. **Teste manual**, em cada navegador que importa:
   - **Chrome/Edge (desktop e Android)**: abra um jogo → "Avisar este jogo" → aceite a permissão → feche a aba → mude o placar na súmula → chega a notificação do sistema.
   - **Firefox**: igual; em janela privada o push não funciona.
   - **Safari/iOS (16.4+)**: o site precisa estar **instalado na tela inicial** (Compartilhar → Adicionar à Tela de Início); só então o botão pede a permissão. Exige `aes128gcm` (já é o padrão do envio).
   - **Brave**: ative "Usar serviços do Google para mensagens push" nas configurações.
6. **Limpeza**: pedidos recusados pelo serviço (410/404) são apagados sozinhos; `push:prune` (diário) apaga os de jogos encerrados.

| Sintoma | Causa provável |
|---|---|
| o botão "Avisar" não aparece | recurso `push` desligado na organização, ou chaves VAPID ausentes (`push:check`) |
| permissão concedida mas nada chega | `queue` parada (Horizon) — o envio roda em `SendGamePush`; conferir `failed_jobs` |
| funciona no Chrome e não no iOS | site não instalado como app, ou iOS < 16.4 |
| `push:check`: "só funcionam em HTTPS" | `APP_URL` sem https |
| 401/403 do serviço de push nos logs | `VAPID_SUBJECT` inválido ou chaves trocadas depois das inscrições |

## 5. Checklist de aceite

- [ ] `realtime:check` e `push:check` sem ✗ em produção.
- [ ] Placar de um jogo de teste muda ao vivo na página pública e no overlay do OBS.
- [ ] Push chegou em: Chrome Android, Chrome desktop e iPhone (app instalado).
- [ ] Derrubar o container `reverb` por 1 minuto: páginas e overlays continuam atualizando por polling e reconectam sozinhas.
- [ ] Horizon e `scheduler` em execução (lembretes aos gestores, `push:prune` e filas dependem deles).
