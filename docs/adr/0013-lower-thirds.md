# ADR 0013 — Lower thirds (faixas de lance) com estilos e animações

Status: aceito (14/10/2026)

## Contexto

A tela de lances mostrava um único cartão (ícone, título, nome) por 7 s. A transmissão precisa de faixas com identidade visual
própria, que apresentem o jogador (camisa, posição, equipe, placar), tratem gols e cartões como momentos especiais e permitam
avisos manuais (narrador, patrocinador, jogador em destaque).

## Decisão

1. **Configuração por canal** (`overlay_channels.lower_third_settings`, `OverlayLowerThirdSettings`): `style` (`arena`, `broadcast`,
   `player`, `minimal`, `slash`, `flash`), `animation` (`slide`, `rise`, `zoom`, `wipe`, `flip`, `bounce`, `fade`), `seconds` (3–15),
   `kinds` (quais lances viram faixa) e `score` (placar no gol). Vai no estado do canal (`lower_third_settings`), muda ao vivo
   (`OverlayUpdated`), entra nas cenas e na API de controle.
2. **Dados da faixa** (`LowerThirdBanner`): lance da súmula, aviso manual ou jogador em destaque, sempre com o mesmo formato:
   `kind`, `title`, `subtitle`, `minute`, equipe (nome, sigla, escudo, cor), `player` (nome público, camisa, posição), `score` (só
   gols) e `detail` ("2º gol do jogador na partida", "HAT-TRICK", "2º cartão amarelo"). **Nomes por `PublicName`** (menor
   abreviado), nunca IDs, documentos, nascimento ou foto.
3. **Comando `lower_third_show`** (painel e API, `operate-games`): `custom` (título/subtítulo livres), `player` (atleta da escalação do
   jogo no ar) ou um tipo de lance (amostra para testar o estilo). Não é guardado e só sai com a tela de lances no ar.
4. **Desenho** em `Components/Overlay/LowerThirdCard.tsx` (um componente por estilo) e animação por Web Animations
   (`lib/lowerThird.ts`, puro e testado), entrada e saída pelos mesmos quadros; `appearance.motion = none` corta a animação.
5. **Painel:** coluna "Lances (lower thirds)" no quadro do canal: estilos, animação, tempo, lances, teste, jogador em destaque e aviso manual.

## Consequências

- Estilo novo = chave em `OverlayLowerThirdSettings::STYLES` + componente em `LowerThirdCard` + entrada em `LOWER_THIRD_STYLES` + teste.
- A faixa continua sob o comando "Lances" (ocultar tira tudo da tela) e uma falha do Reverb nunca desfaz o lance da súmula.
