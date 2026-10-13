# ADR 0004 — Leitura e conferência de súmulas enviadas depois do jogo

Status: aceito (implementação em partes; ver "Entrega em partes")
Data: 13/10/2026

## Contexto

Nem todo jogo é lançado ao vivo pela súmula digital: muitas vezes a mesa preenche a súmula **em papel** (ou em outro sistema) e a
organização só a envia **depois do jogo**. Hoje não há onde guardar esse documento nem como conferi-lo com o que a plataforma já
tem (placar, gols, cartões, escalações). Queremos **enviar a súmula, o sistema ler e entender o conteúdo e apontar divergências**.

Decisões do responsável pelo produto (13/10/2026): aceitar **foto/PDF e também arquivo digital estruturado**; ler com **IA com visão
(Claude) + revisão humana**; **permitir** o envio da imagem a um serviço externo de IA, com aviso e **opção de desligar por
organização**; o resultado depois de confirmado é um **relatório de conferência** (compara a súmula enviada com o que já foi
lançado e aponta diferenças), **sem alterar o jogo**.

## Decisões

### 1. O arquivo: recurso, armazenamento e permissões
- Recurso novo `sheet_import` (`Feature::SheetImport`, só pacote Completo, liberável manualmente). Rotas com `feature:sheet_import`.
- Arquivos em `games` × `game_sheet_uploads` (`GameSheetUpload`), **disco `s3-private`** com URL assinada de 10 min (dado pessoal,
  inclusive de menores). Nunca público, nunca no snapshot/overlay/portal.
- Formatos: **imagem** (JPG, PNG, WebP), **PDF** e **estruturados** (CSV, XLSX). Até 15 MB. Mesmo arquivo (SHA-256) no mesmo jogo é recusado.
- Enviar/excluir exige `operate-games`; consultar vale para qualquer papel da equipe interna. O gestor de equipe **não** vê súmulas enviadas.
- Estados: `uploaded` → `reading` → `read` (aguarda revisão) → `reviewed` (conferência confirmada) | `failed` | `discarded`.

### 2. Leitura (extração)
- Interface `SheetReader` com duas implementações: **estruturada** (CSV/XLSX com cabeçalhos conhecidos, sem IA, sem serviço externo) e
  **visão** (Claude via API, imagem/PDF). O modelo é configuração (`services.anthropic.model`), não código.
- A saída é sempre um **JSON de esquema fixo** (`ExtractedSheet`): placar final (e por tempo/set, quando houver), equipes lidas,
  escalações (nome, camisa, titular/reserva), eventos (tipo, equipe, nome/camisa, minuto/tempo) e observações. Cada campo carrega uma
  **confiança** e o trecho lido; campo ilegível vira `null`, nunca inventado.
- A leitura **não** extrai CPF, RG, endereço, telefone nem qualquer documento: o prompt manda ignorá-los e o esquema não tem campo para eles.
- Roda em **fila** (job), com `CurrentOrganization::using(...)`, tentativas limitadas e erro legível em `failed`.

### 3. Conferência (o resultado)
- `SheetComparison` (puro) compara `ExtractedSheet` com o jogo: placar, gols por equipe, cartões, substituições e escalações
  (atletas ligados ao elenco por **camisa + nome aproximado**; ambíguo = pendência para o revisor, nunca palpite silencioso).
- O relatório classifica cada item: **confere**, **diverge** (mostra os dois lados) ou **não encontrado**. Um humano **revisa a leitura**
  (corrige nomes/números lidos errado) antes de o relatório valer; a correção fica registrada.
- **Nada é gravado no jogo**: o relatório não cria eventos, não encerra jogo nem muda o placar. Aplicar correções é ação manual na
  súmula (`ReopenGame` + lançamentos), como hoje. (Aplicar automaticamente fica como evolução, com novo ADR.)

### 4. LGPD, consentimento e custo
- Interruptor por organização (padrão: ligado, só vale com o recurso): `settings.sheet_import.ai_enabled`. Desligado, só os formatos
  estruturados são lidos e a tela explica.
- A tela de envio avisa **antes** do envio que a imagem será processada por um serviço externo de IA (provedor, finalidade, retenção
  zero configurada quando disponível). Os **modelos de Termos/Privacidade** (`LegalDefaults`) ganham o parágrafo; organizações com
  versão própria publicada precisam atualizar (a tela de Termos lembra).
- Só envia ao provedor o necessário: o arquivo do jogo, sem metadados da plataforma além de esporte e nomes das equipes.
  Nada de cookies/tokens/e-mails. Chave da API só em variável de ambiente (`ANTHROPIC_API_KEY`), nunca no front.
- Limites: tamanho (15 MB), número de leituras por jogo (3) e por organização por mês (parâmetro de plataforma, `config/platform.php`),
  para controlar custo; excedente vira mensagem clara, não falha silenciosa.
- Testes **nunca** chamam o provedor: `SheetReader` fake (como `fakeSender()` do push).

## Entrega em partes
1. **Envio e guarda** (esta): ADR, recurso `sheet_import`, `game_sheet_uploads`, tela Painel > Jogo > Súmulas enviadas (enviar, listar, abrir por URL assinada, excluir).
2. **Leitura**: `SheetReader`, leitor estruturado (CSV/XLSX), cliente Claude com visão, `ExtractedSheet`, job em fila, interruptor/limites, parágrafo nos Termos.
3. **Conferência e revisão**: `SheetComparison`, tela de revisão da leitura e relatório de conferência (tela + CSV).
4. **Acabamentos**: notificação ao organizador quando a leitura termina, métricas de uso por organização na área da plataforma.

## Consequências
- Mais um dado pessoal em `s3-private`: precisa constar na política de retenção (excluir o jogo ou o upload apaga o arquivo).
- Custo recorrente de API: precisa de limite e de observabilidade (parte 4).
- A leitura de manuscrito é imperfeita: a revisão humana não é opcional.
