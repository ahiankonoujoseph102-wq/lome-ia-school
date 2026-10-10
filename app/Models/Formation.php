<?php

namespace App\Models;

use App\Enums\FormationCategory;
use App\Enums\FormationFormat;
use App\Enums\FormationKind;
use App\Services\ImageUploader;
use App\Support\Money;
use App\Support\YouTube;
use Carbon\CarbonImmutable;
use Database\Factories\FormationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Une formation vendue sur la plateforme.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property FormationKind $kind Formation habituelle, offre d'accompagnement (pack) ou groupe de discussion seul.
 * @property string|null $subtitle
 * @property string $description
 * @property int $price Prix en FCFA (entier).
 * @property FormationCategory $category
 * @property Collection<int, FormationFormat> $formats
 * @property string|null $image Image envoyée depuis l'admin (disque « public », dossier formations/) ou ancien fichier de public/images/formations.
 * @property int $sort_order
 * @property bool $is_featured
 * @property bool $is_active
 * @property list<string>|null $outcomes Ce que l'élève va apprendre.
 * @property list<string>|null $audience Pour qui est la formation.
 * @property list<string> $bonuses Bonus facultatifs, saisis dans l'admin (vides par défaut).
 * @property Carbon|null $event_date Date de l'événement d'une formation gratuite.
 * @property string|null $event_location Adresse du présentiel d'une formation gratuite.
 * @property string|null $presentation_video_url Lien YouTube de la vidéo de présentation (formation gratuite).
 * @property bool $presentation_video_enabled Interrupteur « Afficher la vidéo » de la vidéo de présentation.
 * @property string|null $presentation_video_format Format choisi (horizontal, vertical) ; vide = automatique.
 * @property CarbonImmutable|null $registration_ends_at Fin des inscriptions d'une offre d'accompagnement (heure de Lomé) ; vide = aucune limite.
 * @property-read string $formatted_price
 * @property-read string|null $image_url
 * @property-read EloquentCollection<int, Enrollment> $enrollments
 * @property-read EloquentCollection<int, Purchase> $purchases
 * @property-read EloquentCollection<int, CourseModule> $modules
 * @property-read ChatGroup|null $chatGroup
 * @property-read EloquentCollection<int, FreeRegistrationOption> $freeOptions
 * @property-read EloquentCollection<int, FreeRegistration> $freeRegistrations
 * @property-read EloquentCollection<int, Formation> $includedFormations Formations incluses (offre d'accompagnement).
 * @property-read EloquentCollection<int, Formation> $packs Offres qui incluent cette formation.
 */
class Formation extends Model
{
    /** @use HasFactory<FormationFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'kind',
        'subtitle',
        'description',
        'price',
        'category',
        'formats',
        'image',
        'sort_order',
        'is_featured',
        'is_active',
        'outcomes',
        'audience',
        'bonuses',
        'event_date',
        'event_location',
        'presentation_video_url',
        'presentation_video_enabled',
        'presentation_video_format',
        'registration_ends_at',
    ];

    /**
     * Par défaut, une formation est proposée au format enregistré.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'course',
        'formats' => '["recorded"]',
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 0,
        'presentation_video_enabled' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'kind' => FormationKind::class,
            'category' => FormationCategory::class,
            'formats' => AsEnumCollection::of(FormationFormat::class),
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'outcomes' => 'array',
            'audience' => 'array',
            'event_date' => 'date',
            'presentation_video_enabled' => 'boolean',
            'registration_ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Achats de la formation (et suppléments de ses sessions, « live_session_id » renseigné).
     *
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Groupe de discussion de la formation (créé automatiquement avec la formation).
     *
     * @return HasOne<ChatGroup, $this>
     */
    public function chatGroup(): HasOne
    {
        return $this->hasOne(ChatGroup::class);
    }

    protected static function booted(): void
    {
        // Une offre d'accompagnement n'a pas de groupe à elle : ses acheteurs rejoignent les groupes des
        // formations incluses et le Groupe VIP (formation de type « groupe » incluse dans l'offre).
        static::created(fn (self $formation) => $formation->isPack() ? null : ChatGroup::forFormation($formation));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Formations visibles sur le site, dans l'ordre d'affichage.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        // Formations habituelles seulement : les offres d'accompagnement ont leur propre page (/accompagnement)
        // et un groupe seul (Groupe VIP) n'est jamais affiché.
        $query->where('kind', FormationKind::Course->value)->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Offres d'accompagnement visibles sur le site, dans l'ordre d'affichage (niveau 1, 2, 3…).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function publishedPacks(Builder $query): void
    {
        $query->where('kind', FormationKind::Pack->value)->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function isPack(): bool
    {
        return $this->kind === FormationKind::Pack;
    }

    /**
     * Groupe de discussion seul (Groupe VIP) : ni page, ni contenu, ni vente.
     */
    public function isGroupOnly(): bool
    {
        return $this->kind === FormationKind::Group;
    }

    /**
     * Formations incluses dans cette offre (et le Groupe VIP), dans l'ordre choisi.
     *
     * @return BelongsToMany<Formation, $this>
     */
    public function includedFormations(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'formation_pack_items', 'pack_id', 'formation_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position')
            ->orderBy('formations.id');
    }

    /**
     * Offres d'accompagnement qui incluent cette formation.
     *
     * @return BelongsToMany<Formation, $this>
     */
    public function packs(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'formation_pack_items', 'formation_id', 'pack_id')->withTimestamps();
    }

    /**
     * Prix total des formations incluses si on les achetait séparément (vrais prix en base, groupes exclus).
     */
    public function separatePrice(): int
    {
        return (int) $this->includedFormations
            ->filter(fn (self $formation): bool => $formation->kind === FormationKind::Course)
            ->sum('price');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn (): string => $this->isFree() ? 'Gratuit' : Money::fcfa($this->price));
    }

    /**
     * URL de l'image (envoyée depuis l'admin, ou ancien fichier de public/images/formations), ou null pour le visuel de remplacement.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => match (true) {
            blank($this->image) => null,
            str_contains((string) $this->image, '/') => ImageUploader::url($this->image),
            default => asset('images/formations/'.$this->image),
        });
    }

    /**
     * Bonus de la formation, saisis dans l'admin (colonne « bonuses »). Liste vide : aucun bonus affiché.
     *
     * @return Attribute<list<non-empty-string>, list<string>|null>
     */
    protected function bonuses(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => array_values(array_filter(
                array_map(fn ($bonus): string => trim((string) $bonus), (array) json_decode((string) $value, true)),
                fn (string $bonus): bool => $bonus !== '',
            )),
            set: fn (?array $value): ?string => $value === null ? null : (string) json_encode(array_values($value), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @return HasMany<CourseModule, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasManyThrough<Lesson, CourseModule, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseModule::class);
    }

    /**
     * Programme réel : modules ayant au moins une leçon publiée, avec ces seules leçons, dans l'ordre.
     * Vide si la formation n'a encore aucune leçon publiée (la section est alors masquée).
     *
     * @return EloquentCollection<int, CourseModule>
     */
    public function publicCurriculum(): EloquentCollection
    {
        return once(fn (): EloquentCollection => $this->modules()
            ->with(['lessons' => fn ($query) => $query->where('is_published', true)])
            ->get()
            ->filter(fn (CourseModule $module): bool => $module->lessons->isNotEmpty())
            ->values());
    }

    /**
     * Leçons publiées dans l'ordre du programme (modules puis leçons).
     *
     * @return EloquentCollection<int, Lesson>
     */
    public function orderedPublishedLessons(): EloquentCollection
    {
        return new EloquentCollection($this->publicCurriculum()->flatMap(fn (CourseModule $module) => $module->lessons)->all());
    }

    /**
     * Durée totale du programme, seulement si toutes les leçons publiées ont une durée renseignée.
     */
    public function totalDurationSeconds(): ?int
    {
        $lessons = $this->orderedPublishedLessons();

        if ($lessons->isEmpty() || $lessons->contains(fn (Lesson $lesson): bool => ! $lesson->duration_seconds)) {
            return null;
        }

        return (int) $lessons->sum('duration_seconds');
    }

    /**
     * Formation à 0 FCFA : inscription gratuite (bouton « Je m'inscris gratuitement »), jamais de paiement ni d'achat.
     */
    public function isFree(): bool
    {
        return $this->price === 0;
    }

    /**
     * Identifiant YouTube de la vidéo de présentation, seulement pour une formation gratuite, interrupteur allumé
     * et lien reconnu (watch, youtu.be, shorts…). Sinon null : la page s'affiche sans vidéo.
     */
    public function presentationVideoId(): ?string
    {
        if (! $this->isFree() || ! $this->presentation_video_enabled) {
            return null;
        }

        return YouTube::id($this->presentation_video_url);
    }

    /**
     * Vidéo de présentation verticale (9:16) ? Format choisi dans l'admin, sinon vertical pour un lien Shorts.
     */
    public function presentationVideoIsVertical(): bool
    {
        return YouTube::format($this->presentation_video_url, $this->presentation_video_format) === 'vertical';
    }

    /**
     * Options d'inscription d'une formation gratuite, dans l'ordre.
     *
     * @return HasMany<FreeRegistrationOption, $this>
     */
    public function freeOptions(): HasMany
    {
        return $this->hasMany(FreeRegistrationOption::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Inscriptions à une formation gratuite (annulées comprises).
     *
     * @return HasMany<FreeRegistration, $this>
     */
    public function freeRegistrations(): HasMany
    {
        return $this->hasMany(FreeRegistration::class);
    }

    /**
     * « Samedi 10 octobre 2026 », ou null sans date.
     */
    public function eventDateLabel(): ?string
    {
        return $this->event_date ? ucfirst($this->event_date->translatedFormat('l j F Y')) : null;
    }

    /**
     * Inscriptions closes : la date de l'événement est passée (heure de Lomé).
     */
    public function freeRegistrationsClosed(): bool
    {
        return $this->event_date !== null && now()->startOfDay()->gt($this->event_date);
    }

    /**
     * Fin des inscriptions d'une offre d'accompagnement (heure de Lomé), ou null : aucune limite. Les formations
     * habituelles n'en ont jamais (voir App\Packs\PackDeadline).
     */
    public function registrationDeadline(): ?CarbonImmutable
    {
        return $this->isPack() ? $this->registration_ends_at : null;
    }

    /**
     * Offre d'accompagnement dont la date de fin des inscriptions est passée : plus aucune nouvelle commande.
     */
    public function registrationsClosed(): bool
    {
        $deadline = $this->registrationDeadline();

        return $deadline !== null && now()->greaterThanOrEqualTo($deadline);
    }

    /**
     * Prix égal ou supérieur au seuil : l'accès n'est ouvert qu'après validation du paiement par l'équipe.
     */
    public function needsPaymentVerification(): bool
    {
        return $this->price >= (int) config('site.payments.verification_threshold', 50000);
    }

    /**
     * Garanties affichées sur la page de la formation (l'accès immédiat devient « après vérification » au-delà du seuil).
     *
     * @return list<array<string, mixed>>
     */
    public function guarantees(): array
    {
        return array_values(array_map(
            fn (array $guarantee): array => ($guarantee['instant'] ?? false) && $this->needsPaymentVerification()
                ? (array) config('site.verification_guarantee')
                : $guarantee,
            (array) config('site.guarantees', []),
        ));
    }

    public function hasFormat(FormationFormat $format): bool
    {
        return $this->formats->contains($format);
    }
}
