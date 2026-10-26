---
title: Alertas operacionais dos jogos
category: Operação de jogos
tags: alertas, aviso, escalação, árbitro, estádio, conflito, e-mail, véspera
order: 21
ability: manage-users
route: panel.parameters.edit
---
Todo dia, de hora em hora, a plataforma olha os **jogos agendados que estão chegando** e manda um **resumo por e-mail** a quem gerencia competições (administrador e organizador, ou quem tiver essa permissão) quando encontra algo a resolver:

- **Equipe sem escalação** (nenhum titular lançado);
- **Sem árbitro ou mesário escalado** (só se a organização tem árbitros cadastrados);
- **Sem estádio definido**;
- **Conflito de agenda**: mesmo estádio em horários que se sobrepõem ou a mesma equipe com pouco descanso, pelos tempos do regulamento da divisão.

Cada problema é avisado **uma única vez**; se aparecer um problema novo, só ele entra no próximo e-mail. O e-mail leva só nomes de equipes, horários e o que falta, e um link para os jogos no painel.

## Como ajustar
- Em [Parâmetros](route:panel.parameters.edit): quantas **horas à frente** olhar (1 a 72, padrão 24) e quais verificações valem.
- Em [Comunicações](route:panel.communications.edit): ligar ou desligar o aviso por e-mail.
- A instalação pode desligar tudo com `OPERATIONAL_ALERTS=false`.
