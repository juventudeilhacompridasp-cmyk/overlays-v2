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
