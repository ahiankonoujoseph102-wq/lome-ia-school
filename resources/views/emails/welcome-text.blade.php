Bienvenue {{ $user->first_name }} !

Votre compte Lomé IA School est prêt. Voici comment ça marche :

1. Choisissez votre formation dans le catalogue : {{ route('formations.index') }}
2. Payez par Mobile Money (T-Money ou Flooz).
3. Accédez immédiatement à la formation et à son groupe privé.

Votre espace : {{ route('dashboard') }}

Une question ? Répondez à cet e-mail ou écrivez-nous sur WhatsApp au {{ config('site.contact.phones.0') }}.

À très vite,
{{ config('site.trainer.name') }}
Lomé IA School
