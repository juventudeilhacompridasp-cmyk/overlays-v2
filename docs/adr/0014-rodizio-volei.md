# ADR 0014 — Rodízio automático do vôlei

## Status

Aceito

## Contexto

O placar do vôlei é formado por eventos de rally, mas o saque inicial de cada set e a posição atual das equipes ainda não eram derivados. Alterar a escalação ou publicar identificadores de atletas no overlay também criaria riscos de consistência e privacidade.

## Decisão

O painel registra, antes do primeiro rally de cada set, o lado que inicia sacando em `games.volleyball_rotation.servers`. A posição inicial é a ordem dos seis titulares salva na súmula. Depois de cada rally, se o lado vencedor não era o sacador, ele gira uma posição no sentido horário e passa a sacar; se já sacava, as posições permanecem.

O estado é sempre recalculado a partir dos eventos efetivos (`GameEvent::effective()`), portanto desfazer um ponto também desfaz o rodízio. Sets sem saque inicial configurado ficam marcados como não configurados e não inventam uma posição.

O snapshot público e os overlays recebem apenas o set, o lado sacador e as posições numéricas. IDs, nomes e escalações continuam restritos ao painel, em conformidade com a LGPD.

## Consequências

- A configuração pode ser alterada somente antes do primeiro rally do set.
- Não é necessário criar um novo tipo de evento ou editar eventos existentes.
- A ordem dos titulares passa a ser a ordem de rotação; o mesário deve salvá-la nessa ordem.
