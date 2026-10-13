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
- Overlays visuais v1: preset Arena com cores, posição, escala e nível de animação por canal; atualização ao vivo no OBS e
  API privada para trocar jogo, camadas e aparência com chave de controle separada da URL pública.

## Em andamento
- (entregue neste PR) Teste de navegador (Playwright) da fila offline no CI:
  "Fila offline no navegador".

## Pendências conhecidas (por ordem sugerida)
- Basquete: faltas técnicas/antidesportivas e relógio de posse (24 s). Pedidos de tempo entregues (ainda sem mostrar no overlay/página pública e sem parar o relógio sozinho).
- Vôlei: limite de substituições por set, rodízio/líbero, pedidos de tempo.
- Fila offline: escalação, pênaltis e resultado rápido ainda exigem internet; a página não abre sem internet se for
  recarregada (o service worker não guarda páginas por causa da LGPD).
- Gestor de equipe: avisos por push/WhatsApp (hoje só e-mail).
- Overlays: novos presets e variações de layout, fontes próprias, patrocinadores e composição de cenas continuam para os
  próximos PRs; a v1 entrega o contrato de aparência e o preset Arena.
- Cobrança automática (fora do MVP).

## Limitações do ambiente de desenvolvimento do agente
- O `vendor/` nem sempre instala no ambiente em nuvem; a validação completa de PHP é a CI. Teste Node local: `npm run test:js`.
