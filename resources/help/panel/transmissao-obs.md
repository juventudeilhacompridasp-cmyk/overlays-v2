---
title: Transmissão com overlays no OBS
category: Transmissão
tags: obs, overlay, placar, transmissão, escalação, tabela, canal
order: 40
feature: overlays
route: panel.broadcast.index
---
Os overlays são telas de vídeo que o OBS carrega como **fonte de navegador**, com atualização em tempo real.

## Configurar
1. Em [Transmissão](route:panel.broadcast.index) crie um **canal** (quem gerencia competições). Cada canal tem uma URL fixa por tela: placar, eventos, escalação e tabela.
2. No OBS, adicione uma fonte **Navegador** com a URL da tela e o tamanho do vídeo (1920 x 1080).
3. Escolha o **jogo no ar**. O placar, o relógio e as faixas de gol/cartão aparecem sozinhos conforme a súmula é lançada.
4. Ligue as **camadas** sob demanda (escalação, tabela) quando quiser mostrar.

## Conferir antes de transmitir
O **Estúdio de pré-visualização** reúne as quatro telas em cards. Clique em **Visualizar ao vivo** no card que deseja conferir: a prévia abre a URL real do canal em 1920 x 1080 e recebe as mesmas atualizações do OBS.

Você pode trocar o fundo entre campo, quadra, escuro e a grade de transparência para verificar contraste e recorte. Apenas uma prévia por canal fica conectada ao vivo de cada vez, evitando conexões e renderizações desnecessárias no navegador. Use os botões do próprio card para copiar a URL ou abrir a tela separadamente.

## Escolher o estilo
Na seção **Visual do overlay**, escolha entre **Arena** (mais esportivo, com volumes e cantos arredondados) e **Studio** (editorial, compacto e com linhas retas). Cores, posição, escala e nível de animação continuam independentes do estilo. Clique em **Aplicar visual** para atualizar a prévia, o OBS e qualquer controle conectado à API.

> O endereço do canal é o segredo: quem tem a URL vê a tela. Se vazar, use **Renovar** para gerar outra (a antiga para de funcionar).

## Dicas
- Cartões, disciplina e documentos nunca aparecem nos overlays.
- Alterações de visual aparecem na prévia assim que você clica em **Aplicar visual**.
- O estilo escolhido vale para placar, lances, escalação e tabela do mesmo canal.
- Se a conexão em tempo real falhar, a tela continua atualizando por consulta periódica.
- O passo a passo completo com imagens está no guia do OBS que acompanha o projeto.
