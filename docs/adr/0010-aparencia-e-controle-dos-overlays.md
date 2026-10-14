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
## Addendum — patrocinadores (14/10/2026)

- `overlay_sponsors` guarda os logotipos da organização (até 12; nome, WebP 512/128 px no disco público, ordem e ativo),
  reaproveitados por todos os canais. Cadastrar, pausar e excluir é `manage-competitions`.
- `overlay_channels.sponsor_settings` (`OverlaySponsorSettings`) liga a rotação por canal (padrão desligada) e define de 3 a 30
  segundos por logotipo; é operação ao vivo (`operate-games`) e também aceita pela API de controle.
- Tela nova `patrocinadores` (`/overlay/{token}/patrocinadores`): um logotipo por vez, troca por opacidade, posição, escala e
  preset do canal. O estado traz `sponsors {enabled, seconds, items[{name, logo_url}]}`; **com a rotação desligada `items` vai
  vazio** e nunca vão caminhos nem IDs. Toda mudança nos patrocinadores dispara `OverlayUpdated` para os canais abertos.

## Addendum — fontes (14/10/2026)

- `appearance.font` escolhe a fonte do canal: `system` (padrão), uma das 10 fontes embutidas no build (`@fontsource`, mesmas das
  artes; nenhum CDN externo na transmissão) ou o ID (ULID) de uma **fonte própria** da organização.
- `overlay_fonts` (`OverlayFont`, até 4 por organização, 2 MB): WOFF2, WOFF, TTF ou OTF. O formato vem dos **primeiros bytes**
  (`OverlayFontFile::detect`), nunca da extensão; coleções `.ttc` e qualquer outro arquivo são recusados. Enviar e excluir é
  `manage-competitions`; escolher a fonte é operação ao vivo (`operate-games`) e também aceito pela API de controle, que só
  aceita fonte do catálogo ou da própria organização.
- O arquivo é servido pela **mesma origem do overlay** (`/overlay/{token}/fonte/{fonte}`, `nosniff`, cache de 1 dia), sem
  depender de CORS no CDN. O estado traz `font_face {family, url relativa, format}` só quando o canal usa fonte própria; o
  front registra o arquivo com `FontFace` e, até carregar, vale a pilha de reserva. Excluir uma fonte devolve à padrão os canais
  que a usavam e atualiza os abertos no OBS.
- A licença da fonte é responsabilidade da organização (aviso na tela de envio).

## Addendum — cenas (14/10/2026)

- `overlay_scenes` (`OverlayScene`, até 12 por organização, nome único) guarda uma **foto do visual de um canal**: aparência
  (preset, cores, posição, escala, movimento, fonte), camadas ligadas, rotação de patrocinadores e apresentação da escalação
  (modo e duração). **Nunca** guarda o jogo no ar nem as fotos de atletas habilitadas: são decisões de cada transmissão.
- Salvar a cena a partir de um canal e excluí-la é `manage-competitions`; aplicar é operação ao vivo (`operate-games`).
  Aplicar é a MESMA via de mudança do canal (`UpdateOverlayChannel`), então valem as mesmas normalizações e o `OverlayUpdated`
  chega às fontes abertas no OBS; uma cena vale para qualquer canal da organização.
- API de controle: `scene` (ID) aplica a cena e os demais campos enviados junto valem POR CIMA dela
  (`array_replace_recursive`); ID de outra organização ou inexistente é 422.
- Fonte própria excluída depois de salva na cena não quebra: o estado simplesmente não traz `font_face` e vale a fonte padrão.

## Addendum — comandos de exibir/ocultar em tempo real (14/10/2026)

- As camadas ganham `scoreboard` e `lower_third` (placar e faixas de lance), além de `lineup` e `standings`. **Placar e lances
  começam no ar** (`OverlayChannel::DEFAULT_ON`); escalação e tabela começam ocultas. O operador tira qualquer uma da tela com
  um clique, mesmo sem jogo no ar (só escalação e tabela exigem jogo). `OverlayChannel::layerStates()` é a leitura única.
- **Sem fila:** `OverlayUpdated` e `OverlayLowerThird` passam a `ShouldBroadcastNow` — o comando sai do clique direto para o
  Reverb, sem esperar um worker. `OverlayUpdated::dispatchFor` captura falha do broadcaster (a mudança já está gravada e a
  consulta periódica de 3 s cobre o OBS), e a faixa de lance idem: **nenhuma falha de transmissão desfaz um lance da súmula**.
- **Lances** só são enviados aos canais com a camada ligada; ocultar tira da tela na hora, inclusive a faixa que estava saindo.
- **Cliente:** ocultar o placar o remove na hora, sem animação. No painel o botão muda no clique (otimista) e volta ao estado
  real se o servidor recusar; ligar/desligar a rotação de patrocinadores também vale na hora.
- A API de controle (`layers.scoreboard`, `layers.lower_third`) e as cenas já aceitam as novas camadas.

- Preset novo = entrada em `PRESETS` + moldura em `scoreboardFrame` + opção na tela de transmissão + teste.

## Adendo — formato do placar (`appearance.layout`)

`appearance.layout` (`bar`, `stacked`, `compact`, `wide`; padrão `bar`, valor desconhecido volta a `bar`) escolhe a disposição do placar
independentemente do preset (`arena`/`classic`/`minimal`): barra horizontal, equipes empilhadas em duas linhas, pílula compacta com
siglas ou faixa de ponta a ponta. Vale no painel, na API de controle e nas cenas; o contrato de dados do estado não muda. Formato novo =
entrada em `OverlayAppearance::LAYOUTS` + desenho em `Overlay/Scoreboard.tsx` + opção em `Panel/Broadcast/Index.tsx` + teste.
