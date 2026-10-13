# ADR 0007 — Documentação interna das APIs (somente Super Admin)

Status: aceito (parte 1 entregue)
Data: 13/10/2026

## Contexto

A API pública (`/api/v1/*`) tem guia em `docs/07-api-publica.md`, mas as rotas **internas** (painel, gestor, overlays, push, fila
offline `games/{jogo}/sync`, widgets, estado em tempo real) não têm referência navegável. Quem integra ou mantém precisa dela; ela
**não pode ser pública** (revela superfície interna).

## Decisões

- Fonte única: especificação **OpenAPI 3.1** gerada a partir das rotas registradas + metadados mantidos em código (arquivo por módulo
  em `app/Modules/*/Api/*.php` ou atributos no controlador): resumo, parâmetros, corpo, respostas, **autenticação** (pública, sessão,
  token de overlay), **middleware** (`feature:`, `panel.can:`/permissão, `throttle`), exemplos e erros.
- **Teste de cobertura:** toda rota registrada (exceto allowlist explícita) precisa de entrada; CI falha se faltar ou se o exemplo
  não validar contra o esquema.
- Acesso: `/plataforma/api-docs` (página React própria, sem CDN externa) e `/plataforma/api-docs/openapi.json`, atrás de
  `platform.admin` (404 para os demais, como o resto da área). Download do JSON para ferramentas (Postman/Insomnia).
- Dois conjuntos: **pública** (também publicável em `docs/07`) e **interna** (só super admin). Mudou rota ⇒ PR atualiza a descrição.

> **Ajuste na implementação (parte 1):** o catálogo de textos é um único arquivo `resources/api-docs/catalog.php`, indexado pelo nome da
> rota (em vez de um arquivo por módulo); a estrutura (caminho, parâmetros, acesso, middleware, recurso, permissão, limite) é derivada das
> rotas registradas. A cobertura obrigatória, por enquanto, vale para as rotas `/api/*`; as internas aparecem como "sem descrição" até a
> parte 2 (quando a lista de rotas internas sem texto passa a ser um teto que só diminui).

## Entrega em partes
1. Gerador + teste de cobertura + página com as rotas públicas/`/api/v1`.
2. Rotas internas (painel, gestor, overlay, push, sync, widgets) com exemplos.
3. Exportação OpenAPI/Postman e histórico de mudanças (changelog por versão).
