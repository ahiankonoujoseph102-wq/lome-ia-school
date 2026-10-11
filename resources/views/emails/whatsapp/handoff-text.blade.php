Un client WhatsApp attend l'équipe

L'agent WhatsApp s'est mis en pause pour ce client : à vous de lui répondre.

@if ($contact->profile_name)
Client : {{ $contact->profile_name }}
@endif
WhatsApp : {{ $contact->phone() }}
@if ($contact->needs_human_reason)
Raison : {{ $contact->needs_human_reason }}
@endif
Depuis le : {{ $contact->needs_human_at?->translatedFormat('l j F Y à H \h i') }} (heure de Lomé)

Voir et répondre dans l'admin : {{ route('admin.whatsapp.show', $contact) }}
@if ($excerpt !== '')

Derniers messages :
{{ $excerpt }}
@endif

WhatsApp n'autorise une réponse libre que dans les 24 heures qui suivent le dernier message du client.
