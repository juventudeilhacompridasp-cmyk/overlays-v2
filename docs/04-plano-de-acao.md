# 04 — Plano de ação (roadmap e trabalho com múltiplas IAs)

## Princípios
Monolito modular Laravel; contrato antes do código (ADR + migrations + OpenAPI); um módulo = uma pasta = uma branch/PR;
toda IA recebe escopo, critérios de aceite e comandos de teste. Humano revisa migrations, policies e regras de dinheiro/LGPD.

## Fases
| Fase | Entrega | Principais tarefas |
|---|---|---|
| **0 Fundação** | Repo rodando | Skeleton Laravel+React/Inertia, Docker Compose, CI, `AGENTS.md`, Pest/PHPStan/Pint, auth (Fortify/Sanctum), multi-tenancy (org, subdomínio, scopes), RBAC (Spatie), ULIDs, auditoria |
| **1 Núcleo de competição (futebol)** | Organizador cria campeonato | Competição/edição/categoria/fase, clubes, atletas (Pessoa única), comissão, árbitros, estádios, regulamento parametrizável (`SportRuleset` futebol), geração de tabela (pontos corridos, grupos, mata-mata), classificação e desempates, testes de regras com os exemplos do regulamento real |
| **2 Súmula e disciplina** | Jogo lançado e consolidado | Eventos de partida (gol, cartões, substituição, períodos), escalação, relatório, assinatura, artilharia, suspensão automática (3 amarelos, zerar por fase, W.O.), tribunal disciplinar |
| **3 Portal público** | Torcedor acompanha | Páginas SSR/SEO (jogos, tabela, equipe, atleta, partida), tema por organização, impressão A4/PDF, Open Graph, política LGPD aplicada no servidor, API pública versionada |
| **4 Tempo real + Overlays OBS** | Diferencial do produto | Reverb, canais por token, painel do operador, overlays: placar, cronômetro, escalação, lower-thirds (gol/cartão/substituição), tabela, artilharia, próximo jogo; temas; teste visual Playwright; guia de uso no OBS |
| **5 Inscrição e financeiro** | Operação completa | Inscrição online de equipes/atletas, documentos, aprovação, carteirinha QR, relatórios (financeiro/pagamentos online só se o painel mapeado exigir) |
| **6 SaaS** | Operação do produto | Pacotes/recursos por organização (liberação manual), onboarding, domínio próprio, white-label, métricas de uso, termos/LGPD. **Cobrança automática fica para depois** |
| **7 Multiesporte** | Futsal → vôlei → basquete | Novos `SportRuleset`, eventos e classificação por tipo de resultado, overlays por esporte |
| **8 Extras** | Crescimento | PWA do mesário, push/WhatsApp, estatísticas avançadas, widgets embedáveis, escolinhas/reservas/editais (se houver demanda) |

## Status (13/10/2026)
| Fase | Situação |
|---|---|
| 0 Fundação · 1 Núcleo · 2 Súmula e disciplina · 3 Portal · 4 Tempo real e overlays | Entregues |
| 5 Inscrição | Entregue: inscrição online, documentos, aprovação, carteirinha com QR, relatórios e CSV. **Financeiro/pagamentos online ficou de fora** (decisão: só se o painel mapeado exigir) |
| 6 SaaS | Entregue: pacotes/recursos/limites por organização, área `/plataforma`, onboarding, domínio próprio/subdomínio, white-label, termos e privacidade com aceite. **Cobrança automática segue fora** |
| 7 Multiesporte | Entregue: futsal, vôlei (sets) e basquete (pontos) — ver `docs/adr/0002-modelo-de-placar-por-esporte.md` |
| 8 Extras | Entregue: estatísticas públicas, widgets embutíveis, app do mesário (PWA), avisos push e compartilhamento no WhatsApp. **Só se houver demanda:** escolinhas, reservas e editais |

**Pendências conhecidas (sem ordem):** pedidos de tempo, faltas técnicas e relógio de posse no basquete; limite de substituições por set e rodízio/líbero no vôlei; fila de lançamentos offline no app do mesário; WhatsApp Business API (envio automático) e aviso por e-mail; teste real do Reverb e do push em HTTPS; deploy e escolha do nome da plataforma (ver ADR 0001).

## Divisão para múltiplas IAs (paralelizável após a Fase 0)
| Trilha | Escopo (pasta) | Dependências |
|---|---|---|
| A Plataforma | `app/Modules/Tenancy`, `Identity`, CI, Docker | — |
| B Competições | `Modules/Competitions`, `Teams`, `Registration` | A |
| C Partidas | `Modules/Matches` (eventos, súmula, disciplina) | B |
| D Regras | `Modules/Rules` (ruleset, desempates, suspensão) — testes puros, sem I/O | — (contrato de B/C) |
| E Portal | `resources/js/public`, rotas públicas, SEO | B, C |
| F Tempo real/Overlays | `Modules/Broadcast`, `resources/js/overlays` | C |
| G Pacotes/recursos | `Modules/Tenancy` (flags por organização) | A |
Regra de conflito: nenhuma IA edita fora da sua pasta sem abrir um ADR/PR de contrato; migrations numeradas por módulo.

## Definição de pronto (por tarefa)
Testes Pest/Vitest passando, PHPStan 8 limpo, policies cobertas, seed de demonstração atualizado, doc do módulo, PR pequeno (<500 linhas úteis).

## Riscos principais
LGPD (menores, disciplina pública), regras de competição variam por entidade (manter parametrizáveis), latência/queda do tempo real
durante transmissões (fallback por polling nos overlays), escopo do painel ainda não mapeado (ver `05-prompt-mapeamento-painel.md`).

## Próximos passos imediatos
1. Mapear o painel do organizador (doc 05). 2. Sugerir nomes e registrar domínio. 3. Iniciar Fase 0 (esqueleto Laravel+Inertia, Compose, CI). 4. Antecipar a Fase 4 (overlays) logo após a Fase 2.
