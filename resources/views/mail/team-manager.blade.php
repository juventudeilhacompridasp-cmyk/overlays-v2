@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá, {{ $name }}.</p>
    <p style="margin: 0;">{{ $organization }} deu a você acesso de <strong>gestor da equipe {{ $team }}</strong>: pela área do gestor você define a escalação e o esquema tático de cada jogo e acompanha o elenco.</p>
    @if ($newAccount)
        @include('mail.button', ['url' => $link, 'label' => 'Criar minha senha e entrar', 'color' => $color ?? null])
        <p style="margin: 16px 0 0; font-size: 13px; color: #6b7280;">O link vale por 24 horas. Se vencer, use “Esqueci a senha” na tela de entrada com este e-mail.</p>
    @else
        @include('mail.button', ['url' => $link, 'label' => 'Abrir a área do gestor', 'color' => $color ?? null])
        <p style="margin: 16px 0 0; font-size: 13px; color: #6b7280;">Entre com o seu e-mail e a senha que você já usa.</p>
    @endif
@endsection

@section('footer')
    Se você não esperava este convite, ignore esta mensagem.
@endsection
