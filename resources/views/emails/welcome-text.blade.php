Bienvenue {{ $user->first_name }} !

Votre compte Lomé IA School est prêt. Voici comment ça marche :

1. Choisissez votre formation dans le catalogue : {{ route('formations.index') }}
2. Payez par Mobile Money et envoyez votre preuve de paiement sur le site.
3. Accédez à la formation et à son groupe privé depuis votre espace.

Votre espace : {{ route('dashboard') }}

Une question ? Répondez à cet e-mail ou écrivez-nous sur WhatsApp au {{ config('site.contact.phones.0') }}.

À très vite,
{{ config('site.trainer.name') }}
Lomé IA School
