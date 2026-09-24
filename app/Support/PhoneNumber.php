<?php

namespace App\Support;

/**
 * Numéros WhatsApp : normalisation au format international E.164 (ex. +22890123456) et affichage.
 *
 * Chaque pays a son plan de numérotation : longueur du numéro national et, pour certains pays
 * seulement, un « 0 » initial qui n'est qu'un préfixe national (France, Ghana, Nigeria…) et disparaît
 * au format international. Dans la plupart des pays d'Afrique de l'Ouest (Bénin, Burkina Faso,
 * Côte d'Ivoire…), le 0 fait partie du numéro et doit être conservé.
 */
class PhoneNumber
{
    public const string DEFAULT_DIAL_CODE = '+228';

    /**
     * Pays proposés dans le formulaire (indicatif => libellé). Pour un autre pays,
     * l'élève tape directement le numéro complet commençant par « + ».
     *
     * @var array<string, string>
     */
    public const array COUNTRIES = [
        '+228' => '🇹🇬 Togo (+228)',
        '+229' => '🇧🇯 Bénin (+229)',
        '+226' => '🇧🇫 Burkina Faso (+226)',
        '+225' => '🇨🇮 Côte d\'Ivoire (+225)',
        '+233' => '🇬🇭 Ghana (+233)',
        '+227' => '🇳🇪 Niger (+227)',
        '+223' => '🇲🇱 Mali (+223)',
        '+221' => '🇸🇳 Sénégal (+221)',
        '+224' => '🇬🇳 Guinée (+224)',
        '+245' => '🇬🇼 Guinée-Bissau (+245)',
        '+234' => '🇳🇬 Nigeria (+234)',
        '+220' => '🇬🇲 Gambie (+220)',
        '+231' => '🇱🇷 Liberia (+231)',
        '+232' => '🇸🇱 Sierra Leone (+232)',
        '+222' => '🇲🇷 Mauritanie (+222)',
        '+238' => '🇨🇻 Cap-Vert (+238)',
        '+237' => '🇨🇲 Cameroun (+237)',
        '+241' => '🇬🇦 Gabon (+241)',
        '+242' => '🇨🇬 Congo (+242)',
        '+243' => '🇨🇩 RD Congo (+243)',
        '+33' => '🇫🇷 France (+33)',
        '+32' => '🇧🇪 Belgique (+32)',
        '+1' => '🇨🇦 Canada / États-Unis (+1)',
    ];

    /**
     * Plans de numérotation : longueurs possibles du numéro national (sans l'indicatif)
     * et « trunk » = true si un 0 initial saisi localement doit être retiré.
     *
     * @var array<string, array{lengths: list<int>, trunk: bool}>
     */
    public const array PLANS = [
        '+228' => ['lengths' => [8], 'trunk' => false],
        // Depuis le 30 novembre 2024, tous les numéros du Bénin ont 10 chiffres et commencent par 01.
        '+229' => ['lengths' => [10], 'trunk' => false],
        '+226' => ['lengths' => [8], 'trunk' => false],
        '+225' => ['lengths' => [10], 'trunk' => false],
        '+233' => ['lengths' => [9], 'trunk' => true],
        '+227' => ['lengths' => [8], 'trunk' => false],
        '+223' => ['lengths' => [8], 'trunk' => false],
        '+221' => ['lengths' => [9], 'trunk' => false],
        '+224' => ['lengths' => [9], 'trunk' => false],
        '+245' => ['lengths' => [9], 'trunk' => false],
        '+234' => ['lengths' => [10], 'trunk' => true],
        '+220' => ['lengths' => [7], 'trunk' => false],
        '+231' => ['lengths' => [7, 8, 9], 'trunk' => true],
        '+232' => ['lengths' => [8], 'trunk' => true],
        '+222' => ['lengths' => [8], 'trunk' => false],
        '+238' => ['lengths' => [7], 'trunk' => false],
        '+237' => ['lengths' => [9], 'trunk' => false],
        '+241' => ['lengths' => [8], 'trunk' => false],
        '+242' => ['lengths' => [9], 'trunk' => false],
        '+243' => ['lengths' => [9], 'trunk' => true],
        '+33' => ['lengths' => [9], 'trunk' => true],
        '+32' => ['lengths' => [8, 9], 'trunk' => true],
        '+1' => ['lengths' => [10], 'trunk' => false],
    ];

    /**
     * Retourne le numéro au format international, ou null s'il n'est pas valide.
     *
     * Un numéro commençant par « + » ou « 00 » est pris tel quel ; sinon l'indicatif choisi est ajouté.
     * Aucun chiffre n'est retiré, sauf le 0 de préfixe national des pays qui en ont un.
     */
    public static function normalize(?string $number, ?string $dialCode = null): ?string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        $digits = (string) preg_replace('/[^\d+]/', '', $number);

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '+')) {
            [$code, $national] = self::split($digits);

            // Pays hors liste : format international générique, sans modification.
            if ($code === '') {
                return preg_match('/^\+[1-9]\d{7,14}$/', $digits) ? $digits : null;
            }
        } else {
            $code = array_key_exists((string) $dialCode, self::COUNTRIES) ? (string) $dialCode : self::DEFAULT_DIAL_CODE;
            $national = $digits;
        }

        if (! ctype_digit($national)) {
            return null;
        }

        $plan = self::PLANS[$code] ?? null;

        if ($plan !== null) {
            $national = self::applyPlan($code, $national, $plan);

            if ($national === null) {
                return null;
            }
        }

        $e164 = $code.$national;

        return preg_match('/^\+[1-9]\d{7,14}$/', $e164) ? $e164 : null;
    }

    /**
     * Répare un numéro déjà enregistré auquel il manque le 0 initial (ancienne normalisation),
     * ou un ancien numéro béninois à 8 chiffres. Retourne null si le numéro est déjà correct ou non réparable.
     */
    public static function repair(string $e164): ?string
    {
        [$code, $national] = self::split($e164);
        $plan = self::PLANS[$code] ?? null;

        if ($plan === null || $plan['trunk'] || ! ctype_digit($national) || in_array(strlen($national), $plan['lengths'], true)) {
            return null;
        }

        $candidates = $code === '+229' && strlen($national) === 8
            ? ['01'.$national]
            : ['0'.$national];

        foreach ($candidates as $candidate) {
            if (in_array(strlen($candidate), $plan['lengths'], true)) {
                return $code.$candidate;
            }
        }

        return null;
    }

    /**
     * Affichage lisible : +228 90 12 34 56, +229 01 94 55 59 14, sinon indicatif puis groupes de chiffres.
     */
    public static function format(?string $e164): string
    {
        if ($e164 === null || $e164 === '') {
            return '';
        }

        [$dialCode, $local] = self::split($e164);

        if ($dialCode === '') {
            return $e164;
        }

        return trim($dialCode.' '.self::groupDigits($local));
    }

    /**
     * Numéro national découpé en groupes (paires, avec un premier groupe de 3 chiffres si le nombre est impair).
     */
    public static function groupDigits(string $local): string
    {
        $head = strlen($local) % 2 === 1 ? substr($local, 0, 3) : '';
        $rest = $head === '' ? $local : substr($local, 3);

        return trim($head.' '.implode(' ', $rest === '' ? [] : str_split($rest, 2)));
    }

    /**
     * Sépare l'indicatif (s'il fait partie de la liste) du numéro local.
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $e164): array
    {
        $codes = array_keys(self::COUNTRIES);
        usort($codes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($e164, $code)) {
                return [$code, substr($e164, strlen($code))];
            }
        }

        return ['', $e164];
    }

    /**
     * @param  array{lengths: list<int>, trunk: bool}  $plan
     */
    private static function applyPlan(string $code, string $national, array $plan): ?string
    {
        // Préfixe national : le 0 initial disparaît au format international (ex. France 06… → +33 6…).
        if ($plan['trunk'] && str_starts_with($national, '0') && in_array(strlen($national) - 1, $plan['lengths'], true)) {
            $national = substr($national, 1);
        }

        // Ancien numéro béninois à 8 chiffres : il devient 01 suivi des 8 chiffres.
        if ($code === '+229' && strlen($national) === 8) {
            $national = '01'.$national;
        }

        return in_array(strlen($national), $plan['lengths'], true) ? $national : null;
    }
}
