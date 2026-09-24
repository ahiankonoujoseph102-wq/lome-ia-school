<?php

namespace App\Models;

use App\Enums\EnrollmentSource;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inscription d'un élève à une formation : c'est elle qui ouvre l'accès au contenu.
 *
 * @property int $id
 * @property int $user_id
 * @property int $formation_id
 * @property int|null $purchase_id Achat qui a ouvert l'accès.
 * @property EnrollmentSource $source
 * @property Carbon $granted_at Date d'ouverture de l'accès.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Formation $formation
 * @property-read Purchase|null $purchase
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'formation_id',
        'purchase_id',
        'source',
        'granted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => EnrollmentSource::class,
            'granted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Formation, $this>
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
