---
title: Usuários, papéis e perfis de acesso
category: Configurações da organização
tags: usuários, papéis, perfis, permissões, acesso, administrador
order: 53
ability: manage-users
route: panel.users.index
---
## Papéis padrão
Em [Usuários](route:panel.users.index) cada pessoa recebe um acesso:
- **Administrador**: tudo, inclusive usuários.
- **Organizador**: competições, cadastros e operação de jogos.
- **Operador de jogo**: opera a súmula; o resto só consulta.
- **Consulta**: apenas visualiza.

O cadastro público é fechado: os acessos são criados aqui. A senha provisória é mostrada na criação; repasse por um canal seguro.

## Perfis personalizados
Se os papéis não bastam, crie um perfil em [Perfis de acesso](route:panel.profiles.index): dê um nome e marque as permissões (competições, cadastros, operar jogos, usuários). Depois escolha o perfil no campo **Acesso** do usuário.
- Os perfis de sistema (iguais aos papéis) não mudam; **duplique** um deles para personalizar.
- Um perfil em uso não pode ser excluído.
- Permissão não libera recurso fora do pacote da organização.

## Exceções por usuário
Em [Usuários](route:panel.users.index), o botão **Exceções** de cada pessoa ajusta o acesso só para ela, sem criar um perfil novo:
- **Somar** dá uma permissão que o papel ou o perfil não dá.
- **Retirar** tira uma que ele dá.
- **Negar vence**: se a mesma permissão estiver nas duas listas, ela é retirada.
A tabela mostra a **origem** de cada permissão (o papel/perfil ou uma exceção). As exceções ficam com a pessoa mesmo se você trocar o papel ou o perfil dela; para voltar ao padrão, desmarque tudo e salve.

## Auditoria de acesso
[Auditoria de acesso](route:panel.access-audit.index) lista as 200 últimas mudanças: quem criou, trocou ou removeu um acesso, mudou exceções ou mexeu num perfil, e o que passou a valer. O registro não guarda endereço IP e não pode ser editado.

## Proteção
A organização **nunca fica sem alguém que gerencie usuários**. Trocar o acesso, remover o usuário, tirar a permissão do perfil ou retirá-la por exceção é recusado se for o último.

> Remover o acesso não apaga a conta: ela só deixa de entrar no painel.
