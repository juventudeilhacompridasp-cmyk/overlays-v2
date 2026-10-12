# 01 — Mapeamento da plataforma de referência

Fonte detalhada: [`referencia/portal-gdc-esporte-registro-sp.md`](referencia/portal-gdc-esporte-registro-sp.md)
(coleta real do **Portal GDC** público, cliente `esporteregistrosp`, em 12/10/2026).

> **Escopo coberto:** portal público (torcedor) + API pública + código do front (que revela a área logada).
> **NÃO coberto:** painel do organizador (GDC Arena), área logada real, cobrança/financeiro, geração de tabela, sorteio.
> Ver seção 6 (lacunas) e `docs/05-prompt-mapeamento-painel.md`.

## 1. O que o produto é
SaaS multi-cliente (subdomínio por entidade: `<cliente>.portalgdc.com.br`). Cada cliente liga/desliga áreas
por flags (`VER_*`), define cor/logo e privacidade (`EXIBIR_ATLETAS_EQUIPES`). Multiesporte: o tipo de resultado
da modalidade (`TIPORESULTADO`: gols, sets, pontos, performance) muda colunas e estatísticas.

## 2. Hierarquia de domínio observada
Cliente → Competição (ano) → **Grupamento/Especificação** (modalidade × categoria × sexo × fase × prova)
→ Grupos (A/B…) → Confrontos (partidas) → Eventos de súmula. Equipes se inscrevem por especificação;
**Pessoa é cadastro único (CPF)** com papéis de atleta e dirigente; atleta tem histórico por equipe/competição.

## 3. Funcionalidades por área
| Área | Funcionalidades confirmadas |
|---|---|
| Portal público | Home (ticker, próximo confronto, classificação top 5, notícias, apoiadores), competições, jogos/resultados, programação, classificação por grupo/fase, artilharia, defesa, disciplina, destaques, suspensos (atletas/dirigentes × cartões/julgamento), medalhas, equipes, hub da equipe, perfil do atleta, regulamentos (HTML), documentos (PDF), notícias, galerias, agenda institucional, editais, escolinhas, reservas |
| Partida | Placar, status (agendado/ao vivo/encerrado), eventos por período, elenco (atletas+comissão, suspensão, "em súmula"), arbitragem, mídias (transmissão, súmula, relatório), súmula online com relatório |
| Impressão | Programação e classificação em A4 geradas no navegador |
| Conta (só código) | Cadastro com CPF + OTP, login, LGPD (excluir conta), documentos, inscrição individual/dupla/equipe, PAR-Q |
| Gestão de equipe (só código) | Elenco, inscrever atleta/dirigente, camisa, liberação, credencial/QR, gestores auxiliares, solicitações, inscrição em competição |
| Outros módulos (só código) | Escolinhas (turmas, presença, desempenho, fila), reservas de locais, editais com prestação de contas |

## 4. Regras de futebol (exemplo real, configuráveis por regulamento)
Pontos 3/1/0; desempate em ordem (vitórias, saldo, gols pró, confronto direto, menos vermelhos, menos amarelos, sorteio);
pênaltis 5+alternadas; 3 amarelos = 1 jogo; vermelho = 1 jogo + tribunal; amarelos zeram após a 1ª fase;
W.O. cumpre pena; rebaixamento; até 8 substituições em 3 atos; relação 11+11+3; prazos/documentos para condição de jogo;
trocas de atleta por solicitação. **Todos devem ser parâmetros do regulamento, não código fixo.**

## 5. O que copiamos, melhoramos e acrescentamos
**Copiar:** contexto persistente (competição/categoria/fase), temas por tenant, menus por flag, impressão A4, hub da equipe, súmula online.
**Melhorar:** tempo real de verdade (WebSocket), SEO (URLs reais/SSR, Open Graph por partida), validação de dados cadastrados,
LGPD (dados de menores e disciplina **não** devem ser públicos sem consentimento; flag aplicada no servidor), IDs não sequenciais (ULID),
login separado por perfil, API pública documentada e com rate-limit.
**Acrescentar:** overlays OBS, painel do operador de transmissão, app/PWA do mesário, push/WhatsApp, estatísticas avançadas, widgets embedáveis.

## 6. Lacunas a mapear (painel do organizador)
Criação de competição e regulamento parametrizável, sorteio e geração de tabela, súmula (fluxo de lançamento), tribunal disciplinar,
financeiro/cobranças, inscrição (aprovação, documentos), relatórios, usuários/permissões, planos e preços.
Até lá, a arquitetura assume esses módulos com base nas rotas do `api.js` e no regulamento observado.
