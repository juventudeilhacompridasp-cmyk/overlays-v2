---
title: Autenticação em dois fatores (2FA)
category: Segurança
tags: 2fa, dois fatores, código, autenticador, senha, segurança, recuperação, google authenticator
order: 90
---
Com a autenticação em dois fatores, além da senha o login pede um código de 6 dígitos que muda a cada 30 segundos, gerado por um aplicativo no seu celular. Se alguém descobrir a sua senha, ainda assim não entra.

## Ativar
1. Abra **Meu perfil** (menu do usuário) e vá ao cartão **Autenticação em dois fatores**.
2. Digite a senha da conta e clique em **Ativar**.
3. No aplicativo de autenticação (Google Authenticator, Authy, 1Password, Microsoft Authenticator...), escaneie o QR code ou digite a chave manual.
4. Digite o código de 6 dígitos que o aplicativo mostrar e clique em **Confirmar e ativar**.
5. **Guarde os códigos de recuperação** que aparecem (8 códigos). Eles só são mostrados agora.

## Entrar depois de ativar
Depois da senha, digite o código do aplicativo. Cada código vale uma vez; se errar 5 vezes em um minuto, espere um pouco.

## Perdi o celular
Na tela do código, escolha **Usar um código de recuperação** e digite um dos códigos guardados (cada um vale uma única vez). Depois entre em Meu perfil e **gere novos códigos**. Sem aplicativo e sem códigos, peça a um administrador da plataforma para reiniciar os dois fatores da conta (ele confirma que é você e você recebe um e-mail avisando).

## Gerar novos códigos ou desativar
Em Meu perfil, informe a senha da conta e use **Gerar novos códigos** (os antigos deixam de valer) ou **Desativar**.

Ativar e desativar os dois fatores gera um **e-mail de aviso**: se você não fez isso, troque a senha.

> A instalação pode **exigir** o 2FA de quem administra usuários e dos administradores da plataforma; nesse caso, quem ainda não ativou é levado a Meu perfil antes de usar o painel.
