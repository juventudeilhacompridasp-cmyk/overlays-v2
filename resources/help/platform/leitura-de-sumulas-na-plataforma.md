---
title: Leitura de súmulas por IA: chave, custo e limites
category: Plataforma
tags: súmula, ia, claude, custo, limite, anthropic, chave
order: 3
---
A leitura de foto e PDF usa a API da Anthropic. Configure no ambiente de produção:
- `ANTHROPIC_API_KEY`: a chave. Sem ela só a leitura de planilhas (CSV/XLSX) funciona.
- `ANTHROPIC_MODEL`: o modelo usado (padrão definido no projeto).
- `SHEET_IMPORT_MAX_READS_PER_GAME`: leituras por jogo (padrão 3, teto 10 que a organização pode reduzir).
- `SHEET_IMPORT_MONTHLY_READS`: leituras por organização por mês (padrão 200).

## Controle de custo
Só a leitura por IA é contada; a de planilhas é gratuita. Estourado o limite por jogo ou por mês, o usuário recebe uma mensagem clara. Cada organização pode desligar a leitura por IA.

## Privacidade
Ao provedor vão apenas o arquivo da súmula, o esporte e os nomes das equipes. O prompt manda ignorar documentos pessoais e qualquer sequência longa de dígitos é removida do resultado. Toda leitura passa por revisão humana. Lembre a organização de citar o uso de IA nos Termos e na Privacidade.
