# Guia: overlays no OBS

Os overlays são páginas web com **fundo transparente** que o OBS carrega como *Fonte de navegador*. Cada **canal de transmissão** tem URLs fixas; durante o jogo você só escolhe o jogo no ar e liga as camadas, sem tocar no OBS.

## 1. Criar o canal (organizador)
Painel → **Transmissão** → *Novo canal*. Aparecem quatro URLs, todas no formato `https://seu-dominio/overlay/{token}/{tela}`:

| Tela | URL termina em | Quando aparece |
|---|---|---|
| Placar e cronômetro | `/placar` | sempre que há jogo no ar |
| Lances (gol, cartão, substituição) | `/eventos` | sozinha, ~7 s, quando a súmula é lançada |
| Escalação | `/escalacao` | quando o operador clica em *Mostrar escalação* |
| Tabela de classificação | `/tabela` | quando o operador clica em *Mostrar tabela* (fases de pontos corridos e grupos) |

O **token é a senha do canal**: quem tem a URL vê o canal. Se vazar, use *Renovar token* (as URLs antigas param de funcionar e é preciso colar as novas no OBS).

### Aparência

Em cada canal, a seção **Visual do overlay** configura o placar:

- estilo: **Arena** (alto contraste), **Clássico** (barra de TV chapada) ou **Minimal** (pílula translúcida);
- formato do placar: **barra horizontal**, **equipes empilhadas** (duas linhas, bom para o canto da tela), **compacto** (pílula pequena com siglas) ou **faixa larga** (de ponta a ponta, para o rodapé);
- cores de destaque, fundo e texto;
- placar no canto esquerdo, centro ou canto direito, no alto ou embaixo da tela;
- escala entre 75% e 150%;
- animações dinâmicas, suaves ou desligadas.

Clique em **Aplicar visual**. As fontes que já estão abertas no OBS recebem a mudança por Reverb, sem recarregar. As animações
usam somente opacidade e transformação para reduzir o trabalho de renderização do Chromium do OBS.

### Telas extras: pré-jogo, comparativo e cartela
Na coluna **Telas extras** estão três telas sob demanda, cada uma com a sua URL (Fonte de navegador): **Cartela pré-jogo** (escudos, competição,
local, horário e contagem regressiva para o início), **Comparativo de equipes** (posição na tabela, forma dos últimos 5 jogos e confronto
direto — os mesmos dados e interruptores do portal) e **Cartela de transmissão** (tela cheia: “Começamos em breve”, “Intervalo”, “Voltamos
já”, “Fim da transmissão”, com contagem regressiva opcional e os patrocinadores). Todas começam ocultas e o clique em *Mostrar* vale na hora.

### Gols sob o placar e acréscimos
Em *Visual do overlay*, **Mostrar autores dos gols sob o placar** lista os goleadores de cada lado (“Rafa 12', 55'”), por nome público. Em
*Acréscimos no relógio* (coluna Ao vivo) escolha de +1 a +8: o número aparece junto ao relógio enquanto o jogo corre e zera ao trocar o jogo.

### Tabela e pedidos de tempo
A tela **Tabela** segue a posição, a escala e o movimento do canal, mostra V/E/D e saldo, destaca as duas equipes do jogo, usa a cor
de cada equipe e reduz o espaçamento quando passa de 10 linhas. No basquete o placar mostra, abaixo, os **pedidos de tempo que restam** a
cada equipe na metade em andamento.

### Lower thirds (faixas de lance)
Na coluna **Lances (lower thirds)** escolha entre seis estilos — Arena, Barra de TV, Cartão do jogador, Minimal, Esportivo e Flash de
gol — e sete animações, o tempo no ar (3 a 15 s) e quais lances aparecem (gol, gol contra, cartões, substituição). A faixa traz o jogador
(nome público, camisa e posição), a equipe e, no gol, o placar, além de avisos como “2º gol do jogador” e “HAT-TRICK”. Use **Testar o
estilo** para ver cada tipo no OBS, **Jogador em destaque** para apresentar um atleta da escalação e **Aviso manual** para qualquer texto
(narrador, patrocinador, “Intervalo”). Ocultar *Lances* tira tudo da tela. Detalhes no ADR 0013.

### Overlays de campeonato
Na coluna **Campeonato** do canal escolha a tela — tabela, próximos jogos, resultados, artilharia, cartões, jogo em destaque,
faixa de placares ou campeão —, a edição (sem escolher, vale a do jogo no ar), um modelo de estilo e os ajustes. A **prévia** usa
a mesma URL do OBS; copie-a para uma Fonte de navegador (1920 × 1080). Todo o estilo mora na URL: para outra tela ou outro estilo,
copie outra URL. A tela de cartões só mostra dados se o administrador ligar *Cartões* em Painel > Portal público. Modelos próprios
podem ser salvos, exportados e importados (JSON). Com **Controle ao vivo** ligado na URL, a tela só aparece quando o operador clica em *Mostrar…* em *Telas de campeonato no ar* (ou pela API, `championship_live`); sem isso ela fica sempre no ar. Detalhes técnicos no ADR 0012.

### Exibir e ocultar em tempo real

Em **No ar agora**, os botões **Placar**, **Lances**, **Escalação** e **Tabela** tiram e colocam cada tela do ar. O placar e os
lances começam no ar; escalação e tabela, ocultos. Cada clique chega ao OBS na hora, sem fila: ocultar o placar o remove
instantaneamente, sem animação, e ocultar os lances impede que as faixas de gol, cartão e substituição apareçam (a súmula
continua registrando tudo normalmente). A rotação de patrocinadores também liga e desliga com um clique. Se a conexão em tempo
real do OBS cair, a tela confirma o comando em até 3 segundos pela consulta periódica.

### Cenas

Em cada canal, a seção **Cenas** guarda o visual atual (estilo, cores, posição, escala, animação, fonte, camadas ligadas,
patrocinadores e apresentação da escalação) com um nome — por exemplo *Pré-jogo*, *Jogo* e *Intervalo*. Durante a transmissão,
um clique no nome da cena troca tudo de uma vez, em qualquer canal da organização; o jogo no ar e as fotos de atletas escolhidas
não mudam. Quem gerencia competições salva e exclui cenas (até 12); o operador só aplica. Pela API de controle, o campo `scene`
aplica a cena e os outros campos enviados junto valem por cima dela.

### Fontes

Cada canal tem a opção **Fonte** no visual: a padrão do sistema, dez fontes embutidas (Inter, Montserrat, Oswald, Bebas Neue,
Anton, Barlow Condensed, Roboto Condensed, Archivo Black, Playfair Display e Permanent Marker) ou uma **fonte própria** enviada
em **Fontes próprias dos overlays** (WOFF2, WOFF, TTF ou OTF, até 2 MB, no máximo 4). Use só fontes que a sua organização tem
licença para usar na transmissão. A fonte vale para todas as telas do canal; as abertas no OBS mudam sem recarregar. Se a
fonte própria for excluída, o canal volta à fonte padrão.

### Patrocinadores

Em **Patrocinadores dos overlays** (abaixo dos canais) cadastre até 12 logotipos — nome e imagem JPG, PNG ou WebP de até 5 MB. Eles
valem para todos os canais. Em cada canal, a seção **Patrocinadores** liga a rotação e define de 3 a 30 segundos por logotipo.
Adicione no OBS uma fonte de navegador com a URL da tela **Patrocinadores**; ela mostra um logotipo por vez, na posição e na
escala do visual do canal, e fica vazia quando a rotação está desligada ou não há patrocinador ativo. Pausar um patrocinador o
tira da rotação sem perder o cadastro.

### Escalação visual

Na seção **Escalação visual**, escolha entre lista, esquema tático ou a sequência automática **lista → esquema** e defina de
3 a 20 segundos para a transição. O desenho usa a formação e as posições que o gestor da equipe salvou; se uma das equipes
ainda não tiver todas as posições, o overlay mantém a lista em vez de inventar um esquema.

#### Apresentação por equipe (cartões)

As apresentações **Cartões**, **Jogador a jogador** e **Show completo** (grade e depois cada jogador) mostram uma equipe por vez
(*Apresentação por equipe*). Escolha o escopo (só titulares ou todos), a faixa de patrocinadores e o modo da passagem:
**automático** troca de jogador a cada *x* segundos (3 a 30) e **manual** avança com **Anterior / Próximo / Reiniciar**, que
valem na hora no OBS (também pela API, com `lineup_command`). Mudar jogo, equipe ou modo reinicia a apresentação.

Fotos são desligadas por padrão e habilitadas atleta por atleta. Apenas quem já possui foto cadastrada pode ser selecionado.
Ao aplicar, uma miniatura privada de curta duração passa a aparecer na lista e no campo para quem tiver a URL do overlay.

## 2. Configurar o OBS (uma vez)
1. Em *Fontes*, adicione uma **Fonte de navegador** para cada tela e cole a URL.
2. **Largura 1920 e altura 1080** (a tela é desenhada para esse quadro). Não precisa de CSS personalizado: o fundo já é transparente.
3. Deixe **todas as fontes sempre visíveis**. As telas só mostram algo quando há o que mostrar.
4. Marque *Desativar fonte quando não estiver visível* **desligado** e, se quiser, *Atualizar o navegador quando a cena ficar ativa* **desligado** (a tela se atualiza sozinha).

## 3. Durante o jogo (operador)
1. Painel → Transmissão → escolha o **Jogo no ar** (as telas passam a acompanhá-lo).
2. Na **súmula**, inicie o 1º tempo e lance os eventos: placar, cronômetro e as faixas de lance atualizam sozinhos.
3. Para a escalação ou a tabela, clique em **Mostrar…** e depois em **Ocultar…**.
4. Ao terminar, escolha outro jogo ou *Nenhum*.

## 4. Se algo falhar
- **Sem WebSocket** (Reverb fora do ar ou bloqueado): as telas consultam `/overlay/{token}/estado` a cada 3 s e continuam atualizando, com um pequeno atraso.
- **Nada aparece**: confirme que há um *Jogo no ar* no canal e abra a URL num navegador comum para ver a tela.
- **Escalação/tabela vazias**: a escalação só existe depois de salva na súmula; mata-mata não tem tabela.
- Em produção, o endereço público do Reverb vem de `REVERB_PUBLIC_HOST/PORT/SCHEME` (ver `docs/03-infra-cicd.md`).

## 5. Privacidade
Os overlays mostram **o que o operador coloca no ar**: placar, relógio, gols, lances e, sob demanda, escalação e tabela.
Documentos, nascimento, disciplina, notas táticas e IDs pessoais nunca são enviados. Fotos só entram quando o operador habilita
o atleta explicitamente; a URL vem assinada do disco privado, vale por cinco minutos e a resposta da imagem usa `no-store`. A decisão
de exibir nomes e fotos de atletas menores de idade na transmissão é da organização.

## 6. Controle por API

O organizador pode integrar Stream Deck, Companion ou outro controlador em **Transmissão → Controle por API**. Ao criar o canal,
uma chave é mostrada uma única vez. **Guarde-a como segredo**. Renovar a chave invalida a anterior sem mudar as URLs do OBS.

```bash
curl -X PATCH 'https://seu-dominio/api/v1/overlay-channels/ID_DO_CANAL/state' \
  -H 'Authorization: Bearer SUA_CHAVE' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  --data '{
    "game_id": "ID_DO_JOGO",
    "layers": {"lineup": true, "standings": false},
    "appearance": {
      "accent_color": "#7c3aed",
      "background_color": "#0f172a",
      "text_color": "#ffffff",
      "position": "top-right",
      "scale": 1.1,
      "motion": "dynamic"
    },
    "lineup_settings": {
      "presentation": "sequence",
      "list_seconds": 6,
      "photo_person_ids": ["ID_DO_ATLETA_NA_ESCALACAO"]
    }
  }'
```

Todos os campos são opcionais; envie apenas o que quer mudar. Valores aceitos:

| Campo | Valores |
|---|---|
| `game_id` | ULID de um jogo da mesma organização ou `null` |
| `layers.lineup`, `layers.standings` | `true` ou `false` |
| cores | hexadecimal com seis dígitos (`#RRGGBB`) |
| `position` | `top-left`, `top-center`, `top-right` |
| `scale` | `0.75` a `1.5` |
| `motion` | `dynamic`, `subtle`, `none` |
| `lineup_settings.presentation` | `sequence`, `list`, `formation` |
| `lineup_settings.list_seconds` | inteiro de `3` a `20` |
| `lineup_settings.photo_person_ids` | até 40 IDs sem repetição; cada pessoa precisa estar entre titulares ou reservas do jogo no ar |

A resposta contém o estado público completo aplicado. A chave, o hash e `photo_person_ids` nunca são devolvidos. Ao trocar o
jogo, a seleção de fotos anterior é limpa; envie IDs do novo jogo no mesmo pedido se quiser habilitá-las imediatamente. O token
que aparece na URL pública do overlay serve somente para visualização e não funciona como chave da API.
