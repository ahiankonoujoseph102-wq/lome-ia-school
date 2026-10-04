<?php

namespace App\Assistant;

use App\Contracts\SpeechSynthesizer;
use App\Enums\AssistantIncidentType;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantUsage;
use App\Models\AssistantVoiceUsage;
use App\Models\Formation;
use App\Models\User;
use App\Support\VoiceAssistant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Voix naturelle d'Ama : le texte de sa réponse est lu par une voix des modèles « TTS » de Gemini, fabriquée par le
 * serveur ; le navigateur reprend aussitôt la voix du téléphone si l'audio n'arrive pas (voir resources/js/ama-voice.js).
 *
 * Réglages (admin > Réglages du site > Assistante Ama, clé « site.voice_assistant.natural_voice ») : interrupteur
 * (éteint par défaut), voix féminine, modèle (Flash Lite TTS par défaut), demandes par minute pour tout le site.
 *
 * Avant chaque demande à Google, dans cet ordre :
 * 1. Vie privée : seul le texte de la réponse d'Ama est envoyé. Le prénom de la personne connectée en est retiré ; un
 *    texte qui contient une adresse e-mail, un lien, un numéro ou un code, ou un nom propre que le visiteur a lui-même
 *    écrit (son prénom donné à Ama, par exemple), n'est jamais envoyé : la voix du téléphone le lit.
 * 2. Mémoire : une phrase écrite à l'avance (accueil, bulles, aides, demande de répéter, au revoir…) n'est fabriquée
 *    qu'une fois, puis gardée (disque « ama_voice ») et resservie sans demande à Google.
 * 3. Quota du jour épuisé (réponse de Google) : plus aucun essai jusqu'à la nouvelle journée de Google.
 * 4. Limite du site : « minute_limit » demandes par minute pour tout le site (3 par défaut, comme l'offre gratuite).
 */
class NaturalVoice
{
    /**
     * Modèles proposés dans l'admin (identifiants de Google), le premier par défaut.
     *
     * @var array<string, string>
     */
    public const array MODELS = [
        'gemini-3.8-flash-lite-tts' => 'Gemini 3.8 Flash Lite TTS (rapide, par défaut)',
        'gemini-3.8-flash-tts' => 'Gemini 3.8 Flash TTS (plus expressif, plus lent)',
    ];

    /**
     * Voix féminines préenregistrées de Gemini, avec la description donnée par Google (traduite).
     *
     * @var array<string, string>
     */
    public const array VOICES = [
        'Kore' => 'Ferme',
        'Sulafat' => 'Chaleureuse',
        'Aoede' => 'Légère',
        'Leda' => 'Jeune',
        'Zephyr' => 'Lumineuse',
        'Autonoe' => 'Claire et vive',
        'Callirrhoe' => 'Décontractée',
        'Despina' => 'Douce et fluide',
        'Erinome' => 'Nette',
        'Laomedeia' => 'Enjouée',
        'Achernar' => 'Douce',
        'Gacrux' => 'Mûre',
        'Pulcherrima' => 'Assurée',
        'Vindemiatrix' => 'Délicate',
    ];

    public const string DEFAULT_VOICE = 'Kore';

    /**
     * Demandes par minute pour tout le site : par défaut, minimum, maximum.
     *
     * @var array{0: int, 1: int, 2: int}
     */
    public const array MINUTE_LIMIT = [3, 1, 60];

    /**
     * Délai (millisecondes) après lequel le navigateur abandonne la voix naturelle pour la voix du téléphone.
     */
    public const int BROWSER_TIMEOUT_MS = 4000;

    /**
     * Longueur maximale d'un texte à lire (la réponse la plus longue d'Ama fait 1 500 caractères).
     */
    public const int MAX_LENGTH = 1600;

    /**
     * Phrase lue par le bouton « Écouter » de l'admin.
     */
    public const string SAMPLE = VoiceAssistant::DEFAULT_GREETING;

    /**
     * Phrases du mode appel écrites dans le navigateur (mêmes textes que resources/js/ama-call.js).
     */
    public const string CALL_GREETING = 'Bonjour, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.';

    public const string CALL_GOODBYE = 'Je ne vous entends plus. Merci pour votre appel, et à bientôt sur Lomé IA School !';

    /**
     * Noms propres du site qu'Ama peut dire sans risque (jamais pris pour le prénom d'un visiteur).
     */
    private const array SITE_WORDS = ['ama', 'lomé', 'lome', 'school', 'coach', 'joseph', 'ahiankonou', 'whatsapp', 'google', 'meet', 'gemini',
        'mobile', 'money', 'togo', 'bénin', 'benin', 'burkina', 'faso', 'flooz', 'moov', 'mixx', 'yas', 'orange', 'wave', 'mtn', 'momo',
        'celtiis', 'cash', 'fcfa', 'ia', 'youtube', 'tiktok', 'facebook', 'chatgpt', 'claude', 'adidogomé', 'afrique'];

    private const string DAILY_BLOCK_CACHE = 'ama-voice:daily-exhausted';

    private const string MINUTE_LIMITER = 'ama-voice:site-minute';

    /**
     * Dernière raison d'un passage à la voix du téléphone (pour l'admin et les tests).
     */
    public ?string $lastReason = null;

    public function __construct(private readonly SpeechSynthesizer $synthesizer) {}

    /**
     * Voix naturelle allumée dans l'admin, avec Ama (Gemini) et la clé présente sur le serveur.
     */
    public function enabled(): bool
    {
        return (bool) config('site.voice_assistant.natural_voice.enabled', false)
            && VoiceAssistant::usesGemini()
            && $this->synthesizer->isConfigured();
    }

    public function isConfigured(): bool
    {
        return $this->synthesizer->isConfigured();
    }

    public static function voice(): string
    {
        $voice = config('site.voice_assistant.natural_voice.voice');

        return is_string($voice) && isset(self::VOICES[$voice]) ? $voice : self::DEFAULT_VOICE;
    }

    public static function model(): string
    {
        $model = config('site.voice_assistant.natural_voice.model');

        return is_string($model) && isset(self::MODELS[$model]) ? $model : array_key_first(self::MODELS);
    }

    public static function minuteLimit(): int
    {
        [$default, $min, $max] = self::MINUTE_LIMIT;
        $value = config('site.voice_assistant.natural_voice.minute_limit');

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    /**
     * Quota du jour de Google épuisé : plus aucun essai avant la nouvelle journée de Google.
     */
    public static function dailyBlocked(): bool
    {
        return Cache::get(self::DAILY_BLOCK_CACHE) === true;
    }

    /**
     * Audio d'un morceau de réponse d'Ama, ou null : le navigateur lit alors avec la voix du téléphone (raison dans
     * $lastReason). Chaque cas est compté pour l'admin.
     */
    public function speak(string $text, ?User $user = null, ?string $visitorHash = null): ?SpeechAudio
    {
        $this->lastReason = null;
        $text = $this->prepare($text, $user);

        if ($text === null) {
            return $this->fallback('texte vide ou trop long');
        }

        if (! $this->isPrivate($text, $visitorHash)) {
            return $this->fallback('donnée personnelle');
        }

        return $this->produce($text, self::voice(), self::model(), $this->isPrewritten($text));
    }

    /**
     * Bouton « Écouter » de l'admin : la phrase d'exemple lue par la voix et le modèle choisis (gardée en mémoire,
     * une seule demande à Google par voix et par modèle).
     */
    public function preview(string $voice, string $model): ?SpeechAudio
    {
        $this->lastReason = null;

        if (! isset(self::VOICES[$voice]) || ! isset(self::MODELS[$model])) {
            $this->lastReason = 'voix ou modèle inconnu';

            return null;
        }

        return $this->produce(self::SAMPLE, $voice, $model, true, count: false);
    }

    /**
     * Le navigateur a lui-même repris la voix du téléphone (audio pas prêt à temps, lecture impossible).
     */
    public static function recordBrowserFallback(): void
    {
        AssistantVoiceUsage::bump('fallbacks');
    }

    private function produce(string $text, string $voice, string $model, bool $cacheable, bool $count = true): ?SpeechAudio
    {
        $path = $cacheable ? $this->cachePath($text, $voice, $model) : null;

        if ($path !== null) {
            $cached = $this->fromCache($path);

            if ($cached !== null) {
                if ($count) {
                    AssistantVoiceUsage::bump('spoken', 'cached');
                }

                return $cached;
            }
        }

        if (! $this->synthesizer->isConfigured()) {
            return $this->fallback('clé absente', $count);
        }

        if (self::dailyBlocked()) {
            return $this->fallback('quota du jour épuisé', $count);
        }

        if (! RateLimiter::attempt(self::MINUTE_LIMITER, self::minuteLimit(), fn () => true, 60)) {
            return $this->fallback('limite par minute du site', $count);
        }

        AssistantVoiceUsage::bump('google_requests');

        try {
            $audio = $this->synthesizer->synthesize($text, $voice, $model);
        } catch (SpeechFailure $failure) {
            if ($failure->daily) {
                Cache::put(self::DAILY_BLOCK_CACHE, true, AssistantUsage::nextReset());
            }

            AssistantVoiceUsage::bump('refusals', ...($count ? ['fallbacks'] : []));
            $this->lastReason = $failure->daily ? 'quota du jour épuisé' : ($failure->quota ? 'quota atteint' : 'refus de Google');

            return null;
        }

        if ($path !== null) {
            $this->toCache($path, $audio);
        }

        if ($count) {
            AssistantVoiceUsage::bump('spoken');
        }

        return $audio;
    }

    private function fallback(string $reason, bool $count = true): null
    {
        $this->lastReason = $reason;

        if ($count) {
            AssistantVoiceUsage::bump('fallbacks');
        }

        return null;
    }

    /**
     * Texte envoyé à Google : prénom de la personne connectée retiré, espaces nettoyés ; null s'il est vide ou trop long.
     */
    private function prepare(string $text, ?User $user): ?string
    {
        $text = self::normalizeSpaces($text);
        $first = $user !== null ? VoiceAssistant::firstName($user) : '';

        if ($first !== '') {
            $text = self::removeWord($text, $first);
        }

        return $text === '' || mb_strlen($text) > self::MAX_LENGTH || preg_match('/[\p{L}\p{N}]/u', $text) !== 1 ? null : $text;
    }

    /**
     * Retire un mot (un prénom) d'une phrase, sans laisser d'espace avant une virgule.
     */
    public static function removeWord(string $text, string $word): string
    {
        $text = (string) preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/iu', '', $text);
        $text = (string) preg_replace('/\s+([,.])/u', '$1', $text);
        $text = (string) preg_replace('/([,;:])(?=[,.!?;:])/u', '', $text);

        return self::normalizeSpaces($text);
    }

    private static function normalizeSpaces(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['•', '*', '#', '_', '`'], ' ', $text)));
    }

    /**
     * Le texte peut-il partir chez Google ? Jamais d'adresse e-mail, de lien, de numéro ou de code, ni de nom propre
     * écrit par le visiteur lui-même dans sa conversation en cours.
     */
    public function isPrivate(string $text, ?string $visitorHash): bool
    {
        if (PersonalData::redact($text) !== $text || preg_match('/\[\s*(TRANSMETTRE|INCONNU)/iu', $text) === 1) {
            return false;
        }

        $visitorWords = $this->visitorWords($visitorHash);

        if ($visitorWords === []) {
            return true;
        }

        $allowed = $this->siteWords();

        // Mots à majuscule hors début de phrase (un prénom repris par Ama : « Merci Kossi »).
        preg_match_all('/(?<![.!?…]\s)(?<!^)(?<=\s)(\p{Lu}[\p{L}\'’-]+)/u', $text, $matches);

        foreach ($matches[1] as $word) {
            $word = mb_strtolower($word);

            if (mb_strlen($word) >= 2 && isset($visitorWords[$word]) && ! isset($allowed[$word])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mots écrits par le visiteur dans sa conversation en cours (derniers messages, 2 dernières heures).
     *
     * @return array<string, true>
     */
    private function visitorWords(?string $visitorHash): array
    {
        if ($visitorHash === null) {
            return [];
        }

        $conversation = AssistantConversation::query()
            ->where('visitor_hash', $visitorHash)
            ->where('last_message_at', '>=', now()->subHours(2))
            ->latest('last_message_at')
            ->first();

        if ($conversation === null) {
            return [];
        }

        $words = [];
        $messages = $conversation->messages()->where('role', AssistantMessage::VISITOR)->latest('id')->limit(8)->pluck('content');

        foreach ($messages as $content) {
            preg_match_all('/[\p{L}\'’-]{2,}/u', (string) $content, $found);

            foreach ($found[0] as $word) {
                $words[mb_strtolower($word)] = true;
            }
        }

        return $words;
    }

    /**
     * Noms propres du site : école, coordonnées, opérateurs et titres des formations.
     *
     * @return array<string, true>
     */
    private function siteWords(): array
    {
        $titles = Cache::remember('ama-voice:site-words', 600, fn (): string => Formation::query()->pluck('title')->implode(' '));
        preg_match_all('/[\p{L}\'’-]{2,}/u', $titles.' '.self::corpus(), $found);

        $words = array_fill_keys(self::SITE_WORDS, true);

        foreach ($found[0] as $word) {
            $words[mb_strtolower($word)] = true;
        }

        return $words;
    }

    /**
     * Phrase écrite à l'avance (ou morceau d'une telle phrase, le navigateur les découpe) : gardée en mémoire.
     */
    public function isPrewritten(string $text): bool
    {
        $text = self::key($text);

        return mb_strlen($text) >= 6 && str_contains(self::key(self::corpus()), $text);
    }

    /**
     * Toutes les phrases écrites à l'avance, avec « Bonjour » ou « Bonsoir » devant (voir personalGreeting de ama.js).
     */
    public static function corpus(): string
    {
        $phrases = [
            VoiceAssistant::DEFAULT_GREETING,
            self::CALL_GREETING,
            self::CALL_GOODBYE,
            AmaPrompt::MISHEARD_REPLY,
            FounderHandoff::confirmation(),
            ...array_map(fn (array $difficulty): string => $difficulty[1], VoiceAssistant::DIFFICULTIES),
            ...array_map(fn (string $type): string => VoiceAssistant::nudgeText($type), array_keys(VoiceAssistant::NUDGES)),
            ...array_map(fn (AssistantIncidentType $type): string => $type->visitorMessage(), AssistantIncidentType::cases()),
        ];

        $variants = [];

        foreach (array_unique($phrases) as $phrase) {
            $rest = (string) preg_replace('/^(bonjour|bonsoir|salut|hello)\b\s*[,!.]?\s*/iu', '', $phrase);
            $variants[] = $phrase;

            foreach (['Bonjour', 'Bonsoir'] as $word) {
                $variants[] = $rest === $phrase ? $word.' ! '.$phrase : $word.', '.$rest;
            }
        }

        return implode("\n", $variants);
    }

    private static function key(string $text): string
    {
        return mb_strtolower(self::normalizeSpaces(str_replace(['’', ' ', ' '], ["'", ' ', ' '], $text)));
    }

    private function cachePath(string $text, string $voice, string $model): string
    {
        return Str::slug($model).'/'.Str::lower($voice).'/'.sha1(self::key($text));
    }

    private function fromCache(string $path): ?SpeechAudio
    {
        try {
            $disk = Storage::disk('ama_voice');

            foreach (['wav', 'mp3', 'ogg'] as $extension) {
                $bytes = $disk->get($path.'.'.$extension);

                if (is_string($bytes) && $bytes !== '') {
                    return new SpeechAudio($bytes, SpeechAudio::mimeFor($extension));
                }
            }
        } catch (Throwable) {
            // Mémoire illisible : l'audio est simplement refait.
        }

        return null;
    }

    private function toCache(string $path, SpeechAudio $audio): void
    {
        try {
            Storage::disk('ama_voice')->put($path.'.'.$audio->extension(), $audio->bytes);
        } catch (Throwable) {
            // Mémoire impossible à écrire : l'audio sera refait la prochaine fois.
        }
    }
}
