<?php

namespace App\Payments;

/**
 * Un numéro Mobile Money sur lequel l'élève peut payer (défini dans config/site.php, clé « payments.methods »).
 */
final readonly class PaymentMethod
{
    public function __construct(
        public string $key,
        public string $country,
        public string $name,
        public string $number,
        public string $beneficiary,
        public string $color,
    ) {}

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
            'initials' => $this->initials(),
            'note' => $this->representativeNote(),
        ];
    }
}
