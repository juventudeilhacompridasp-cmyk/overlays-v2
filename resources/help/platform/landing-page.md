---
title: Landing page do produto e contatos
category: Plataforma
tags: landing, site, produto, contato, formulário, marketing, pacotes
order: 6
route: platform.leads.index
---
A **landing page** apresenta a plataforma a quem ainda não é cliente. Ela está sempre em `/produto` e tem um formulário de contato; as mensagens chegam em [Contatos da landing](route:platform.leads.index).

## O que a página mostra
Recursos, overlays, esportes, **pacotes** e perguntas frequentes. A tabela de pacotes é montada a partir dos próprios pacotes e recursos do sistema: quando você muda um pacote ou cria um recurso novo, a página acompanha sozinha. Recursos **sob demanda** aparecem marcados e fora de todos os pacotes. Não há preços: a cobrança é combinada diretamente.

## Receber os contatos
- Cada mensagem fica guardada com nome, e-mail, organização, assunto, texto e a data do **aceite da política de privacidade**. Não guardamos IP, telefone nem CPF.
- Defina `PLATFORM_CONTACT_EMAIL` para receber um e-mail a cada contato. Sem ele, as mensagens só ficam na lista.
- Em **Contatos da landing** marque cada um como *Em contato* ou *Arquivado* e responda pelo e-mail. **Apague** os contatos que já não precisa guardar: o botão remove os dados pessoais.
- O formulário tem limite de 5 envios por minuto por endereço e uma armadilha contra robôs.

## Usar a landing como página inicial
Por padrão a raiz do site continua sendo o portal da organização. Para a raiz do domínio da plataforma mostrar a landing, ligue `PLATFORM_LANDING_ROOT=true`: ela passa a aparecer na raiz do domínio-base e do `www` dele (ou dos hosts de `PLATFORM_LANDING_HOSTS`, separados por vírgula). O endereço `www.{domínio-base}` não é tratado como organização. DNS e proxy continuam por sua conta.
