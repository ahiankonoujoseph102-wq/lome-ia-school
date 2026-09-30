@php
    use App\Models\AssistantHandoff;
    use App\Support\PhoneNumber;
@endphp
Question transmise par Ama

{{ $handoff->first_name }} souhaite une réponse personnelle de Coach Joseph.

Question :
{{ $handoff->question }}

Réponse souhaitée : {{ $handoff->channel === AssistantHandoff::EMAIL ? 'par e-mail ('.$handoff->email.')' : 'sur WhatsApp ('.PhoneNumber::format($handoff->phone).')' }}
@if ($handoff->page)
Page d'origine : {{ $handoff->page }}
@endif
@if ($handoff->formation)
Formation : {{ $handoff->formation }}
@endif
Envoyée depuis : {{ $handoff->sourceLabel() }}
Reçue le : {{ $handoff->created_at?->translatedFormat('l j F Y à H \h i') }} (heure de Lomé)

@if ($whatsappUrl)
Répondre sur WhatsApp : {{ $whatsappUrl }}
@else
Pour répondre, utilisez simplement « Répondre » : votre réponse partira à {{ $handoff->email }}.
@endif
@if ($handoff->excerpt)

Extrait de la conversation avec Ama :
{{ $handoff->excerpt }}
@endif

Voir les demandes transmises : {{ route('admin.assistant.handoffs.index') }}
