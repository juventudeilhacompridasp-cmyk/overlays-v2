# ADR 0018 — Relatórios ilustrados (PDF e overlay)

## Status

Aceito (26/10/2026)

## Contexto

Pediram relatórios em PDF da partida e do campeonato "bem ilustrativos" (ícones, gráficos, elementos) e que os mesmos relatórios pudessem ir ao ar como overlays interativos na transmissão. O PDF existente (`Matches/Support/PdfReport`) é só texto, e a regra do projeto é não trazer dependência de renderização (nem para o PDF, nem para o OBS).

## Decisão

- **Módulo novo `Reports`** (`app/Modules/Reports`). O relatório é um **documento de blocos** (só dados): `GameReport::build($game, $sections, $public)` e `ChampionshipReport::build($division, $sections, $public)`. Blocos fixos em `ReportKit` (`kpis`, `versus`, `columns`, `donut`, `ranking`, `table`, `timeline`, `form`, `list`, `note`); seções em `ReportSections` (ordem fixa; escolha vazia = todas).
- **Uma só fonte para os ícones**: `ReportIcons` guarda caminhos SVG simples (M/L/C/Z, regra nonzero) que o PDF converte em operadores de caminho e as telas desenham em `<svg>`.
- **PDF vetorial nativo** (`Reports/Pdf/PdfDocument`: Helvetica com WinAnsi para os acentos, formas, rosca, transparência, recorte, JPEG, Flate) e diagramação em `ReportPdf` (capa, seções, tabelas e linha do tempo que quebram de página, rodapé "página x de y"). Escudos convertidos de WebP para JPEG por `ReportImages` (só disco público).
- **Duas versões do mesmo documento**: o PDF é **interno** (nomes completos, cartões, "uso interno" no rodapé); a versão **pública** (`$public = true`, usada no overlay) usa `PublicName` e só mostra cartões com `game_cards`. Nunca IDs, documentos, nascimento, fotos ou notas táticas. Disciplina do campeonato é sempre **por equipe**.
- **Painel**: `panel.visual-reports.*` (prévia + PDF), recurso `reports`, grupo `panel.can:operate-games` (consultar/baixar é de qualquer membro, como os PDFs da partida).
- **Contrato com `Broadcast`**: nova tela extra `report` (`/overlay/{token}/relatorio`, `Overlay/Report`) com `overlay_channels.report_settings` (`OverlayReportSettings`) e o comando `report_command` (`next|previous|reset`). Cada seção é um slide; a etapa é do **servidor** (manual = `step`; automático = `step` + tempo desde `anchor_ms`), espelhada em `lib/report.ts` (`reportSlideAt`). Mudar relatório/divisão/seções/modo/tempo, ligar a tela ou trocar o jogo (relatório da partida) recomeça do 1º slide. O estado (`OverlayState.report`) só é montado com a tela no ar. Animações só com `transform`/`opacity`.

## Consequências

- Bloco novo = construtor em `ReportKit` + desenho em `ReportPdf` + caso em `Components/Report/ReportBlock.tsx`.
- O overlay corta blocos longos (`compactBlock`); o PDF mostra tudo.
- `App\Support\Pdf\PdfDocument` (PDFs da partida, institucional) nasceu em paralelo e é mais simples; unificar os dois escritores fica como próximo passo, sem mudar os PDFs já existentes.
- Fora por enquanto: relatório da equipe, gráficos de linha, envio automático por e-mail ao encerrar o jogo e PDF com escudos no relatório do campeonato.
