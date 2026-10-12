{{-- E-mail d'un envoi groupé de l'admin (App\Mail\NewsletterMail). Styles en ligne, lisible sur téléphone (560 px au plus). --}}
@php
    $gold = '#fbbf24';
    $night = '#0b1030';
    $muted = '#4b5068';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $newsletter->subject }}</title>
    <style>a { color: #6d28d9; }</style>
</head>
<body style="margin:0;padding:0;background:#eef0f7;font-family:Helvetica,Arial,sans-serif;color:#1e2340;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f7;padding:20px 10px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="background:{{ $night }};background-image:linear-gradient(135deg,#0b1030 0%,#3b1c8c 100%);padding:22px 24px;">
                            <a href="{{ $siteUrl }}" style="text-decoration:none;color:#ffffff;">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="Lomé IA School" height="40" style="display:block;height:40px;width:auto;max-width:100%;border:0;">
                                @else
                                    <span style="font-size:17px;font-weight:bold;color:#ffffff;"><span style="display:inline-block;width:8px;height:18px;background:{{ $gold }};border-radius:2px;vertical-align:middle;margin-right:8px;"></span>Lomé IA School</span>
                                @endif
                            </a>
                        </td>
                    </tr>

                    @if ($imageUrl)
                        <tr>
                            <td style="padding:0;">
                                <img src="{{ $imageUrl }}" alt="" width="560" style="display:block;width:100%;max-width:560px;height:auto;border:0;">
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:26px 24px 8px;font-size:16px;line-height:1.65;color:#1e2340;">
                            <p style="margin:0 0 16px;">Bonjour {{ $firstName }},</p>
                            @foreach ($paragraphs as $paragraph)
                                <p style="margin:0 0 16px;word-wrap:break-word;">{{ \App\Chat\MessageText::toHtml($paragraph) }}</p>
                            @endforeach
                        </td>
                    </tr>

                    @if ($video)
                        <tr>
                            <td style="padding:6px 24px 8px;" data-test="newsletter-video">
                                <a href="{{ $video['url'] }}" style="text-decoration:none;">
                                    @if ($video['thumbnail'])
                                        <img src="{{ $video['thumbnail'] }}" alt="Regarder la vidéo" width="512" style="display:block;width:100%;max-width:512px;height:auto;border:0;border-radius:14px;background:{{ $night }};">
                                    @endif
                                </a>
                                <p style="margin:14px 0 0;text-align:center;">
                                    <a href="{{ $video['url'] }}" style="display:inline-block;background:{{ $night }};color:#ffffff;font-weight:bold;font-size:15px;text-decoration:none;padding:12px 22px;border-radius:999px;border:2px solid {{ $gold }};">&#9654;&nbsp; Regarder la vidéo</a>
                                </p>
                            </td>
                        </tr>
                    @endif

                    @if ($button)
                        <tr>
                            <td style="padding:16px 24px 6px;text-align:center;" data-test="newsletter-button">
                                <a href="{{ $button['url'] }}" style="display:inline-block;background:{{ $gold }};color:{{ $night }};font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">{{ $button['label'] }}</a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:18px 24px 26px;font-size:15px;line-height:1.6;color:{{ $muted }};">
                            À très vite,<br><strong style="color:#1e2340;">{{ config('site.trainer.name') }}</strong><br>Lomé IA School
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 24px;background:#f7f7fb;font-size:13px;line-height:1.6;color:#6b7089;text-align:center;">
                            <p style="margin:0;font-weight:bold;color:#1e2340;">Lomé IA School</p>
                            @if ($address !== '')
                                <p style="margin:4px 0 0;">{{ $address }}</p>
                            @endif
                            @if ($whatsappNumber)
                                <p style="margin:4px 0 0;">WhatsApp : <a href="{{ $whatsappUrl }}" style="color:#6d28d9;text-decoration:underline;">{{ $whatsappNumber }}</a></p>
                            @endif
                            <p style="margin:4px 0 0;"><a href="{{ $siteUrl }}" style="color:#6d28d9;text-decoration:underline;">{{ $siteHost }}</a></p>
                            <p style="margin:14px 0 0;font-size:12px;color:#7a7f99;">Vous recevez cet e-mail car vous avez un compte sur Lomé IA School.</p>
                            <p style="margin:6px 0 0;font-size:12px;">
                                <a href="{{ $unsubscribeUrl }}" style="color:#4b5068;text-decoration:underline;" data-test="newsletter-unsubscribe">Se désabonner de ces e-mails</a>
                            </p>
                            <p style="margin:6px 0 0;font-size:11px;color:#8a8fa8;">Vous continuerez à recevoir les e-mails liés à votre compte et à vos achats.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
