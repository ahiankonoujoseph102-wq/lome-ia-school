{{-- Version texte de l'e-mail groupé (texte brut : rien n'est échappé en HTML). --}}
Bonjour {!! $firstName !!},

@foreach ($paragraphs as $paragraph)
{!! $paragraph !!}

@endforeach
@if ($video)
Regarder la vidéo : {!! $video['url'] !!}

@endif
@if ($button)
{!! $button['label'] !!} : {!! $button['url'] !!}

@endif
À très vite,
{!! config('site.trainer.name') !!}
Lomé IA School

--
Lomé IA School
@if ($address !== '')
{!! $address !!}
@endif
@if ($whatsappNumber)
WhatsApp : {!! $whatsappNumber !!}
@endif
{!! $siteUrl !!}

Vous recevez cet e-mail car vous avez un compte sur Lomé IA School.
Se désabonner de ces e-mails : {!! $unsubscribeUrl !!}
Vous continuerez à recevoir les e-mails liés à votre compte et à vos achats.
