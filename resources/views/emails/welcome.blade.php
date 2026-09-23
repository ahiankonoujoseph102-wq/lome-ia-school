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
    <title>Bienvenue chez Lomé IA School</title>
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
                            <h1 style="margin:22px 0 0;font-size:26px;line-height:1.25;color:#ffffff;">Bienvenue {{ $user->first_name }} !</h1>
                            <p style="margin:10px 0 0;font-size:15px;line-height:1.5;color:#c4b5fd;">Votre compte est prêt. Vous pouvez dès maintenant choisir votre formation.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Merci de rejoindre Lomé IA School. Voici comment ça marche :</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.5;">
                                @foreach (['Choisissez votre formation dans le catalogue.', 'Payez par Mobile Money (T-Money ou Flooz).', 'Accédez immédiatement à la formation et à son groupe privé.'] as $step)
                                    <tr>
                                        <td width="34" valign="top" style="padding:6px 0;">
                                            <span style="display:inline-block;width:24px;height:24px;line-height:24px;text-align:center;border-radius:12px;background:#ede9fe;color:#6d28d9;font-weight:bold;font-size:13px;">{{ $loop->iteration }}</span>
                                        </td>
                                        <td style="padding:6px 0;">{{ $step }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            <p style="margin:26px 0 0;text-align:center;">
                                <a href="{{ route('formations.index') }}" style="display:inline-block;background:{{ $gold }};color:{{ $night }};font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Découvrir les formations</a>
                            </p>
                            <p style="margin:14px 0 0;text-align:center;font-size:14px;">
                                <a href="{{ route('dashboard') }}" style="color:#6d28d9;">Accéder à mon espace</a>
                            </p>
                            <p style="margin:26px 0 0;font-size:14px;line-height:1.6;color:#4b5068;">
                                Une question ? Répondez simplement à cet e-mail ou écrivez-nous sur WhatsApp au {{ config('site.contact.phones.0') }}.
                            </p>
                            <p style="margin:18px 0 0;font-size:14px;line-height:1.6;color:#4b5068;">À très vite,<br><strong style="color:#1e2340;">{{ config('site.trainer.name') }}</strong><br>Lomé IA School</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f7f7fb;font-size:12px;line-height:1.5;color:#7a7f99;text-align:center;">
                            {{ config('site.contact.address') }}<br>
                            Vous recevez cet e-mail car vous avez créé un compte sur Lomé IA School.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
