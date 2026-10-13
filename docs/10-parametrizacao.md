# Revisão de parametrização (o que hoje é fixo no código e deveria ser configurável)

Levantamento feito no código em 13/10/2026. **Regra do projeto:** regras de competição, comunicações e liberação de
funcionalidades são parâmetros, não constantes. Este documento lista o que ainda está fixo, onde, em que nível deveria
morar a configuração e a ordem sugerida. Marque `[x]` ao entregar cada item (e atualize `docs/09`).

## Níveis de configuração (onde cada coisa deve morar)
1. **Plataforma** (`config/platform.php` + variáveis de ambiente; só quem administra a instalação): padrões globais e
   limites de segurança (throttles, TTLs de link assinado).
2. **Organização** (`organizations.settings`, editável em Painel por administrador; recursos/limites em `/plataforma`):
   comunicações, identidade, janelas do app do mesário, o que o portal público mostra.
3. **Edição/divisão** (`seasons.registration`, `seasons.rules`, regulamento): regras da disputa e da inscrição.
4. **Pessoa** (preferências do usuário, ex.: `team_managers.notify`): receber ou não cada tipo de aviso.

Padrão de implementação sugerido: um objeto de leitura por área (como `PublicTheme`/`LegalDocuments::settings()`), com
padrões no código, valor salvo sobrescrevendo, validação em `FormRequest` e tela em Painel > Configurações. Chave nova de
configuração = teste do padrão + teste do valor salvo.

## 1. Comunicações (prioridade alta)
| Hoje | Onde | Proposta |
|---|---|---|
| Lembrete de escalação ao gestor: limiares 24 h e 2 h fixos; janela de busca de 4 dias fixa; roda a cada 10 min fixo | `Teams/Support/LineupReminders` (`DAY`, `SOON`, `addDays(4)`), `routes/console.php` | Organização: ligar/desligar o lembrete e escolher os limiares (lista de minutos antes do prazo). |
| Avisos ao gestor (mudança de horário/local, adiado/cancelado, suspensões): sempre enviados se o recurso existe | `Teams/Support/GameNotices`, `ManagerNotifier` | Organização: um interruptor por tipo (`lineup`, `game_change`, `suspension`). Pessoa: hoje há só um liga/desliga geral (`team_managers.notify`); evoluir para por tipo. |
| E-mails da inscrição online (aberta, aprovada, devolvida, recusada): sempre enviados | `Registration/Http/Controllers/ApplyController` e `ReviewController` (`Mail::to(...)->queue`) | Organização: interruptor por tipo e texto de apresentação/assinatura opcional; remetente/responder-para (`reply-to`) da organização. |
| Convite do gestor: sempre envia e-mail; validade do link 24 h por variável de ambiente | `Teams/Actions/InviteTeamManager`, `AUTH_PASSWORD_RESET_EXPIRE` | Organização: opção "criar o acesso sem enviar e-mail" (o administrador repassa o link); validade em horas. |
| Push de jogo: dispara em início, placar e fim; no basquete só início/fim, sempre | `Portal/Push/GamePushNotifier` | Organização: escolher quais eventos geram push (início, gol/set, fim) e se vale para equipes seguidas. |
| Remetente e nome nos e-mails: apenas `MAIL_FROM_*` global | `config/mail.php` | Organização: nome de exibição (e `reply-to`) por organização, mantendo o remetente técnico da plataforma. |
| Textos dos e-mails fixos em português | `RegistrationMail`, `ManagerNotice`, `TeamManagerInvitation` | Organização: trechos editáveis (assunto/introdução/rodapé) com variáveis simples; nunca HTML livre. |

## 2. Ativar/desativar funcionalidades (prioridade alta)
- Recursos por pacote já existem (`Feature`), mas **só a plataforma liga/desliga**. Falta, dentro de um recurso liberado,
  a organização escolher usar ou não: ex.: carteirinha, push, widgets, portal do gestor. Proposta: `settings.features_enabled`
  (lista) que só pode **reduzir** o que o pacote permite; `CurrentOrganization::has()` considera os dois.
- Partes do portal público fixas: seções da home (ao vivo, próximos, recentes, competições), página de estatísticas,
  botões de compartilhar/push, link da API pública, rodapé. Proposta: `settings.portal.sections` com interruptores.
- API pública `/api/v1/*`: sempre ligada. Proposta: interruptor por organização (e, depois, chaves de acesso).
- Cadastro público de usuário: `OPEN_REGISTRATION` global (ok); manter.
- Tela do mesário: resultado rápido sempre disponível onde o esporte permite. Proposta: por edição, permitir ou bloquear o
  resultado rápido (obrigar súmula completa).
- Fila offline da súmula: sempre ligada. Proposta: interruptor por organização (para suporte).

## 3. Janelas, limites e números fixos (prioridade média)
| Hoje | Onde | Proposta |
|---|---|---|
| Mesário: jogos de hoje + próximos 2 dias + encerrados nas últimas 6 h; limite de 40 cartões | `MesarioController` (`addDays(2)`, `subHours(6)`, `subHours(3)`, `LIMIT`) | Organização: dias à frente, horas de encerrados e limite. |
| Portal: 8 itens por bloco da home, 6 nos widgets, 10 artilheiros, estatísticas top 5 e forma dos últimos 5 | `PortalController::SHOWN`, `EmbedController::LIST`, `SeasonPresenter::SCORERS`, `SeasonStats::TOP/FORM` | Organização: quantidades; parâmetros de URL nos widgets (`?linhas=` já existe na classificação). |
| Links assinados de documento/foto: 10 min e 30 min | `ManageRegistrationDocuments` (10), `ImageStore` (30) | Plataforma (`config/platform.php`): TTL por tipo (segurança, não por organização). |
| Throttles (push 30/min, embeds 120/min, carteirinha 30/min, inscrição 10/min, API 60/min, estado do overlay 120/min) | `routes/web.php`, `routes/api.php` | Plataforma: valores em `config/platform.php` via ambiente. |
| Prazo de apagar pedidos de push de jogos encerrados (1 dia) e agendamentos (`push:prune` diário, lembretes a cada 10 min) | `routes/console.php`, `PruneWebPushSubscriptions` | Plataforma: dias de retenção; frequência por ambiente. |
| Tamanho máximo de imagem e variantes (40 Mpx, 512/128/400/96 px) | `ImageVariants` | Plataforma (já é política de armazenamento; só externalizar). |
| Cor padrão do portal | `PublicTheme::DEFAULT_COLOR` | Plataforma: padrão por ambiente (a organização já sobrescreve). |

## 4. Regras esportivas ainda fixas em perfil (prioridade média/baixa)
Já são parâmetros: pontos, desempates, cartões/suspensão, limites de súmula, formação, janela do gestor
(`lineup.manager_deadline_minutes`), janelas de elenco e inscrição por edição. Ainda fixos nos perfis (`SportProfiles`):
- Basquete: pedidos de tempo (2/3/1 por grupo), grupos de faltas de equipe, W.O. de 20-0, duração dos quartos.
- Futsal/futebol: duração dos tempos e da prorrogação, limite de faltas de equipe.
- Vôlei: limite de substituições por set, quantidade de sets e pontos (parte já está em `scoring.*`).
Proposta: mover o que o regulamento real costuma variar (duração, pedidos de tempo, faltas de equipe) para
`seasons.rules` com o perfil como padrão — usando o mecanismo que já existe (`RuleSet::fromArray($rules, $sportDefaults)`).

## 5. Ordem sugerida de entrega
1. Tela única **Painel > Comunicações** (organização): interruptores dos e-mails da inscrição, avisos do gestor, convite
   e eventos de push + limiares do lembrete de escalação. É o que mais gera suporte e o menor risco.
2. **Recursos ligáveis pela própria organização** (só reduzem o pacote) e seções do portal.
3. **Janelas e quantidades** do mesário e do portal.
4. **Perfis esportivos parametrizáveis por edição** (tempo, pedidos de tempo, faltas).
5. **Limites de segurança** em `config/platform.php` (throttles, TTLs, retenção).

## Cuidados
- Padrão novo = comportamento atual (nada muda para quem não mexer).
- Interruptor de comunicação desligado não pode quebrar dedupe nem fila (`manager_notifications`).
- LGPD: nada de texto livre HTML nos e-mails; variáveis só de dados já exibidos ao destinatário.
- Recurso/rota nova mantém `feature:` e `panel.can:`; a organização só reduz o que o pacote libera.
