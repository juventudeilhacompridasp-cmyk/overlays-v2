# ADR 0010 — Aparência e controle externo dos overlays

Status: aceito (13/10/2026)

## Contexto

Os canais do OBS já têm URLs fixas, recebem o estado completo por Reverb e usam polling como contingência. A apresentação,
porém, é fixa no código e a operação só pode ser feita pelo painel. Precisamos permitir identidade visual por canal e
integrações com controladores externos sem dar poder de escrita a quem apenas conhece a URL pública carregada no OBS.

## Decisão

1. `overlay_channels.appearance` guarda somente parâmetros de apresentação, em JSON: preset, cores, posição, escala e nível
   de movimento. `OverlayAppearance` fornece valores padrão e normaliza o documento; novos parâmetros compatíveis podem ser
   acrescentados sem nova coluna.
2. O primeiro preset é `arena`: contraste alto, hierarquia legível em vídeo e animações CSS baseadas em `transform` e
   `opacity`, evitando animação de layout e trabalho contínuo no navegador do OBS. O usuário pode reduzir ou desligar o
   movimento.
3. O estado completo do canal passa a incluir `appearance`. Alterar a aparência dispara o mesmo `OverlayUpdated` usado para
   jogo, placar e camadas, portanto as fontes abertas no OBS mudam sem recarregar.
4. O controle externo usa `PATCH /api/v1/overlay-channels/{channel}/state`. Ele pode mudar jogo no ar, camadas e aparência e
   devolve o novo estado completo.
5. A API exige `Authorization: Bearer {chave}`. A chave de controle é diferente do token público presente nas URLs do OBS,
   tem 64 caracteres, é armazenada somente como SHA-256 e aparece uma única vez ao criar ou renovar. Conhecer uma URL de
   exibição nunca autoriza escrita.
6. A rota mantém o escopo da organização resolvida pelo domínio, exige o recurso `overlays`, aplica limite de requisições e
   nunca devolve a chave ou seu hash.

## Consequências

- Canais existentes continuam com o preset padrão e precisam gerar uma chave antes de usar a API.
- Renovar a chave invalida imediatamente a anterior, sem alterar as URLs configuradas no OBS.
- Novos presets devem preservar o mesmo contrato de estado e oferecer versão sem movimento; imagens e fontes externas não
  entram no preset inicial para evitar downloads e instabilidade durante a transmissão.


## Addendum — novos presets e posições (14/10/2026)

- `OverlayAppearance::PRESETS` ganha `classic` (barra de TV compacta, fundo chapado) e `minimal` (pílula translúcida com o
  relógio na cor de destaque); `arena` continua o padrão. Todos usam o mesmo estado e o mesmo contrato da API; a moldura de
  cada preset fica em `scoreboardFrame` (`resources/js/lib/overlayVisual.ts`), sem CSS novo e sem imagens.
- `POSITIONS` ganha `bottom-left`, `bottom-center` e `bottom-right`; a escala cresce a partir do canto/borda de ancoragem.
- Preset novo = entrada em `PRESETS` + moldura em `scoreboardFrame` + opção na tela de transmissão + teste.
