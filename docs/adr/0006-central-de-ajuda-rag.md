# ADR 0006 — Central de ajuda com busca e respostas (estilo RAG)

Status: aceito (partes 1 e 2 entregues: artigos, ajuda contextual, "isso ajudou?" e notas da organização; falta a resposta com IA)
Data: 13/10/2026

## Contexto

Operadores, gestores e administradores precisam consultar **como configurar as coisas e tirar dúvidas** sem acionar o suporte. O
conhecimento hoje está espalhado em `AGENTS.md`, `docs/*` e na cabeça de quem desenvolveu.

## Decisões

### 1. Conteúdo
- Artigos em Markdown versionados no repositório (`resources/help/{publico}/{slug}.md`, cabeçalho: `title`, `audience`
  (`operator|manager|admin|superadmin`), `tags`, `feature` opcional, `route` opcional da tela relacionada, `updated`). Revisados em PR
  como o código; um teste falha se artigo referencia rota/recurso inexistente.
- Renderizador próprio e seguro (como `LegalText`: títulos, listas, negrito, links internos; **nunca HTML**).

> **Ajuste na implementação (parte 1):** o acervo é pequeno e versionado, então o índice é montado em PHP (`HelpSearch`: sem acento,
> singular aproximado, tolerância a 1 erro de digitação), sem tabelas nem `tsvector`. Funciona igual em qualquer banco e não precisa de
> migração; se o acervo crescer muito, migramos para PostgreSQL FTS/`pg_trgm` mantendo a mesma interface. Públicos: `panel`, `manager`
> e `platform` (a "ajuda do operador/administrador" se separa por `ability`).

### 2. Busca (fase 1, sem IA, sem custo, sem dados saindo)
- Índice `help_articles` + `help_chunks` (trechos por seção) no PostgreSQL com busca de texto completo em português (`tsvector`,
  `unaccent`) + similaridade (`pg_trgm`) para erros de digitação; ranqueia por título/tags/trecho.
- **Filtra pelo que o usuário pode usar**: público do artigo × perfil/permissões do usuário × recursos do pacote (não mostra ajuda de
  recurso que a organização não tem, nem de área que o perfil não acessa).
- Ajuda contextual: botão "?" em cada tela abre os artigos da rota (`route`).

### 3. Respostas (fase 2, opcional, estilo RAG)
- Embeddings dos trechos (pgvector) + resposta gerada por Claude **só com os trechos recuperados**, com citações dos artigos e
  "não encontrei na documentação" quando não há base. Interruptor por organização (padrão desligado) e recurso sob demanda, com o
  mesmo cuidado de LGPD da ADR 0004: ao provedor vão **apenas a pergunta e os trechos públicos de ajuda — nunca dados da organização**.
- Limite de perguntas por usuário/dia e por organização/mês; registro de perguntas sem resposta para o time melhorar a documentação.

### 3. Telas e acesso
- `/painel/ajuda` e `/gestor/ajuda` (busca, categorias, artigo, "isso ajudou?"). Super admin: artigos `superadmin` (operação da plataforma).
- Administrador da organização pode **acrescentar notas próprias** (procedimentos internos) que aparecem só para a organização.

## Entrega em partes
1. Estrutura de artigos + renderizador + busca textual + telas + conteúdo inicial (guias por módulo).
2. Ajuda contextual por tela + "isso ajudou?" + notas da organização.
3. Resposta com IA (RAG) opcional, com limites e registro de lacunas.
