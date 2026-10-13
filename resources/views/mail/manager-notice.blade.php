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
    @include('mail.button', ['url' => $link, 'label' => 'Abrir na área do gestor', 'color' => $color ?? null])
@endsection

@section('footer')
    Para parar de receber estes avisos, desligue-os no início da área do gestor.
@endsection
