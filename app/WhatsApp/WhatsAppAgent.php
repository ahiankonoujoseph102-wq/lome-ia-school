<?php

namespace App\WhatsApp;

use App\Assistant\AssistantFailure;
use App\Assistant\PersonalData;
use App\Assistant\UnansweredReply;
use App\Contracts\AssistantModel;
use App\Contracts\WhatsAppSender;
use App\Mail\WhatsAppHandoffMail;
use App\Models\AssistantUsage;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Support\VoiceAssistant;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Agent WhatsApp : répond aux clients qui écrivent au numéro de l'API, avec Gemini (comme Ama).
 *
 * Pour chaque client qui vient d'écrire (appelé juste après la réponse au webhook de Meta) :
 * 1. rien si l'interrupteur est éteint, si Meta ou Gemini ne sont pas configurés, ou si l'agent est en pause pour
 *    ce client (l'équipe a pris la main) ;
 * 2. un seul traitement à la fois par client (verrou) : deux messages rapprochés reçoivent une seule réponse ;
 * 3. message lu (coches bleues) et « en train d'écrire… » ;
 * 4. image, note vocale ou fichier sans texte : demande d'écrire la question (sans Gemini) ;
 * 5. limites : réponses par client et par jour, puis demandes à Gemini par minute et par jour, partagées avec Ama
 *    (même projet Google) ; limite atteinte, panne de Gemini : message d'attente et l'équipe prend le relais ;
 * 6. réponse de Gemini envoyée au client ; repère [EQUIPE] : l'équipe prend le relais (agent en pause pour ce
 *    client, e-mail à l'admin) ; repère [INCONNU] : réponse notée « sans réponse ».
 *
 * Aucune donnée personnelle chez Google : ni le numéro, ni le nom du profil ; les messages sont nettoyés
 * (PersonalData::redact) avant l'envoi.
 */
class WhatsAppAgent
{
    /**
     * Messages précédents envoyés à Gemini (comme Ama).
     */
    public const int HISTORY = 12;

    public const string MEDIA_REPLY = 'Merci pour votre message. Je ne peux lire que les messages écrits pour le moment : pouvez-vous écrire votre question en quelques mots ?';

    public function __construct(
        private readonly AssistantModel $model,
        private readonly WhatsAppSender $sender,
    ) {}

    /**
     * Message d'attente quand l'équipe prend le relais (limite atteinte, panne) : horaires lus dans les réglages.
     */
    public static function handoffNotice(): string
    {
        $hours = trim((string) config('site.contact.hours'));

        return 'Merci pour votre patience. Je transmets votre message à l\'équipe de Lomé IA School, qui vous répondra ici même dès que possible'
            .($hours !== '' ? ', '.mb_strtolower(mb_substr($hours, 0, 1)).mb_substr($hours, 1) : '').'.';
    }

    /**
     * @param  list<int>  $contactIds
     */
    public function respondAll(array $contactIds): void
    {
        foreach ($contactIds as $id) {
            $contact = WhatsAppContact::query()->find($id);

            if ($contact !== null) {
                try {
                    $this->respond($contact);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    public function respond(WhatsAppContact $contact): void
    {
        if (! AgentSettings::enabled()) {
            return;
        }

        $lock = Cache::lock('whatsapp-agent:'.$contact->id, 60);

        try {
            $lock->block(30, fn () => $this->answer($contact));
        } catch (LockTimeoutException) {
            // Un autre traitement est en cours pour ce client : il répondra à tous ses messages.
        }
    }

    private function answer(WhatsAppContact $contact): void
    {
        $contact->refresh();

        if (! $contact->agentActive()) {
            return;
        }

        $latest = WhatsAppMessage::query()
            ->where('whatsapp_contact_id', $contact->id)
            ->where('sender', WhatsAppMessage::CLIENT)
            ->latest('id')
            ->first();

        if ($latest === null || $latest->id <= (int) $contact->last_answered_message_id
            || $latest->created_at?->lessThan(now()->subMinutes(WebhookInbox::STALE_MINUTES))) {
            return;
        }

        $contact->forceFill(['last_answered_message_id' => $latest->id])->save();

        if ($latest->wa_message_id !== null) {
            $this->sender->markAsRead($latest->wa_message_id);
        }

        if (trim((string) $latest->content) === '') {
            $this->send($contact, self::MEDIA_REPLY, WhatsAppMessage::AGENT);

            return;
        }

        $todayReplies = WhatsAppMessage::query()
            ->where('whatsapp_contact_id', $contact->id)
            ->where('sender', WhatsAppMessage::AGENT)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayReplies >= AgentSettings::contactDailyLimit()) {
            $this->handOver($contact, 'Limite de réponses de l\'agent atteinte pour ce client aujourd\'hui.', self::handoffNotice());

            return;
        }

        // Même projet Google qu'Ama : mêmes compteurs, pour rester sous les quotas gratuits.
        if (! RateLimiter::attempt('ama:site-minute', VoiceAssistant::limit('minute_limit'), fn () => true, 60)
            || ! AssistantUsage::reserve(VoiceAssistant::limit('daily_limit'))) {
            $this->handOver($contact, 'Limite de demandes à Gemini atteinte (partagée avec Ama).', self::handoffNotice());

            return;
        }

        $firstReply = ! WhatsAppMessage::query()->where('whatsapp_contact_id', $contact->id)->where('sender', WhatsAppMessage::AGENT)->exists();

        try {
            $reply = $this->model->reply(WhatsAppPrompt::build($this->recentText($contact), $firstReply), $this->history($contact));
        } catch (AssistantFailure $failure) {
            AssistantUsage::query()->where('day', AssistantUsage::today())->increment('failures');
            $this->handOver($contact, 'Gemini indisponible : '.$failure->type->label().'.', self::handoffNotice());

            return;
        } catch (Throwable $exception) {
            report($exception);
            AssistantUsage::query()->where('day', AssistantUsage::today())->increment('failures');
            $this->handOver($contact, 'Gemini indisponible (erreur inattendue).', self::handoffNotice());

            return;
        }

        ['text' => $text, 'team' => $team, 'summary' => $summary] = WhatsAppPrompt::extractTeamRequest($reply);
        ['text' => $text, 'unanswered' => $unanswered] = UnansweredReply::inspect($text);

        if (trim($text) === '') {
            $text = self::handoffNotice();
        }

        $this->send($contact, $text, WhatsAppMessage::AGENT, $unanswered);

        if ($team) {
            $this->handOver($contact, $summary ?? 'Le client demande à parler à l\'équipe.');
        }
    }

    /**
     * L'équipe prend le relais : message d'attente éventuel, agent en pause pour ce client, e-mail à l'admin (une seule
     * fois tant que la demande n'est pas traitée).
     */
    public function handOver(WhatsAppContact $contact, string $reason, ?string $notice = null): void
    {
        if ($notice !== null) {
            $this->send($contact, $notice, WhatsAppMessage::AGENT);
        }

        $alreadyWaiting = $contact->needs_human_at !== null;
        $contact->pauseAgent();
        $contact->forceFill([
            'needs_human_at' => $contact->needs_human_at ?? now(),
            'needs_human_reason' => mb_substr($reason, 0, 200),
        ])->save();

        if ($alreadyWaiting) {
            return;
        }

        try {
            Mail::to((string) config('site.payments.admin_email'))->send(new WhatsAppHandoffMail($contact));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Envoie un message au client et l'enregistre (avec l'erreur de Meta en cas d'échec).
     */
    public function send(WhatsAppContact $contact, string $text, string $sender, bool $unanswered = false, ?User $user = null): WhatsAppMessage
    {
        $id = null;
        $error = null;

        try {
            $id = $this->sender->sendText($contact->wa_id, $text);
        } catch (WhatsAppSendFailed $failure) {
            $error = $failure->getMessage();
        }

        $message = WhatsAppMessage::query()->create([
            'whatsapp_contact_id' => $contact->id,
            'sender' => $sender,
            'type' => 'text',
            'content' => $text,
            'wa_message_id' => $id,
            'status' => $id !== null ? 'sent' : 'failed',
            'error' => $error,
            'unanswered' => $unanswered,
            'user_id' => $user?->id,
        ]);

        $contact->forceFill(['last_message_at' => now(), 'messages_count' => $contact->messages_count + 1])->save();

        return $message;
    }

    /**
     * Derniers messages du client (le dernier et le précédent) : une formation qu'il nomme est envoyée en détail.
     */
    private function recentText(WhatsAppContact $contact): string
    {
        return PersonalData::redact(WhatsAppMessage::query()
            ->where('whatsapp_contact_id', $contact->id)
            ->where('sender', WhatsAppMessage::CLIENT)
            ->latest('id')
            ->limit(2)
            ->pluck('content')
            ->filter()
            ->implode(' '));
    }

    /**
     * Conversation envoyée à Gemini : derniers messages, nettoyés, rôles « user » (client) et « model » (agent et
     * équipe) ; deux messages de suite du même côté sont regroupés, et la conversation commence par le client.
     *
     * @return list<array{role: string, text: string}>
     */
    private function history(WhatsAppContact $contact): array
    {
        $messages = WhatsAppMessage::query()
            ->where('whatsapp_contact_id', $contact->id)
            ->latest('id')
            ->limit(self::HISTORY)
            ->get()
            ->reverse();

        $history = [];

        foreach ($messages as $message) {
            $role = $message->sender === WhatsAppMessage::CLIENT ? 'user' : 'model';
            $text = $message->sender === WhatsAppMessage::CLIENT ? PersonalData::redact($message->displayText()) : (string) $message->content;

            if ($history === [] && $role === 'model') {
                continue;
            }

            if ($history !== [] && $history[array_key_last($history)]['role'] === $role) {
                $history[array_key_last($history)]['text'] .= "\n".$text;
            } else {
                $history[] = ['role' => $role, 'text' => $text];
            }
        }

        return $history;
    }
}
