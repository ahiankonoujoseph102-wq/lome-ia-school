<?php

namespace App\Assistant;

use App\Contracts\AssistantModel;
use App\Enums\AssistantIncidentType;
use App\Models\AssistantConversation;
use App\Models\AssistantIncident;
use App\Models\AssistantMessage;
use App\Models\AssistantUsage;
use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Un message d'un visiteur à Ama (Gemini) : contrôles, appel à l'IA, enregistrement, relève.
 *
 * Ordre des contrôles (le premier qui bloque donne un message clair et le bouton WhatsApp, jamais d'erreur
 * technique ; chaque blocage est noté comme incident pour l'admin) :
 * 1. clé GEMINI_API_KEY présente ;
 * 2. protection contre les abus : messages par minute du visiteur (et de sa connexion) ;
 * 3. messages du visiteur aujourd'hui (journée de Lomé) ;
 * 4. demandes du site par minute, puis par jour (journée de Google), sous le quota gratuit de Google ;
 * 5. réponse de Gemini (erreur, quota, délai dépassé, réponse vide → relève WhatsApp).
 *
 * Mode appel ($onText fourni) : la réponse de Gemini arrive en continu et chaque morceau est transmis tout de suite
 * au navigateur, qui la lit phrase par phrase ; Ama reçoit aussi la consigne de répondre comme au téléphone.
 * Chaque phrase dite par le visiteur compte comme un message, avec les mêmes limites.
 *
 * Aucune donnée personnelle : le texte du visiteur est nettoyé (PersonalData) avant l'envoi et
 * l'enregistrement, et le contexte de la page est réduit à la liste fermée de VoiceAssistant::FIELDS.
 */
class AmaChat
{
    /**
     * Messages précédents envoyés à Gemini avec la question (les plus récents).
     */
    public const int HISTORY = 16;

    public function __construct(private readonly AssistantModel $model) {}

    /**
     * @param  array<array-key, mixed>  $context
     * @param  (callable(string): void)|null  $onText  mode appel : reçoit chaque morceau de la réponse
     * @return array{reply: string, fallback: bool, conversation: string|null, whatsapp: string|null, handoff: array{first_name: string, channel: string, contact: string, question: string}|null}
     */
    public function handle(string $message, ?string $conversationId, array $context, bool $voice, string $visitorHash, string $ipHash, ?callable $onText = null): array
    {
        $context = VoiceAssistant::pickContext($context);
        $conversation = $this->conversation($conversationId, $visitorHash);

        $blocked = $this->blocked($visitorHash, $ipHash);

        if ($blocked !== null) {
            return $this->fallback($blocked, $conversation);
        }

        $text = PersonalData::redact($message);
        $conversation ??= AssistantConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_hash' => $visitorHash,
        ]);

        $conversation->fill([
            'page' => $context['page'] ?: null,
            'formation' => $context['formation'] ?: null,
            'voice_used' => $conversation->voice_used || $voice || $onText !== null,
        ]);
        $this->store($conversation, AssistantMessage::VISITOR, $text);
        // « Transmettre au fondateur » : l'adresse e-mail ou le numéro écrit reste sur le serveur (jamais chez Google).
        FounderHandoff::rememberContacts($conversation->public_id, $message);

        try {
            $reply = $onText !== null
                ? $this->model->stream(AmaPrompt::build($context, call: true), $this->history($conversation), $onText)
                : $this->model->reply(AmaPrompt::build($context), $this->history($conversation));
        } catch (AssistantFailure $failure) {
            AssistantUsage::query()->where('day', AssistantUsage::today())->increment('failures');

            return $this->fallback($failure->type, $conversation, $failure->getMessage());
        } catch (Throwable $exception) {
            AssistantUsage::query()->where('day', AssistantUsage::today())->increment('failures');

            return $this->fallback(AssistantIncidentType::GeminiError, $conversation, $exception::class);
        }

        // Récapitulatif « Transmettre au fondateur » : carte de confirmation, ou Ama redemande poliment ce qui manque
        // (prénom, moyen de réponse, coordonnée au bon format). La demande ne part qu'au toucher de « Envoyer ».
        ['text' => $reply, 'draft' => $draft] = FounderHandoff::extract($reply);
        $handoff = null;

        if ($draft !== null) {
            ['card' => $handoff, 'problem' => $problem] = FounderHandoff::proposal($draft, $conversation->public_id, $onText !== null);
            $reply = $problem ?? $reply;
        }

        ['text' => $reply, 'unanswered' => $unanswered] = UnansweredReply::inspect($reply);
        $this->store($conversation, AssistantMessage::AMA, $reply, $unanswered);

        return ['reply' => $reply, 'fallback' => false, 'conversation' => $conversation->public_id, 'whatsapp' => null, 'handoff' => $handoff];
    }

    /**
     * Conversation en cours de ce visiteur (jamais celle d'un autre navigateur).
     */
    private function conversation(?string $id, string $visitorHash): ?AssistantConversation
    {
        if ($id === null || ! Str::isUuid($id)) {
            return null;
        }

        return AssistantConversation::query()->where('public_id', $id)->where('visitor_hash', $visitorHash)->first();
    }

    private function blocked(string $visitorHash, string $ipHash): ?AssistantIncidentType
    {
        if (! $this->model->isConfigured()) {
            return AssistantIncidentType::MissingKey;
        }

        $perMinute = VoiceAssistant::limit('visitor_minute_limit');

        // Même connexion partagée (cybercafé, réseau mobile) : limite plus large que pour un seul navigateur.
        if (! RateLimiter::attempt('ama:visitor:'.$visitorHash, $perMinute, fn () => true, 60)
            || ! RateLimiter::attempt('ama:ip:'.$ipHash, $perMinute * 4, fn () => true, 60)) {
            return AssistantIncidentType::VisitorMinuteLimit;
        }

        $today = AssistantMessage::query()
            ->where('role', AssistantMessage::VISITOR)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereHas('conversation', fn ($query) => $query->where('visitor_hash', $visitorHash))
            ->count();

        if ($today >= VoiceAssistant::limit('visitor_daily_limit')) {
            return AssistantIncidentType::VisitorDailyLimit;
        }

        if (! RateLimiter::attempt('ama:site-minute', VoiceAssistant::limit('minute_limit'), fn () => true, 60)) {
            return AssistantIncidentType::GlobalMinuteLimit;
        }

        return $this->reserveDailyRequest() ? null : AssistantIncidentType::GlobalDailyLimit;
    }

    /**
     * Compte une demande de plus pour la journée de Google, seulement si la limite du site n'est pas atteinte
     * (mise à jour conditionnelle : deux visiteurs en même temps ne la dépassent jamais).
     */
    private function reserveDailyRequest(): bool
    {
        $day = AssistantUsage::today();
        AssistantUsage::query()->insertOrIgnore(['day' => $day, 'requests' => 0, 'failures' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return AssistantUsage::query()
            ->where('day', $day)
            ->where('requests', '<', VoiceAssistant::limit('daily_limit'))
            ->update(['requests' => DB::raw('requests + 1'), 'updated_at' => now()]) === 1;
    }

    private function store(AssistantConversation $conversation, string $role, string $content, bool $unanswered = false): void
    {
        $conversation->messages()->create(['role' => $role, 'content' => $content, 'unanswered' => $unanswered]);
        $conversation->messages_count++;
        $conversation->last_message_at = now();
        $conversation->save();
    }

    /**
     * @return list<array{role: string, text: string}>
     */
    private function history(AssistantConversation $conversation): array
    {
        return array_values(AssistantMessage::query()
            ->where('assistant_conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(self::HISTORY)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (AssistantMessage $message): array => ['role' => $message->role, 'text' => $message->content])
            ->all());
    }

    /**
     * Relève : message clair et bouton « Écrivez-nous sur WhatsApp ». L'incident est noté pour l'admin.
     *
     * @return array{reply: string, fallback: bool, conversation: string|null, whatsapp: string|null, handoff: null}
     */
    private function fallback(AssistantIncidentType $type, ?AssistantConversation $conversation, string $detail = ''): array
    {
        AssistantIncident::query()->create([
            'assistant_conversation_id' => $conversation?->id,
            'type' => $type,
            'detail' => $detail !== '' ? mb_substr($detail, 0, 300) : null,
        ]);

        if ($conversation !== null && ! $conversation->fallback_shown) {
            $conversation->forceFill(['fallback_shown' => true])->save();
        }

        return [
            'reply' => $type->visitorMessage(),
            'fallback' => true,
            'conversation' => $conversation?->public_id,
            'whatsapp' => WhatsApp::url(),
            'handoff' => null,
        ];
    }
}
