<?php

/*
| Descrições das rotas de SITE: portal público, status page, landing page, inscrição online, cadastro do Mercado e
| autenticação/conta (ADR 0007, parte 4). São páginas HTML e formulários, não API estável: o acesso (pública, sessão,
| token na URL, recurso do pacote, limite) sai das próprias rotas, então aqui só entra o que cada uma faz. Indexado
| pelo NOME da rota, como o catálogo principal.
*/

$page = fn (string $summary, ?string $description = null) => array_filter([
    'summary' => $summary,
    'description' => $description,
    'responses' => ['200' => ['description' => 'Página (HTML; no portal, renderizada no servidor).'], '404' => ['description' => 'Registro inexistente, edição em rascunho ou recurso fora do pacote.']],
]);

$file = fn (string $summary, string $type, ?string $description = null) => array_filter([
    'summary' => $summary,
    'description' => $description,
    'responses' => ['200' => ['description' => "Arquivo ({$type})."], '404' => ['description' => 'Registro inexistente, edição em rascunho ou recurso fora do pacote.']],
]);

$form = fn (string $summary, ?string $description = null) => array_filter([
    'summary' => $summary,
    'description' => $description,
    'responses' => ['302' => ['description' => 'Redireciona de volta com o aviso `success` ou os erros por campo.'], '404' => ['description' => 'Registro inexistente ou recurso fora do pacote.'], '422' => ['description' => 'Dados inválidos (erros por campo).'], '429' => ['description' => 'Limite de requisições por minuto excedido.']],
]);

return [
    // ───────────── Portal público ─────────────
    'portal.home' => $page('Página inicial do portal: jogos ao vivo, próximos, recentes e competições', 'Os blocos podem ser desligados pela organização (Painel > Portal público). Só edições publicadas.'),
    'portal.competition' => $page('Competição: histórico de edições, campeões, títulos e recordes', 'Com `competition_page` desligada redireciona para a edição publicada mais recente.'),
    'portal.season' => $page('Edição: tabela, jogos, chaveamento, regulamento e artilharia', 'Edição em rascunho é 404.'),
    'portal.stats' => $page('Estatísticas da edição (ataque, defesa, forma, maiores resultados)', 'Nunca traz cartões, disciplina ou atletas. 404 com `season_stats` desligada.'),
    'portal.venue' => $page('Local (estádio): endereço e jogos de edições publicadas'),
    'portal.athlete' => $page('Perfil público do atleta (só com interruptor ligado, autorização no cadastro e maioridade)', 'Nome público, equipes, edições, camisa, jogos e gols. Nunca foto, nascimento, documento ou cartões. 404 sem o interruptor, sem autorização, para menores e para edição em rascunho; `noindex`.'),
    'portal.team' => $page('Equipe: campanhas, forma, artilheiros e (se habilitado) elenco', 'Nomes de atletas passam por `PublicName` (menores abreviados).'),
    'portal.team.calendar' => $file('Calendário `.ics` dos jogos da equipe (RFC 5545)', 'text/calendar', 'UTC, 120 min por jogo, sem cancelados; 404 sem o interruptor `team_calendar`.'),
    'portal.search' => $page('Busca por competições e equipes (`?q=`, mínimo 2 letras)', 'Nunca acha atletas; `noindex`.'),
    'portal.feed' => $file('Feed Atom com os 30 resultados mais recentes', 'application/atom+xml'),
    'portal.sitemap' => $file('Mapa do site para buscadores', 'application/xml'),
    'portal.robots' => $file('robots.txt', 'text/plain'),
    'portal.card' => $page('Verificação pública de uma carteirinha de atleta', 'Mostra só nome público, função, camisa, equipe, edição e se vale; nunca foto, CPF ou nascimento. `no-store` e `noindex`.'),
    'portal.terms' => $page('Termos de uso da organização (versão publicada ou texto-modelo)'),
    'portal.privacy' => $page('Política de privacidade da organização (versão publicada ou texto-modelo)'),
    'public.games.show' => $page('Página do jogo ao vivo (Reverb, com polling de contingência)'),
    'public.games.image' => $file('Imagem de compartilhamento do jogo (PNG 1200 x 630)', 'image/png', 'Gerada só com dados públicos; cache de 60 s.'),

    // ───────────── Inscrição online ─────────────
    'registration.apply' => $page('Formulário do pedido de inscrição da equipe', 'Só edição publicada, com status "Inscrições" e dentro do prazo.'),
    'registration.store' => $form('Abre o pedido de inscrição e envia o link pessoal por e-mail', 'O link com o token é o único acesso da equipe ao pedido.'),
    'registration.manage' => $page('Pedido de inscrição pelo link pessoal (`noindex`)', 'Pedidos enviados, aprovados ou recusados ficam só para leitura.'),
    'registration.team' => $form('Atualiza os dados da equipe no pedido'),
    'registration.people.store' => $form('Cadastra atleta ou comissão no pedido', 'O CPF só é guardado como HMAC; menor exige responsável e autorização.'),
    'registration.people.update' => $form('Atualiza uma pessoa do pedido'),
    'registration.people.destroy' => $form('Remove uma pessoa do pedido'),
    'registration.documents.store' => $form('Envia um documento da pessoa', 'Disco privado, só por URL assinada.'),
    'registration.documents.destroy' => $form('Remove um documento da pessoa'),
    'registration.submit' => $form('Envia o pedido para análise', 'Confere mínimos/máximos de atletas e documentos exigidos pela edição.'),

    // ───────────── Landing page e status page ─────────────
    'marketing.landing' => $page('Página do produto (landing page): recursos, pacotes, galeria e contato'),
    'marketing.contact' => $form('Envia um contato pela landing page', 'Exige o aceite da privacidade; campo-armadilha `website` deve vir vazio. Sem IP nem telefone.'),
    'marketing.metrics' => [
        'summary' => 'Registra contagens anônimas de acesso à landing page (só com consentimento)',
        'description' => 'Aceita apenas métricas de uma lista fechada; sem IP, cookie de rastreio ou URL. 404 com a medição desligada.',
        'responses' => ['204' => ['description' => 'Contagens registradas.'], '404' => ['description' => 'Medição desligada.'], '429' => ['description' => 'Limite de requisições por minuto excedido.']],
    ],
    'csp.report' => [
        'summary' => 'Recebe relatórios de violação da Content-Security-Policy (enviados pelo navegador)',
        'description' => 'Sem login e sem CSRF. Corpo limitado a 8 KB; só diretiva, origem bloqueada e página (sem query string) vão ao log. Nada é devolvido.',
        'responses' => ['204' => ['description' => 'Relatório recebido (ou ignorado).'], '429' => ['description' => 'Limite de requisições por minuto excedido.']],
    ],
    'status.index' => $page('Status page da plataforma (atualiza sozinha a cada 30 s)'),
    'status.json' => [
        'summary' => 'Situação da plataforma em JSON (componentes, incidentes e manutenções)',
        'description' => 'Cache de 30 s; CORS aberto. Só dados técnicos e globais, sem organização ou dado pessoal.',
        'responses' => ['200' => ['description' => 'Situação geral, componentes com uptime de 90 dias, incidentes dos últimos 14 dias e manutenções pendentes.']],
    ],
    'status.atom' => $file('Feed Atom dos incidentes', 'application/atom+xml'),
    'status.badge' => $file('Selo SVG com a situação ("status | operacional")', 'image/svg+xml', 'Cache de 60 s; CORS aberto.'),
    'status.subscribe' => $form('Pede o aviso por e-mail (dupla confirmação)', 'A resposta é sempre a mesma, nunca revela se o e-mail já existe.'),
    'status.confirm' => $page('Confirma a inscrição nos avisos de status'),
    'status.unsubscribe.show' => $page('Mostra o botão de cancelar os avisos (cancelamento em duas etapas)'),
    'status.unsubscribe' => $form('Cancela a inscrição nos avisos'),
    'status.preferences.show' => $page('Escolha dos componentes acompanhados (só quem confirmou)'),
    'status.preferences' => $form('Salva os componentes acompanhados (mínimo um)'),

    // ───────────── Mercado (cadastro público) ─────────────
    'market.signup' => $page('Cadastro do atleta no Mercado de jogadores livres', 'Recurso sob demanda `market`; só visitantes sem login; `noindex`.'),
    'market.signup.store' => $form('Cria a conta do atleta e o perfil em rascunho', 'Menores de 18 anos são recusados; grava o aceite dos termos.'),

    // ───────────── Autenticação e conta ─────────────
    'login' => $page('Tela de entrada'),
    'register' => $page('Cadastro (fechado, exceto o primeiro usuário de uma instalação nova)'),
    'password.request' => $page('Pedido de redefinição de senha'),
    'password.email' => $form('Envia o link de redefinição de senha por e-mail'),
    'password.reset' => $page('Formulário de nova senha (link do e-mail)'),
    'password.store' => $form('Grava a nova senha'),
    'verification.notice' => $page('Aviso de e-mail ainda não verificado'),
    'verification.verify' => $form('Verifica o e-mail pelo link assinado'),
    'verification.send' => $form('Reenvia o e-mail de verificação'),
    'password.confirm' => $page('Confirmação da senha antes de uma ação sensível'),
    'password.update' => $form('Troca a senha da conta'),
    'logout' => $form('Encerra a sessão'),
    'dashboard' => [
        'summary' => 'Leva cada usuário ao painel do seu papel',
        'description' => 'Painel do organizador, do gestor de equipe ou do atleta.',
        'responses' => ['302' => ['description' => 'Redireciona para o painel correspondente.']],
    ],
    'profile.edit' => $page('Perfil da conta'),
    'profile.update' => $form('Atualiza nome e e-mail da conta'),
    'two-factor.challenge' => $page('Tela do código da autenticação em dois fatores (depois da senha)', 'Só existe com a verificação pendente na sessão (10 minutos); sem ela volta ao login.'),
    'two-factor.verify' => $form('Confere o código do aplicativo ou um código de recuperação e abre a sessão', 'Cada código do aplicativo vale uma vez; 5 erros por minuto bloqueiam; cada código de recuperação vale uma vez.'),
    'profile.two-factor.begin' => $form('Gera o segredo da autenticação em dois fatores (exige a senha da conta)', 'Ainda sem efeito no login até a confirmação.'),
    'profile.two-factor.confirm' => $form('Confirma o primeiro código do aplicativo, ativa e mostra os códigos de recuperação uma única vez'),
    'profile.two-factor.recovery' => $form('Gera novos códigos de recuperação (exige a senha); os antigos deixam de valer'),
    'profile.two-factor.disable' => $form('Desativa a autenticação em dois fatores (exige a senha)'),
    'profile.destroy' => $form('Apaga a conta'),
    'legal.accept.show' => $page('Aceite dos termos e da privacidade vigentes'),
    'legal.accept.store' => $form('Registra o aceite da versão mais recente'),
];
