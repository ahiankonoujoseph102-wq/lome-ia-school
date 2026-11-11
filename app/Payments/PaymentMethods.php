<?php

namespace App\Payments;

use Illuminate\Support\Collection;

/**
 * Moyens de paiement Mobile Money configurés dans config/site.php.
 */
class PaymentMethods
{
    /**
     * Moyens actifs, dans l'ordre de la configuration.
     *
     * @return Collection<string, PaymentMethod>
     */
    public static function enabled(): Collection
    {
        return self::all()->filter(fn (PaymentMethod $method): bool => (bool) config("site.payments.methods.{$method->key}.enabled", true));
    }

    /**
     * Tous les moyens, actifs ou non (pour afficher les anciens achats).
     *
     * @return Collection<string, PaymentMethod>
     */
    public static function all(): Collection
    {
        $methods = [];

        foreach ((array) config('site.payments.methods', []) as $key => $method) {
            if (! is_array($method)) {
                continue;
            }

            $methods[(string) $key] = new PaymentMethod(
                key: (string) $key,
                country: (string) ($method['country'] ?? ''),
                name: (string) ($method['name'] ?? $key),
                number: (string) ($method['number'] ?? ''),
                beneficiary: (string) ($method['beneficiary'] ?? ''),
                color: (string) ($method['color'] ?? '#8b5cf6'),
                ussd: (string) ($method['ussd'] ?? ''),
            );
        }

        return collect($methods);
    }

    public static function find(?string $key): ?PaymentMethod
    {
        return self::all()->get((string) $key);
    }

    public static function findEnabled(?string $key): ?PaymentMethod
    {
        return self::enabled()->get((string) $key);
    }

    /**
     * Pays qui ont au moins un moyen actif : code => [name, flag, note].
     *
     * @return array<string, array{name: string, flag: string, note: ?string}>
     */
    public static function countries(): array
    {
        $used = self::enabled()->map(fn (PaymentMethod $method): string => $method->country)->unique()->all();
        $countries = [];

        foreach ((array) config('site.payments.countries', []) as $code => $country) {
            if (is_array($country) && in_array($code, $used, true)) {
                $countries[(string) $code] = [
                    'name' => (string) ($country['name'] ?? $code),
                    'flag' => (string) ($country['flag'] ?? ''),
                    'note' => isset($country['note']) && $country['note'] !== '' ? (string) $country['note'] : null,
                ];
            }
        }

        return $countries;
    }

    public static function verificationThreshold(): int
    {
        return (int) config('site.payments.verification_threshold', 50000);
    }

    /**
     * Nom d'un pays précédé de son article, pour « depuis le Togo » ou « depuis la Côte d'Ivoire ».
     */
    public static function countryWithArticle(string $code, string $name): string
    {
        return (in_array($code, self::FEMININE_COUNTRIES, true) ? 'la ' : 'le ').$name;
    }

    /**
     * Pays dont le nom est féminin (« la Côte d'Ivoire »).
     */
    private const array FEMININE_COUNTRIES = ['ci'];
}
