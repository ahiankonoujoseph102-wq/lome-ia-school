<?php

namespace App\Http\Requests\Admin;

use App\Enums\SessionFormat;
use App\Enums\SessionPriceType;
use App\Models\LiveSession;
use App\Support\GoogleMeet;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire de session (admin et modérateurs) : en direct avec un lien Google Meet,
 * ou en présentiel avec un lieu, un nombre de places et un tarif (inclus ou supplément).
 */
class LiveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $onsite = $this->input('format') === SessionFormat::Onsite->value;
        $supplement = $onsite && $this->input('price_type') === SessionPriceType::Supplement->value;
        /** @var LiveSession|null $current */
        $current = $this->route('session');

        return [
            'formation_id' => ['required', 'integer', Rule::exists('formations', 'id')],
            'format' => ['required', Rule::enum(SessionFormat::class)],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i', function (string $attribute, mixed $value, Closure $fail) use ($current): void {
                $start = $this->startsAt();

                // Une nouvelle session (ou une nouvelle date) doit être dans le futur.
                if ($start !== null && $start->isPast() && ($current === null || ! $start->equalTo($current->starts_at))) {
                    $fail('Choisissez une date et une heure à venir.');
                }
            }],
            'duration' => ['required', 'integer', 'min:15', 'max:720'],
            'meet_url' => [$onsite ? 'nullable' : 'required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($onsite): void {
                if (! $onsite && GoogleMeet::normalize(is_string($value) ? $value : null) === null) {
                    $fail('Collez le lien de la réunion Google Meet, par exemple https://meet.google.com/abc-defg-hij.');
                }
            }],
            'location' => [$onsite ? 'required' : 'nullable', 'string', 'max:255'],
            'capacity' => [$onsite ? 'required' : 'nullable', 'integer', 'min:1', 'max:1000'],
            'price_type' => [$onsite ? 'required' : 'nullable', Rule::enum(SessionPriceType::class)],
            'supplement_amount' => [$supplement ? 'required' : 'nullable', 'integer', 'min:100', 'max:5000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'formation_id.required' => 'Choisissez la formation.',
            'format.required' => 'Choisissez le type de session.',
            'date.required' => 'Choisissez la date.',
            'time.required' => 'Choisissez l\'heure de début.',
            'duration.required' => 'Indiquez la durée.',
            'duration.min' => 'Durée de 15 minutes au minimum.',
            'duration.max' => 'Durée de 12 heures au maximum.',
            'meet_url.required' => 'Collez le lien Google Meet de la session.',
            'location.required' => 'Indiquez le lieu de la session.',
            'capacity.required' => 'Indiquez le nombre de places.',
            'capacity.min' => 'Au moins 1 place.',
            'price_type.required' => 'Choisissez le tarif.',
            'supplement_amount.required' => 'Indiquez le montant du supplément en FCFA.',
            'supplement_amount.min' => 'Supplément de 100 FCFA au minimum.',
        ];
    }

    public function startsAt(): ?CarbonImmutable
    {
        $date = (string) $this->input('date');
        $time = (string) $this->input('time');

        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$time, 'Africa/Lome') ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionData(): array
    {
        $format = SessionFormat::from($this->string('format')->toString());
        $onsite = $format === SessionFormat::Onsite;
        $priceType = $onsite ? SessionPriceType::from($this->string('price_type')->toString()) : SessionPriceType::Included;

        return [
            'formation_id' => $this->integer('formation_id'),
            'format' => $format,
            'title' => trim($this->string('title')->toString()) ?: null,
            'description' => trim($this->string('description')->toString()) ?: null,
            'starts_at' => $this->startsAt(),
            'duration_minutes' => $this->integer('duration'),
            'meet_url' => $onsite ? null : GoogleMeet::normalize($this->string('meet_url')->toString()),
            'location' => $onsite ? trim($this->string('location')->toString()) : null,
            'capacity' => $onsite ? $this->integer('capacity') : null,
            'price_type' => $priceType,
            'supplement_amount' => $priceType === SessionPriceType::Supplement ? $this->integer('supplement_amount') : null,
        ];
    }
}
