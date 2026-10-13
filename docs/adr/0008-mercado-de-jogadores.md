# ADR 0008 — Mercado de jogadores livres

Status: aceito (implementação em partes; partes 1 a 3 entregues — ver "Entrega em partes")
Data: 13/10/2026

## Contexto

Hoje um atleta só existe no sistema quando uma equipe o inscreve (`Person` + `RosterEntry`). Quem está **sem time** e quer jogar não
tem onde se apresentar, e o gestor de equipe (ADR 0003) não tem onde procurar reforço fora do próprio círculo. Queremos um
**Mercado**: o jogador se cadastra, informa o que importa para a modalidade (posição, disponibilidade, foto, vídeos de jogadas) e o
gestor encontra e entra em contato.

## Decisões já tomadas (com o responsável pelo produto)

| # | Decisão | Resposta |
|---|---|---|
| 1 | Escopo do mercado | **Por organização.** Cada liga/endereço tem o seu mercado; nada cruza organizações. |
| 2 | Menores de 18 anos | **Não entram.** Trava por data de nascimento. **E-mail verificado é obrigatório.** |
| 3 | Vídeos | **Só link externo** (YouTube, Vimeo, Instagram), sem upload. |
| 4 | Visibilidade antes do aceite | O gestor **vê o perfil antes** do aceite (cartão sem contato). Telefone/e-mail só depois. |
| 5 | Pacote | **Recurso sob demanda** (`Feature::isOptIn()`): fora de todos os pacotes e desligado até o Super Admin liberar. |

## Decisões de desenho

### 1. Identidade e acesso
- O atleta é um `User` global com novo `Role::Athlete` (`athlete`, "Atleta") em `memberships`, **sem nenhuma `Ability`** do painel
  (mesmo padrão do `TeamManager`). Um usuário tem um papel por organização: quem já é equipe interna ou gestor naquela organização
  não vira atleta ali (a tela avisa).
- Painel próprio **`/atleta`** (`routes/athlete.php`, nomes `athlete.*`; middleware `athlete.member` + `legal.accepted` +
  `feature:market`). `panel.member` e `manager.member` recusam o papel; `/dashboard` o manda para `/atleta`.
- **Cadastro aberto só para este papel**, em `/mercado/cadastro` (no endereço da organização, só com o recurso ligado e 404 sem ele):
  nome, e-mail, senha, **data de nascimento (≥ 18 anos)** e aceite dos termos. O e-mail precisa ser **verificado** antes de
  qualquer perfil ser publicado (`MustVerifyEmail`; sem verificação o atleta edita rascunho, mas não publica). `OPEN_REGISTRATION`
  continua fechado para todos os outros papéis.
- Atletas **não contam no `max_users`** e não aparecem em Painel > Usuários (como o gestor).

### 2. Modelo de dados (módulo novo `Market`)
```
market_profiles          organization_id, user_id (único por organização), status
  status: draft | published | paused | signed | suspended
  sport, positions[], dominant_foot, height_cm, level, bio
  availability (json: dias × períodos), city, neighborhood
  display_name (apelido opcional), birth_date, contact_channel (email | whatsapp), contact_value (cifrado)
  published_at, expires_at (revalidação a cada 60 dias)
market_media             profile_id, kind (photo | video_link), path | url, sort
market_contact_requests  profile_id, team_id, requested_by, message, status, answered_at, expires_at
  status: pending | accepted | declined | withdrawn | expired
  único PENDENTE por (perfil × equipe); expira em 14 dias
market_blocks            profile_id, team_id           (o atleta bloqueia uma equipe)
market_reports           profile_id, reported_by, reason, status   (denúncia → moderação)
```
- **Posições, nível e atributos vêm do `SportProfile`** (`Rules/Sports`), nunca fixos no front: esporte novo = entrada no perfil + teste.
- **Não cria `Person` nem `RosterEntry`.** O mercado não polui a base de elenco; a pessoa só nasce quando for inscrita (ver 5).
- **Sem CPF no mercado.** Documento só entra no fluxo de inscrição existente (HMAC, `s3-private`).
- Telefone/e-mail de contato guardados **cifrados** (`encrypted` cast) e nunca devolvidos antes do aceite.

### 3. Mídia
- **Foto:** `ImageVariants` (WebP 400/96 px, descarta EXIF/GPS) em disco **`s3-private`**, por URL assinada de curta duração — não vai
  ao snapshot público nem ao portal. Base nova a cada envio, a anterior é apagada (`ImageStore`).
- **Vídeo:** só `video_link`; o servidor valida host numa lista permitida (`youtube.com`, `youtu.be`, `vimeo.com`, `instagram.com`),
  normaliza para a forma de embed e **nunca** baixa nem hospeda o arquivo. Máx. 3 vídeos. Sem script de terceiro na página:
  mostrar miniatura/link e carregar o `<iframe>` só ao clicar.

### 4. Quem vê o quê
- **Gestor de equipe** (recurso `team_portal` + `market`): `/gestor/mercado` lista perfis **publicados** da organização, com filtros
  (posição, cidade/bairro, disponibilidade, nível, faixa de idade). O cartão mostra: nome público (`PublicName`: apelido ou nome),
  foto, posições, faixa de idade, disponibilidade, bio e vídeos. **Nunca** data de nascimento exata, e-mail, telefone, CPF.
- **Contato** só aparece **depois do aceite do atleta**, e apenas o canal que ele escolheu.
- **Organizador** (`market.moderate`, permissão nova no catálogo junto com a rota): vê perfis, denúncias e pedidos em Painel > Mercado
  e pode suspender perfil. Não vê contato.
- **Portal público:** nada. Rotas do mercado respondem `noindex` e ficam fora do `robots`. Página pública opcional do atleta fica
  para depois, atrás de interruptor em `PortalSettings` (padrão desligado).
- Equipe de **outro gestor** nunca vê pedidos feitos por equipes concorrentes. Escopo por `ManagedTeams`, sempre com 404 fora dele.

### 5. Fluxo de contato e contratação
1. O gestor envia um **pedido de contato** (mensagem curta) a partir do cartão, **em nome de uma das suas equipes**.
2. O atleta recebe e-mail (só texto; link calculado na requisição com `OrganizationUrl::to`) e vê o pedido em `/atleta`.
3. Ele **aceita** (o gestor passa a ver o canal escolhido) ou **recusa**. Pode **bloquear** a equipe ou **denunciar**.
4. Combinado o jogo, o gestor abre o **pedido de reforço** (`StartSupplement`, ADR 0003); o atleta preenche CPF/documentos no link
   pessoal. Aprovado em `ReviewRegistration::approve`, o perfil vira `signed` e **sai do mercado** (contrato entre `Registration` e
   `Market` por evento, sem um módulo editar o outro).

Salvaguardas: máx. de pedidos por equipe por dia (padrão 10, parametrizável), um pendente por par perfil × equipe, pedido expira em 14
dias, atleta pode pausar o perfil a qualquer momento, perfil sem revalidação em 60 dias passa a `paused` (aviso 7 dias antes).

### 6. Encaixe nas regras do projeto
- `Feature::Market` (`market`) **sob demanda**: fica em `Feature::isOptIn()` e é liberado em `/plataforma`
  (`organizations.opt_in_features`). Toda rota nova com `feature:market` (público = 404, painel = 403 com aviso).
- `CommunicationSettings`: chaves `market_contact_request`, `market_contact_answered`, `market_profile_expiring` (+ checkbox em
  Painel > Comunicações e teste). Todo envio consulta `CommunicationSettings::enabled(...)`.
- Termos de uso/privacidade: o texto-modelo de `LegalDefaults` ganha o parágrafo do mercado (finalidade, visibilidade a gestores da
  organização, retirada a qualquer momento) e o cadastro grava o aceite (`legal_acceptances`).
- **Exclusão:** o atleta apaga o perfil e a conta quando quiser (apaga mídia, pedidos e bloqueios); `signed` mantém só o vínculo no
  elenco, que é de outra base legal.
- Ajuda: artigos novos em `resources/help/athlete`, `manager` e `panel` (o público `athlete` entra em `HelpLibrary`/`HelpAccess`).
- Testes Pest obrigatórios: menor recusado; publicar sem e-mail verificado recusado; contato oculto antes do aceite; isolamento por
  organização e por equipe (404); cota diária; expiração; bloqueio; foto só por URL assinada; link de vídeo fora da lista recusado;
  recurso desligado = 404/403; `signed` some da busca.

## Entrega em partes
1. **Parte 1 — perfil (entregue):** (a foto fica em `market_profiles.photo_path`; a tabela `market_media` só nasce com os vídeos, na parte 4) `Role::Athlete`, `/atleta`, cadastro com verificação de e-mail e trava de menor, perfil (posições do esporte,
   disponibilidade, região, bio), foto, publicar/pausar/revalidar, `Feature::Market` sob demanda.
2. **Parte 2 — busca do gestor (entregue):** `/gestor/mercado`, filtros e cartão do atleta (sem contato).
3. **Parte 3 — contato (entregue):** pedidos, aceite/recusa, e-mails, bloqueio, cota diária e expiração.
4. **Parte 4 — vídeos:** links validados e embed sob clique.
5. **Parte 5 — moderação e contratação:** Painel > Mercado (denúncias, suspender), ligação com o pedido de reforço (`signed`).
6. **Depois:** alertas ("novo goleiro na sua região"), página pública opcional, mercado entre organizações com opt-in, menores com
   responsável, upload de vídeo.

## Consequências
- Primeiro papel de cadastro público desde o início do projeto: atenção especial a abuso (rate limit no cadastro, e-mail verificado).
- Novo módulo `Market`; mudanças em `Tenancy` (enum `Role`, `Feature`), `Help` (público `athlete`) e `Registration` (evento ao aprovar).
- `panel.member`/`manager.member` passam a recusar `athlete`; testes de isolamento em cada rota nova.
