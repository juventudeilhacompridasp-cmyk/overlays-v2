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
- O que ele **faz**: escalação e esquema tático dos jogos da sua equipe (antes do prazo), dados esportivos básicos do próprio elenco
  (apelido, camisa), desligamento e pedidos de reforço (parte 3). Ele **não** opera a súmula nem muda placar, jogos, regulamento ou
  inscrições.

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
  depois da confirmação a limpa (volta a rascunho): quem muda **quem joga** (o mesário pela súmula, por exemplo) desfaz a confirmação; reordenar ou mudar só a posição, não. O gestor vê a situação (`confirmed`/`draft`/`missing`) na lista de jogos e o mesário, na súmula.

### 6. Formações (esquema tático)
- Pacote puro `Rules/Tactics` (sem banco): `Formation` (código, rótulo, esporte, **vagas** com `key`, rótulo, sigla, e coordenadas
  `x`/`y` de 0 a 100 para desenhar o campo/quadra) e `Formations` (registro por esporte), com testes. Futebol 4-4-2, 4-3-3, 4-2-3-1,
  3-5-2, 3-4-3, 5-3-2, 4-5-1, 4-1-4-1; futsal 1-2-1 (losango), 2-2, 3-1, 1-1-2, 4-0 (fixo + linha); basquete (5 posições:
  armador, ala-armador, ala, ala-pivô, pivô); vôlei (rodízio 5-1, 6-2 e 4-2 pelas 6 posições da quadra + líbero).
- Cada titular ocupa **uma** vaga distinta da formação; menos titulares que vagas é permitido (rascunho / equipe incompleta).
  Posição **não é regra de jogo** (o servidor não impede um zagueiro na ponta): é informação tática. Só a consistência (vaga
  existente, sem repetição, formação do esporte) é validada.
- Capitão: um atleta titular ou reserva (o regulamento do esporte pode exigir; hoje apenas validamos que é da escalação).
- A formação e a confirmação aparecem na súmula e a formação, a sigla da posição e o capitão, no **overlay de escalação**. A página
  pública do jogo **não** mostra escalação (decisão mantida: o snapshot público continua só com placar, relógio e gols). O adversário
  vê a escalação do outro lado só depois que o jogo começa, com `PublicName`. Instruções privadas e dados pessoais, nunca.

### 7. LGPD
- Menores: o gestor só vê elenco da **própria** equipe, com foto por URL assinada de curta duração e sem CPF/documentos. Nomes do
  **adversário** passam por `PublicName` (menor = primeiro nome + inicial).
- Auditoria: `game_tactics.updated_by/confirmed_by` e o log do Laravel guardam quem mexeu; nenhum IP é armazenado.
- O vínculo do gestor é removível a qualquer momento pelo organizador; remover o usuário da organização apaga os vínculos.

### 8. Fora do escopo desta decisão
Avisos por push/WhatsApp ao gestor, escalação do adversário por e-mail, pagamento, substituições planejadas no pré-jogo,
envio de documentos pelo gestor depois da aprovação (usa nova solicitação de elenco, parte 3) e app nativo.

## Entrega em partes
1. **Parte 1 — acesso e painel**: papel, vínculo, convite, `/gestor` (início, minha equipe/elenco, jogos, disciplina), isolamento por equipe, testes.
2. **Parte 2 — escalação e esquema tático**: `Rules/Tactics`, migração, `SubmitTeamLineup`, editor de campo/quadra, prazo, confirmação,
   exibição na súmula e no overlay.
3. **Parte 3 — elenco** (entregue): dentro da janela de alterações da edição (`registration.roster_changes` e `roster_changes_until`, na
   regra de inscrição da edição; edição encerrada fecha tudo):
   - **camisa e apelido** pelo próprio gestor, com as mesmas regras do cadastro do painel (camisa única entre atletas ativos; o apelido
     só muda se a pessoa não estiver ativa em equipe de outro gestor), e **desligamento** imediato (sai também das escalações dos jogos
     ainda não iniciados, desfazendo a confirmação; quem tem histórico de jogos fica como "removido"). Tudo vai para `roster_changes`
     (histórico visível ao gestor). *Ajuste em relação ao texto inicial:* a saída NÃO precisa de aprovação, porque não cria elegibilidade
     nova; o que cria — a **inclusão** — continua dependendo do organizador;
   - **inclusão** = **pedido de reforço**: um `Registration` com `kind = supplement` ligado à inscrição existente (`entry_id`), pelo mesmo
     fluxo da inscrição online (cadastro, CPF só em HMAC, documentos em `s3-private`, autorização do responsável de menores, análise
     pessoa a pessoa). Aprovar inclui as pessoas aprovadas no elenco existente via `AddToRoster` (nada de equipe/inscrição nova); o teto de
     atletas vale para o elenco inteiro. O gestor abre o pedido pela área dele e preenche no link do pedido (exige o recurso `online_registration`);
   - **convite do responsável** na aprovação de uma inscrição (botão explícito do organizador) e **carteirinhas** do próprio elenco
     (recurso `athlete_cards`, sem renovar códigos).

4. **Parte 4 — avisos por e-mail** (entregue): lembrete de escalação pendente (24 h e 2 h antes do prazo), mudança de horário/local/situação
   do jogo e suspensões após cada jogo, uma única vez por pessoa e aviso, com opção de desligar. O adversário e dados de outras equipes
   nunca entram nos e-mails. Push/WhatsApp para o gestor ficam para depois.

## Consequências
- Rotas, middleware e páginas novas; `panel.member` passa a recusar o papel `manager`.
- `Role::TeamManager` não dá `Ability` nenhuma: nada do painel do organizador se abre por engano.
- `game_lineups` ganha colunas (retrocompatível: nulas); `SaveGameLineup` continua sendo o ponto único de regras da escalação.
- Testes obrigatórios de isolamento (gestor A não acessa equipe/jogo/elenco da equipe B) em cada rota nova.
