# ADR 0002 — Modelo de placar por esporte (Fase 7)

Status: aceito (13/10/2026). Contexto: o futsal reaproveitou o modelo do futebol (gols em períodos com relógio). Vôlei e basquete não cabem nele.

## Decisão
1. **Eventos continuam sendo a fonte da verdade** (somente de acréscimo, desfazer = evento compensatório). O placar do jogo (`games.home_score/away_score`) é uma **projeção** de eventos, calculada pelo modelo de placar do esporte (`SportProfile::scoreModel`).
2. Três modelos:
   | Modelo | Esporte | Evento que pontua | `home_score`/`away_score` | Detalhe |
   |---|---|---|---|---|
   | `goals` | futebol, futsal | `goal`, `own_goal` | gols | — |
   | `sets` | vôlei | `point` (ponto de rally, `period` = nº do set) | **sets ganhos** | `games.sets` (JSON, derivado): pontos de cada set |
   | `points` | basquete (parte 3) | `basket` (1/2/3 pontos) | pontos totais | pontos por quarto derivados |
3. Regras puras e sem banco em `Rules/Scoring` (ex.: `SetMatch`: quando um set acaba, quando a partida acaba). A ligação com o banco fica em `ProjectGameScore`, que escolhe o modelo pelo esporte.
4. **Parâmetros do regulamento** em `RuleSet` (seção `scoring`): sets para vencer, pontos do set, pontos do set decisivo, vantagem mínima, pontos na tabela para vitória/derrota por placar apertado (3-2). O modelo em si vem do esporte e não é editável.
5. **Classificação**: `GameResult` ganha os pontos de rally (`homePoints/awayPoints`); critérios novos `set_ratio` e `point_ratio`; no vôlei os "gols pró/contra" da tabela são **sets** pró/contra. Vitória de 3-0/3-1 = 3 pontos; 3-2 = 2 (vencedor) e 1 (perdedor) — tudo parâmetro.
6. **Desfazer ponto**: só o **último** ponto pode ser anulado (anular um ponto do meio mudaria quais sets terminaram e a que set os pontos seguintes pertencem).
7. **Sem relógio** no vôlei (`hasClock = false`): o "período" é o set. O snapshot público continua trazendo `clock` (sempre "não iniciado") e passa a trazer `score_model` e `sets`; telas escolhem o que mostrar pelo modelo.
8. **Fora desta etapa (registrado):** limite de substituições *por set* e controle de rodízio/líbero no vôlei (hoje: substituição livre e volante, sem limite por set); tempos técnicos e pedidos de tempo; resultado rápido em esportes de sets (é recusado: lance os pontos).

## Consequências
- Acrescentar esporte = enum `Sport` + `SportProfile` + (se o placar não for gols) um modelo de placar puro e testado.
- `home_score`/`away_score` significam coisas diferentes por esporte; o rótulo vem do perfil (`scoreModel`) nas telas.
- Mata-mata e classificação continuam funcionando porque só dependem de `home_score`/`away_score` e do `GameResult`.
