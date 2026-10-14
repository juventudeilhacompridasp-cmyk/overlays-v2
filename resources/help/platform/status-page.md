---
title: Status page da plataforma
category: Plataforma
tags: status, incidente, manutenção, disponibilidade, uptime, monitoramento
order: 5
route: platform.status.index
---
A **status page** é a página pública (`/status`, e a página inicial de `status.{domínio-base}`) em que clientes veem se o painel, o placar ao vivo e os overlays estão no ar. Em [Status page](route:platform.status.index) você acompanha a situação e comunica o que o monitoramento não sabe.

## O que é monitorado
Painel e portal público, **tempo real** (placar e overlays), banco de dados e cache, **filas** e armazenamento de arquivos. A sondagem roda **a cada minuto** (`status:probe`) e guarda 90 dias de medições.

- **3 falhas seguidas** abrem um incidente automático ("Investigando", impacto *Fora do ar*); **2 sucessos seguidos** o resolvem sozinho.
- Durante uma **manutenção em andamento** nenhum incidente automático é aberto.
- A página mostra a barra dos últimos 90 dias de cada componente: verde (≥ 99,9 %), âmbar (≥ 95 %), vermelho (abaixo) e cinza (sem medições).

## Comunicar um incidente
Use **Novo comunicado**: título, impacto (*Instabilidade* ou *Fora do ar*), situação, componentes afetados e uma **mensagem pública** em linguagem simples. Depois publique **atualizações** (Investigando → Causa identificada → Monitorando → Resolvido); resolver encerra o incidente na página.

## Agendar manutenção
Troque para **Mudar para manutenção**, informe início e fim previstos e os componentes. Ela aparece em "Manutenções programadas" antes de começar e muda o componente para *Em manutenção* dentro da janela (ou quando você marcar *Em andamento*). Conclua pelo formulário de atualização.

## Avisos por e-mail
Quem quiser se inscreve na própria status page: informa o e-mail, **confirma pelo link recebido** (sem confirmação nada é enviado; o pedido expira em 7 dias) e passa a receber um e-mail quando um incidente é aberto, atualizado ou resolvido e quando uma manutenção é agendada. Todo e-mail traz o link para **cancelar** (em duas etapas, para leitores de e-mail não cancelarem sozinhos). A quantidade de inscritos aparece em [Status page](route:platform.status.index); a lista de e-mails não é exibida. `STATUS_NOTIFY=false` desliga os envios.

## Selo de status
`/status/selo.svg` é uma imagem "status | operacional" que muda de cor sozinha. O código pronto para colar em outros sites está em [Status page](route:platform.status.index). Nos portais das organizações **sem marca própria** o selo já aparece no rodapé.

## Integrações e contingência
- `GET /status.json` (aberto a qualquer origem) e `/status.atom` (feed) trazem o mesmo conteúdo da página.
- Com a variável `STATUS_PUBLISH_DISK` (ex.: `s3`), a cada minuto o retrato vai para `status/status.json` nesse disco, permitindo servir a página por CDN mesmo com a aplicação fora do ar.
- A página **nunca** mostra dados de organizações, atletas ou tokens: só situação técnica e textos que você escreve. Não coloque dados pessoais nas mensagens.
