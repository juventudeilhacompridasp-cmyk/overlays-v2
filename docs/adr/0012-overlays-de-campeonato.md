# ADR 0012 — Overlays de campeonato para o OBS

Status: aceito (14/10/2026)

## Contexto

Além do placar, lances, escalação, tabela e patrocinadores de UM jogo, a transmissão precisa de telas do campeonato inteiro:
tabela, próximos jogos, últimos resultados, artilharia, cartões, jogo em destaque, faixa de placares e campeão. A plataforma
original da organização (`juventude.tech/manage/broadcast`) já tinha esse conjunto — 8 visões, 7 estilos, 9 animações,
parâmetros por URL e modelos —, e o v2 deve oferecê-lo com as mesmas opções, respeitando multi-tenancy, edição publicada e LGPD.

## Decisão

1. **Rotas** (sem login; o token do canal é o acesso e exige o recurso `overlays`): `/overlay/{token}/campeonato/{visão}`
   (visões: `standings`, `fixtures`, `results`, `scorers`, `discipline`, `matchcard`, `ticker`, `champion`) e
   `/overlay/{token}/campeonato/dados` (JSON, `no-store`). A página (`Overlay/Championship`) lê toda a aparência da própria URL
   (`style`, `anim`, `pos`, `accent`, `bg`, `text`, `font`, `rows`, `rotate`, `hold`, `gap`, `speed`, `cols`, `scale`, `width`,
   `dx`, `dy`, `surface`, `radius`, `pad`, `bw`, `bc`, `sh`, `blur`, `head`, `dots`, `logos`, `title`, `subtitle`, `group`,
   `round`, `match`, `refresh`) — nada disso fica no banco, então cada fonte do OBS tem sua própria URL. O parser é puro
   (`resources/js/lib/obsChampionship.ts`, testado em Node) e qualquer valor fora da lista/faixa volta ao padrão.
2. **Qual campeonato:** `?edicao=` (ID de edição publicada), `?c=` (slug da competição → edição publicada mais recente),
   `?div=` (divisão; padrão = primeira) ou, na falta, a edição do **jogo no ar** do canal. Edição em rascunho nunca existe
   (resposta `data: null`).
3. **Dados** (`ChampionshipBundle`): partem do `SeasonPresenter` do portal (mesmos dados públicos) e viram um pacote plano:
   equipes (escudo, cor), tabela (ou grupos), jogos (`scheduled|live|finished|cancelled`), artilharia (nomes por `PublicName`)
   e campeão (`CompetitionPage::podium`). **Cartões/disciplina só saem se a organização ligou `game_cards`** no portal
   (padrão desligado; `cards: null` caso contrário) e trazem apenas nome público, equipe e contagem — nunca IDs, escalação,
   documentos, fotos ou nascimento. Este é um acoplamento declarado de `Broadcast` com `Portal/Support`.
4. **Modelos:** 8 modelos prontos no código (`OBS_TEMPLATES`) e "Meus modelos" por organização (`overlay_style_models`, até 30,
   nome único, atualizar pelo mesmo nome). Só guardam chaves de estilo; `ChampionshipStyle::sanitize` descarta o resto, inclusive
   no arquivo importado (`juventude-obs-models`, JSON). Salvar, importar e excluir = `manage-competitions`; aplicar é do navegador.
5. **Painel:** coluna "Campeonato" no quadro de configurações do canal (`ChampionshipStudio`): tela, edição/divisão, modelos,
   aparência, conteúdo e tempo, prévia com a URL real e URL pronta para copiar. As escolhas ficam no `localStorage` do operador.

## Consequências

- Sem estado novo por canal além de "Meus modelos": mudar o estilo é mudar a URL (recolar no OBS), ao contrário do placar, que
  reage ao canal em tempo real. Os dados atualizam por consulta (`refresh`, 15 s por padrão), sem Reverb.
- Tela nova de campeonato = chave em `ChampionshipOverlayController::VIEWS` + `OBS_VIEWS` + desenho em `Championship.tsx` + testes.
- A ajuda e o guia do OBS explicam que o estilo vive na URL.

## Adendo — controle ao vivo das telas de campeonato

URLs com `live=1` só aparecem quando o operador as coloca no ar: `overlay_channels.layers.championship` guarda, por tela
(`OverlayChannel::CHAMPIONSHIP_VIEWS`), se está no ar (padrão: oculta). O estado do canal traz `championship` (mapa tela → bool);
a página se inscreve no mesmo canal Reverb `overlay.{token}` (com a contingência de 3 s) e anima a entrada/saída. O comando é
`championship_live` (painel e API de controle, `operate-games`), e dispara `OverlayUpdated` como as demais camadas. Sem `live=1` a
tela continua sempre no ar (e não abre conexão Reverb). Cenas não guardam este estado.
