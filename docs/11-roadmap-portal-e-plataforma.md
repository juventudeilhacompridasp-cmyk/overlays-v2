# Roadmap: portal público rico, ajuda, API interna, perfis e leitura de súmulas

Pedidos de 13/10/2026 (ordem de decisão do responsável). Cada item vira PRs pequenos, sempre **parametrizável** (padrão = comportamento atual;
nada novo liga sozinho) e com testes. Atualize o status em `docs/09-status-e-pendencias.md`.

## 1. Leitura de súmulas (ADR 0004) — **desligada para todos, ativada e configurada à parte**
- Recurso **sob demanda** (`Feature::isOptIn()`): fora de TODOS os pacotes; só o Super Admin libera por organização (`organizations.opt_in_features`,
  /plataforma). *(entregue)*
- Configuração por organização (parte 2): `settings.sheet_import` — `ai_enabled` (visão), `max_reads_per_game`, `monthly_limit`,
  formatos aceitos; limites e chave do provedor em `config/platform.php`/ambiente. Tela Painel > Súmulas enviadas > Configuração.
- Parte 2: `SheetReader` estruturado (CSV/XLSX) + visão (Claude) + `ExtractedSheet` + fila. Parte 3: conferência e revisão.

## 2. Portal público muito mais rico (dados e telas)
Hoje: home (ao vivo/próximos/recentes), edição (tabela, jogos, artilharia), equipe, jogo ao vivo, estatísticas e widgets. Falta riqueza:
| Tela | Acrescentar (todos opcionais por organização em `settings.portal`) |
|---|---|
| Jogo (**feito**: linha do tempo, tabela, forma, confronto direto; falta o resto da linha) | linha do tempo completa (gols, cartões **se a organização permitir**, substituições), confronto direto (histórico), posição das duas equipes na tabela, forma recente, local com mapa/endereço, árbitros, escalações públicas (nomes via `PublicName`; menores só inicial), mídia (vídeo/transmissão), compartilhar/calendário (.ics) |
| Equipe (**feito**: campanhas, forma, artilheiros, .ics, elenco opcional; falta calendário completo paginado, estádio e redes sociais) | histórico por edição, calendário completo, forma, elenco público (`PublicName`, camisa, posição; sem foto de menor), artilheiros da equipe, campanhas, estádio (**página do local feita**), redes sociais |
| Edição (**feito**: chaveamento visual, campeão, regras e desempates explicados) | **chaveamento visual** do mata-mata, tabela com critérios de desempate explicados, melhor ataque/defesa, artilharia **e assistências**, disciplina "fair play" (só se a organização permitir), calendário por rodada/dia/local, regulamento e notícias, melhores momentos |
| Competição | ✅ histórico de edições, campeões e vice, títulos e recordes (`CompetitionPage`); falta: regulamento permanente |
| Atleta | **fora** (LGPD), exceto ranking de artilharia com `PublicName` |
| Estádio | página com endereço, mapa, jogos agendados |
| Geral | busca global (equipes/competições, **feita**), calendário .ics por equipe/competição, feeds RSS/Atom (**Atom de resultados feito**), JSON-LD (`SportsEvent`/`SportsTeam`, **feito** nas páginas do jogo, da equipe e da edição), imagens Open Graph por jogo (**feito**), modo TV (placar grande rotativo), PWA do torcedor |
Dependências de dados: **assistências** (novo evento/campo opcional), **árbitros** (já há `game_officials`), **posição** do atleta, **mídia/transmissão** do jogo
(URL), **notícias** simples por edição, endereço/mapa do local. Todo dado novo respeita: edição publicada, `PublicName`, nada de CPF/documentos/disciplina
sem interruptor explícito da organização (`portal.show_cards`).

## 3. Central de ajuda (ADR 0006) — operadores, gestores e administradores
Busca textual primeiro; resposta com IA (RAG) depois, opcional. Conteúdo versionado no repositório.

## 4. Documentação interna das APIs (ADR 0007) — só Super Admin

## 5. Perfis, privilégios e módulos totalmente parametrizáveis (ADR 0005)

## Ordem sugerida
1. (feito) sheet_import sob demanda + ADRs 0005/0006/0007 + este roadmap.
2. Perfis e permissões, partes 1 e 2 (catálogo, perfis e tela de perfis; feitas) — **base para ajuda, API e leitura de súmulas por perfil**.
3. Leitura de súmulas, parte 2 (leitor estruturado + visão + configuração) e parte 3 (conferência).
4. Portal rico: dados novos (assistências, mídia, notícias) e as telas, uma tela por PR.
5. Central de ajuda: busca e artigos (feita); ajuda contextual, "isso ajudou?" e notas da organização (a fazer).
6. Documentação interna das APIs: gerador, tela e API pública (feitos); rotas internas em volume (a fazer).
7. Perfis, partes 2–3 (tela de perfis e exceções por usuário).
