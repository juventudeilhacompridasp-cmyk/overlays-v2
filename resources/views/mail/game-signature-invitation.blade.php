@extends('mail.layout')

@section('content')
<p>Olá, {{ $recipientName }}.</p>
<p>Você foi convidado(a) a confirmar a súmula como <strong>{{ $role }}</strong>. O link é válido por 24 horas e pode ser usado uma única vez.</p>
<p><a href="{{ $link }}">Confirmar assinatura da súmula</a></p>
<p>Se você não esperava este convite, ignore esta mensagem.</p>
@endsection
