<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sondage d'un groupe (message de type « poll ») : une question, de 2 à 10 choix, un ou plusieurs votes par membre.
 *
 * @property int $id
 * @property int $chat_message_id
 * @property string $question
 * @property bool $allows_multiple
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatMessage $message
 * @property-read Collection<int, ChatPollOption> $options
 * @property-read Collection<int, ChatPollVote> $votes
 */
class ChatPoll extends Model
{
    public const int MIN_OPTIONS = 2;

    public const int MAX_OPTIONS = 10;

    public const int QUESTION_MAX_LENGTH = 300;

    public const int OPTION_MAX_LENGTH = 150;

    protected $fillable = ['chat_message_id', 'question', 'allows_multiple'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allows_multiple' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    /**
     * @return HasMany<ChatPollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ChatPollOption::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<ChatPollVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Résultats pour l'affichage : pour chaque choix, nombre de votes et pourcentage des votants ; choix de la personne.
     *
     * @return array{voters: int, mine: array<int, int>, options: array<int, array{id: int, label: string, votes: int, percent: int, mine: bool}>}
     */
    public function results(User $viewer): array
    {
        $votes = $this->votes;
        $voters = $votes->pluck('user_id')->unique()->count();
        $counts = $votes->countBy('chat_poll_option_id');
        $mine = $votes->where('user_id', $viewer->id)->pluck('chat_poll_option_id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        return [
            'voters' => $voters,
            'mine' => $mine,
            'options' => $this->options->map(fn (ChatPollOption $option): array => [
                'id' => $option->id,
                'label' => $option->label,
                'votes' => $count = (int) ($counts[$option->id] ?? 0),
                'percent' => $voters > 0 ? (int) round($count * 100 / $voters) : 0,
                'mine' => in_array($option->id, $mine, true),
            ])->values()->all(),
        ];
    }
}
