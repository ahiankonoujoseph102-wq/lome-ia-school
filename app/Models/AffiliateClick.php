<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Clic sur un lien d'affiliation. Une même personne n'est comptée qu'une fois par affilié
 * (empreintes anonymes : identifiant de navigateur et adresse IP hachés, jamais en clair).
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int|null $formation_id
 * @property string $visitor_hash
 * @property string $ip_hash
 * @property Carbon|null $created_at
 * @property-read Affiliate $affiliate
 */
class AffiliateClick extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['affiliate_id', 'formation_id', 'visitor_hash', 'ip_hash'];

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
