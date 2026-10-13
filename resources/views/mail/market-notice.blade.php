@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px;">Olá, {{ $name }}.</p>
    <p style="margin: 0 0 16px; font-size: 17px; font-weight: 600;">{{ $headline }}</p>
    @if (count($lines) > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 8px; background-color: #f9fafb; border-radius: 8px;">
            @foreach ($lines as $line)
                <tr>
                    <td style="padding: 10px 14px; border-bottom: 1px solid #e5e7eb; font-size: 14px;">{{ $line }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    @include('mail.button', ['url' => $link, 'label' => $label, 'color' => $color ?? null])
@endsection

@section('footer')
    Você recebe este aviso por causa do Mercado de jogadores de {{ $organization }}. Você pode pausar ou apagar o seu perfil a qualquer momento.
@endsection
