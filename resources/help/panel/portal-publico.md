---
title: Portal público: tema, logotipo e o que aparece
category: Configurações da organização
tags: portal, tema, logotipo, marca, cores, público, seo
order: 51
ability: manage-users
route: panel.organization.edit
---
O portal é o site que o público vê: início com jogos ao vivo e próximos, competições, tabelas, equipes e a página de cada jogo.

## Personalizar
Em [Portal público](route:panel.organization.edit) o administrador ajusta a aparência (**tema**). Com o recurso de marca própria também troca o **logotipo** e o rodapé "Feito com..." deixa de aparecer.

## O que é público
- **Só edições publicadas.** Rascunho dá página não encontrada.
- Placar, classificação, artilharia, estatísticas e gols (autor pelo apelido ou nome).
- Nomes de **menores** aparecem abreviados ("João S."); adultos, pelo apelido ou nome.
- Nunca aparecem cartões, disciplina, escalações, elenco, fotos, CPF ou documentos.

## Página inicial e compartilhamento
No mesmo cartão, os grupos **Página inicial** e **Compartilhamento** escolhem o que a página inicial mostra (ao vivo, próximos jogos, resultados recentes, lista de competições), se existe a **página de estatísticas** da edição (desligada, o endereço responde "não encontrado" e o botão some) e os **botões de compartilhar** (WhatsApp e copiar link). Tudo ligado por padrão. Quantos itens aparecem em cada lista fica em [Parâmetros](route:panel.parameters.edit).

## Página pública do jogo
Em Portal público, o cartão **O que as páginas públicas mostram** liga ou desliga o que aparece abaixo do placar:
- **Linha do tempo**: gols e substituições com minuto (futebol e futsal; vôlei e basquete não têm).
- **Cartões** na linha do tempo: **desligado por padrão**, porque cartão é dado disciplinar. Ligue só se a organização quiser mostrar.
- **Posição na tabela** das duas equipes, **forma recente** (últimos 5 jogos) e **confronto direto** (histórico entre as duas equipes).

## Página pública da equipe
No mesmo cartão (agora "O que as páginas públicas mostram") há as opções da página da equipe:
- **Campanha por edição**: jogos, vitórias, empates, derrotas, gols e colocação na tabela.
- **Forma recente**, **artilheiros da equipe** (nome público, 5 maiores) e **calendário (.ics)**, para a pessoa adicionar os jogos à agenda do celular.
- **Elenco público**: atletas (nome público e camisa) e técnico da edição mais recente. **Desligado por padrão**; nunca traz foto, nascimento ou documento.

## Página pública da edição
- **Chaveamento do mata-mata**: quadro visual com as fases (oitavas, quartas, semifinal, final), placar de cada confronto (soma dos dois jogos, quando há ida e volta), pênaltis, o vencedor destacado e o **campeão** no fim.
- **Regras da tabela explicadas**: quantos pontos vale vitória, empate e derrota e a **ordem dos critérios de desempate** da edição, como estão no regulamento.

## Página pública da competição
O endereço da competição (`/competicoes/nome`) mostra o histórico de todas as edições **publicadas** (rascunho nunca aparece):
- **Página própria**: se desligar, o endereço volta a levar direto à edição mais recente.
- **Campeão e vice** de cada edição: no mata-mata, pela final decidida; em pontos corridos, pelo líder da tabela **depois que a edição é encerrada**. Fase de grupos sozinha não define campeão.
- **Recordes e títulos**: equipes com mais títulos, maiores vitórias e jogos com mais placar, só com jogos encerrados em campo (W.O. fica de fora).

Os nomes seguem a regra de privacidade: menores aparecem abreviados. Escalações, fotos e documentos nunca aparecem.

Cada página já sai pronta para buscadores e compartilhamento (título, descrição e imagem). O botão de impressão gera a tabela em A4.
