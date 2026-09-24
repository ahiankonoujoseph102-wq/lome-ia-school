<?php

namespace App\Affiliates;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Liens d'affiliation (?ref=CODE) : mémorise le dernier lien cliqué pendant la durée prévue
 * (cookie « lis_ref » : code + date du clic) et compte chaque personne une seule fois par affilié.
 */
class ReferralTracker
{
    /** Robots des aperçus de liens (WhatsApp, Facebook…) et moteurs de recherche : ni clic ni attribution. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|curl|wget|python|headless/i';

    /**
     * Clic sur un lien : renvoie les cookies à poser, ou une liste vide si le lien est ignoré.
     *
     * @return list<Cookie>
     */
    public function track(Request $request, ?Formation $formation = null): array
    {
        $affiliate = Affiliate::findByCode($request->query('ref') ? (string) $request->query('ref') : null);

        if ($affiliate === null || $affiliate->isSuspended() || $this->isBot($request)) {
            return [];
        }

        // L'affilié qui clique sur son propre lien n'est ni compté ni attribué.
        if ($request->user() instanceof User && $request->user()->id === $affiliate->user_id) {
            return [];
        }

        $visitor = $request->cookies->getString($this->cookieName('visitor_cookie', 'lis_visitor'));
        $cookies = [];

        if (! Str::isUuid($visitor)) {
            $visitor = (string) Str::uuid();
            $cookies[] = cookie($this->cookieName('visitor_cookie', 'lis_visitor'), $visitor, 60 * 24 * 365);
        }

        $this->recordClick($affiliate, $formation, $visitor, $request);

        // Le dernier lien cliqué remplace le précédent.
        $cookies[] = cookie(
            $this->cookieName('cookie', 'lis_ref'),
            $affiliate->code.'|'.now()->getTimestamp(),
            60 * 24 * $this->days(),
        );

        return $cookies;
    }

    /**
     * Affilié du dernier lien cliqué, si le clic date de moins de la durée prévue.
     */
    public function current(Request $request): ?Affiliate
    {
        $value = $request->cookies->getString($this->cookieName('cookie', 'lis_ref'));

        if (! preg_match('/^([A-Za-z0-9]{1,20})\|(\d{9,11})$/', $value, $matches)) {
            return null;
        }

        $clickedAt = Carbon::createFromTimestamp((int) $matches[2]);

        if ($clickedAt->isFuture() || $clickedAt->lt(now()->subDays($this->days()))) {
            return null;
        }

        $affiliate = Affiliate::findByCode($matches[1]);

        return $affiliate !== null && ! $affiliate->isSuspended() ? $affiliate : null;
    }

    /**
     * Code à enregistrer sur un achat : jamais celui de l'acheteur lui-même (auto-parrainage).
     */
    public function codeFor(User $buyer, Request $request): ?string
    {
        $affiliate = $this->current($request);

        return $affiliate !== null && $affiliate->user_id !== $buyer->id ? $affiliate->code : null;
    }

    private function recordClick(Affiliate $affiliate, ?Formation $formation, string $visitor, Request $request): void
    {
        $visitorHash = $this->hash('visitor:'.$visitor);
        $ipHash = $this->hash('ip:'.$request->ip().'|'.$request->userAgent());

        // Même navigateur, ou même connexion et même appareil dans les dernières 24 heures : déjà compté.
        $alreadyCounted = AffiliateClick::query()
            ->where('affiliate_id', $affiliate->id)
            ->where(fn ($query) => $query
                ->where('visitor_hash', $visitorHash)
                ->orWhere(fn ($query) => $query->where('ip_hash', $ipHash)->where('created_at', '>=', now()->subDay())))
            ->exists();

        if (! $alreadyCounted) {
            AffiliateClick::query()->insertOrIgnore([
                'affiliate_id' => $affiliate->id,
                'formation_id' => $formation?->id,
                'visitor_hash' => $visitorHash,
                'ip_hash' => $ipHash,
                'created_at' => now(),
            ]);
        }
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::BOT_PATTERN, $agent) === 1;
    }

    /**
     * @param  non-empty-string  $default
     * @return non-empty-string
     */
    private function cookieName(string $key, string $default): string
    {
        $name = (string) config('affiliate.'.$key);

        return $name !== '' ? $name : $default;
    }

    private function days(): int
    {
        return max(1, (int) config('affiliate.cookie_days', 30));
    }
}
