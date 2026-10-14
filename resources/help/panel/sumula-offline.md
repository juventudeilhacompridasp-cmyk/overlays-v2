---
title: Súmula sem internet (fila offline)
category: Operação de jogos
tags: offline, internet, fila, sincronizar, celular, pwa
order: 21
ability: operate-games
---
Se a internet cair durante o jogo, **continue lançando**: os lançamentos ficam numa fila guardada no próprio aparelho e são enviados sozinhos quando a conexão volta.

## Como funciona
- Cada lançamento (gol, ponto, período, relógio principal ou de posse, anulação, encerrar) vira um item da fila, com a **hora real do lance**.
- A tela mostra uma **projeção local** do jogo (placar, quem está em campo, substituições e relógio). Sets, faltas da equipe e anulações só atualizam depois do envio.
- Ao voltar a internet o envio é feito **em ordem**. Se um item for recusado (por exemplo, jogador já expulso), o envio **para ali**: o item e os seguintes ficam na fila para você descartar ou tentar de novo.
- Reenviar nunca duplica: cada item tem uma chave própria.

## O que ainda exige internet
Montar a escalação, a disputa de pênaltis e o resultado rápido.

## Instalar como aplicativo
No Mesário use **Instalar app** para abrir direto do celular. O aplicativo guarda só arquivos do sistema, nunca páginas com dados de atletas.

> Em aparelho compartilhado, saia da conta ao terminar. A fila guarda apenas números de identificação e horários, sem nomes.
