@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá{{ $name ? ', '.$name : '' }}.</p>
    <p style="margin: 0 0 16px;">Estes jogos estão chegando e ainda têm algo a resolver:</p>
    @foreach ($games as $game)
        <p style="margin: 0 0 4px; font-weight: 700;">{{ $game['label'] }} <span style="font-weight: 400; color: #6b7280;">· {{ $game['when'] }}</span></p>
        <ul style="margin: 0 0 16px; padding-left: 20px;">
            @foreach ($game['problems'] as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
    @endforeach
    <p style="margin: 0;"><a href="{{ $link }}" style="color: {{ $color }};">Abrir os jogos no painel</a></p>
@endsection

@section('footer')
    Você recebe este aviso por gerenciar competições. A organização pode desligá-lo em Painel &gt; Comunicações.
@endsection
