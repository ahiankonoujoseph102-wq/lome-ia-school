Nouvelle demande de devis

Nom : {{ $quote->name }}
@if ($quote->organization)
Structure : {{ $quote->organization }}
@endif
Besoin : {{ $quote->serviceLabel() }}
E-mail : {{ $quote->email }}
@if ($quote->phoneLabel())
WhatsApp : {{ $quote->phoneLabel() }}
@endif

Message :
{{ $quote->message }}

Voir les demandes de devis : {{ route('admin.quotes.index') }}
