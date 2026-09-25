<?php

namespace App\Models;

use App\Enums\FormationCategory;
use App\Enums\FormationFormat;
use App\Services\ImageUploader;
use App\Support\Money;
use Database\Factories\FormationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Une formation vendue sur la plateforme.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
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
 * @property-read string $formatted_price
 * @property-read string|null $image_url
 * @property-read EloquentCollection<int, Enrollment> $enrollments
 * @property-read EloquentCollection<int, CourseModule> $modules
 * @property-read ChatGroup|null $chatGroup
 */
class Formation extends Model
{
    /** @use HasFactory<FormationFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
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
    ];

    /**
     * Par défaut, une formation est proposée au format enregistré.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'formats' => '["recorded"]',
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'category' => FormationCategory::class,
            'formats' => AsEnumCollection::of(FormationFormat::class),
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'outcomes' => 'array',
            'audience' => 'array',
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
        static::created(fn (self $formation) => ChatGroup::forFormation($formation));
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
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn (): string => Money::fcfa($this->price));
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
