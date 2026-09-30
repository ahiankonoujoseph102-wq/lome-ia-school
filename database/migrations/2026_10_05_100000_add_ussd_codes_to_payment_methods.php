<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Page de paiement simplifiée (capture seule), pour les réglages déjà enregistrés depuis l'admin :
 * - codes USSD de transfert (bouton « Payer maintenant ») ajoutés aux moyens de paiement (« site.payments.methods ») ;
 *   un moyen qui a déjà un code, même vide, n'est jamais modifié ;
 * - réponse de la FAQ « Comment se passe le paiement ? » mise à jour seulement si elle n'a jamais été modifiée
 *   (elle demandait encore l'identifiant de la transaction).
 * Sans réglage en base, le site lit directement config/site.php, déjà à jour.
 */
return new class extends Migration
{
    /**
     * Codes relevés en septembre 2026 (clé du moyen => [pays, code]).
     */
    private const array CODES = [
        'tg-mixx' => ['tg', '*145*1#'],
        'tg-flooz' => ['tg', '*155*1#'],
        'bf-orange' => ['bf', '*144*2#'],
        'bf-wave' => ['bf', ''],
        'bj-mtn' => ['bj', '*880#'],
        'bj-moov' => ['bj', '*855*1#'],
        'bj-celtiis' => ['bj', '*889#'],
    ];

    private const string OLD_FAQ_ANSWER = 'Par Mobile Money : vous envoyez le montant exact sur l\'un de nos numéros (Togo, Burkina Faso ou Bénin), puis vous déposez sur le site l\'identifiant de la transaction et une capture d\'écran du paiement. Les frais de transfert sont à la charge de l\'expéditeur.';

    private const string NEW_FAQ_ANSWER = 'Par Mobile Money : vous envoyez le montant exact sur l\'un de nos numéros (Togo, Burkina Faso ou Bénin) depuis votre téléphone, puis vous envoyez sur le site la capture d\'écran de votre paiement. Rien d\'autre à remplir. Les frais de transfert sont à la charge de l\'expéditeur.';

    public function up(): void
    {
        $this->addUssdCodes();
        $this->updateFaq();

        Cache::forget('site-settings');
    }

    private function addUssdCodes(): void
    {
        $row = DB::table('site_settings')->where('key', 'site.payments.methods')->first();

        if ($row === null) {
            return;
        }

        $methods = json_decode((string) $row->value, true);

        if (! is_array($methods)) {
            return;
        }

        foreach ($methods as $key => $method) {
            if (! is_array($method) || array_key_exists('ussd', $method)) {
                continue;
            }

            [$country, $code] = self::CODES[$key] ?? [null, ''];
            // Un moyen inconnu, ou dont le pays a changé, reçoit un code vide (pas de bouton).
            $methods[$key]['ussd'] = $country !== null && ($method['country'] ?? null) === $country ? $code : '';
        }

        DB::table('site_settings')->where('key', 'site.payments.methods')->update([
            'value' => json_encode($methods, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function updateFaq(): void
    {
        $row = DB::table('site_settings')->where('key', 'site.faq')->first();
        $faq = $row !== null ? json_decode((string) $row->value, true) : null;

        if (! is_array($faq) || ! is_array($faq['home'] ?? null)) {
            return;
        }

        $changed = false;

        foreach ($faq['home'] as $index => $item) {
            if (is_array($item) && ($item['answer'] ?? null) === self::OLD_FAQ_ANSWER) {
                $faq['home'][$index]['answer'] = self::NEW_FAQ_ANSWER;
                $changed = true;
            }
        }

        if ($changed) {
            DB::table('site_settings')->where('key', 'site.faq')->update([
                'value' => json_encode($faq, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Rien à défaire : le code USSD est ignoré par une version précédente du site.
    }
};
