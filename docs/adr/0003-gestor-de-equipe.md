# ADR 0003 — Gestor de equipe: login, painel próprio, escalação e esquema tático

Status: aceito (implementação em partes; ver "Entrega em partes")
Data: 13/10/2026

## Contexto

Hoje a equipe só tem o **link pessoal** da inscrição (`/minha-inscricao/{token}`): sem login, trava ao enviar e perde a utilidade
depois da aprovação. Os papéis existentes (`Role`: Administrador, Organizador, Operador, Consulta) são da **organização**: quem
os tem enxerga o painel inteiro (inclusive disciplina e dados de todas as equipes). Nada disso serve a quem representa **uma**
equipe e precisa, no dia a dia, definir a escalação de cada jogo, escolher o esquema tático e acompanhar o próprio elenco.

Requisitos: o gestor tem **login e painel próprios**; define **escalação por partida** (titulares, reservas, comissão, capitão),
**esquema tático** (formação e posição de cada titular) e vê a situação da **própria** equipe; nunca enxerga dados internos de outra.

## Decisões

### 1. Identidade: mesmo usuário, novo papel e vínculo com a equipe
- Continua existindo um único `users` e um único login. Novo papel `Role::TeamManager` (`manager`, "Gestor de equipe") em
  `memberships`, **sem nenhuma `Ability` do painel** (`abilities()` vazio).
- Tabela nova `team_managers` (`organization_id`, `user_id`, `team_id`, único por usuário × equipe): o escopo do gestor é a **equipe**
  (e portanto todas as inscrições `Entry` dela, em qualquer edição). Um gestor pode ter várias equipes; uma equipe pode ter vários gestores.
- O papel vale por organização, como os demais. Um usuário só tem **um** papel por organização: quem já é da equipe interna
  (administrador, organizador...) não vira gestor na mesma organização (a tela de convite avisa).
- **Gestores não contam no `max_users`** do pacote (o limite é de equipe interna); o limite próprio fica para quando houver cobrança.

### 2. Dois painéis, uma porta
- O gestor entra pelo **mesmo `/login`**. `/dashboard` (destino padrão após o login) manda cada papel para o seu painel:
  gestor → `/gestor`; demais → `/painel`.
- `/painel` (`panel.member`) **recusa** o gestor (tela "sem acesso", 403): ele nunca cai nas telas do organizador, nem para consulta.
  O gestor, por sua vez, só entra em `/gestor` (`manager.member`: logado, verificado e papel `manager`).
- Rotas em `routes/manager.php` (prefixo `/gestor`, nome `manager.*`), controladores no módulo `Teams/Http/Controllers/Manager`.
  Termos e privacidade (`legal.accepted`) valem também para o gestor.
- Recurso novo do pacote: `Feature::TeamPortal` (`team_portal`, "Área do gestor de equipe"), nos pacotes Padrão e Completo,
  com o middleware `feature:team_portal` nas rotas do gestor (403 com aviso, como no painel).

### 3. Autorização: escopo por equipe, sempre no servidor
- `ManagedTeams` (Teams/Support) resolve as equipes do usuário. **Toda** rota do gestor que recebe `{entry}`, `{game}`, `{team}`...
  confere se o registro pertence a uma equipe dele (`authorizeEntry`/`authorizeGame`; o jogo precisa ter a inscrição do gestor
  como mandante ou visitante) e responde **404** (não 403) fora do escopo, para não revelar a existência.
- O que o gestor **vê**:
  - da própria equipe: elenco ativo (nome, apelido, camisa, função, idade, foto por URL assinada), situação disciplinar (cartões,
    suspensões, pendurados), jogos, escalações, carteirinhas (quando o recurso existe);
  - do adversário: só dados públicos do jogo (equipe, data, local), e a escalação dele **somente depois de publicada** (ver 6);
  - **nunca**: CPF/`document_hash`, documentos, dados de outras equipes, súmula completa, cartões do adversário.
- O que ele **faz**: escalação e esquema tático dos jogos da sua equipe (antes do prazo), edição de dados esportivos básicos
  (apelido, camisa) do próprio elenco (parte 3) e solicitações de alteração do elenco (parte 3). Ele **não** opera a súmula nem muda
  placar, jogos, regulamento ou inscrições.

### 4. Convite (sem senha repassada)
- Quem tem `manage-registry` convida em Painel > Equipes > (equipe) > "Gestores": nome + e-mail. Se o e-mail não existe, cria o
  usuário (e-mail verificado, senha aleatória descartável) e envia o **convite por e-mail** com o link de definição de senha
  (broker de redefinição, `password.reset`, calculado **na requisição** — regra do link público; validade configurável, 24 h).
  Se já existe, só ganha o vínculo/papel (e recebe um aviso). Remover o vínculo é imediato; sem vínculos restantes o papel `manager`
  é removido da organização.
- Na aprovação de uma inscrição (`ReviewRegistration::approve`) o painel oferece "convidar o responsável como gestor" com os
  dados do pedido (nome/e-mail do responsável), sempre por ação explícita do organizador.

### 5. Escalação por partida (modelo de dados)
- `game_lineups` continua sendo a **única** fonte da escalação (a súmula, o overlay e `BuildGameSheet` já a leem). Ganha as colunas
  `position` (código da posição na formação, ex.: `gk`, `lb`; nulo para reserva/comissão), `sort` (ordem no banco / na formação) e
  `is_captain`.
- Tabela nova `game_tactics` (`game_id` × `entry_id` único; `formation` = código; `notes` = instruções **privadas** da equipe,
  texto curto; `confirmed_at`, `confirmed_by`, `updated_by`). Instruções privadas nunca vão ao snapshot público, ao overlay ou ao adversário.
- Ação nova `SubmitTeamLineup` (Matches) = validação de formação/posições/capitão + `SaveGameLineup` (todas as regras já existentes:
  elenco ativo, limites do regulamento, sem suspensos, sem repetição, trava após lançamentos) + gravação de `game_tactics`, numa
  transação. `SaveGameLineup` passa a **preservar** posição/capitão de quem continua titular quando o mesário salva só os ids.
- Janela de edição do gestor: jogo `scheduled` (não iniciado, sem lançamentos) e antes do **prazo** = `scheduled_at` menos
  `lineup.manager_deadline_minutes` do regulamento (padrão 30; 0 = até o horário; jogo sem horário = até começar).
  Depois disso o gestor só **vê**; o mesário/organizador continua podendo ajustar pela súmula até o primeiro lançamento (inalterado).
- Salvar rascunho é permitido (escalação parcial, desde que válida); "Confirmar escalação" grava `confirmed_at`. Qualquer alteração
  depois da confirmação a limpa (volta a rascunho) — o organizador e o mesário veem, na lista de jogos e na súmula, quem já confirmou.

### 6. Formações (esquema tático)
- Pacote puro `Rules/Tactics` (sem banco): `Formation` (código, rótulo, esporte, **vagas** com `key`, rótulo, sigla, e coordenadas
  `x`/`y` de 0 a 100 para desenhar o campo/quadra) e `Formations` (registro por esporte), com testes. Futebol 4-4-2, 4-3-3, 4-2-3-1,
  3-5-2, 3-4-3, 5-3-2, 4-5-1, 4-1-4-1; futsal 1-2-1 (losango), 2-2, 3-1, 1-1-2, 4-0 (fixo + linha); basquete (5 posições:
  armador, ala-armador, ala, ala-pivô, pivô); vôlei (rodízio 5-1, 6-2 e 4-2 pelas 6 posições da quadra + líbero).
- Cada titular ocupa **uma** vaga distinta da formação; menos titulares que vagas é permitido (rascunho / equipe incompleta).
  Posição **não é regra de jogo** (o servidor não impede um zagueiro na ponta): é informação tática. Só a consistência (vaga
  existente, sem repetição, formação do esporte) é validada.
- Capitão: um atleta titular ou reserva (o regulamento do esporte pode exigir; hoje apenas validamos que é da escalação).
- A formação, a posição e o capitão aparecem na súmula (atalho), no **overlay de escalação** (formação, sigla da posição, "C") e,
  publicados, na página pública do jogo **depois que o jogo começa**. Dados pessoais e instruções privadas, nunca.

### 7. LGPD
- Menores: o gestor só vê elenco da **própria** equipe, com foto por URL assinada de curta duração e sem CPF/documentos. Nomes do
  **adversário** passam por `PublicName` (menor = primeiro nome + inicial).
- Auditoria: `game_tactics.updated_by/confirmed_by` e o log do Laravel guardam quem mexeu; nenhum IP é armazenado.
- O vínculo do gestor é removível a qualquer momento pelo organizador; remover o usuário da organização apaga os vínculos.

### 8. Fora do escopo desta decisão
Chat/avisos por e-mail ao gestor (mudança de horário, escalação do adversário), pagamento, substituições planejadas no pré-jogo,
envio de documentos pelo gestor depois da aprovação (usa nova solicitação de elenco, parte 3) e app nativo.

## Entrega em partes
1. **Parte 1 — acesso e painel**: papel, vínculo, convite, `/gestor` (início, minha equipe/elenco, jogos, disciplina), isolamento por equipe, testes.
2. **Parte 2 — escalação e esquema tático**: `Rules/Tactics`, migração, `SubmitTeamLineup`, editor de campo/quadra, prazo, confirmação,
   exibição na súmula e no overlay.
3. **Parte 3 — elenco**: dados esportivos pelo próprio gestor (camisa, apelido) e solicitações de inclusão/saída de atletas com
   aprovação do organizador (`roster_change_requests`), convite pela aprovação da inscrição, carteirinhas do próprio elenco.

## Consequências
- Rotas, middleware e páginas novas; `panel.member` passa a recusar o papel `manager`.
- `Role::TeamManager` não dá `Ability` nenhuma: nada do painel do organizador se abre por engano.
- `game_lineups` ganha colunas (retrocompatível: nulas); `SaveGameLineup` continua sendo o ponto único de regras da escalação.
- Testes obrigatórios de isolamento (gestor A não acessa equipe/jogo/elenco da equipe B) em cada rota nova.
