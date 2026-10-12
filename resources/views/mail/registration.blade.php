<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, Helvetica, sans-serif; color: #111827; line-height: 1.5;">
    <p>Olá, {{ $registration->contact_name }}.</p>
    <p>{{ $headline }}</p>
    @if ($note)
        <p style="background: #f3f4f6; padding: 12px; border-radius: 6px;"><strong>Mensagem da organização:</strong><br>{{ $note }}</p>
    @endif
    <p><strong>Equipe:</strong> {{ $registration->team_name }}</p>
    <p><a href="{{ $link }}" style="color: #047857;">Abrir meu pedido de inscrição</a></p>
    <p style="font-size: 12px; color: #6b7280;">Este link é pessoal e dá acesso aos dados do pedido. Não o compartilhe. — {{ $organization }}</p>
</body>
</html>
