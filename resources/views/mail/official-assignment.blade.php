@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá, {{ $name }}.</p>
    <p style="margin: 0 0 16px; font-size: 17px; font-weight: 600;">Você foi escalado como {{ mb_strtolower($role) }}.</p>
    <table role="presentation" style="margin: 0 0 16px; font-size: 14px; border-collapse: collapse;">
        <tr><td style="padding: 4px 16px 4px 0; color: #6b7280;">Jogo</td><td style="padding: 4px 0;"><strong>{{ $game }}</strong></td></tr>
        <tr><td style="padding: 4px 16px 4px 0; color: #6b7280;">Competição</td><td style="padding: 4px 0;">{{ $competition }}</td></tr>
        <tr><td style="padding: 4px 16px 4px 0; color: #6b7280;">Data e hora</td><td style="padding: 4px 0;">{{ $when }}</td></tr>
        <tr><td style="padding: 4px 16px 4px 0; color: #6b7280;">Local</td><td style="padding: 4px 0;">{{ $venue }}</td></tr>
    </table>
    <p style="margin: 0; font-size: 14px;">Se não puder comparecer, avise a organização o quanto antes.</p>
@endsection

@section('footer')
    Você recebe este aviso porque foi escalado em {{ $organization }}. Responda a organização para ajustes na escala.
@endsection
