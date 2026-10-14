# Sugestões de novos overlays

Levantamento de 14/10/2026, depois de concluir placar (4 formatos), lances/lower thirds (6 estilos), escalação (lista, esquema,
cartões e jogador a jogador), tabela, patrocinadores e as 8 telas de campeonato. Cada ideia diz o que já existe para reaproveitar,
o esforço (P/M/G) e o cuidado com a LGPD. Nada aqui está implementado.

## Prioridade alta (muito valor, quase tudo pronto)

| # | Overlay | O que mostra | Reaproveita | Esforço |
|---|---|---|---|---|
| 1 | **Cartela pré-jogo** | Escudos, competição, local, horário e **contagem regressiva** para o início | `PublicGameSnapshot`, cenas (“Começamos em breve”) | P |
| 2 | **Comparativo de equipes** | Posição na tabela, forma (últimos 5), confronto direto e artilheiro de cada lado | `GameInsights` (forma, confronto direto, tabela), `TeamPage` | M |
| 3 | **Resumo do intervalo / fim de jogo** | Placar, gols por autor e minuto, cartões (se liberados) e “craque do jogo” escolhido pelo operador | eventos válidos, `PublicName`, `ChampionshipBundle` | M |
| 4 | **Gols no placar** | Lista compacta dos autores e minutos sob o placar (“Rafa 12’, Pepê 40’”) | `PublicGameSnapshot.goals` | P |
| 5 | **Disputa de pênaltis** | Bolinhas ✓/✗ por cobrança, jogador e placar da série | eventos `penalty_kick` (`RecordShootout`) | M |
| 6 | **Chaveamento (mata-mata)** | Quadro de confrontos com placares, ida e volta e campeão | `BracketView` (já usado no portal) | M |
| 7 | **Faixa de acréscimos** | “+4” no relógio, definido pelo operador | comando no canal (como `lineup_command`) | P |

## Prioridade média

| # | Overlay | O que mostra | Reaproveita | Esforço |
|---|---|---|---|---|
| 8 | **Atalhos de aviso manual** | Botões prontos: “VAR”, “Pênalti”, “Substituição em andamento”, “Cartão em análise” | `lower_third_show` (kind `custom`) | P |
| 9 | **Cartelas de transmissão** | “Voltamos já”, “Intervalo”, “Fim da transmissão”, com logotipo e patrocinador | cenas + patrocinadores | P |
| 10 | **Chamada para redes sociais** | Faixa rotativa com @, site e QR code | patrocinadores (mesma rotação), `qrcode` já no projeto | P |
| 11 | **Artilharia / líderes do jogo** | Top artilheiros da competição entrando em loop entre os lances | `ChampionshipBundle.scorers` | P |
| 12 | **Basquete: faltas e posse** | Faltas por jogador (alerta no 4º), pedidos de tempo e relógio de posse de 24 s | `PublicGameSnapshot.timeouts`, perfil do esporte | M/G |
| 13 | **Vôlei: placar de sets e rodízio** | Sets ganhos, pontos de cada set e quem saca | `sets` do snapshot; rodízio exige ADR | M/G |

## Ideias maiores (pedem decisão de produto)

| # | Overlay | Observação |
|---|---|---|
| 14 | **Votação do público** (“craque do jogo”) | QR code para uma página pública, apuração e resultado no overlay. Pede voto anônimo, proteção contra abuso e termos. |
| 15 | **Desfalques / suspensos** | Útil no pré-jogo, mas é dado disciplinar: só atrás de interruptor da organização (como `game_cards`). |
| 16 | **Replay e melhores momentos** | Depende de integração de vídeo fora do escopo da plataforma. |

## Ordem sugerida

1. Pacote pré-jogo: **1 + 2 + 9** (o operador abre a transmissão com tudo pronto).
2. Pacote durante o jogo: **4 + 7 + 8** (pequenos e muito usados).
3. Pacote pós-jogo e mata-mata: **3 + 5 + 6**.
4. Esportes: **12** e **13** junto com as pendências de basquete e vôlei do `docs/09`.

Cada tela nova segue o padrão do `AGENTS.md`: entrada em `OverlayChannel::OVERLAYS` (ou nas telas de campeonato), página em
`resources/js/Pages/Overlay/*`, dado em `OverlayState`/`ChampionshipBundle` sem dado pessoal, comando ao vivo quando fizer sentido,
artigo de ajuda e testes.
