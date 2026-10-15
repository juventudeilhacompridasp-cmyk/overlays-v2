@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá, {{ $name }}.</p>
    <p style="margin: 0 0 16px; font-size: 17px; font-weight: 600;">{{ $headline }}</p>
    <p style="margin: 0 0 16px; font-size: 14px;">{{ $detail }}</p>
    @include('mail.button', ['url' => $link, 'label' => $label, 'color' => $color ?? null])
@endsection

@section('footer')
    Você recebe este aviso porque pediu a leitura de uma súmula em {{ $organization }}. Desligue-o em Painel &gt; Comunicações (administrador).
@endsection
