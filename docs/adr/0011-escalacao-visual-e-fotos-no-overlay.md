# ADR 0011 — Escalação visual, esquema tático e fotos no overlay

Status: aceito (13/10/2026)

## Contexto

O overlay de escalação mostra duas listas estáticas. O domínio já guarda, por jogo e equipe, a formação escolhida, a vaga
de cada titular e as coordenadas da vaga no campo/quadra, mas a transmissão não usa essas coordenadas. As fotos dos atletas
ficam no disco privado e, pela decisão anterior, não saem do painel. A transmissão agora precisa apresentar a escalação e
depois o esquema tático, com escolha explícita de quais fotos podem aparecer, sem transformar o overlay em uma API de elenco.

## Decisão

1. `overlay_channels.lineup_settings` guarda a apresentação da escalação separadamente da aparência geral: modo
   (`sequence`, `list` ou `formation`), duração da lista antes da transição e os IDs dos atletas cujas fotos foram
   explicitamente habilitadas. Canal existente recebe `sequence`, seis segundos e nenhuma foto.
2. A posição esportiva continua tendo uma única fonte: `game_lineups.position`, validada contra a formação de
   `game_tactics` e as coordenadas puras de `Rules/Tactics/Formations`. O painel de transmissão não edita a escalação nem
   duplica posições; ele apenas escolhe como apresentar o que a equipe definiu.
3. No modo `sequence`, a camada entra como lista de titulares e reservas e transiciona uma vez para dois campos/quadras
   táticos. `list` e `formation` fixam uma das fases. Se não houver formação ou posições suficientes, o lado continua em
   lista, sem inventar coordenadas.
4. A API privada do canal pode alterar `lineup_settings`, com os mesmos limites do painel. IDs de foto precisam pertencer à
   escalação do jogo no ar, não podem se repetir e são descartados quando o canal troca de jogo.
5. Fotos permanecem no disco privado. O estado do overlay recebe somente uma URL assinada curta para cada atleta
   habilitado; atletas não selecionados recebem `photo_url: null`. O estado público não devolve ID da pessoa, caminho do
   arquivo, nascimento, documentos, disciplina nem instruções táticas privadas.
6. URLs assinadas de foto são reutilizadas por alguns minutos para impedir novo download a cada polling do OBS. Desligar uma
   foto a remove do próximo estado/Reverb; a URL já emitida expira pouco depois.
7. A tela mantém o orçamento do preset Arena: animações somente de `opacity` e `transform`, imagens de 96 px, sem filtros,
   vídeo, canvas ou trabalho contínuo. `motion: none` troca as fases sem animação.

## Consequências

- Exibir foto é uma decisão consciente do operador por atleta e por canal, não consequência automática de a pessoa ter foto.
- Quem possui a URL secreta do overlay pode ver, durante a validade curta, as fotos que o operador colocou no ar; a interface
  deve deixar esse efeito claro.
- Mudanças de formação e posição feitas pelo gestor chegam ao overlay pelo estado já existente; notas privadas continuam fora.
- Novos modos de apresentação devem ampliar `lineup_settings` de forma compatível e manter um fallback sem fotos.

## Adendo — apresentação completa e palco com transições

`lineup_settings.presentation = full` junta, para a equipe escolhida, a grade de cartões, o esquema tático (`FormationCard`: campo/quadra e
lista dos titulares no mesmo conceito visual) e, com `full_individuals`, cada jogador. As etapas e durações saem de
`OverlayLineupTimeline::plan` (espelhada em `lineupPlan` no front): grade (`list_seconds`), esquema (`formation_seconds`, só se a equipe
tem `formation_ready`; o servidor calcula isso em `OverlayState::lineupPlan`) e jogadores (`individual_seconds`). Depois da última etapa `show` e
`full` voltam à grade. A troca entre etapas é do `LineupStage`: a etapa que sai e a que entra ficam sobrepostas e usam `lineup_settings.transition`
(`fade|slide|wipe|zoom|flip`, só transform/opacity/clip-path); a entrada própria de cada cartão (`animation`) só toca na primeira etapa. A faixa de
patrocinadores (`sponsor_banner`) é UMA só, fora do palco, e não reinicia a rotação nas trocas; vale também para `sequence`/`list`/`formation`.

## Adendo — apresentação individual e grade de cartões

`lineup_settings` ganhou as apresentações `cards` (grade de cartões da equipe com o técnico), `individual` (um jogador por
vez: foto grande, camisa, posição, nome) e `show` (grade e depois cada jogador), com `team`, `individual_mode`
(`auto` por `individual_seconds`, 3 a 30 s, ou `manual`), `individual_scope` (titulares ou todos) e `sponsor_banner`.
A etapa atual é do servidor: `step` (manual) ou `anchor_ms` (automático, o cliente deriva a etapa do relógio do servidor, então
todas as telas ficam sincronizadas). O operador usa `lineup_command` (`next`, `previous`, `reset`) no painel ou na API; o servidor
calcula a nova etapa (`OverlayLineupTimeline`, espelhada em `resources/js/lib/overlayLineup.ts`) e nenhum cliente envia `step`
ou `anchor_ms`. Mudar jogo, equipe, modo, tempos, apresentação ou ligar a camada reinicia. A foto grande (400 px, URL assinada de
5 min) segue a mesma regra: só para o atleta habilitado pelo operador.
