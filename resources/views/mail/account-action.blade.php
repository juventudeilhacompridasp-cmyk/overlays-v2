@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá{{ $name ? ', '.$name : '' }}.</p>
    <p style="margin: 0;">{{ $headline }}</p>
    @include('mail.button', ['url' => $url, 'label' => $label, 'color' => $color ?? null])
@endsection

@section('footer')
    {{ $footer }}
@endsection
