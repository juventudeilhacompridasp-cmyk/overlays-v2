---
title: Registro de atividades (auditoria)
category: Administração
tags: auditoria, log, registro, histórico, quem fez, mesário, segurança, atividades
order: 9
ability: manage-users
route: panel.activity-log.index
---
A tela [Registro de atividades](route:panel.activity-log.index) guarda, no banco de dados, **tudo o que os usuários fazem** para você poder auditar: quem fez, quando, o quê, em qual jogo, equipe ou pessoa, e se deu certo.

## O que é registrado
- **Ações:** toda ação de escrita no painel, na área do gestor, na do atleta e na plataforma (lançar na súmula, encerrar jogo, mudar camadas da transmissão, editar equipe, criar usuário…), inclusive as **recusadas** (sem permissão, dados inválidos ou erro).
- **Mudanças em jogos:** placar, situação, horário e local com o **valor de antes e de depois**, e cada lance lançado na súmula (gol, cartão, substituição…).
- **Quem:** nome e papel da pessoa no momento da ação (continua aparecendo mesmo se o usuário for removido).

## O que nunca é registrado
IP, CPF, senhas e os valores digitados nos formulários: o registro mostra só os **nomes dos campos** enviados.

## Como usar
Busque por pessoa, ação ou jogo; filtre por pessoa, área, resultado e tipo; abra uma linha para ver os campos e as mudanças. Para ver o histórico de um jogo, abra a tela com `?game=` do jogo (a súmula e o painel do operador levam até lá). O botão **Baixar CSV** exporta até 5.000 linhas com os mesmos filtros.

> Os registros não podem ser editados nem apagados individualmente. A instalação define por quanto tempo são guardados (padrão 365 dias, variável `ACTIVITY_LOG_RETENTION_DAYS`) e pode desligar a gravação (`ACTIVITY_LOG_ENABLED`) ou deixar de guardar tentativas recusadas (`ACTIVITY_LOG_REFUSED`).
