# 14 — Landing page do produto e status page (mapeamento)

Pedido de 14/10/2026: depois do Modo operador, **mapear** (não implementar ainda) uma landing page que mostre o produto e uma status page pública. Este documento é o ponto de partida; cada parte vira ADR + PRs pequenos antes de codar.

## 1. Landing page (site do produto)

**Objetivo:** apresentar a plataforma a organizadores de competições e transmissores, e levar a um contato/cadastro. Não é o portal público de uma organização.

### Onde mora
- Hoje `/` é o portal da organização resolvida pelo endereço (`ResolveOrganization`). A landing precisa de um **host próprio da plataforma** (ex.: `{PLATFORM_BASE_DOMAIN}` raiz e `www`), sem organização, para não competir com a organização padrão.
- Decisão a tomar (ADR): (a) host raiz = landing e a organização padrão passa a viver em subdomínio; ou (b) landing em rota `/produto` do host padrão enquanto a plataforma tem uma só organização. Recomendação: (a) com `RESERVED_SLUGS` já cobrindo `www`/`status`, e fallback (b) em ambientes de teste.
- Código: módulo novo `Marketing` (controladores + dados estáticos), páginas `resources/js/Pages/Marketing/*`, SSR + SEO (`SeoHead`, JSON-LD `SoftwareApplication`/`Organization`), `sitemap`, `robots`.

### Conteúdo (seções)
1. **Hero:** "Gestão de competições com overlays ao vivo para OBS", chamada para "Falar com a equipe"; vídeo/GIF curto do Modo operador.
2. **Overlays:** placar, lances, escalação (com esquema tático e patrocinadores), tabela, pênaltis, estatísticas, Builder livre — com prints reais gerados do próprio produto (os scripts de captura do harness servem de base).
3. **Modo operador:** layout redimensionável, atalhos, sem rolagem (print + vídeo curto).
4. **Competições:** futebol, futsal, vôlei, basquete; regulamento parametrizável; súmula com fila offline (PWA).
5. **Portal público e widgets**, **inscrição online**, **carteirinha**, **gestor de equipe**, **mercado de jogadores livres** (sob demanda).
6. **Pacotes (Básico, Padrão, Completo)**: tabela comparativa lida de `Plan`/`Feature` (fonte única — a landing nunca duplica a lista à mão); preço fica fora enquanto a cobrança automática estiver fora do MVP ("sob consulta").
7. **LGPD e segurança** (CPF só em HMAC, menores, fotos privadas), **FAQ**, **contato**.
8. Rodapé: links para `/termos`, `/privacidade` e **status**.

### Contato/captação
- Formulário simples (`marketing_leads`: nome, e-mail, organização, mensagem, aceite da privacidade; sem CPF), `throttle`, honeypot, e-mail à equipe (`CommunicationSettings` da plataforma). Nada de rastreadores de terceiros; métricas só agregadas se houver consentimento.

### Entregas sugeridas
- PR 1: ADR + módulo `Marketing`, página única com hero/overlays/pacotes/FAQ, SEO e testes (200 público, sem organização, `noindex` fora de produção).
- PR 2: formulário de contato + leads no painel da plataforma (`/plataforma/leads`).
- PR 3: galeria de prints/vídeos gerada por script versionado (`npm run marketing:shots`), imagens WebP otimizadas.
- Critérios: LCP < 2,5 s, sem JS bloqueante, acessível (contraste, foco), tema claro apenas.

## 2. Status page

**Objetivo:** transparência operacional para clientes (organizadores e operadores ao vivo): "o placar ao vivo e os overlays estão no ar?".

### Onde mora
- Host `status.{PLATFORM_BASE_DOMAIN}` (slug reservado) servido pela MESMA aplicação, mas idealmente **independente do que ela monitora**: a página precisa continuar abrindo quando banco/Redis/Reverb caem. Recomendação: página estática gerada (JSON + HTML) e publicada no bucket/CDN a cada minuto pelo comando de sondagem, com a rota dinâmica só como contingência.

### Componentes monitorados
| Componente | Como sondar |
|---|---|
| Painel e portal público | requisição HTTP a uma rota leve (`/up`) + tempo de resposta |
| API pública (`/api/v1`) | requisição a um endpoint público barato |
| Tempo real (Reverb) | `realtime:check` (evento de teste) — já existe |
| Overlays do OBS | `/overlay/{token-de-teste}/estado` de um canal sintético da plataforma |
| Filas (Horizon) | idade do job mais antigo / último heartbeat do scheduler |
| Armazenamento (S3/R2) | HEAD em objeto de teste |
| Avisos push | `push:check` (apenas quando o recurso está ativo) |
| E-mail | último envio de teste bem-sucedido (opcional) |

### Modelo de dados (proposta)
- `status_components` (chave, nome, ordem, visível), `status_checks` (componente, ok, latência, medido em; retenção 90 dias, agregada por hora depois de 7 dias), `status_incidents` (título, impacto `none|minor|major|maintenance`, estado `investigating|identified|monitoring|resolved`, componentes afetados, início/fim) e `status_incident_updates` (texto, estado, publicado em).
- Tabelas **globais da plataforma** (sem `organization_id`); nunca guardam dado de organização, atleta ou token real.

### Comportamento
- Comando agendado `status:probe` (a cada minuto) grava as medições e abre/resolve incidente automático após N falhas seguidas (limiar configurável); incidentes manuais e **manutenções programadas** no painel da plataforma (`/plataforma/status`, só Super Admin).
- Página pública: situação geral (verde/amarelo/vermelho), componentes com barra de uptime de 90 dias, incidentes recentes com linha do tempo, próxima manutenção; `GET /status.json` e `/status.rss` (feed Atom como o do portal) para integrações; atualização automática sem recarregar.
- Opcional (fase 2): inscrição por e-mail para avisos de incidente (dupla confirmação, link de cancelamento, sem dado pessoal além do e-mail) e selo/widget "status" para o rodapé do portal.
- **Regras:** textos em português; horários no fuso de São Paulo com UTC no `title`; nenhum dado de tenant; a página não pode exigir login nem cookie.

### Entregas sugeridas
- PR 1: ADR + modelo de dados + `status:probe` + painel da plataforma (incidentes manuais) + testes com sondas falsas.
- PR 2: página pública + `status.json` + feed, com contingência estática.
- PR 3: manutenções programadas, aviso por e-mail e selo para o portal.

## 3. Pontos comuns e ordem recomendada
1. ADR único "Site da plataforma (landing + status)" definindo hosts e módulo.
2. Status page primeiro (menor, valor operacional imediato para quem transmite ao vivo), landing em seguida.
3. Ambos reutilizam `PlatformAdmin` (Super Admin), `SeoHead` e a central de ajuda (artigos `platform`).
4. Fora de escopo por enquanto: cobrança/checkout, blog, multi-idioma.
