<?php

namespace App\Support;

use App\Models\Formation;
use App\Payments\PaymentMethods;

/**
 * Page d'une formation payante (novembre 2026, après l'étude des sites de formation professionnels) :
 * « L'essentiel » (fiche d'identité sous le prix) et « Comment se déroule la formation » (parcours de l'élève).
 *
 * Uniquement des faits vrais pour toutes les formations enregistrées, fournis par Joseph : vidéos pas à pas avec
 * les documents de chaque leçon, un téléphone ou un ordinateur avec internet suffit, accès immédiat (ou après
 * vérification au-delà du seuil), accès à vie, accompagnement de 3 mois, attestation de fin de formation,
 * paiement Mobile Money. Aucune durée, aucun niveau, aucun chiffre inventé.
 */
final class FormationOverview
{
    public const string EQUIPMENT = 'Un téléphone ou un ordinateur, avec une connexion internet';

    /**
     * Cases de « L'essentiel ». Les titres de l'accès et de l'accompagnement viennent des garanties
     * (config site.guarantees, modifiables dans l'admin).
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function essentials(Formation $formation): array
    {
        $guarantees = collect($formation->guarantees())->keyBy('icon');
        $access = $formation->needsPaymentVerification()
            ? (array) config('site.verification_guarantee')
            : (array) collect((array) config('site.guarantees', []))->firstWhere('instant', true);

        $items = [
            ['icon' => 'play-circle', 'title' => 'Vidéos pas à pas', 'text' => 'Avec les documents de chaque leçon'],
            ['icon' => 'device-phone-mobile', 'title' => 'Téléphone ou ordinateur', 'text' => 'Avec une connexion internet'],
            [
                'icon' => (string) ($access['icon'] ?? 'bolt'),
                'title' => (string) ($access['title'] ?? 'Accès immédiat'),
                'text' => $formation->needsPaymentVerification() ? 'Dès que votre paiement est vérifié' : 'Dès l\'envoi de votre preuve de paiement',
            ],
            ['icon' => 'lock-open', 'title' => (string) ($guarantees->get('lock-open')['title'] ?? 'Accès à vie'), 'text' => 'Revoyez tout, sans date limite'],
            ['icon' => 'lifebuoy', 'title' => (string) ($guarantees->get('lifebuoy')['title'] ?? 'Accompagnement de 3 mois'), 'text' => 'Groupe privé ou WhatsApp'],
        ];

        if (self::hasCertificate($formation)) {
            $items[] = ['icon' => 'document-check', 'title' => 'Attestation', 'text' => 'Remise à la fin de la formation'];
        }

        $items[] = ['icon' => 'language', 'title' => 'En français', 'text' => 'À suivre à votre rythme'];

        $countries = array_column(PaymentMethods::countries(), 'name');
        $items[] = ['icon' => 'banknotes', 'title' => 'Paiement Mobile Money', 'text' => $countries ? self::join($countries) : 'Depuis votre téléphone'];

        return $items;
    }

    /**
     * Étapes de « Comment se déroule la formation », dans l'ordre réel du parcours.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function journey(Formation $formation): array
    {
        // Noms des pays, indexés par leur code (pour l'article : « le Togo », « la Côte d'Ivoire »).
        $countries = array_map(fn (array $country): string => $country['name'], PaymentMethods::countries());

        $steps = [
            [
                'icon' => 'banknotes',
                'title' => 'Vous payez par Mobile Money',
                'text' => ($countries ? 'Depuis '.self::join(array_map(PaymentMethods::countryWithArticle(...), array_keys($countries), $countries), ' ou ') : 'Depuis votre téléphone').', puis vous envoyez la capture de votre paiement sur le site.',
            ],
            [
                'icon' => 'lock-open',
                'title' => 'La formation s\'ouvre dans votre espace',
                'text' => $formation->needsPaymentVerification()
                    ? 'Dès que votre paiement est vérifié, '.config('site.payments.verification_hours').', avec son groupe privé.'
                    : 'Tout de suite, avec son groupe privé de discussion.',
            ],
            [
                'icon' => 'play-circle',
                'title' => 'Vous suivez les vidéos pas à pas',
                'text' => 'Sur votre téléphone ou votre ordinateur, à votre rythme, avec les documents de chaque leçon.',
            ],
            [
                'icon' => 'chat-bubble-left-right',
                'title' => 'Vous posez vos questions',
                'text' => 'Dans le groupe privé de la formation ou sur WhatsApp, pendant 3 mois après la formation.',
            ],
        ];

        if (self::hasCertificate($formation)) {
            $steps[] = ['icon' => 'document-check', 'title' => 'Vous recevez votre attestation', 'text' => 'Une attestation de fin de formation vous est remise.'];
        }

        return $steps;
    }

    private static function hasCertificate(Formation $formation): bool
    {
        return collect($formation->afterTraining())->contains(fn (array $item): bool => str_contains(mb_strtolower($item['title']), 'attestation'));
    }

    /**
     * « Togo, Burkina Faso, Bénin et Côte d'Ivoire ».
     *
     * @param  list<string>  $names
     */
    private static function join(array $names, string $last = ' et '): string
    {
        if (count($names) < 2) {
            return implode('', $names);
        }

        return implode(', ', array_slice($names, 0, -1)).$last.end($names);
    }
}
