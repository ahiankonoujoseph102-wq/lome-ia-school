@php
    use App\Mail\MailQuota;
    use App\Newsletters\NewsletterQuota;
    use App\Settings\SiteSettings;

    $quota = (array) SiteSettings::get('site.mail_quota');
    $limit = MailQuota::dailyLimit();
    $reserve = MailQuota::reserve();
    $sent = MailQuota::sentToday();
    $pending = MailQuota::pendingAnnouncements();
    $available = MailQuota::availableForAnnouncements();
    $percent = min(100, (int) round(($sent / $limit) * 100));
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p>Tous les e-mails du site passent par votre fournisseur (Brevo), qui en permet un nombre limité par jour. Le site compte chaque e-mail parti (codes de confirmation, inscriptions, paiements, récapitulatifs des groupes, annonces…). La journée recommence à minuit, heure de Lomé.</p>
        <p>Une <strong class="text-white">annonce de groupe par e-mail</strong> est refusée si elle entamerait la réserve : les codes de confirmation et les e-mails de paiement gardent toujours leur place. Ces e-mails-là ne sont jamais bloqués par le site.</p>
        <p>Les <strong class="text-white">envois groupés</strong> (Élèves &gt; Envoi d'e-mails) ne dépassent jamais leur plafond du jour ni la réserve, et attendent les annonces des groupes déjà en cours : la suite part le lendemain.</p>
    </div>

    <section class="card p-5 sm:p-6" data-test="mail-quota-today">
        <h2 class="font-semibold text-white">Aujourd'hui</h2>
        <p class="mt-2 text-3xl font-bold text-white tabular-nums">{{ $sent }} <span class="text-base font-medium text-slate-400">/ {{ $limit }} e-mails partis</span></p>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-night-800 ring-1 ring-night-600">
            <div class="h-full rounded-full bg-electric-500" style="width: {{ $percent }}%"></div>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-2xl bg-night-900 p-3 ring-1 ring-night-600">
                <dt class="text-slate-400">Annonces en cours d'envoi</dt>
                <dd class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $pending }}</dd>
            </div>
            <div class="rounded-2xl bg-night-900 p-3 ring-1 ring-night-600">
                <dt class="text-slate-400">Réserve gardée</dt>
                <dd class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $reserve }}</dd>
            </div>
            <div class="rounded-2xl bg-night-900 p-3 ring-1 ring-night-600">
                <dt class="text-slate-400">Encore possibles pour une annonce</dt>
                <dd class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $available }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-slate-400">Le chiffre exact de Brevo se lit dans son tableau de bord (Statistiques des e-mails transactionnels) ; le compteur du site ne voit que les e-mails envoyés par ce site.</p>
    </section>

    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-5 p-5 sm:p-6">
            <x-admin.field name="daily_limit" label="E-mails par jour permis par votre offre Brevo" type="number" :value="old('daily_limit', $quota['daily_limit'] ?? 300)" hint="Offre gratuite de Brevo : 300 par jour. Vérifiez le chiffre de votre offre dans Brevo." />
            <x-admin.field name="reserve" label="Réserve à garder pour les codes, inscriptions et paiements" type="number" :value="old('reserve', $quota['reserve'] ?? 100)" hint="Une annonce par e-mail n'est envoyée que si elle laisse au moins cette réserve pour le reste de la journée." />
            <x-admin.field name="bulk_daily_limit" label="Plafond des envois groupés par jour (Envoi d'e-mails)" type="number" :value="old('bulk_daily_limit', $quota['bulk_daily_limit'] ?? NewsletterQuota::DEFAULT_DAILY_LIMIT)" hint="Au plus « quota moins réserve ». Au-delà, l'envoi continue tout seul le lendemain. Aujourd'hui : {{ NewsletterQuota::sentToday() }} e-mails groupés envoyés." />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
