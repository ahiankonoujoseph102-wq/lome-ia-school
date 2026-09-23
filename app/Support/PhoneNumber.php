<?php

namespace App\Support;

/**
 * Numéros WhatsApp : normalisation au format international E.164 (ex. +22890123456) et affichage.
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
        '+233' => '🇬🇭 Ghana (+233)',
        '+225' => '🇨🇮 Côte d\'Ivoire (+225)',
        '+226' => '🇧🇫 Burkina Faso (+226)',
        '+227' => '🇳🇪 Niger (+227)',
        '+223' => '🇲🇱 Mali (+223)',
        '+221' => '🇸🇳 Sénégal (+221)',
        '+224' => '🇬🇳 Guinée (+224)',
        '+234' => '🇳🇬 Nigeria (+234)',
        '+237' => '🇨🇲 Cameroun (+237)',
        '+241' => '🇬🇦 Gabon (+241)',
        '+242' => '🇨🇬 Congo (+242)',
        '+243' => '🇨🇩 RD Congo (+243)',
        '+33' => '🇫🇷 France (+33)',
        '+32' => '🇧🇪 Belgique (+32)',
        '+1' => '🇨🇦 Canada / États-Unis (+1)',
    ];

    /**
     * Retourne le numéro au format international, ou null s'il n'est pas valide.
     *
     * Un numéro commençant par « + » ou « 00 » est pris tel quel ; sinon l'indicatif choisi est ajouté.
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

        if (! str_starts_with($digits, '+')) {
            $dialCode = array_key_exists((string) $dialCode, self::COUNTRIES) ? (string) $dialCode : self::DEFAULT_DIAL_CODE;
            $digits = $dialCode.ltrim($digits, '0');
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $digits)) {
            return null;
        }

        // Les numéros togolais comptent 8 chiffres après l'indicatif.
        if (str_starts_with($digits, '+228') && strlen($digits) !== 12) {
            return null;
        }

        return $digits;
    }

    /**
     * Affichage lisible : +228 90 12 34 56 pour le Togo, sinon indicatif puis groupes de chiffres.
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

        return trim($dialCode.' '.implode(' ', str_split($local, 2)));
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
}
