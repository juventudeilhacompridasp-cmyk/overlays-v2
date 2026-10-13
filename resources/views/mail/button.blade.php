<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
    <tr>
        <td style="background-color: {{ $color ?? '#059669' }}; border-radius: 8px;">
            <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none;">{{ $label }}</a>
        </td>
    </tr>
</table>
<p style="margin: 0; font-size: 12px; color: #6b7280; word-break: break-all;">Se o botão não funcionar, copie este endereço no navegador:<br><a href="{{ $url }}" style="color: #6b7280;">{{ $url }}</a></p>
