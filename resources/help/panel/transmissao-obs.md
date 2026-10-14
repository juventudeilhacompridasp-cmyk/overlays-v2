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
Em **Escalação visual** escolha *Cartões*, *Jogador a jogador* ou *Show completo* e a equipe. No modo **automático** cada jogador fica o tempo que você definir (3 a 30 s); no **manual** use **Anterior**, **Próximo** e **Reiniciar** para passar na hora. A foto grande só aparece para quem você habilitou, e a faixa de patrocinadores é opcional.

## Gols sob o placar, acréscimos e avisos rápidos
Marque **Mostrar autores dos gols sob o placar** em *Visual do overlay* para listar os goleadores de cada equipe. Em *Acréscimos no relógio* escolha +1 a +8 e o número aparece ao lado do relógio. Na coluna **Escalação**, abra **Visual da apresentação** para escolher o conceito (clássico, túnel, carta de coleção, coluna lateral, telão de estádio ou programa do jogo), o estilo do clássico, a animação de entrada, o tamanho do texto, a opacidade, os cantos, a posição livre, o tamanho da faixa de patrocinadores e se o esquema tático mostra a foto ou o número. Na coluna **Estatísticas da partida** lance finalizações, escanteios, faltas e a posse de bola, escolha a visão (comparativo, gols e cartões ou destaque de um atleta) e use **Mostrar**: a tela sai sozinha depois do tempo escolhido (0 deixa no ar até ocultar). Trocar de jogo zera os números. Em **Patrocinadores** escolha o formato (logotipo, logotipo e nome, só o nome, banner largo), o estilo, a animação e a posição da rotação. Para a **barra de patrocinadores 1500 x 200**, envie o banner de cada patrocinador (ideal 1500 x 200; *Enviar banner* na lista), ajuste tempo, transição, moldura e posição em *Visual da rotação e da barra* e mostre a tela **Barra de patrocinadores** em *Telas extras*. Em **Placar avançado** (dentro de *Visual do overlay*) ajuste a posição livre em X e Y, o tamanho do texto, o bloco do período, os escudos, a opacidade, os cantos, a sombra, a animação de entrada e a **comemoração de gol** (texto, animação e duração da palavra e do nome da equipe). Os estilos *Vidro*, *Alto impacto*, *Neon*, *Faixa dinâmica* e *Gradiente* se somam aos três primeiros. Na coluna **Lances** há avisos de um clique: VAR, Pênalti, Cartão em análise, Jogo paralisado, Intervalo e Fim de jogo.

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
