@php($brand = $color ?? '#059669')
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $organization }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, 'Segoe UI', Roboto, Arial, Helvetica, sans-serif; color: #111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px;">
                    <tr>
                        <td style="background-color: {{ $brand }}; border-radius: 12px 12px 0 0; padding: 20px 28px; color: #ffffff; font-size: 18px; font-weight: 700;">
                            {{ $organization }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 28px; font-size: 15px; line-height: 1.6; color: #111827; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 12px 12px; padding: 16px 28px; font-size: 12px; line-height: 1.5; color: #6b7280;">
                            @yield('footer')
                            <div style="margin-top: 8px; color: #9ca3af;">{{ $organization }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
