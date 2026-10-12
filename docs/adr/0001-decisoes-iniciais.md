# ADR 0001 — Decisões iniciais (12/10/2026)

| # | Decisão | Escolha | Observação |
|---|---|---|---|
| 1 | Back-end | Laravel (PHP 8.4) monolito | Equipe domina; IAs produzem bem |
| 2 | Front | Inertia + React (painel **e** portal público com SSR) | API `/api/v1` pode ser publicada dentro do mesmo projeto quando houver app móvel/integrações |
| 3 | Overlays OBS | Entry React separada (Vite), sem Inertia, servida como estático | Consome endpoints públicos + WebSocket |
| 4 | Tempo real | Laravel Reverb (Redis) | Fallback por polling nos overlays |
| 5 | Banco | PostgreSQL em container, volume no disco do VPS | Backup diário externo obrigatório |
| 6 | Cache/filas | Redis (Horizon) | |
| 7 | Arquivos | Armazenamento compatível com S3: Cloudflare R2 (decisão final pendente da familiaridade do time) | Mesmo código do S3; muda só endpoint/credenciais. S3Mock no local |
| 8 | Proxy externo | Nginx Proxy Manager (já conhecido) → nginx → php-fpm | Traefik fica como alternativa futura |
| 9 | Deploy | 1 VPS, Docker Compose (Portainer opcional) | |
| 10 | Cobrança | Fora do MVP; liberação manual de recursos por organização | Vendido como pacote do produto de transmissões |
| 11 | Esporte inicial | Futebol; depois futsal, vôlei, basquete | `SportRuleset` |
| 12 | Nome | Pendente; sugerir opções depois | |
