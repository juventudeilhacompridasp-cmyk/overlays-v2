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
- Para as rotas descritas: resumo, parâmetros, corpo da requisição e respostas com **exemplos**. As rotas do painel, do gestor e do atleta têm um resumo do que fazem (telas, ações e arquivos). As demais aparecem como "sem descrição" (a estrutura continua correta, só falta o texto).
- A API pública estável é `/api/v1` (somente leitura, sem login, só edições publicadas).

## Baixar para outras ferramentas
O botão **Baixar OpenAPI (JSON)** entrega a especificação OpenAPI 3.1, que Postman, Insomnia e outras ferramentas importam. O botão **Baixar coleção do Postman** entrega a coleção pronta (v2.1): uma pasta por grupo, `{{baseUrl}}` no endereço e os exemplos de corpo. Tokens e chaves ficam como variáveis vazias (`channelToken`, `controlKey`): preencha na sua máquina; nada secreto vai no arquivo.

## Histórico de mudanças
O fim da página lista as versões da referência (a versão corrente aparece no topo e vai na especificação). Mudou rota, parâmetro ou resposta? O PR acrescenta uma entrada em `resources/api-docs/changelog.php`.

## Para quem desenvolve
A descrição de cada rota fica em `resources/api-docs/catalog.php` (API pública, overlays, widgets e tempo real) ou `resources/api-docs/internal.php` (painel, gestor e atleta), indexadas pelo **nome da rota**. Rota nova de API (`/api/*`), de overlay, de widget ou de tempo real **precisa** de entrada no catálogo; um teste falha sem ela, e também se o exemplo não cumprir o esquema ou se a entrada apontar para rota que não existe mais.
