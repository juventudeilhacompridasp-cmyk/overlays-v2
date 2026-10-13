# ADR 0009 — Módulo de artes de partidas

Status: aceito (implementação em partes; parte 1 em andamento — ver "Entrega em partes")
Data: 13/10/2026

## Contexto

Toda organização precisa divulgar jogos: "jogo do dia", resultado, escalação, tabela, artilheiros. Hoje isso é feito à mão em
editores externos. Queremos um **módulo de artes**: a plataforma monta a arte a partir dos dados da partida, em qualquer
**proporção**, com **estilo totalmente parametrizável** (cores, fontes, layout, elementos), e a pessoa baixa o PNG pronto.

## Decisões

### 1. Renderização no navegador, com motor puro
- O desenho acontece no **navegador** (canvas 2D): a prévia e o PNG baixado são **o mesmo código**, então o que se vê é o que sai.
  Nada de servidor de imagem na parte 1 (sem Chromium, sem fila).
- O motor tem duas camadas: `lib/arts.ts` (**puro**: recebe estilo + dados + tamanho e devolve uma lista de operações de desenho
  — retângulos, textos, imagens —, testável em Node) e `lib/artsDraw.ts` (executa a lista num canvas, com ajuste de texto à
  largura e carregamento das imagens). Tipo de arte novo = função de layout nova + teste, sem tocar na tela.
- **Imagens (escudos, logotipo) viajam como data URI** montados pelo servidor (disco público, 128 px): o canvas não "suja" com
  imagens de outra origem e o PNG pode ser exportado.
- **Fontes:** famílias do sistema, em pilhas seguras (sans, condensada, display, serif, mono). Fontes web embutidas entram numa
  parte seguinte (catálogo já isolado em `ART_FONTS`, para trocar sem mexer nos layouts).

### 2. Tudo é parâmetro
- **Formato** (proporção): presets 1:1 (1080×1080), 4:5 (1080×1350), 9:16 (1080×1920, story), 16:9 (1920×1080), 1,91:1
  (1200×630, link/OG) e **personalizado** (largura × altura, 200–4000 px).
- **Estilo** (`ArtStyle`, JSON): plano de fundo (cor sólida ou degradê com ângulo; padrão: nenhum, listras, grade, pontos;
  sombra), **paleta** (primária, secundária, texto, texto suave, destaque), **tipografia** (família de títulos e de texto,
  escala, peso, caixa alta, espaçamento), **layout** (variante, alinhamento, margem, moldura e raio), **escudos** (mostrar,
  tamanho, forma/fundo) e **elementos** ligáveis (competição, fase/rodada, data, hora, local, situação, logotipo da
  organização, rodapé, hashtag) com **textos personalizados** (título, rodapé, hashtag). Opção "usar as cores das equipes".
- Toda opção é validada no servidor (`ArtStyle::sanitize`: cores `#rgb`/`#rrggbb`, números dentro de faixas, listas fechadas) —
  entrada fora do esperado volta ao padrão, nunca vira erro nem HTML.
- **Modelos** (`art_templates`): a organização salva formato + estilo com um nome e reutiliza em qualquer partida. Há modelos
  prontos do sistema em código (`ArtPresets`), que se duplicam para personalizar.

### 3. Tipos de arte
Parte 1: **Jogo do dia** (confronto, data, hora, local), **Resultado** (placar, pênaltis, situação) e **Escalação** (titulares,
reservas e técnico dos dois lados). Depois: **Rodada** (vários jogos), **Classificação**, **Artilheiros**, **Destaque do jogo**.

### 4. Dados e privacidade (LGPD)
- Só dados que o portal público já mostra, mais a escalação do jogo: nomes de atletas **sempre por `PublicName`** (menor =
  primeiro nome + inicial, sem apelido). **Nunca** cartões, disciplina, documentos, fotos nem data de nascimento.
- Escalação só aparece se existir lançada; sem escalação a tela avisa em vez de desenhar vazio.

### 5. Acesso e pacote
- Recurso `arts` (pacotes Padrão e Completo). Gerar e baixar: qualquer membro do painel; **salvar/editar/excluir modelos**:
  `manage-competitions`. Nenhuma rota pública na parte 1.

## Entrega em partes
1. **Parte 1 — motor e estúdio:** formatos, estilo parametrizável, 3 tipos de arte de jogo, modelos do sistema e da organização,
   editor com prévia ao vivo e download do PNG, entrada pela súmula/lista de jogos.
2. **Parte 2 — mais tipos:** rodada, classificação, artilheiros, destaque; fundos por imagem enviada; fontes web embutidas.
3. **Parte 3 — servidor e automação:** renderização no servidor (Chromium), arte gerada ao encerrar o jogo, link público de
   compartilhamento e pacote de artes por rodada.

## Consequências
- Novo módulo `Arts` e recurso `arts` (`Feature`); nenhuma mudança nos outros módulos.
- Layouts testáveis sem navegador; a variação visual cresce por dados (estilo/modelos), não por tela.
