<?php

namespace App\WhatsApp;

use App\Assistant\PersonalData;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Lecture d'une notification de Meta (webhook « messages ») : enregistre les messages reçus des clients et le suivi
 * des messages envoyés (distribué, lu, échec). Un message déjà reçu (Meta renvoie une notification tant qu'elle n'a
 * pas eu de réponse) n'est enregistré qu'une fois. Les notifications destinées à un autre numéro sont ignorées.
 *
 * Format (API WhatsApp Business Cloud) : entry[].changes[] avec field = « messages » et value = {metadata:
 * {phone_number_id}, contacts: [{wa_id, profile: {name}}], messages: [{from, id, timestamp, type, text: {body}…}],
 * statuses: [{id, status, errors: […]}]}.
 */
class WebhookInbox
{
    /**
     * Un message reçu plus de 60 minutes après son envoi (panne, notification renvoyée tard) est enregistré mais
     * l'agent n'y répond pas automatiquement : une réponse si tardive surprendrait le client.
     */
    public const int STALE_MINUTES = 60;

    /**
     * Types de messages qui n'appellent aucune réponse (réaction à un message, message système, erreur).
     *
     * @var list<string>
     */
    private const array IGNORED_TYPES = ['reaction', 'system', 'unsupported', 'errors', 'request_welcome'];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<int> clients ayant écrit un nouveau message récent (à qui l'agent doit répondre)
     */
    public function handle(array $payload): array
    {
        $contacts = [];
        $phoneNumberId = AgentSettings::phoneNumberId();

        if ($phoneNumberId === '') {
            return [];
        }

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                if (($change['field'] ?? null) !== 'messages' || (string) ($value['metadata']['phone_number_id'] ?? '') !== $phoneNumberId) {
                    continue;
                }

                $names = [];
                foreach ((array) ($value['contacts'] ?? []) as $contact) {
                    $names[(string) ($contact['wa_id'] ?? '')] = (string) ($contact['profile']['name'] ?? '');
                }

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $id = is_array($message) ? $this->storeMessage($message, $names) : null;

                    if ($id !== null) {
                        $contacts[$id] = $id;
                    }
                }

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    if (is_array($status)) {
                        $this->storeStatus($status);
                    }
                }
            }
        }

        return array_values($contacts);
    }

    /**
     * @param  array<array-key, mixed>  $message
     * @param  array<string, string>  $names
     * @return int|null client à qui répondre (message nouveau et récent), sinon null
     */
    private function storeMessage(array $message, array $names): ?int
    {
        $from = preg_replace('/\D/', '', (string) ($message['from'] ?? '')) ?? '';
        $waMessageId = (string) ($message['id'] ?? '');
        $type = (string) ($message['type'] ?? '');

        if ($from === '' || strlen($from) > 20 || $waMessageId === '' || in_array($type, self::IGNORED_TYPES, true)) {
            return null;
        }

        $sentAt = is_numeric($message['timestamp'] ?? null) ? CarbonImmutable::createFromTimestamp((int) $message['timestamp']) : now()->toImmutable();
        $sentAt = $sentAt->isFuture() ? now()->toImmutable() : $sentAt;

        $contact = WhatsAppContact::query()->createOrFirst(['wa_id' => $from]);
        $name = trim($names[$from] ?? '');

        try {
            WhatsAppMessage::query()->create([
                'whatsapp_contact_id' => $contact->id,
                'sender' => WhatsAppMessage::CLIENT,
                'type' => mb_substr($type, 0, 20),
                'content' => self::text($message),
                'wa_message_id' => $waMessageId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Notification déjà reçue : rien à refaire.
            return null;
        }

        $contact->forceFill([
            'profile_name' => $name !== '' ? mb_substr($name, 0, 120) : $contact->profile_name,
            'last_inbound_at' => $contact->last_inbound_at === null || $sentAt->greaterThan($contact->last_inbound_at) ? $sentAt : $contact->last_inbound_at,
            'last_message_at' => now(),
            'messages_count' => $contact->messages_count + 1,
        ])->save();

        return $sentAt->greaterThan(now()->subMinutes(self::STALE_MINUTES)) ? $contact->id : null;
    }

    /**
     * Texte du message (mots de passe et codes secrets masqués) : texte, bouton touché, légende d'un média.
     *
     * @param  array<array-key, mixed>  $message
     */
    private static function text(array $message): ?string
    {
        $text = match ((string) ($message['type'] ?? '')) {
            'text' => $message['text']['body'] ?? null,
            'button' => $message['button']['text'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? null,
            'image', 'video', 'document' => $message[$message['type']]['caption'] ?? null,
            default => null,
        };

        $text = is_string($text) ? trim($text) : '';

        return $text !== '' ? mb_substr(PersonalData::redactSecrets($text), 0, 4096) : null;
    }

    /**
     * Suivi d'un message envoyé : un statut n'avance que dans l'ordre (envoyé, distribué, lu) ; un échec est noté.
     *
     * @param  array<array-key, mixed>  $status
     */
    private function storeStatus(array $status): void
    {
        $message = WhatsAppMessage::query()->where('wa_message_id', (string) ($status['id'] ?? ''))->first();
        $value = (string) ($status['status'] ?? '');

        if ($message === null) {
            return;
        }

        if ($value === 'failed') {
            $error = (array) ($status['errors'][0] ?? []);
            $detail = trim(($error['code'] ?? '').' '.($error['error_data']['details'] ?? $error['title'] ?? $error['message'] ?? ''));
            $message->forceFill(['status' => 'failed', 'error' => mb_substr($detail !== '' ? $detail : 'Échec signalé par WhatsApp', 0, 300)])->save();

            return;
        }

        $order = array_flip(WhatsAppMessage::STATUSES);

        if (isset($order[$value]) && $message->status !== 'failed' && $order[$value] > ($order[$message->status] ?? -1)) {
            $message->forceFill(['status' => $value])->save();
        }
    }
}
