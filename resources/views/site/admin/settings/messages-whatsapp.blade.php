@php
    use App\Support\WhatsAppMessages;
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p>Chaque bouton WhatsApp du site ouvre une conversation avec un message déjà écrit, adapté à la page. La personne n'a plus qu'à écrire sa question à la fin.</p>
        <p>Chaque message doit se terminer par « Ma question : » (ou « Mon problème : »). Les repères entre accolades sont remplacés automatiquement : <span class="text-slate-100">{formation}</span> nom de la formation, <span class="text-slate-100">{etape}</span> étape du paiement, <span class="text-slate-100">{date}</span> date, <span class="text-slate-100">{recu}</span> numéro du reçu. Champ vidé : le texte d'origine revient.</p>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach (WhatsAppMessages::TEMPLATES as $template => $details)
            <section class="card space-y-3 p-5 sm:p-6" data-test="whatsapp-template-{{ $template }}">
                <x-admin.field
                    :name="'messages['.$template.']'"
                    :label="$details['label']"
                    type="textarea"
                    :rows="3"
                    :value="WhatsAppMessages::template($template)"
                    :hint="$details['where'].'. Repères : '.($details['placeholders'] === [] ? 'aucun' : implode(', ', $details['placeholders'])).'.'"
                />
            </section>
        @endforeach

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
