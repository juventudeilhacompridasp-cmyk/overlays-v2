# ADR 0017 — Assinatura digital de súmula e relatórios

## Status

Em implementação — 26/10/2026

## Contexto

Súmulas oficiais e relatórios precisam registrar a aprovação de árbitros, capitães e mesários sem depender de impressão. A plataforma não deve guardar imagem de assinatura nem tratar biometria.

## Decisão proposta

- A assinatura é uma confirmação autenticada por link de uso único enviado ao e-mail cadastrado ou por código temporário no painel.
- Cada pedido registra documento, versão, hash SHA-256 do PDF, pessoa, papel, organização, IP, user-agent, data e método; o hash nunca é substituído quando o documento muda.
- O documento assinado é imutável. Uma correção gera nova versão e exige novas assinaturas.
- O PDF recebe uma página/rodapé de evidências com hash, assinantes e horários em UTC e São Paulo; dados pessoais exibidos são mínimos.
- Não há assinatura desenhada, biometria, CPF ou armazenamento de segredo de autenticação.
- A assinatura exige que a escalação e o resultado estejam confirmados; reabrir o jogo invalida a versão assinada e exige novo ciclo.

## Entregue nesta etapa

- `DocumentSignature::hash()` gera o SHA-256 determinístico do PDF, sem persistir o conteúdo nem expor dados pessoais.
- A tabela `game_document_signatures` guarda versão, hash, papel, método, estado e hashes de token/IP/user-agent; tokens e dados de rede nunca são persistidos em claro.

## Consequências

- A implementação será separada em: modelo/auditoria, fluxo de convite e confirmação, integração com PDF e painel de conferência.
- E-mail e expiração dos links precisam usar os mesmos limites e registros de comunicação da plataforma.
- A validade jurídica deve ser revisada pelo responsável do projeto antes da ativação em produção.
