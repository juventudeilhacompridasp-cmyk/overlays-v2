---
title: Parâmetros da organização
category: Configurações da organização
tags: parâmetros, recursos, desligar, mesário, janelas, limite
order: 55
ability: manage-users
route: panel.parameters.edit
---
Em [Parâmetros](route:panel.parameters.edit) o administrador ajusta o que é próprio da organização. O padrão é o comportamento original: se você não mexer, nada muda.

## Recursos em uso
Mostra os recursos opcionais que o seu pacote libera (inscrição online, carteirinha, relatórios, widgets, avisos push, área do gestor, artes e, quando liberados pela plataforma, súmulas enviadas e Mercado). Desmarque o que a organização **não quer usar**: ele some do menu e das páginas e as rotas passam a recusar o acesso. Nada é apagado, e dá para religar quando quiser.

> Isso só **reduz** o que o pacote libera; nunca liga algo que a organização não tem. Overlays para OBS e domínio próprio não entram na lista, para ninguém tirar a transmissão do ar sem querer.

## App do mesário
Define quais jogos aparecem em [Mesário](route:panel.mesario):
- **Dias à frente**: jogos agendados até daqui a esse número de dias (0 mostra só os de hoje).
- **Horas de encerrados**: por quanto tempo um jogo encerrado continua na tela.
- **Limite de jogos**: máximo de cartões na tela.

## Portal público e widgets
Define quantos itens aparecem nas listas públicas:
- **Itens por bloco da página inicial** (4 a 24): jogos ao vivo, próximos e recentes. Padrão 8.
- **Jogos por lista dos widgets** (3 a 20): próximos e recentes do widget de jogos. Padrão 6.
- **Artilheiros por divisão** (3 a 30): tabela de artilharia da edição. Padrão 10.

## API pública
Por padrão a API de consulta (`/api/v1`: competições, edições, estatísticas e jogos publicados) está ligada. Desmarque para que ela responda "não encontrado" neste endereço. O portal, os widgets e o controle privado dos overlays (chave própria do canal) continuam funcionando.

## Lançamento de resultados
**Permitir o resultado rápido** liga ou desliga o lançamento do placar final sem súmula detalhada (tela da fase e [Mesário](route:panel.mesario)). Desligado, o placar só entra pela súmula, com eventos; o W.O. continua disponível. Padrão: permitido.
