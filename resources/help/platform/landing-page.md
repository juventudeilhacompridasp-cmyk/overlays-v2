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

## Galeria "O produto em ação"
São 7 prints das telas reais (placar, faixa de lance, escalação, classificação, pênaltis, barra de patrocinadores e Modo operador), feitos com **dados fictícios** e guardados em `public/marketing`. Quando uma dessas telas mudar de aparência, quem desenvolve roda `npm run marketing:shots` e publica as imagens novas junto com a mudança.

## Medição de acessos
A landing pergunta, na primeira visita, se pode **contar os acessos de forma anônima**. Só quem clica em *Permitir* é medido, e quem usa *Do Not Track* ou *Global Privacy Control* no navegador nem vê a pergunta. O rodapé tem *Preferências de medição* para mudar de ideia.

- Guardamos **apenas contadores por dia**: visitas (uma por sessão do navegador), de onde vieram (direto, buscadores, redes sociais ou outros sites, sem guardar o endereço), celular ou computador, seções vistas e botões clicados. Nada de IP, identificador, cookie de rastreio ou histórico de navegação.
- Em [Medição da landing](route:platform.metrics.index) veja os últimos 7, 30 ou 90 dias: visitas por dia, contatos enviados e *contatos por visita*. Esse último é **aproximado**, porque o formulário também recebe mensagens de quem não permitiu a medição.
- Os contadores com mais de 400 dias são apagados sozinhos. `PLATFORM_LANDING_ANALYTICS=false` desliga a medição e o aviso.

## Receber os contatos
- Cada mensagem fica guardada com nome, e-mail, organização, assunto, texto e a data do **aceite da política de privacidade**. Não guardamos IP, telefone nem CPF.
- Defina `PLATFORM_CONTACT_EMAIL` para receber um e-mail a cada contato. Sem ele, as mensagens só ficam na lista.
- Em **Contatos da landing** marque cada um como *Em contato* ou *Arquivado* e responda pelo e-mail. **Apague** os contatos que já não precisa guardar: o botão remove os dados pessoais.
- O formulário tem limite de 5 envios por minuto por endereço e uma armadilha contra robôs.

## Usar a landing como página inicial
Por padrão a raiz do site continua sendo o portal da organização. Para a raiz do domínio da plataforma mostrar a landing, ligue `PLATFORM_LANDING_ROOT=true`: ela passa a aparecer na raiz do domínio-base e do `www` dele (ou dos hosts de `PLATFORM_LANDING_HOSTS`, separados por vírgula). O endereço `www.{domínio-base}` não é tratado como organização. DNS e proxy continuam por sua conta.
