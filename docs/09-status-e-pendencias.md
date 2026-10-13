# Status do projeto e pendências (ponto de partida para quem assume)

Atualize este arquivo em todo PR que entregar ou descobrir algo. O detalhe técnico de cada entrega está no `AGENTS.md`
(regras e arquitetura), nos ADRs (`docs/adr/`) e nos guias (`docs/06`, `docs/07`, `docs/08`). Fluxo de trabalho: PR pequeno,
CI verde (Pint, Larastan, Pest, Front, "Tempo real e push", Docker), descrição em português.

## Entregue
- Fases 1–6: competições, súmula, regras, mata-mata, portal público, overlays do OBS, inscrição online, carteirinhas e
  relatórios, pacotes/recursos/limites, onboarding de organizações, domínios próprios, marca própria, termos e privacidade.
- Fase 7: multiesporte (futsal, vôlei, basquete).
- Fase 8: estatísticas públicas, widgets embutíveis, app do mesário (PWA), avisos push e compartilhamento.
- Fase 9: gestor de equipe (login, painel `/gestor`, escalação e esquema tático, elenco, reforços, avisos por e-mail);
  validação do Reverb e do Web Push de verdade no CI; **fila offline da súmula** (PR 33).

## Em andamento
- **Leitura de súmulas enviadas depois do jogo (ADR 0004)**: partes 1 (envio e guarda), 2 (leitura) e 3 (conferência) entregues — leitor estruturado CSV/XLSX sem IA, leitor por IA com visão (Claude, `ANTHROPIC_API_KEY`), `ExtractedSheet`, job em fila, interruptor e limite por jogo da organização, limite mensal da plataforma, aviso de privacidade e texto nos Termos; **recurso segue sob demanda (desligado para todos até o Super Admin liberar)**. Parte 3 entregue (revisão humana da leitura com registro das correções, relatório de conferência na tela e em CSV, sem alterar o jogo). Falta a parte 4 (notificação ao terminar a leitura e métricas de uso na área da plataforma).
- (entregue neste PR) Teste de navegador (Playwright) da fila offline no CI:
  "Fila offline no navegador".

## Pendências conhecidas (por ordem sugerida)
- **Novos pedidos de 13/10 (plano em `docs/11-roadmap-portal-e-plataforma.md`)**: (a) leitura de súmulas parte 2/3, desligada por padrão e ativada pelo Super Admin; (b) portal público muito mais rico; (c) Central de ajuda com busca/RAG (ADR 0006); (d) documentação interna das APIs só para Super Admin (ADR 0007); (e) perfis/permissões totalmente parametrizáveis (ADR 0005) — **parte 1 feita** (catálogo `Permission`, `access_profiles`, `memberships.access_profile_id`, `User::permissions()`; sem perfil vale o `Role`); **parte 2 feita** (Painel > Perfis de acesso: criar/editar/duplicar/excluir, matriz de permissões, perfil escolhido em Usuários, guarda do último gestor de usuários); falta exceções por usuário + auditoria (parte 3) e novas permissões finas conforme os módulos (ver/consultar por módulo, relatórios, comunicações, etc.).
- **Parametrização** (tela Painel > Comunicações entregue; falta o restante do plano) (comunicações, ativar/desativar funcionalidades, janelas e limites): levantamento e plano em `docs/10-parametrizacao.md` — começar pela tela Painel > Comunicações.
- Basquete: faltas técnicas/antidesportivas e relógio de posse (24 s). Pedidos de tempo entregues (ainda sem mostrar no overlay/página pública e sem parar o relógio sozinho).
- Vôlei: limite de substituições por set, rodízio/líbero, pedidos de tempo.
- Fila offline: escalação, pênaltis e resultado rápido ainda exigem internet; a página não abre sem internet se for
  recarregada (o service worker não guarda páginas por causa da LGPD).
- Gestor de equipe: avisos por push/WhatsApp (hoje só e-mail).
- Cobrança automática (fora do MVP).

## Limitações do ambiente de desenvolvimento do agente
- O `vendor/` nem sempre instala no ambiente em nuvem; a validação completa de PHP é a CI. Teste Node local: `npm run test:js`.
