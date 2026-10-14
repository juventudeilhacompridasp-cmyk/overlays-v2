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

## Escalação
- Escolha entre lista, esquema tático ou a sequência que começa na lista e muda para o campo depois do tempo configurado.
- As posições vêm da escalação confirmada pelo gestor. Se apenas uma equipe tiver o esquema completo, ela aparece no campo e a outra continua em lista, sem posições inventadas.
- Fotos são opcionais e ativadas atleta por atleta. Se a imagem ficar indisponível, o overlay mantém o número da camisa no lugar.
- Use **Sem animação** quando quiser cortes instantâneos; a preferência de movimento reduzido do computador também é respeitada.

> O endereço do canal é o segredo: quem tem a URL vê a tela. Se vazar, use **Renovar** para gerar outra (a antiga para de funcionar).

## Dicas
- Cartões, disciplina e documentos nunca aparecem nos overlays.
- Se a conexão em tempo real falhar, a tela continua atualizando por consulta periódica.
- Para a escalação, mantenha a fonte Navegador em 1920 x 1080; a tela evita trabalho contínuo e só anima durante entradas e trocas de fase.
- O passo a passo completo com imagens está no guia do OBS que acompanha o projeto.
