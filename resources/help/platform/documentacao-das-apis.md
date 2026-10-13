---
title: Documentação interna das APIs
category: Plataforma
tags: api, openapi, rotas, documentação, integração, postman
order: 4
route: platform.api-docs.index
---
Em [Documentação das APIs](route:platform.api-docs.index) está a referência de **todas as rotas** da aplicação, gerada das próprias rotas registradas. Só o Super Admin enxerga esta página: ela revela a superfície interna.

## O que aparece
- Método e caminho, o **tipo de acesso** (pública, sessão do painel, gestor, Super Admin, token na URL), o **recurso do pacote** exigido, a **permissão** e o limite de requisições.
- Para as rotas descritas: resumo, parâmetros, corpo da requisição e respostas com **exemplos**. As demais aparecem como "sem descrição" (a estrutura continua correta, só falta o texto).
- A API pública estável é `/api/v1` (somente leitura, sem login, só edições publicadas).

## Baixar para outras ferramentas
O botão **Baixar OpenAPI (JSON)** entrega a especificação OpenAPI 3.1, que Postman, Insomnia e outras ferramentas importam.

## Para quem desenvolve
A descrição de cada rota fica em `resources/api-docs/catalog.php`, indexada pelo **nome da rota**. Rota nova de API (`/api/*`) **precisa** de entrada no catálogo; um teste falha sem ela, e também se o exemplo não cumprir o esquema ou se a entrada apontar para rota que não existe mais.
