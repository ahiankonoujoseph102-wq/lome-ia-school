<?php

namespace App\Assistant;

use App\Mail\FounderHandoffMail;
use App\Models\AssistantConversation;
use App\Models\AssistantHandoff;
use App\Models\AssistantMessage;
use App\Support\PhoneNumber;
use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Throwable;

use function Illuminate\Support\defer;

/**
 * « Transmettre au fondateur » : quand Ama n'a pas la réponse, elle propose de transmettre la question à Coach
 * Joseph, demande dans la conversation le prénom et, au choix du visiteur, son adresse e-mail ou son numéro WhatsApp,
 * puis récapitule. Une carte de confirmation s'affiche (« Envoyer à Coach Joseph » / « Corriger ») : la demande ne
 * part QUE si le visiteur touche « Envoyer ».
 *
 * - Ama termine son récapitulatif par la ligne technique « [TRANSMETTRE] {…} » (prénom, canal, question), retirée
 *   avant l'affichage et jamais lue à voix haute : elle fait apparaître la carte (extract(), proposal()).
 * - Les adresses e-mail et numéros écrits par le visiteur ne partent jamais chez Google (PersonalData les remplace
 *   par « [adresse e-mail] » / « [numéro] ») : le serveur garde la dernière coordonnée écrite, chiffrée, 2 heures au
 *   plus (rememberContacts()), pour préremplir la carte.
 * - Format vérifié par le serveur (à la proposition de la carte et à l'envoi) : sinon Ama le redemande poliment.
 * - Envoi (submit()) : 3 demandes au plus par visiteur et par jour de Lomé, champ piège contre les robots et limites par
 *   connexion ; demande enregistrée (admin « Demandes transmises », à traiter / traitée) et e-mail envoyé tout de suite
 *   à l'admin (juste après la réponse, jamais par la file d'attente), avec « Répondre à » l'adresse du visiteur ou un
 *   bouton « Répondre sur WhatsApp ». Effacée après 90 jours, comme les conversations.
 */
class FounderHandoff
{
    public const string MARKER = '[TRANSMETTRE]';

    /**
     * Demandes transmises au plus par visiteur et par jour (journée de Lomé).
     */
    public const int DAILY_LIMIT = 3;

    /**
     * Demandes au plus par connexion (adresse IP) : par 10 minutes et par jour (cybercafé, réseau partagé).
     */
    public const int IP_TEN_MINUTES_LIMIT = 5;

    public const int IP_DAILY_LIMIT = 10;

    public const int MAX_FIRST_NAME = 60;

    public const int MAX_QUESTION = 1000;

    public const int MAX_EMAIL = 191;

    /**
     * Messages de la conversation joints à l'e-mail et à la page admin (les plus récents).
     */
    public const int EXCERPT_MESSAGES = 8;

    /**
     * Durée pendant laquelle la dernière coordonnée écrite est gardée pour préremplir la carte.
     */
    public const int CONTACT_MINUTES = 120;

    public const string EMAIL_EXAMPLE = 'prenom@gmail.com';

    public const string PHONE_EXAMPLE = '+228 91 23 45 67';

    /**
     * Garde, chiffrées, la dernière adresse e-mail et le dernier numéro écrits par le visiteur (avant leur
     * remplacement par « [adresse e-mail] » / « [numéro] » dans ce qui part chez Google).
     */
    public static function rememberContacts(string $conversationId, string $raw): void
    {
        $found = [];

        // Même motif que PersonalData::redact (ce qui est remplacé chez Google est ce qui est gardé ici).
        if (preg_match_all('/[^\s@]+@[^\s@]+\.[^\s@]+/u', $raw, $emails) > 0) {
            $found[AssistantHandoff::EMAIL] = trim((string) end($emails[0]), " \t.,;:!?()«»\"'");
        }

        if (preg_match_all('/(?:\+|\b00)?\d(?:[\s.\-]?\d){7,}/u', $raw, $phones) > 0) {
            $found[AssistantHandoff::WHATSAPP] = trim((string) end($phones[0]));
        }

        if ($found === []) {
            return;
        }

        $key = self::contactKey($conversationId);
        $stored = self::rememberedContacts($conversationId);
        Cache::put($key, Crypt::encryptString((string) json_encode([...$stored, ...$found])), now()->addMinutes(self::CONTACT_MINUTES));
    }

    /**
     * @return array<string, string>
     */
    public static function rememberedContacts(string $conversationId): array
    {
        $value = Cache::get(self::contactKey($conversationId));

        if (! is_string($value)) {
            return [];
        }

        try {
            $data = json_decode(Crypt::decryptString($value), true);
        } catch (Throwable) {
            return [];
        }

        return is_array($data) ? array_filter($data, fn ($contact, $channel): bool => is_string($contact) && is_string($channel), ARRAY_FILTER_USE_BOTH) : [];
    }

    public static function forgetContacts(string $conversationId): void
    {
        Cache::forget(self::contactKey($conversationId));
    }

    private static function contactKey(string $conversationId): string
    {
        return 'ama:handoff-contact:'.$conversationId;
    }

    /**
     * Sépare la réponse d'Ama et la ligne technique « [TRANSMETTRE] {…} » (jamais montrée ni lue).
     *
     * @return array{text: string, draft: array{first_name: string, channel: string, contact: string, question: string}|null}
     */
    public static function extract(string $reply): array
    {
        if (preg_match('/\[\s*TRANSMETTRE\s*\]?/iu', $reply, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return ['text' => $reply, 'draft' => null];
        }

        $position = (int) $match[0][1];
        $text = rtrim(substr($reply, 0, $position));
        $tail = substr($reply, $position + strlen($match[0][0]));
        $start = strpos($tail, '{');
        $end = strrpos($tail, '}');
        $data = $start !== false && $end !== false && $end > $start ? json_decode(substr($tail, $start, $end - $start + 1), true) : null;
        $data = is_array($data) ? $data : [];

        // Valeur d'exemple recopiée telle quelle (« <prénom> ») : ignorée.
        $field = fn (string ...$names): string => (string) preg_replace('/^<[^>]*>$/u', '', trim((string) (collect($names)->map(fn (string $name) => $data[$name] ?? null)->first(fn ($value) => is_scalar($value) && trim((string) $value) !== '') ?? '')));
        $channel = mb_strtolower($field('canal', 'channel'));
        $contact = $field('contact');

        return [
            'text' => $text,
            'draft' => [
                'first_name' => self::cleanFirstName($field('prenom', 'prénom', 'first_name')),
                'channel' => match (true) {
                    str_contains($channel, 'mail') => AssistantHandoff::EMAIL,
                    str_contains($channel, 'whatsapp') => AssistantHandoff::WHATSAPP,
                    default => '',
                },
                // « [adresse e-mail] », « [numéro] » : coordonnée remplacée avant Google, reprise de ce que le site a gardé.
                'contact' => str_starts_with($contact, '[') ? '' : $contact,
                'question' => mb_substr($field('question'), 0, self::MAX_QUESTION),
            ],
        ];
    }

    /**
     * Carte de confirmation à partir du récapitulatif d'Ama. À l'écrit, la carte n'apparaît que si tout est prêt :
     * sinon, Ama redemande poliment ce qui manque (problem). En mode appel, la carte s'affiche à la fin de l'appel,
     * pour écrire la coordonnée (jamais dictée à voix haute).
     *
     * @param  array{first_name: string, channel: string, contact: string, question: string}  $draft
     * @return array{card: array{first_name: string, channel: string, contact: string, question: string}|null, problem: string|null}
     */
    public static function proposal(array $draft, string $conversationId, bool $call): array
    {
        $remembered = self::rememberedContacts($conversationId);

        if ($draft['channel'] !== '' && isset($remembered[$draft['channel']])) {
            $draft['contact'] = $remembered[$draft['channel']];
        }

        if ($call) {
            return ['card' => $draft, 'problem' => null];
        }

        $problem = match (true) {
            $draft['first_name'] === '' => 'Pour transmettre votre question à Coach Joseph, quel est votre prénom ?',
            $draft['channel'] === '' => 'Comment voulez-vous recevoir la réponse de Coach Joseph : par e-mail ou sur WhatsApp ?',
            $draft['question'] === '' => 'Quelle question voulez-vous transmettre à Coach Joseph ?',
            $draft['contact'] === '' => $draft['channel'] === AssistantHandoff::EMAIL
                ? 'Pour que Coach Joseph puisse vous répondre, quelle est votre adresse e-mail ? Par exemple : '.self::EMAIL_EXAMPLE
                : 'Pour que Coach Joseph puisse vous répondre, quel est votre numéro WhatsApp, avec l\'indicatif du pays ? Par exemple : '.self::PHONE_EXAMPLE,
            self::normalizeContact($draft['channel'], $draft['contact']) === null => self::invalidMessage($draft['channel']),
            default => null,
        };

        return ['card' => $problem === null ? $draft : null, 'problem' => $problem];
    }

    /**
     * Adresse e-mail (en minuscules) ou numéro WhatsApp au format international, ou null si le format est faux.
     * Le numéro doit commencer par l'indicatif du pays (« + » ou « 00 »).
     */
    public static function normalizeContact(string $channel, string $value): ?string
    {
        $value = trim((string) preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', ' ', $value));

        if ($channel === AssistantHandoff::EMAIL) {
            $value = mb_strtolower(str_replace(' ', '', $value));

            return Validator::make(['email' => $value], ['email' => ['required', 'string', 'max:'.self::MAX_EMAIL, 'email:rfc,filter']])->passes()
                && preg_match('/@[^@]+\.[a-z]{2,}$/', $value) === 1 ? $value : null;
        }

        if ($channel === AssistantHandoff::WHATSAPP) {
            if (preg_match('/^(?:\+|00)/', ltrim($value, ' (')) !== 1 || preg_match('/[^\d\s+().\-]/', $value) === 1) {
                return null;
            }

            return PhoneNumber::normalize($value);
        }

        return null;
    }

    public static function invalidMessage(string $channel): string
    {
        return $channel === AssistantHandoff::EMAIL
            ? 'Il me semble que cette adresse e-mail n\'est pas complète. Pouvez-vous me la réécrire ? Par exemple : '.self::EMAIL_EXAMPLE
            : 'Il me semble que ce numéro WhatsApp n\'est pas complet. Pouvez-vous me le réécrire avec l\'indicatif du pays ? Par exemple : '.self::PHONE_EXAMPLE;
    }

    /**
     * Message d'Ama après l'envoi (horaires lus dans les Réglages du site).
     */
    public static function confirmation(): string
    {
        $hours = trim((string) preg_replace('/,\s*/u', ' ', lcfirst(trim((string) config('site.contact.hours')))), ' .');

        return 'C\'est transmis ! Coach Joseph vous répondra dès que possible'.($hours !== '' ? ', '.$hours : '').'. Merci pour votre patience.';
    }

    public static function limitMessage(): string
    {
        return 'Vous avez déjà transmis '.self::DAILY_LIMIT.' questions aujourd\'hui. Pour une autre demande, écrivez-nous sur WhatsApp : notre équipe vous répondra.';
    }

    private static function cleanFirstName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return mb_substr($name, 0, self::MAX_FIRST_NAME);
    }

    /**
     * Envoi de la demande (bouton « Envoyer à Coach Joseph » de la carte, ou formulaire de relève).
     * Toujours une réponse simple pour le navigateur : ok, ou les erreurs de format à corriger, ou la limite du jour.
     *
     * @param  array<array-key, mixed>  $input
     * @param  array<array-key, mixed>  $context
     * @return array<string, mixed>
     */
    public function submit(array $input, array $context, string $visitorHash, string $ipHash): array
    {
        $firstName = self::cleanFirstName(is_string($input['first_name'] ?? null) ? $input['first_name'] : '');
        $channel = in_array($input['channel'] ?? null, [AssistantHandoff::EMAIL, AssistantHandoff::WHATSAPP], true) ? (string) $input['channel'] : '';
        $contact = is_string($input['contact'] ?? null) ? $input['contact'] : '';
        $question = trim(is_string($input['question'] ?? null) ? str_replace("\r\n", "\n", $input['question']) : '');
        $source = in_array($input['source'] ?? null, ['chat', 'call', 'form'], true) ? (string) $input['source'] : 'form';

        $errors = [];

        if ($firstName === '' || preg_match('/\d|@|https?:/iu', $firstName) === 1) {
            $errors['first_name'] = 'Écrivez votre prénom, par exemple : Awa.';
        }

        if ($channel === '') {
            $errors['channel'] = 'Choisissez comment recevoir la réponse : par e-mail ou sur WhatsApp.';
        }

        $normalized = $channel !== '' ? self::normalizeContact($channel, $contact) : null;

        if ($channel !== '' && $normalized === null) {
            $errors['contact'] = trim($contact) === ''
                ? ($channel === AssistantHandoff::EMAIL ? 'Écrivez votre adresse e-mail, par exemple : '.self::EMAIL_EXAMPLE.'.' : 'Écrivez votre numéro WhatsApp avec l\'indicatif du pays, par exemple : '.self::PHONE_EXAMPLE.'.')
                : ($channel === AssistantHandoff::EMAIL ? 'Cette adresse e-mail n\'est pas complète. Exemple : '.self::EMAIL_EXAMPLE.'.' : 'Ce numéro WhatsApp n\'est pas complet : ajoutez l\'indicatif du pays. Exemple : '.self::PHONE_EXAMPLE.'.');
        }

        if (mb_strlen($question) < 3) {
            $errors['question'] = 'Écrivez la question à transmettre à Coach Joseph.';
        } elseif (mb_strlen($question) > self::MAX_QUESTION) {
            $errors['question'] = 'Votre question est trop longue : '.self::MAX_QUESTION.' caractères au maximum.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                // Ama redemande poliment ce qui ne va pas (la coordonnée en premier).
                'reply' => isset($errors['contact']) && $channel !== '' ? self::invalidMessage($channel) : 'Il manque une information pour transmettre votre question. Pouvez-vous vérifier la fiche ?',
            ];
        }

        if ($this->limitReached($visitorHash, $ipHash)) {
            return ['ok' => false, 'limit' => true, 'reply' => self::limitMessage(), 'whatsapp' => WhatsApp::url()];
        }

        $conversationId = is_string($input['conversation'] ?? null) ? $input['conversation'] : null;
        $conversation = $conversationId !== null
            ? AssistantConversation::query()->where('public_id', $conversationId)->where('visitor_hash', $visitorHash)->first()
            : null;
        $context = VoiceAssistant::pickContext($context);

        $handoff = AssistantHandoff::query()->create([
            'assistant_conversation_id' => $conversation?->id,
            'visitor_hash' => $visitorHash,
            'first_name' => $firstName,
            'channel' => $channel,
            'email' => $channel === AssistantHandoff::EMAIL ? $normalized : null,
            'phone' => $channel === AssistantHandoff::WHATSAPP ? $normalized : null,
            // Jamais de mot de passe ni de code secret, même dans la question.
            'question' => PersonalData::redactSecrets($question),
            'page' => $conversation->page ?? (($context['page'] ?? '') ?: null),
            'formation' => $conversation->formation ?? (($context['formation'] ?? '') ?: null),
            'excerpt' => $conversation !== null ? $this->excerpt($conversation) : null,
            'source' => $source,
            'status' => AssistantHandoff::PENDING,
        ]);

        RateLimiter::hit('ama-handoff:ip10:'.$ipHash, 600);
        RateLimiter::hit('ama-handoff:ipday:'.$ipHash, 86400);

        $reply = self::confirmation();

        if ($conversation !== null) {
            $conversation->messages()->create(['role' => AssistantMessage::AMA, 'content' => $reply, 'unanswered' => false]);
            $conversation->messages_count++;
            $conversation->last_message_at = now();
            $conversation->save();
            self::forgetContacts($conversation->public_id);
        }

        // Tout de suite (juste après la réponse), jamais par la file d'attente : Joseph est prévenu sans attendre
        // le planificateur. Un échec d'envoi est noté dans le journal ; la demande reste dans l'admin.
        defer(function () use ($handoff): void {
            try {
                Mail::to((string) config('site.payments.admin_email'))->send(new FounderHandoffMail($handoff));
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        return ['ok' => true, 'reply' => $reply];
    }

    private function limitReached(string $visitorHash, string $ipHash): bool
    {
        $today = AssistantHandoff::query()
            ->where('visitor_hash', $visitorHash)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return $today >= self::DAILY_LIMIT
            || RateLimiter::tooManyAttempts('ama-handoff:ip10:'.$ipHash, self::IP_TEN_MINUTES_LIMIT)
            || RateLimiter::tooManyAttempts('ama-handoff:ipday:'.$ipHash, self::IP_DAILY_LIMIT);
    }

    /**
     * Derniers messages de la conversation (déjà nettoyés des numéros, adresses et codes), une ligne par message.
     */
    private function excerpt(AssistantConversation $conversation): string
    {
        return AssistantMessage::query()
            ->where('assistant_conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(self::EXCERPT_MESSAGES)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (AssistantMessage $message): string => ($message->role === AssistantMessage::VISITOR ? 'Visiteur : ' : 'Ama : ').mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', $message->content)), 0, 400, '…'))
            ->implode("\n");
    }
}
