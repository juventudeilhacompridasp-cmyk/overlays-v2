# Mapeamento dos overlays do projeto anterior (`overlays`) e o que falta aqui

Fonte: repositório `juventudeilhacompridasp-cmyk/overlays` (Overlay Studio: `public/app.js`, `TECHNICAL_SPEC.md`, `CHANGELOG.md`, `docs/MAPEAMENTO_FUNCIONALIDADES.md`). Este documento lista **cada overlay**, **cada parâmetro** que ele tinha e o estado aqui (✅ feito · 🟡 parcial · ⬜ falta). Atualize a cada PR da série.

## 1. Overlays de transmissão (um por tela do OBS)

| Overlay antigo (`layer=`) | Equivalente aqui | Estado |
|---|---|---|
| `scoreboard` (placar) | `/overlay/{token}/placar` | ✅ (2.1) |
| `event` (gols, cartões, substituições, gol contra) | `/overlay/{token}/eventos` (lower thirds, ADR 0013) | ✅ (2.2) |
| `lineup` (lista de escalação) | `/overlay/{token}/escalacao` | ✅ |
| `photo-lineup` (apresentação com fotos) | `/overlay/{token}/escalacao` (cartões, individual, show) | ✅ (2.3) |
| `sponsor` (patrocinador rotativo) | `/overlay/{token}/patrocinadores` | 🟡 faltam formatos, estilos e animações (2.4) |
| `sponsor-bar` (barra 1500 x 200) | — | ⬜ (2.5) |
| `stats` (estatísticas: comparativo, gols/cartões, destaque do atleta) | `/comparativo` só com dados da súmula | ⬜ estatísticas manuais e 3 visões (2.6) |
| `custom` (Builder: overlays por elementos) | — | ⬜ (2.7) |
| `all` (todas as camadas numa fonte) | — | ⬜ (2.8) |
| `/obs/<visão>` (campeonato: tabela, jogos, resultados, artilharia, cartões, jogo em destaque, faixa, campeão) | `/overlay/{token}/campeonato/*` (ADR 0012) | ✅ |
| Pré-jogo / resumo para narradores | `/prejogo`, `/resumo` | ✅ |

## 2. Parâmetros e configurações por overlay (antigo → aqui)

### 2.1 Placar
Antigo: `scoreboardScale`, `scoreboardFont` (tamanho do texto), `scoreboardTypeface`, `scoreboardX/Y` (posição livre em %), `scoreboardLayout`, `scoreboardShowBadge` (escudo), `scoreboardStyle` (clássico, minimalista, vidro, alto impacto, neon, faixa dinâmica, gradiente), `scoreboardRadius`, `scoreboardSurface` (opacidade), `scoreboardAccent` (espessura do destaque), `scoreboardShadow` (nenhuma/suave/forte), `scoreboardAnimation` (assemble, slide, zoom…), `scoreboardAnimationSpeed`; período: `periodScale`, `periodFont`, `periodSurface`, `extraTimeScale`; gol: `goalText` ("GOOOL"), `goalWordDuration`, `goalTeamDuration`, `goalAnimation` (typewriter, bounce, sweep); `cardDisplayMode`.
Aqui já existe: preset (arena/classic/minimal), `layout` (bar/stacked/compact/wide), cores, 6 posições fixas, escala, movimento, fonte (catálogo + própria), gols sob o placar, acréscimos.
**Entregue (PR do placar):** posição livre X/Y, tamanho do texto separado da escala, escudos ligados/desligados, 8 estilos (arena, clássico, minimal, vidro, alto impacto, neon, faixa dinâmica, gradiente), raio, opacidade, espessura da faixa da equipe, sombra, entrada (aparecer, deslizar, zoom, montar) e velocidade, escala do bloco de período e dos acréscimos, comemoração de gol (texto, durações, digitada/quicando/varredura). Tipografia por overlay segue sendo a fonte do canal.

### 2.2 Eventos
Antigo: `eventScale/Font/Typeface/X/Y`, `eventPosition` (esquerda/centro/direita), `eventStyle` (faixa TV, linha limpa, bloco esportivo), `cardDisplayMode` (faixa ou tela cheia).
Aqui: 6 estilos e 7 animações de lower third (ADR 0013), tempo, tipos de lance e placar. **Entregue:** posição (esquerda, centro, direita ou livre em X/Y), tamanho do texto e modo do cartão (faixa ou cartão grande no centro). A tipografia segue sendo a fonte do canal.

### 2.3 Escalação com fotos
Antigo: `photoLineupLayout` (classic, tunnel, poster, sidebar, stadium, matchday), `photoLineupStyle` (editorial, cards, glass, premier, champions, diagonal), `photoLineupAnimation` (slide, wipe, rise, zoom, split, cascade), `photoLineupFormationMark` (número/foto) e `photoLineupFormationPhotoSize` (60–200%), `photoLineupIndividualDuration`, `photoLineupPanelDuration`, `photoLineupSponsorCount`, `photoLineupSponsorBarSize`, `photoLineupShowSponsors`, escala/fonte/tipografia/X/Y/opacidade/raio.
Aqui: cartões, individual (auto ou manual) e show, esquema tático por `Formations`, fotos só se habilitadas, tempos. **Entregue:** os 5 conceitos novos (túnel, carta de coleção, coluna lateral, telão de estádio, programa do jogo) além do clássico, os 6 estilos, as 6 animações, foto/número no esquema com tamanho, tamanho da faixa de patrocinadores, posição livre, opacidade, cantos e texto. Diferença consciente: o rodapé mostra um patrocinador por vez, em rotação (não N logos lado a lado).

### 2.4 Patrocinador rotativo
Antigo: `sponsorFormat` (texto, logo + nome, banner, banner + nome), `sponsorAnimation`, `sponsorAnimationSpeed`, `sponsorDuration`, `sponsorStyle` (box, limpo, faixa), escala/fonte/tipografia/X/Y.
Aqui: biblioteca, rotação 3–30 s, logotipo WebP. **Falta:** formatos, estilos, animações/velocidade e posição livre.

### 2.5 Barra de patrocinadores (1500 x 200)
Antigo: saída própria; `sponsorBarDuration`, `sponsorBarTransition` (fade, slide, zoom, flip, elastic), `sponsorBarFit` (preencher/conter), `sponsorBarBorder`, `sponsorBarShadow`, `sponsorBarScale`, `sponsorBarX/Y`, `sponsorBarOpacity`, `sponsorBarRadius`, `sponsorBarBackground`, modo imagem/vídeo.
Aqui: nada equivalente. **Falta tudo** (tela `barra-patrocinio`, banners largos na biblioteca, parâmetros acima).

### 2.6 Estatísticas
Antigo: estatísticas lançadas à mão (finalizações, no gol, escanteios, faltas, impedimentos, defesas, posse de bola), três visões (comparativo com até 6 indicadores, gols e cartões por equipe, destaque do atleta com foto/camisa/gols/cartões/observação), `statsStyle` (painel TV, vidro, minimal), `statsAnimation`, `statsDuration` (0 = fica no ar), escala/fonte/tipografia/X/Y.
Aqui: `/comparativo` (campanha, forma, confronto direto) — outro conteúdo. **Falta tudo** do estúdio de estatísticas da partida.

### 2.7 Builder (overlays livres)
Antigo: canvas com até 60 elementos (texto, imagem, vídeo, forma), posição/tamanho/camadas, animações de entrada/saída (10), atraso/duração, 7 modelos prontos (lower third, mini-placar, aviso central, intervalo, selo, faixa rolante…), duplicar, exportar/importar JSON, dados ao vivo (`{home.name}`, `{home.score}`, `{clock}`, `{period}`, `{competition}`, `{sponsor}`, `{time}`, `{table.N.*}`, `{champ.name}`), URL por overlay (`layer=custom&id=`).
Aqui: **falta tudo**. (Os avisos rápidos e a chamada social cobrem só casos fixos.)

### 2.8 Camada `all`, aparência global, esportes
Antigo: `layer=all`; aparência global (fonte global, 9 tipografias, predefinições compacto/impacto), "Restaurar padrões do módulo", seções recolhíveis, controle por esporte (relógio de posse regressivo de 24 s no basquete, saque e sets no vôlei, faltas no futsal).
Aqui: aparência por canal, cenas (predefinições), fontes. **Falta:** `all`, restaurar padrões por módulo, relógio de posse (24 s), saque no vôlei.

## 3. Ordem de entrega (um PR por linha; atualize a coluna Estado acima)

1. Placar: parâmetros restantes (2.1) + comemoração de gol.
2. Eventos: posição livre e modo do cartão (2.2).
3. Escalação: conceitos, estilos, animações e foto no esquema (2.3).
4. Patrocinador rotativo + barra de patrocinadores (2.4, 2.5).
5. Estúdio de estatísticas da partida (2.6).
6. Builder de overlays por elementos (2.7), o maior item.
7. `all`, restaurar padrões e relógio de posse/saque (2.8).

Regra para todos: dados públicos apenas (LGPD, `PublicName`), nada de ID/documento no estado do overlay, parâmetro novo passa por `UpdateOverlayChannel`, API de controle, cenas e testes.
