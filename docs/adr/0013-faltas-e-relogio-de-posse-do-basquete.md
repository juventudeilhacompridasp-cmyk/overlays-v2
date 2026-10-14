# ADR 0013 — Faltas especiais e relógio de posse do basquete

Status: aceito (14/10/2026).

## Contexto

O basquete já possui placar por pontos, faltas pessoais e de equipe e relógio regressivo parável. Faltas técnicas/antidesportivas e o relógio de posse ficaram fora do ADR 0002. Guardar o relógio de posse em uma coluna mutável quebraria a regra de que os eventos são a fonte da verdade e tornaria anulações e a fila offline incoerentes.

## Decisão

1. `technical_foul` e `unsportsmanlike_foul` são eventos explícitos e exclusivos do modelo de placar `points`. Nesta etapa ambos são lançados para um atleta da escalação, contam como falta pessoal e de equipe e respeitam o limite de cinco faltas. Faltas técnicas de banco/comissão e desqualificação automática por combinação de faltas especiais ficam fora do escopo.
2. O relógio de posse é uma projeção pública, nunca uma coluna mutável. `shot_clock_reset` registra um reinício com `payload.seconds` igual a 24 ou 14. O início de cada período equivale a um reinício em 24 segundos.
3. A projeção consome, em ordem, os marcos do relógio principal e os reinícios de posse. Ela corre somente enquanto o relógio principal corre, pausa/retoma junto dele, para em zero e é encerrada com o período.
4. Reiniciar a posse exige um período em andamento e um esporte de pontos com relógio parável. O comando integra a mesma fila offline idempotente da súmula. Anular o último reinício recompõe a projeção a partir dos eventos válidos.
5. Snapshot público, página ao vivo e overlay recebem apenas estado e segundos do relógio de posse. As faltas especiais alimentam somente os contadores públicos agregados; tipo e atleta não são expostos.

## Consequências

- OBS e navegador calculam a animação localmente a partir de `base_seconds`, `running_since_ms` e `server_time_ms`, sem requisições por segundo.
- O operador pode corrigir um reinício por evento compensatório, preservando auditoria.
- Uma violação de 24 segundos não cria falta nem troca a posse automaticamente; o operador decide o próximo lançamento.
