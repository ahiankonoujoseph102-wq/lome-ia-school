{{-- Gabarit commun des e-mails (même style que l'e-mail de bienvenue). Sections : title, heading, intro, content. --}}
@php
    $gold = '#fbbf24';
    $night = '#0b1030';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#eef0f7;font-family:Helvetica,Arial,sans-serif;color:#1e2340;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="background:{{ $night }};background-image:linear-gradient(135deg,#0b1030 0%,#3b1c8c 100%);padding:28px 28px 32px;">
                            <p style="margin:0;font-size:15px;font-weight:bold;color:#ffffff;">
                                <span style="display:inline-block;width:8px;height:18px;background:{{ $gold }};border-radius:2px;vertical-align:middle;margin-right:8px;"></span>Lomé IA School
                            </p>
                            <h1 style="margin:22px 0 0;font-size:24px;line-height:1.25;color:#ffffff;">@yield('heading')</h1>
                            <p style="margin:10px 0 0;font-size:15px;line-height:1.5;color:#c4b5fd;">@yield('intro')</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.6;">
                            @yield('content')
                            <p style="margin:26px 0 0;font-size:14px;line-height:1.6;color:#4b5068;">
                                Une question ? Répondez simplement à cet e-mail ou écrivez-nous sur WhatsApp au {{ config('site.contact.phones.0') }}.
                            </p>
                            <p style="margin:18px 0 0;font-size:14px;line-height:1.6;color:#4b5068;">À très vite,<br><strong style="color:#1e2340;">{{ config('site.trainer.name') }}</strong><br>Lomé IA School</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f7f7fb;font-size:12px;line-height:1.5;color:#7a7f99;text-align:center;">
                            {{ config('site.contact.address') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
