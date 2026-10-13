<!DOCTYPE html>
<html lang="pt-BR">
<body style="font-family: Arial, Helvetica, sans-serif; color: #111827; line-height: 1.5;">
    <p>Olá, {{ $name }}.</p>
    <p>{{ $headline }}</p>
    @if (count($lines) > 0)
        <ul>
            @foreach ($lines as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    @endif
    <p><a href="{{ $link }}" style="color: #047857;">Abrir na área do gestor</a></p>
    <p style="font-size: 12px; color: #6b7280;">Para parar de receber estes avisos, desligue-os no início da área do gestor. — {{ $organization }}</p>
</body>
</html>
