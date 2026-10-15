---
title: Transmissão com overlays no OBS
category: Transmissão
tags: obs, overlay, placar, transmissão, escalação, tabela, canal
order: 40
feature: overlays
route: panel.broadcast.index
---
Os overlays são telas de vídeo que o OBS carrega como **fonte de navegador**, com atualização em tempo real.

## Escalação jogador a jogador
Em **Escalação visual** escolha *Cartões*, *Jogador a jogador*, *Show completo* ou **Completa (cartões, esquema tático e jogador por jogador)** e a equipe. Na **Completa** defina o tempo do esquema tático e se os jogadores passam depois dele; escolha a animação entre as etapas em *Visual da apresentação*. A barra de patrocinadores 1500 x 200 fica fixa por baixo durante toda a sequência (e também na sequência *Lista → esquema*): um banner por vez, todos em loop (envie os banners em *Patrocinadores*; tempo e transição em *Visual da rotação e da barra*). Em *Visual da apresentação* escolha um **pacote de animação** (Clássico de TV, Suave, Impacto, Estúdio, Dinâmico ou Cinema) ou ajuste a entrada e saída do pacote inteiro, a animação entre as etapas e a velocidade. No modo **automático** cada jogador fica o tempo que você definir (3 a 30 s); no **manual** use **Anterior**, **Próximo** e **Reiniciar** para passar na hora. A foto grande só aparece para quem você habilitou, e a faixa de patrocinadores é opcional.

## Gols sob o placar, acréscimos e avisos rápidos
Marque **Mostrar autores dos gols sob o placar** em *Visual do overlay* para listar os goleadores de cada equipe. Em *Acréscimos no relógio* escolha +1 a +8 e o número aparece ao lado do relógio. Na coluna **Escalação**, abra **Visual da apresentação** para escolher o conceito (clássico, túnel, carta de coleção, coluna lateral, telão de estádio ou programa do jogo), o estilo do clássico, a animação de entrada, o tamanho do texto, a opacidade, os cantos, a posição livre, o tamanho da faixa de patrocinadores e se o esquema tático mostra a foto ou o número. Em **Overlays livres (Builder)** (embaixo na página) monte telas próprias: escolha um modelo, adicione textos, imagens e formas, arraste na prévia, ajuste cores, fonte e animações de entrada e saída. Nos textos, `{home.score}`, `{clock}`, `{competition}` e outros dados trazem o jogo no ar. Salve e, na coluna **Overlays livres** do canal, use **Mostrar** e copie a URL para uma fonte de navegador do tamanho do overlay no OBS. Dá para exportar e importar o desenho em JSON. Na coluna **Estatísticas da partida** lance finalizações, escanteios, faltas e a posse de bola, escolha a visão (comparativo, gols e cartões ou destaque de um atleta) e use **Mostrar**: a tela sai sozinha depois do tempo escolhido (0 deixa no ar até ocultar). Trocar de jogo zera os números. Em **Patrocinadores** escolha o formato (logotipo, logotipo e nome, só o nome, banner largo), o estilo, a animação e a posição da rotação. Para a **barra de patrocinadores 1500 x 200**, envie o banner de cada patrocinador (ideal 1500 x 200; *Enviar banner* na lista), ajuste tempo, transição, moldura e posição em *Visual da rotação e da barra* e mostre a tela **Barra de patrocinadores** em *Telas extras*. Em **Placar avançado** (dentro de *Visual do overlay*) ajuste a posição livre em X e Y, o tamanho do texto, o bloco do período, os escudos, a opacidade, os cantos, a sombra, a animação de entrada e a **comemoração de gol** (texto, animação e duração da palavra e do nome da equipe). Os estilos *Vidro*, *Alto impacto*, *Neon*, *Faixa dinâmica* e *Gradiente* se somam aos três primeiros. Na coluna **Lances** há avisos de um clique: VAR, Pênalti, Cartão em análise, Jogo paralisado, Intervalo e Fim de jogo.

## Telas extras (pré-jogo, comparativo e cartela)
Na coluna **Telas extras** use *Mostrar* e *Ocultar* para a cartela pré-jogo (com contagem regressiva), o comparativo de equipes e a cartela de transmissão em tela cheia (“Começamos em breve”, “Intervalo”…). Na cartela escolha um modelo, ajuste título e subtítulo e, se quiser, uma contagem regressiva em minutos. Há também a **Chamada para redes sociais** (até 4 linhas e um QR code), o **Resumo do jogo** (intervalo ou fim, com o craque do jogo que você escolher) e o **Chaveamento do mata-mata**. Cada tela tem a sua URL para uma Fonte de navegador no OBS.

## Faixas de lance (lower thirds)
Na coluna **Lances** escolha o estilo da faixa (seis opções), a animação, o tempo no ar e quais lances aparecem. Gols e cartões lançados na súmula viram faixa sozinhos, com o jogador, a camisa e, no gol, o placar. **Testar o estilo** dispara um exemplo no OBS; **Jogador em destaque** apresenta um atleta da escalação; **Aviso manual** mostra qualquer texto. Escolha também a posição da faixa (esquerda, centro, direita ou livre em X e Y), o tamanho do texto e se os cartões saem em faixa ou como um cartão grande no centro da tela. Se você ocultar *Lances*, nada aparece.

## Overlays de campeonato
Na coluna **Campeonato** do canal escolha a tela (tabela, próximos jogos, resultados, artilharia, cartões, jogo em destaque, faixa de placares ou campeão), a edição e o estilo. Os **modelos** aplicam um visual pronto de uma vez; em **Meus modelos** você guarda o seu, exporta e importa. A prévia mostra exatamente o que o OBS vai exibir: copie a URL para uma Fonte de navegador. O estilo vive na URL, então cada tela do OBS tem a sua. Ligue **Controle ao vivo** para a tela só aparecer quando você clicar em *Mostrar…* em **Telas de campeonato no ar** (coluna Ao vivo); desligado, ela fica sempre no ar. Cartões só aparecem se o administrador liberar em Painel > Portal público.

## Configurar
1. Em [Transmissão](route:panel.broadcast.index) crie um **canal** (quem gerencia competições). Cada canal tem uma URL fixa por tela: placar, eventos, escalação e tabela.
2. No OBS, adicione uma fonte **Navegador** com a URL da tela e o tamanho do vídeo (1920 x 1080).
3. Escolha o **jogo no ar**. O placar, o relógio e as faixas de gol/cartão aparecem sozinhos conforme a súmula é lançada.
4. Ligue as **camadas** sob demanda (escalação, tabela) quando quiser mostrar.

## Conferir antes de transmitir
O **Estúdio de pré-visualização** reúne as quatro telas em cards. Clique em **Visualizar ao vivo** no card que deseja conferir: a prévia abre a URL real do canal em 1920 x 1080 e recebe as mesmas atualizações do OBS.

Você pode trocar o fundo entre campo, quadra, escuro e a grade de transparência para verificar contraste e recorte. Apenas uma prévia por canal fica conectada ao vivo de cada vez, evitando conexões e renderizações desnecessárias no navegador. Use os botões do próprio card para copiar a URL ou abrir a tela separadamente.

> O endereço do canal é o segredo: quem tem a URL vê a tela. Se vazar, use **Renovar** para gerar outra (a antiga para de funcionar).

## Modo operador (para a hora da transmissão)
O botão **Modo operador**, no alto da página, abre uma tela cheia pensada para operar ao vivo: tudo compacto e visível ao mesmo tempo, **sem rolar** (testado de 1024 x 768 a 1920 x 1080). Nela ficam o jogo no ar e as cenas, as cinco camadas grandes (Placar, Lances, Escalação, Tabela e Patrocinadores), as telas extras, as telas de campeonato, os overlays livres, o controle da escalação (apresentação, equipe, Anterior, Próximo e Início), os avisos de um clique, o jogador em destaque, as cartelas prontas, os acréscimos, o saque e os contadores das estatísticas. Atalhos: **1 a 5** ligam e desligam as camadas, **← →** passam a escalação, **R** reinicia e **Esc** volta ao quadro. Os atalhos não disparam enquanto você digita. Com vários canais, escolha o canal nas abas do topo. O quadro com todos os ajustes (visual, estúdios e configurações) continua igual: use **← Quadro** para voltar. A escolha fica neste navegador; quem só consulta vê a tela com os botões desativados.

### Montar o layout do seu jeito
### Monitor PGM
O bloco **PGM (no ar)** mostra, numa miniatura 16:9, tudo o que o canal está mandando ao ar agora: placar, lances, escalação, tabela, patrocinadores, barra e as telas extras ligadas, com uma conexão só. É o que o **canal envia**, não uma captura do OBS: se uma fonte estiver oculta na cena do OBS, ela ainda aparece aqui. Campeonato e overlays livres (cada um é uma fonte própria) não entram. Use o seletor de fundo (escuro ou xadrez) e **Congelar** para parar a prévia e poupar o computador; ela também pausa sozinha com a aba oculta. Ao lado dele, o bloco **PVW (prévia)** mostra como ficaria no ar a camada ou tela sobre a qual você passa o mouse (Escalação, Tabela, Patrocinadores e as telas extras) **antes** de você clicar; o clique continua sendo o que manda ao ar, e **Limpar** esvazia a prévia. A prévia vale só para quem está logado no painel e não muda nada no canal. Quem usa um layout salvo antes pode trazer os blocos em **Blocos**.

No Modo operador, clique em **Editar layout** para organizar a tela: **arraste pelo título** para mover um bloco e use o **canto ◢** para aumentar ou diminuir; tudo encaixa numa grade e os blocos vizinhos são empurrados. Pelo teclado, foque o título (ou o canto) e use as **setas** para mover (com **Shift** para redimensionar). Se não couber, o contorno fica vermelho e o bloco volta para a última posição válida. Em **Blocos** você mostra ou oculta cada bloco, em **Modelos** escolhe *Padrão*, *Escalação em foco* ou *Jogo rolando*, e **Restaurar padrão** desfaz tudo. Durante a edição os botões ficam travados (nada vai ao ar sem querer) e **Concluir layout** volta a operar. O bloco **OBS (saída real)** não vem nos modelos: mostre-o em **Blocos**. Ele conecta o seu navegador ao OBS desta máquina (Ferramentas > Configurações do servidor WebSocket, porta 4455, com senha) e mostra a foto do que está no ar de verdade, mais transmissão, gravação e desempenho. A senha não é guardada e nada passa pela plataforma. O layout fica salvo neste navegador; em telas pequenas os blocos empilham.

## Exibir e ocultar ao vivo
Em **No ar agora** use os botões **Placar**, **Lances**, **Escalação** e **Tabela** para tirar e colocar cada tela do ar: cada clique vale na hora no OBS. Ocultar o placar o remove instantaneamente; ocultar os lances impede as faixas de gol, cartão e substituição (a súmula continua registrando tudo). Escalação e tabela só podem ser mostradas com um jogo no ar. A rotação de patrocinadores também liga e desliga com um clique.

## Cenas
Em **Visual do overlay** escolha também o **formato do placar**: barra horizontal, equipes empilhadas, compacto ou faixa larga. Ajuste o visual de um canal do jeito que quer (estilo, cores, posição, fonte, camadas, patrocinadores) e use **Salvar cena** para guardá-lo com um nome, como *Pré-jogo*, *Jogo* ou *Intervalo*. Na hora da transmissão, clique no nome da cena para trocar tudo de uma vez — vale para qualquer canal. O jogo no ar e as fotos escolhidas na escalação não mudam. Quem gerencia competições salva e exclui cenas; o operador só aplica.

## Fontes
No visual do canal escolha a **Fonte** do placar, dos lances, da escalação e da tabela: a padrão, uma das fontes embutidas ou uma fonte própria. Quem gerencia competições envia as fontes da organização (WOFF2, WOFF, TTF ou OTF, até 2 MB, no máximo 4) em **Fontes próprias dos overlays** — envie só fontes que a organização pode usar na transmissão. Excluir uma fonte devolve à padrão os canais que a usavam.

## Patrocinadores
Cadastre os logotipos em **Patrocinadores dos overlays** (quem gerencia competições) e, em cada canal, ligue a rotação e escolha o tempo de cada logotipo. Adicione no OBS a URL da tela **Patrocinadores**: ela mostra um logotipo por vez na posição do visual do canal. Pausar um patrocinador o tira da rotação.

## Dicas
- Cartões, disciplina e documentos nunca aparecem nos overlays.
- O **estilo do placar** pode ser Arena, Clássico ou Minimal, e o placar pode ficar no alto ou embaixo da tela; as cores valem para os três.
- Alterações de visual aparecem na prévia assim que você clica em **Aplicar visual**.
- Se a conexão em tempo real falhar, a tela continua atualizando por consulta periódica.
- O passo a passo completo com imagens está no guia do OBS que acompanha o projeto.
