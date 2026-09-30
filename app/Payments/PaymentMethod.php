<?php

namespace App\Payments;

/**
 * Un numéro Mobile Money sur lequel l'élève peut payer (défini dans config/site.php, clé « payments.methods »).
 */
final readonly class PaymentMethod
{
    /**
     * Repères utilisables dans le code USSD de transfert (réglé dans l'admin).
     */
    public const array USSD_TOKENS = ['{numero}', '{montant}'];

    public function __construct(
        public string $key,
        public string $country,
        public string $name,
        public string $number,
        public string $beneficiary,
        public string $color,
        public string $ussd = '',
    ) {}

    /**
     * Code USSD de transfert à composer, repères {numero} et {montant} remplacés ; null si aucun code n'est réglé.
     * Jamais de code secret : l'élève le tape lui-même dans le menu de son opérateur.
     */
    public function ussdCode(int $amount): ?string
    {
        if ($this->ussd === '') {
            return null;
        }

        return strtr($this->ussd, ['{numero}' => $this->localNumber(), '{montant}' => (string) $amount]);
    }

    /**
     * Lien « tel: » qui ouvre le clavier du téléphone avec le code déjà tapé. Chaque « # » est encodé
     * en %23 (sinon le navigateur le prend pour une ancre et coupe le code) ; les « * » restent tels quels.
     */
    public function ussdTelUrl(int $amount): ?string
    {
        $code = $this->ussdCode($amount);

        return $code === null ? null : 'tel:'.str_replace('#', '%23', $code);
    }

    /**
     * Paiement par l'application de l'opérateur, sans code USSD (Wave).
     */
    public function usesApp(): bool
    {
        return $this->ussd === '' && str_contains(mb_strtolower($this->name), 'wave');
    }

    /**
     * Numéro sans l'indicatif du pays, tel qu'on le saisit dans le menu de transfert (ex. « 72240231 »).
     */
    public function localNumber(): string
    {
        $digits = (string) preg_replace('/\D+/', '', $this->number);
        $dialCode = (string) preg_replace('/\D+/', '', (string) config("site.payments.countries.{$this->country}.dial_code", ''));

        if ($dialCode !== '' && str_starts_with($this->number, '+') && str_starts_with($digits, $dialCode)) {
            return substr($digits, strlen($dialCode));
        }

        return $digits;
    }

    public function countryName(): string
    {
        return (string) config("site.payments.countries.{$this->country}.name", $this->country);
    }

    public function countryFlag(): string
    {
        return (string) config("site.payments.countries.{$this->country}.flag", '');
    }

    /**
     * Mention du représentant local (Burkina Faso, Bénin), sinon null.
     */
    public function representativeNote(): ?string
    {
        $note = config("site.payments.countries.{$this->country}.note");

        return is_string($note) && $note !== '' ? $note : null;
    }

    /**
     * Paiement reçu par un représentant : à confirmer avec lui avant validation.
     */
    public function viaRepresentative(): bool
    {
        return $this->representativeNote() !== null;
    }

    /**
     * Numéro sans espaces, pour le bouton « Copier ».
     */
    public function rawNumber(): string
    {
        return (string) preg_replace('/\s+/', '', $this->number);
    }

    /**
     * Initiales de la pastille (ex. « MY » pour Mixx by Yas).
     */
    public function initials(): string
    {
        $words = preg_split('/[\s\/]+/u', $this->name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, fn (string $word): bool => mb_strtolower($word) !== 'by'));

        return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }

    /**
     * Couleur inverse de la pastille (compensation du mode sombre forcé de Chrome, voir site.css).
     */
    public function inverseColor(): string
    {
        $hex = ltrim($this->color, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '#000000';
        }

        return sprintf('#%06x', 0xFFFFFF - (int) hexdec($hex));
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'country' => $this->country,
            'name' => $this->name,
            'number' => $this->number,
            'raw_number' => $this->rawNumber(),
            'beneficiary' => $this->beneficiary,
            'color' => $this->color,
            'inverse_color' => $this->inverseColor(),
            'initials' => $this->initials(),
            'note' => $this->representativeNote(),
            'ussd' => $this->ussd,
        ];
    }
}
