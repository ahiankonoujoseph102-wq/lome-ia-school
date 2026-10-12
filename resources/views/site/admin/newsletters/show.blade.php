@php
    use App\Enums\NewsletterStatus;

    $daysLabel = match (true) {
        $days === null => null,
        $days <= 1 => 'aujourd\'hui',
        default => 'environ '.$days.' jours',
    };
@endphp

<x-admin.page :title="$newsletter->subject" :subtitle="$newsletter->status->label()" :back="route('admin.newsletters.index')" back-label="Envoi d'e-mails" width="max-w-4xl">
    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    @if ($newsletter->isDraft())
        <section class="card space-y-4 p-5 sm:p-6" data-test="newsletter-draft-summary">
            <p class="text-sm text-slate-300">Destinataires : {{ $newsletter->audience->label() }}.</p>
            <p class="text-3xl font-bold text-white tabular-nums" data-test="newsletter-recipients">{{ $recipients }} <span class="text-base font-medium text-slate-400">{{ $recipients > 1 ? 'destinataires' : 'destinataire' }}</span></p>
            @if ($days === null)
                <x-site.alert type="error">Le plafond des envois groupés est à 0 : réglez-le dans Réglages du site &gt; E-mails envoyés.</x-site.alert>
            @elseif ($recipients > 0)
                <p class="text-sm text-slate-300" data-test="newsletter-days">Durée de l'envoi : <strong class="text-white">{{ $daysLabel }}</strong> ({{ $dailyLimit }} e-mails groupés par jour au maximum, il en reste {{ $availableToday }} aujourd'hui). La suite part toute seule les jours suivants.</p>
            @endif
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.newsletters.edit', $newsletter) }}" class="btn-ghost"><flux:icon.pencil-square class="size-5" /> Modifier</a>
                <form method="POST" action="{{ route('admin.newsletters.test', $newsletter) }}">
                    @csrf
                    <button type="submit" class="btn-ghost" data-test="newsletter-test"><flux:icon.paper-airplane class="size-5" /> M'envoyer un test</button>
                </form>
            </div>
        </section>
    @else
        <section class="card p-5 sm:p-6" data-test="newsletter-stats">
            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                @foreach ([
                    ['Destinataires', $newsletter->recipients_count],
                    ['Envoyés', $stats['sent']],
                    ['En attente', $stats['pending']],
                    ['En échec', $stats['failed']],
                    ['Non envoyés (désabonnés, bloqués, annulés)', $stats['skipped'] + $stats['cancelled']],
                    ['Désabonnements', $stats['unsubscribed']],
                ] as [$label, $value])
                    <div class="rounded-2xl bg-night-900 p-3 ring-1 ring-night-600">
                        <dt class="text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-3 text-xs text-slate-400">
                Lancé le {{ $newsletter->started_at?->timezone('Africa/Lome')->translatedFormat('j F Y à H \h i') }}
                @if ($newsletter->completed_at) · terminé le {{ $newsletter->completed_at->timezone('Africa/Lome')->translatedFormat('j F Y à H \h i') }} @endif
                @if ($newsletter->cancelled_at) · annulé le {{ $newsletter->cancelled_at->timezone('Africa/Lome')->translatedFormat('j F Y à H \h i') }} @endif
            </p>
            @if ($newsletter->status === NewsletterStatus::Sending)
                <p class="mt-2 text-sm text-slate-300">{{ $dailyLimit }} e-mails groupés par jour au maximum ; encore {{ $availableToday }} possibles aujourd'hui. La suite part toute seule.</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($newsletter->status === NewsletterStatus::Sending)
                    <form method="POST" action="{{ route('admin.newsletters.pause', $newsletter) }}">@csrf<button type="submit" class="btn-ghost" data-test="newsletter-pause"><flux:icon.pause class="size-5" /> Mettre en pause</button></form>
                @elseif ($newsletter->status === NewsletterStatus::Paused)
                    <form method="POST" action="{{ route('admin.newsletters.resume', $newsletter) }}">@csrf<button type="submit" class="btn-gold" data-test="newsletter-resume"><flux:icon.play class="size-5" /> Reprendre l'envoi</button></form>
                @endif
                @if (in_array($newsletter->status, [NewsletterStatus::Sending, NewsletterStatus::Paused], true))
                    <form method="POST" action="{{ route('admin.newsletters.cancel', $newsletter) }}" x-data x-on:submit="if (! confirm('Annuler l\'envoi ? Les e-mails pas encore partis ne partiront jamais.')) $event.preventDefault()">@csrf<button type="submit" class="btn-ghost text-rose-200" data-test="newsletter-cancel"><flux:icon.x-circle class="size-5" /> Annuler l'envoi</button></form>
                @endif
            </div>
        </section>
    @endif

    <section class="space-y-3" x-data="{ text: false }">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-white">Aperçu de l'e-mail reçu</h2>
            <button type="button" class="text-sm text-slate-300 underline underline-offset-4" x-on:click="text = ! text" x-text="text ? 'Voir la version mise en forme' : 'Voir la version texte'">Voir la version texte</button>
        </div>
        <p class="text-xs text-slate-400">Avec votre prénom ; chaque personne reçoit le sien.</p>
        <div class="mx-auto w-full max-w-[400px] overflow-hidden rounded-[28px] bg-night-900 p-2 ring-1 ring-night-600">
            <iframe x-show="! text" src="{{ route('admin.newsletters.preview', $newsletter) }}" title="Aperçu de l'e-mail" class="block h-[640px] w-full rounded-[22px] bg-[#eef0f7]" loading="lazy" data-test="newsletter-preview"></iframe>
            <iframe x-show="text" x-cloak src="{{ route('admin.newsletters.preview', [$newsletter, 'version' => 'texte']) }}" title="Version texte de l'e-mail" class="block h-[640px] w-full rounded-[22px] bg-[#eef0f7]" loading="lazy"></iframe>
        </div>
    </section>

    @if ($newsletter->isDraft() && $recipients > 0 && $days !== null)
        <section class="card space-y-4 p-5 sm:p-6" x-data="{ confirming: false }" data-test="newsletter-send">
            <h2 class="font-semibold text-white">Envoyer pour de vrai</h2>
            <button type="button" class="btn-gold w-full sm:w-auto" x-show="! confirming" x-on:click="confirming = true">Envoyer à {{ $recipients }} {{ $recipients > 1 ? 'personnes' : 'personne' }}</button>
            <div x-show="confirming" x-cloak class="space-y-4 rounded-2xl bg-night-900 p-4 shadow-[inset_0_0_0_2px_var(--color-gold-500)]">
                <p class="text-white" data-test="newsletter-confirm-text">
                    Cet e-mail sera envoyé à <strong>{{ $recipients }} {{ $recipients > 1 ? 'personnes' : 'personne' }}</strong>.
                    Durée de l'envoi : <strong>{{ $days <= 1 ? '1 jour (aujourd\'hui)' : 'environ '.$days.' jours' }}</strong>, {{ $dailyLimit }} au maximum par jour.
                </p>
                <p class="text-sm text-slate-300">Une fois lancé, l'e-mail ne peut plus être modifié ; l'envoi peut être mis en pause ou annulé.</p>
                <form method="POST" action="{{ route('admin.newsletters.send', $newsletter) }}" class="flex flex-wrap gap-2">
                    @csrf
                    <input type="hidden" name="confirm_count" value="{{ $recipients }}">
                    <button type="submit" class="btn-gold" data-test="newsletter-confirm">Oui, envoyer à {{ $recipients }} {{ $recipients > 1 ? 'personnes' : 'personne' }}</button>
                    <button type="button" class="btn-ghost" x-on:click="confirming = false">Annuler</button>
                </form>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.newsletters.destroy', $newsletter) }}" x-data x-on:submit="if (! confirm('Supprimer ce brouillon ?')) $event.preventDefault()">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-rose-300 underline underline-offset-4">Supprimer ce brouillon</button>
        </form>
    @endif
</x-admin.page>
