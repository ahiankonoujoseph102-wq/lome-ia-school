<?php

namespace App\Models;

use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone Numéro WhatsApp au format international (+228…).
 * @property UserRole $role
 * @property string|null $avatar_path Fichier sur le disque « public ».
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar_url
 * @property-read string $first_name
 * @property-read string $formatted_phone
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, Formation> $formations
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Le rôle n'est pas modifiable par formulaire : il se change avec la commande app:make-admin.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'student',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
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
     * Formations auxquelles l'élève a accès.
     *
     * @return BelongsToMany<Formation, $this>
     */
    public function formations(): BelongsToMany
    {
        return $this->belongsToMany(Formation::class, 'enrollments')
            ->withPivot(['source', 'granted_at'])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isEnrolledIn(Formation $formation): bool
    {
        return $this->enrollments()->where('formation_id', $formation->id)->exists();
    }

    /**
     * Donne l'accès à une formation (sans doublon si l'élève y a déjà accès).
     */
    public function enroll(Formation $formation, EnrollmentSource $source): Enrollment
    {
        return $this->enrollments()->firstOrCreate(
            ['formation_id' => $formation->id],
            ['source' => $source, 'granted_at' => now()],
        );
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * @return Attribute<string, never>
     */
    protected function firstName(): Attribute
    {
        return Attribute::get(fn (): string => Str::before(trim($this->name), ' '));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedPhone(): Attribute
    {
        return Attribute::get(fn (): string => PhoneNumber::format($this->phone));
    }
}
