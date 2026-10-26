# ADR 0015 — Restrições de sorteio por vínculo

- **Status:** aceito para implementação
- **Data:** 26/10/2026
- **Escopo:** sorteio de grupos e chave eliminatória

## Contexto

O sorteio já consegue tentar separar equipes da mesma cidade. A pendência também pede evitar equipes do mesmo clube ou com o mesmo patrocinador no mesmo grupo ou confronto. Hoje `teams` guarda apenas nome, cidade, escudo e cor; não existe um vínculo de clube ou patrocinador no modelo de uma inscrição. Tentar inferir esses dados do nome da equipe seria frágil e poderia expor uma regra incorreta durante uma competição.

## Decisão

Os vínculos serão dados explícitos da inscrição na edição, e não atributos globais da equipe:

1. `entries` receberá dois campos opcionais, `club_key` e `sponsor_key`, armazenados como identificadores opacos normalizados (sem CPF, documento ou contato).
2. O organizador poderá informar os vínculos na divisão antes do sorteio. A ausência de um vínculo significa que não há restrição para aquele critério.
3. O motor TypeScript continuará puro e receberá um mapa de restrições por entrada. A função de custo contará cada par que compartilha clube ou patrocinador, além da cidade já existente.
4. A tela mostrará cada conflito com o critério correspondente. As tentativas automáticas continuam sendo heurísticas e o organizador poderá salvar um sorteio com avisos.
5. O backend validará e persistirá os vínculos no payload do sorteio. O estado público de overlays e portal não receberá esses campos.

## Alternativas rejeitadas

- **Campo no cadastro global da equipe:** uma mesma equipe pode participar de edições com patrocinadores diferentes, e o vínculo pertence à edição.
- **Comparação pelo nome:** nomes não são identificadores estáveis e gerariam falsos positivos.
- **Bloquear o salvamento:** a regra é uma orientação de sorteio; bloquear exigiria resolver casos matematicamente impossíveis e quebraria sorteios externos.

## Próximos passos

Criar a migration e os campos da inscrição, adicionar a edição no painel e no endpoint de sorteio, cobrir `groupClashes`/`bracketClashes` com testes Node e incluir teste Pest de isolamento entre organizações. Depois disso, atualizar o status removendo a pendência de restrições além da cidade.
