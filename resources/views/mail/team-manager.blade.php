<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, Helvetica, sans-serif; color: #111827; line-height: 1.5;">
    <p>Olá, {{ $name }}.</p>
    <p>{{ $organization }} deu a você acesso de <strong>gestor da equipe {{ $team }}</strong>: pela área do gestor você define a escalação e o esquema tático de cada jogo e acompanha o elenco.</p>
    @if ($newAccount)
        <p><a href="{{ $link }}" style="color: #047857;">Criar minha senha e entrar</a></p>
        <p style="font-size: 12px; color: #6b7280;">O link vale por 24 horas. Se vencer, use “Esqueci a senha” na tela de entrada com este e-mail.</p>
    @else
        <p><a href="{{ $link }}" style="color: #047857;">Abrir a área do gestor</a></p>
        <p style="font-size: 12px; color: #6b7280;">Entre com o seu e-mail e a senha que você já usa.</p>
    @endif
    <p style="font-size: 12px; color: #6b7280;">Se você não esperava este convite, ignore esta mensagem. — {{ $organization }}</p>
</body>
</html>
