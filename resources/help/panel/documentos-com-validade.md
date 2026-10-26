---
title: Documentos com validade (atestado médico)
category: Cadastros
tags: atestado, médico, documento, validade, vencimento, seguro, escalação, aviso
order: 30
ability: manage-registry
route: panel.documents.index
---
Guarde o atestado médico (e outros documentos) de cada pessoa com a **data de validade** e acompanhe o que está vencendo.

## Cadastrar
1. Abra a ficha da pessoa em [Atletas e comissão](route:panel.people.index) e vá ao cartão **Documentos com validade**.
2. Escolha o **tipo** (atestado médico, seguro ou outro), informe a **validade** e envie o arquivo (PDF ou imagem, até 5 MB).
3. O arquivo fica em armazenamento privado (LGPD) e só abre pelo botão **Abrir**, por um link temporário. Excluir apaga o arquivo.

## Acompanhar
- A tela [Documentos a vencer](route:panel.documents.index) mostra os documentos **vencidos** e os que vencem nos próximos 30 dias, com as equipes de cada pessoa. Use a busca e os filtros por tipo e situação.
- Cada documento tem uma situação: **Válido**, **Vence em breve** (dentro da janela do regulamento, 15 dias por padrão) ou **Vencido**. O documento vale até o fim do dia da validade.

## Aviso ao gestor
O gestor da equipe recebe um e-mail quando documentos de atletas dele estão perto de vencer (janela do regulamento) ou venceram nos últimos 30 dias, uma vez para cada conjunto de documentos. O administrador pode desligar em [Comunicações](route:panel.communications.edit).

## Exigir atestado para escalar
Na edição, no bloco **Regulamento: documentos com validade**, marque **Exigir atestado médico válido**. Com isso, atleta (titular ou reserva) sem atestado válido **na data do jogo** não entra na escalação, seja pelo mesário, pelo organizador ou pelo gestor; a mensagem mostra quem está sem atestado. A comissão técnica não é conferida. Também dá para ajustar quantos dias antes o gestor é avisado.
