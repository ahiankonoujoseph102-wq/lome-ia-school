<?php

namespace App\Models;

use App\Enums\FormationCategory;
use App\Enums\FormationFormat;
use App\Support\Money;
use Database\Factories\FormationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string|null $image Fichier dans public/images/formations.
 * @property int $sort_order
 * @property bool $is_featured
 * @property bool $is_active
 * @property list<string>|null $outcomes Ce que l'élève va apprendre.
 * @property list<array{title: string, lessons: list<string>}>|null $curriculum Programme par modules.
 * @property list<string>|null $audience Pour qui est la formation.
 * @property list<string>|null $bonuses
 * @property-read string $formatted_price
 * @property-read string|null $image_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Enrollment> $enrollments
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
        'curriculum',
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
            'curriculum' => 'array',
            'audience' => 'array',
            'bonuses' => 'array',
        ];
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
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
     * URL de l'image déposée dans public/images/formations, ou null pour le visuel de remplacement.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image ? asset('images/formations/'.$this->image) : null);
    }

    public function hasFormat(FormationFormat $format): bool
    {
        return $this->formats->contains($format);
    }
}
