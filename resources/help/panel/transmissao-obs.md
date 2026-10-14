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
- O **estilo visual** pode ser Arena, Clássico, Minimal ou Studio. O Studio aplica a linguagem editorial ao placar, aos lances, à tabela e às apresentações da escalação; o placar pode ficar no alto ou embaixo da tela e as cores valem para todos.
- Alterações de visual aparecem na prévia assim que você clica em **Aplicar visual**.
- Se a conexão em tempo real falhar, a tela continua atualizando por consulta periódica.
- O passo a passo completo com imagens está no guia do OBS que acompanha o projeto.
