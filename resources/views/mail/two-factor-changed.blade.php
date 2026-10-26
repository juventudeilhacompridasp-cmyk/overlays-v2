@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá{{ $name ? ', '.$name : '' }}.</p>
    <p style="margin: 0;">{{ $headline }}</p>
@endsection

@section('footer')
    Se não foi você, troque a senha agora e fale com o administrador da sua organização.
@endsection
