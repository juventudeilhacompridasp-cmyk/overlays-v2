# Otimizações e performance

Inventário vivo das otimizações analisadas e entregues. O objetivo é manter o painel leve e as fontes de navegador do OBS estáveis mesmo durante uma transmissão longa.

## Entregue

| Área | Otimização | Resultado |
| --- | --- | --- |
| Overlays unificados | A rota `/overlay/{token}/todos` compartilha uma assinatura Reverb e um polling entre todas as camadas. | Uma conexão e uma contingência por fonte, em vez de uma por tela. |
| Estado do canal | `OverlayState::for` normaliza aparência, escalação e telas uma única vez por atualização e reutiliza o snapshot público do jogo. | Menos trabalho de CPU e menos serializações repetidas em cada evento. |
| Escalação no overlay | A consulta de elenco é limitada aos atletas presentes na escalação da partida. | Evita carregar o elenco inteiro da inscrição quando só parte dele aparece. |
| Imagens | Variantes WebP, caminhos versionados e cache imutável no disco público. | Downloads menores e sem invalidação manual de cache. |
| Fotos no OBS | URLs assinadas de foto são reutilizadas por alguns minutos e só são emitidas quando o operador habilita o atleta. | Menos requisições e menor exposição de dados pessoais. |
| Camadas inativas | Escalação, tabela, estatísticas e telas extras só são montadas quando estão no ar. | O estado enviado ao OBS não calcula dados que a transmissão não usa. |
| Builder | Animações usam `transform`/`opacity`; vídeos são HTTPS, sem áudio e em loop. | Composição compatível com Chromium/OBS sem filtros pesados ou dependências externas. |
| Pré-visualização | O estúdio usa uma única prévia ativa e o PGM mantém um iframe único para as telas empilhadas. | Reduz conexões e trabalho de renderização no painel. |
| Tabelas | Só a página atual vai ao DOM; busca com índice normalizado calculado uma vez por lista e `useDeferredValue`; no servidor, busca adiada em 350 ms, visitas parciais do Inertia (`only`) e paginação no banco com `id` de desempate. | Digitação fluida e respostas pequenas, mesmo com listas grandes. |
| Portal e embeds | Imagens têm dimensões declaradas e carregamento `lazy` nas listas e galerias. | Evita reflow e adia downloads fora da área visível. |

## Decisões de operação

- O polling do overlay continua ativo quando o Reverb está indisponível: transmissão não pode depender de uma única conexão.
- O service worker não armazena páginas do painel nem respostas da API, preservando LGPD e evitando estado obsoleto.
- O estado público nunca inclui documentos, cartões, escalação ou identificadores pessoais que não sejam necessários à tela ligada.
- Consultas de tabela, estatísticas e chaveamento permanecem condicionais às telas que as solicitam.

## Próximas medições

1. Medir p95 de `/overlay/{token}/estado` e tempo de montagem de `OverlayState` com 30 overlays ativos.
2. Medir bytes transferidos por atualização e quantidade de reconexões Reverb por fonte do OBS.
3. Instrumentar o painel para identificar renders longos no estúdio de overlays, sem registrar dados pessoais.
4. Avaliar cache curto de dados públicos de portal e invalidação por evento, mantendo o estado ao vivo fora do cache.

Cada nova otimização deve registrar o cenário medido, o comportamento preservado e o teste que impede regressão.
