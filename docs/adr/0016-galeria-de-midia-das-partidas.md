# ADR 0016 — Galeria pública de mídia das partidas

## Status

Aceito — 26/10/2026

## Contexto

Organizadores querem divulgar fotos, vídeos e replays associados a uma partida. A página pública já suporta transmissão do YouTube, mas ainda não possui galeria nem upload contextualizado ao jogo.

## Decisão

- A mídia pertence ao jogo (`game_media`), com título opcional, tipo (`image` ou `video`), URL/path, ordem, publicação e autor interno.
- Imagens enviadas pela organização usam o disco público `s3`, passam por `ImageVariants` e nunca preservam EXIF/GPS.
- Vídeos externos aceitam somente HTTPS e hosts conhecidos (YouTube, Vimeo e Instagram); a página não carrega player até a pessoa clicar.
- A galeria só aparece quando `settings.portal.game_media` estiver ligada; o padrão é desligado.
- O painel modera, ordena, publica e remove itens. Mídia não publicada não aparece em snapshot, SEO, feed, widgets ou overlays.
- Fotos de atletas não são inferidas da escalação: continuam submetidas às regras de privacidade e consentimento já existentes.

## Consequências

- A primeira entrega deve separar upload, moderação e visualização pública em PRs pequenos. A parte inicial de vídeos externos já está entregue; upload de fotos fica para a próxima parte.
- A página pública pode exibir miniaturas responsivas e links de vídeo sem aumentar o payload do placar ao vivo.
- A remoção deve apagar a variante pública do armazenamento e invalidar o cache da galeria.
