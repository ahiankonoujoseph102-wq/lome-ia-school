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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property bool $wants_newsletter Accepte d'être informé des nouvelles formations.
 * @property Carbon|null $blocked_at Compte bloqué par l'admin : connexion impossible.
 * @property bool $chat_push_enabled Notifications sur téléphone des groupes de discussion.
 * @property bool $chat_digest_enabled E-mail récapitulatif des messages non lus (au plus un par jour).
 * @property Carbon|null $chat_digest_sent_at
 * @property bool $session_email_enabled Rappels des sessions par e-mail.
 * @property int|null $referred_by_affiliate_id Inscrit arrivé par un lien d'affiliation.
 * @property Carbon|null $email_verified_at Adresse e-mail confirmée (null : pas encore confirmée).
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
 * @property-read Collection<int, Purchase> $purchases
 * @property-read Collection<int, FreeRegistration> $freeRegistrations
 * @property-read Collection<int, ChatGroupMember> $chatMemberships
 * @property-read Collection<int, PushSubscription> $pushSubscriptions
 * @property-read Collection<int, LiveSessionRegistration> $liveSessionRegistrations
 * @property-read Affiliate|null $affiliate
 * @property-read Affiliate|null $referrer
 */
#[Fillable(['name', 'email', 'phone', 'password', 'wants_newsletter', 'chat_push_enabled', 'chat_digest_enabled', 'session_email_enabled'])]
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
            'wants_newsletter' => 'boolean',
            'blocked_at' => 'datetime',
            'chat_push_enabled' => 'boolean',
            'chat_digest_enabled' => 'boolean',
            'chat_digest_sent_at' => 'datetime',
            'session_email_enabled' => 'boolean',
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

    /**
     * Inscriptions aux formations gratuites (annulées comprises).
     *
     * @return HasMany<FreeRegistration, $this>
     */
    public function freeRegistrations(): HasMany
    {
        return $this->hasMany(FreeRegistration::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<ChatGroupMember, $this>
     */
    public function chatMemberships(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class);
    }

    /**
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * @return HasMany<LiveSessionRegistration, $this>
     */
    public function liveSessionRegistrations(): HasMany
    {
        return $this->hasMany(LiveSessionRegistration::class);
    }

    /**
     * Compte affilié (si l'élève a activé le programme d'affiliation).
     *
     * @return HasOne<Affiliate, $this>
     */
    public function affiliate(): HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    /**
     * Affilié dont le lien a amené l'inscription.
     *
     * @return BelongsTo<Affiliate, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'referred_by_affiliate_id');
    }

    /**
     * Adresse e-mail confirmée (code ou bouton de l'e-mail, ou par l'admin). Nécessaire pour payer
     * et pour demander un retrait d'affiliation (voir App\Accounts\EmailConfirmation).
     */
    public function hasConfirmedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isModerator(): bool
    {
        return $this->role === UserRole::Moderator;
    }

    /**
     * Admin ou modérateur : membre de tous les groupes, avec les outils de modération.
     */
    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function chatBadge(): ?string
    {
        return $this->role->chatBadge();
    }

    public function isEnrolledIn(Formation $formation): bool
    {
        return $this->enrollments()->where('formation_id', $formation->id)->exists();
    }

    /**
     * Donne l'accès à une formation (sans doublon si l'élève y a déjà accès).
     */
    public function enroll(Formation $formation, EnrollmentSource $source, ?Purchase $purchase = null): Enrollment
    {
        return $this->enrollments()->firstOrCreate(
            ['formation_id' => $formation->id],
            ['source' => $source, 'granted_at' => now(), 'purchase_id' => $purchase?->id],
        );
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        // Lettres uniquement (un nom comme « Afi (Exemple) » donne « AE », pas « A( »).
        $initials = Str::initials(trim((string) preg_replace('/[^\p{L}\s-]+/u', ' ', $this->name)) ?: $this->name, true);

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
