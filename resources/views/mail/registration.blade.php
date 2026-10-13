@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá, {{ $registration->contact_name }}.</p>
    <p style="margin: 0 0 16px;">{{ $headline }}</p>
    @if ($note)
        <div style="margin: 0 0 16px; padding: 12px 14px; background-color: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 4px; font-size: 14px;">
            <strong>Mensagem da organização:</strong><br>{{ $note }}
        </div>
    @endif
    <p style="margin: 0;"><span style="color: #6b7280;">Equipe:</span> <strong>{{ $registration->team_name }}</strong></p>
    @include('mail.button', ['url' => $link, 'label' => 'Abrir meu pedido de inscrição', 'color' => $color ?? null])
@endsection

@section('footer')
    Este link é pessoal e dá acesso aos dados do pedido. Não o compartilhe.
@endsection
