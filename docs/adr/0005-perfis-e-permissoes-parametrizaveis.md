# ADR 0005 — Perfis de acesso e permissões totalmente parametrizáveis

Status: proposto (implementação em partes)
Data: 13/10/2026

## Contexto

Hoje o acesso é decidido por `Role` (enum fixo: Administrador, Organizador, Operador, Consulta, Gestor de equipe) → `Ability`
(`manage-competitions`, `manage-registry`, `operate-games`, `manage-users`) em `Tenancy/Enums/Role.php`. Um usuário tem **um** papel por
organização (`memberships`). Isso não permite, por exemplo, "operador que também aprova inscrições, mas não vê relatórios", nem que
cada organização monte seus próprios perfis. O pedido é: **usuário × perfil × acesso a módulos totalmente parametrizável**.

## Decisões

### 1. Catálogo de permissões em código (o que existe)
- `Permission` (registro em `Tenancy/Access`): chave `modulo.acao` com rótulo, grupo (módulo) e recurso exigido (`Feature`), p. ex.
  `competitions.view|manage`, `registry.view|manage`, `games.view|operate`, `registrations.review`, `reports.view`, `cards.manage`,
  `broadcast.view|operate|manage`, `communications.manage`, `portal.manage`, `legal.manage`, `users.manage`, `profiles.manage`,
  `sheet_import.use|configure`, `help.manage`. **Módulo novo = entradas novas no catálogo** (teste de arquitetura impede rota de
  painel sem permissão declarada).
- Permissão **não libera** recurso fora do pacote: continua valendo `feature:` + permissão (as duas).

### 2. Perfis por organização (o que cada um pode)
- `access_profiles` (`organization_id`, `name`, `slug`, `description`, `is_system`) e `access_profile_permissions`
  (`profile_id`, `permission`). Perfis de sistema espelham os papéis atuais (Administrador, Organizador, Operador, Consulta) e **não
  podem ser apagados** (podem ser duplicados e ajustados). Organização cria quantos perfis quiser.
- `memberships.access_profile_id` aponta o perfil; sem perfil, vale o `Role` legado (compatibilidade e migração sem corte).

### 3. Exceções por usuário
- `membership_permissions` (`membership_id`, `permission`, `effect` = `allow|deny`). Efetivas = permissões do perfil + `allow` − `deny`
  (**negar vence**). O painel mostra de onde veio cada permissão (perfil / exceção).

### 4. Aplicação
- As `Ability` atuais viram **derivadas**: `Gate::allows('manage-competitions')` = tem `competitions.manage`; o mapeamento fica numa
  tabela única (compat), e o novo código usa a permissão fina (`can('games.operate')`). Middleware `panel.can:` passa a aceitar as duas.
- Front: prop `auth.permissions` (lista) + `useCan('games.operate')`; o front só esconde, o servidor decide.
- **Guardas:** a organização nunca fica sem alguém com `users.manage` + `profiles.manage`; ninguém remove a própria última permissão de
  administração; super admin da plataforma não depende de perfil. Gestor de equipe (`team_managers`) segue escopado por equipe e fora do
  painel `/painel`.
- **Auditoria:** `access_audit` (quem mudou perfil/exceção, de/para, quando).

### 5. Telas
- Painel > Perfis de acesso (matriz módulo × ação, duplicar, descrever) e Painel > Usuários (perfil + exceções + "o que este usuário
  pode fazer"). Só quem tem `profiles.manage` / `users.manage`.

## Entrega em partes
1. Catálogo `Permission`, perfis de sistema migrados, `memberships.access_profile_id`, Gate compatível — **sem UI nova**, zero mudança de comportamento.
2. Tela de perfis (CRUD + matriz) e escolha do perfil em Usuários.
3. Exceções por usuário + auditoria + "permissões efetivas" — **feita** (`membership_permissions` e `access_audit`; a auditoria guarda o nome e um resumo em texto, sem IP; as exceções permanecem ao trocar o papel/perfil).
4. (Opcional) escopo por competição/edição.

## Consequências
- Mais superfície de segurança: testes Pest cobrem a matriz (perfil × rota) e as guardas; nenhuma rota nova sem permissão.
- Migração reversível: enquanto `access_profile_id` for nulo vale o `Role`.
