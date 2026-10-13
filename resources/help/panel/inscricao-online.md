---
title: Inscrição online das equipes
category: Competições
tags: inscrição, equipes, aprovar, link pessoal, documentos, menores
order: 11
ability: manage-competitions
feature: online_registration
route: panel.registrations.index
---
Com a inscrição online as equipes se cadastram sozinhas, sem login, e a organização apenas **analisa**.

## Abrir as inscrições
Na edição, ajuste o status para **Inscrições** e preencha as regras: prazo, instruções, mínimo e máximo de atletas e se o documento é obrigatório. O link público é `/inscricoes/{competição}/{edição}`.

## O que a equipe faz
A equipe recebe por e-mail um **link pessoal** (é o único acesso dela), cadastra atletas e comissão, envia os documentos e **envia o pedido**. Para menores de idade é exigido o responsável e a autorização.

## O que você faz
Em [Inscrições](route:panel.registrations.index):
1. Abra o pedido e **aprove ou recuse cada pessoa** (recusar exige o motivo).
2. **Devolver** reabre o pedido para a equipe ajustar.
3. **Aprovar** cria a equipe (ou reaproveita), a inscrição confirmada e o elenco, tudo de uma vez. Se houver conflito (camisa repetida, atleta já ativo em outra equipe da divisão) nada é gravado e o motivo aparece.

## Privacidade
O CPF nunca é guardado em claro, só um código irreversível usado para evitar cadastro duplicado. Documentos ficam em área privada e abrem por link temporário. Os e-mails enviados podem ser ligados ou desligados em [Comunicações](help:comunicacoes).
