<?php

namespace App\Assistant;

use App\Contracts\SpeechSynthesizer;
use App\Enums\AssistantIncidentType;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantUsage;
use App\Models\AssistantVoiceEvent;
use App\Models\AssistantVoiceQuota;
use App\Models\AssistantVoiceUsage;
use App\Models\Formation;
use App\Models\User;
use App\Support\VoiceAssistant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Voix naturelle d'Ama : le texte de sa réponse est lu par une voix fabriquée par le serveur, chez le fournisseur choisi
 * dans l'admin (PROVIDERS) : Cloudflare Workers AI (MeloTTS, en français, App\Assistant\CloudflareSpeech) ou Google
 * (modèles « TTS » de Gemini, App\Assistant\GeminiSpeech, lié à l'interface SpeechSynthesizer). Le navigateur reprend
 * aussitôt la voix du téléphone si l'audio n'arrive pas (voir resources/js/ama-voice.js), quel que soit le fournisseur.
 *
 * Réglages (admin > Réglages du site > Assistante Ama, clé « site.voice_assistant.natural_voice ») : interrupteur
 * (éteint par défaut), fournisseur (« provider », Google si rien n'est enregistré), voix féminine et modèle (Google
 * seulement), demandes par minute pour tout le site (une limite par fournisseur), plafond de minutes d'audio par jour
 * (Cloudflare seulement).
 *
 * Avant chaque demande au fournisseur, dans cet ordre (« Google » ci-dessous vaut aussi pour Cloudflare) :
 * 1. Vie privée : seul le texte de la réponse d'Ama est envoyé. Le prénom de la personne connectée en est retiré ; un
 *    texte qui contient une adresse e-mail, un lien, un numéro ou un code, ou un nom propre que le visiteur a lui-même
 *    écrit (son prénom donné à Ama, par exemple), n'est jamais envoyé : la voix du téléphone le lit.
 * 2. Mémoire : une phrase écrite à l'avance (accueil, bulles, aides, demande de répéter, au revoir…) n'est fabriquée
 *    qu'une fois, puis gardée (disque « ama_voice ») et resservie sans demande à Google.
 * 3. Quota du jour épuisé (réponse du fournisseur) : plus aucun essai jusqu'à sa nouvelle journée (Google : minuit,
 *    heure du Pacifique ; Cloudflare : 0 h 00 UTC, soit 0 h 00 à Lomé).
 * 4. Cloudflare seulement : plafond de minutes d'audio du jour (« daily_minutes », 400 par défaut) ; au-delà, voix du
 *    téléphone jusqu'au lendemain. Les minutes sont mesurées dans chaque fichier reçu (AudioDuration).
 * 5. Limite du site : demandes par minute pour tout le site, une par fournisseur (« minute_limit » pour Google, 3 par
 *    défaut comme son offre gratuite ; « cloudflare_minute_limit » pour Cloudflare, 20 par défaut).
 *
 * Mémoire séparée par fournisseur (dossiers différents du disque « ama_voice »).
 *
 * Une réponse courte (jusqu'à « split_length » caractères, 300 par défaut) part en une seule demande ; plus longue, en deux
 * (la première phrase, puis la suite) : le découpage est fait par le navigateur.
 *
 * Mesure (table assistant_voice_events, jamais de texte) : temps mis par Google pour chaque demande, et cause exacte de
 * chaque passage à la voix du téléphone (CAUSES), décidé ici ou par le navigateur (délai dépassé, lecture refusée…).
 *
 * Phrases écrites à l'avance (accueil du mode appel en « Bonjour » et « Bonsoir », au revoir, demande de répéter,
 * messages de limite, « C'est transmis ! ») : préparées en arrière-plan par PrepareAmaVoicePhrases, exactement dans les
 * morceaux que le navigateur demandera, sans jamais prendre la dernière demande de la minute à un visiteur.
 */
class NaturalVoice
{
    public const string GEMINI = 'gemini';

    public const string CLOUDFLARE = 'cloudflare';

    /**
     * Fournisseurs de la voix proposés dans l'admin (code enregistré => libellé).
     *
     * @var array<string, string>
     */
    public const array PROVIDERS = [
        self::CLOUDFLARE => 'Cloudflare (MeloTTS)',
        self::GEMINI => 'Google (Gemini)',
    ];

    /**
     * Cloudflare : demandes par minute pour tout le site (par défaut, minimum, maximum). La documentation de Cloudflare
     * ne donne aucune limite par minute pour la synthèse vocale (les autres tâches vont de 300 à 3 000 demandes par
     * minute) : la vraie limite est l'offre gratuite du jour. 20 par minute laisse parler plusieurs visiteurs à la fois
     * sans qu'un abus ne vide les minutes du jour en quelques instants.
     *
     * @var array{0: int, 1: int, 2: int}
     */
    public const array CLOUDFLARE_MINUTE_LIMIT = [20, 1, 120];

    /**
     * Cloudflare : plafond de minutes d'audio fabriquées par jour (par défaut, minimum, maximum). Offre gratuite :
     * 10 000 neurones par jour ; MeloTTS : 18,63 neurones par minute d'audio, soit environ 536 minutes. 400 minutes
     * (environ 7 450 neurones) laissent une marge ; le maximum (520, environ 9 690 neurones) reste sous l'offre gratuite.
     *
     * @var array{0: int, 1: int, 2: int}
     */
    public const array DAILY_MINUTES = [400, 1, 520];

    public const float CLOUDFLARE_NEURONS_PER_MINUTE = 18.63;

    public const int CLOUDFLARE_FREE_NEURONS = 10000;

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
     * Longueur (caractères) au-delà de laquelle une réponse est demandée en deux fois (la première phrase, puis la suite) :
     * par défaut, minimum, maximum. En dessous, UNE seule demande pour toute la réponse (même valeur par défaut que
     * SPLIT_LENGTH de resources/js/ama-voice.js).
     *
     * @var array{0: int, 1: int, 2: int}
     */
    public const array SPLIT_LENGTH = [300, 80, 1500];

    /**
     * Délai (secondes) après lequel le navigateur abandonne la voix naturelle pour la voix du téléphone : par défaut,
     * minimum, maximum. Réglable dans l'admin. 10 s par défaut : Gemini 3.8 Flash TTS fabrique environ 4 secondes de voix
     * en 2,5 secondes (source non officielle), soit 6 à 8 s pour une réponse du mode appel (150 à 250 caractères) ; les
     * anciennes 4 secondes ne suffisaient qu'aux phrases très courtes, comme l'accueil.
     *
     * @var array{0: int, 1: int, 2: int}
     */
    public const array TIMEOUT = [10, 3, 20];

    /**
     * Causes pour lesquelles la voix de Gemini n'a pas dit une partie de réponse (code enregistré => libellé de l'admin),
     * qu'elle ait été lue par la voix du téléphone ou seulement affichée. Celles de BROWSER_CAUSES sont décidées par le
     * navigateur ; « old_page » : passage signalé sans cause par une page ouverte avant la mise à jour qui envoie la cause.
     * Aucune cause « Autre » : chaque passage a une cause précise.
     *
     * @var array<string, string>
     */
    public const array CAUSES = [
        'empty' => 'Texte vide ou trop long',
        'private' => 'Numéro, e-mail, lien ou prénom dans la phrase (voulu)',
        'no_key' => 'Clé Gemini absente',
        'daily_quota' => 'Quota du jour de Google épuisé',
        'site_minute_limit' => 'Limite de demandes par minute du site',
        'google_quota' => 'Limite de Google atteinte (refus 429)',
        'google_timeout' => 'Google trop lent (délai du serveur dépassé)',
        'google_error' => 'Erreur ou réponse sans audio de Google',
        'timeout' => 'Voix pas prête avant le délai du téléphone',
        'network' => 'Réseau ou serveur du site injoignable',
        'playback_refused' => 'Lecture refusée par le navigateur du téléphone',
        'playback_paused' => 'Son mis en pause par le téléphone (micro, appel, notification, écouteurs) et pas repris',
        'playback_error' => 'Fichier audio illisible par le téléphone',
        'playback_stalled' => 'Son bloqué : commencé mais jamais terminé',
        'playback' => 'Lecture refusée ou coupée par le téléphone (ancienne mesure, avant le détail des causes)',
        'old_page' => 'Page ouverte avant la mise à jour du site (cause non transmise)',
        'cloudflare_no_key' => 'Clé Cloudflare absente',
        'cloudflare_cap' => 'Plafond de minutes du jour atteint (Cloudflare, réglé dans l\'admin)',
        'cloudflare_daily_quota' => 'Offre gratuite du jour de Cloudflare épuisée (refus 3036)',
        'cloudflare_quota' => 'Cloudflare occupé ou limite atteinte (refus 429)',
        'cloudflare_timeout' => 'Cloudflare trop lent (délai du serveur dépassé)',
        'cloudflare_error' => 'Erreur ou réponse sans audio de Cloudflare',
    ];

    /**
     * Causes que le navigateur peut signaler (POST /assistante/voix/secours).
     */
    public const array BROWSER_CAUSES = ['timeout', 'network', 'playback_refused', 'playback_paused', 'playback_error', 'playback_stalled'];

    /**
     * Aucune préparation des phrases écrites à l'avance pendant un appel : il faut deux minutes sans aucune activité
     * vocale d'un visiteur (message du mode appel ou demande de voix) avant de reprendre.
     */
    public const int CALL_QUIET_SECONDS = 120;

    private const string CALL_ACTIVITY_CACHE = 'ama-voice:call-activity';

    /**
     * Préparation en arrière-plan : nombre maximal de phrases écrites à l'avance (garde-fou).
     */
    public const int PREPARE_MAX = 30;

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

    public const string CALL_ERROR = 'Ama ne peut pas répondre pour le moment. Notre équipe vous répond sur WhatsApp.';

    /**
     * Noms propres du site qu'Ama peut dire sans risque (jamais pris pour le prénom d'un visiteur).
     */
    private const array SITE_WORDS = ['ama', 'lomé', 'lome', 'school', 'coach', 'joseph', 'ahiankonou', 'whatsapp', 'google', 'meet', 'gemini',
        'mobile', 'money', 'togo', 'bénin', 'benin', 'burkina', 'faso', 'flooz', 'moov', 'mixx', 'yas', 'orange', 'wave', 'mtn', 'momo',
        'celtiis', 'cash', 'fcfa', 'ia', 'youtube', 'tiktok', 'facebook', 'chatgpt', 'claude', 'adidogomé', 'afrique'];

    // Google : noms d'origine gardés ; Cloudflare : suffixe « :cloudflare ».
    private const string DAILY_BLOCK_CACHE = 'ama-voice:daily-exhausted';

    private const string MINUTE_LIMITER = 'ama-voice:site-minute';

    /**
     * Dernière cause d'un passage à la voix du téléphone (code de CAUSES, ou « invalid_voice » pour un essai de l'admin
     * avec une voix inconnue ; pour l'admin et les tests).
     */
    public ?string $lastReason = null;

    /**
     * Fournisseur de la dernière demande (pour les mesures).
     */
    private ?string $provider = null;

    /**
     * La partie demandée ne sera pas lue par la voix du téléphone en cas d'échec (secours éteint dans l'admin, ou voix de
     * Gemini déjà entendue dans cette réponse) : l'échec est compté comme « réponse affichée sans voix ».
     */
    private bool $silentOnFailure = false;

    /**
     * @param  SpeechSynthesizer  $synthesizer  voix de Google (GeminiSpeech)
     */
    public function __construct(private readonly SpeechSynthesizer $synthesizer, private readonly CloudflareSpeech $cloudflare) {}

    /**
     * Voix naturelle allumée dans l'admin, avec Ama (Gemini) et la clé du fournisseur choisi présente sur le serveur.
     */
    public function enabled(): bool
    {
        return (bool) config('site.voice_assistant.natural_voice.enabled', false)
            && VoiceAssistant::usesGemini()
            && $this->isConfigured();
    }

    /**
     * Clé du fournisseur (celui choisi dans l'admin par défaut) présente sur le serveur ?
     */
    public function isConfigured(?string $provider = null): bool
    {
        return $this->synthesizer($provider)->isConfigured();
    }

    /**
     * Fournisseur choisi dans l'admin ; Google si rien n'est enregistré (réglages d'avant Cloudflare).
     */
    public static function provider(): string
    {
        $provider = config('site.voice_assistant.natural_voice.provider');

        return is_string($provider) && isset(self::PROVIDERS[$provider]) ? $provider : self::GEMINI;
    }

    public static function providerName(?string $provider = null): string
    {
        return ($provider ?? self::provider()) === self::CLOUDFLARE ? 'Cloudflare' : 'Google';
    }

    private function synthesizer(?string $provider = null): SpeechSynthesizer
    {
        return ($provider ?? self::provider()) === self::CLOUDFLARE ? $this->cloudflare : $this->synthesizer;
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

    /**
     * Demandes par minute pour tout le site, pour le fournisseur donné (celui choisi par défaut).
     */
    public static function minuteLimit(?string $provider = null): int
    {
        $cloudflare = ($provider ?? self::provider()) === self::CLOUDFLARE;
        [$default, $min, $max] = $cloudflare ? self::CLOUDFLARE_MINUTE_LIMIT : self::MINUTE_LIMIT;
        $value = config('site.voice_assistant.natural_voice.'.($cloudflare ? 'cloudflare_minute_limit' : 'minute_limit'));

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    /**
     * Cloudflare : plafond de minutes d'audio par jour réglé dans l'admin.
     */
    public static function dailyMinutes(): int
    {
        [$default, $min, $max] = self::DAILY_MINUTES;
        $value = config('site.voice_assistant.natural_voice.daily_minutes');

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    /**
     * Cloudflare : minutes d'audio déjà fabriquées aujourd'hui (journée UTC = journée de Lomé).
     */
    public static function usedMinutes(): float
    {
        return AssistantVoiceQuota::usedMs(self::CLOUDFLARE) / 60000;
    }

    /**
     * Cloudflare : plafond du jour atteint (ou dépassé avec ce texte, si on le donne) ?
     */
    public static function capReached(string $text = ''): bool
    {
        $used = AssistantVoiceQuota::usedMs(self::CLOUDFLARE);
        $next = $text === '' ? 0 : AudioDuration::estimate($text);

        return $text === '' ? $used >= self::dailyMinutes() * 60000 : $used + $next > self::dailyMinutes() * 60000;
    }

    /**
     * Neurones de Cloudflare estimés pour des minutes d'audio (MeloTTS : 18,63 par minute).
     */
    public static function neurons(float $minutes): int
    {
        return (int) round($minutes * self::CLOUDFLARE_NEURONS_PER_MINUTE);
    }

    private static function limiterKey(string $provider): string
    {
        return $provider === self::CLOUDFLARE ? self::MINUTE_LIMITER.':cloudflare' : self::MINUTE_LIMITER;
    }

    private static function dailyBlockKey(string $provider): string
    {
        return $provider === self::CLOUDFLARE ? self::DAILY_BLOCK_CACHE.':cloudflare' : self::DAILY_BLOCK_CACHE;
    }

    public static function timeoutMs(): int
    {
        [$default, $min, $max] = self::TIMEOUT;
        $value = config('site.voice_assistant.natural_voice.timeout');

        return 1000 * (is_numeric($value) ? max($min, min($max, (int) $value)) : $default);
    }

    /**
     * « Voix du téléphone en secours » (admin, allumé par défaut). Éteint : seule la voix de Gemini parle ; si elle échoue,
     * la réponse est affichée à l'écran sans être dite.
     */
    public static function phoneFallback(): bool
    {
        return (bool) config('site.voice_assistant.natural_voice.phone_fallback', true);
    }

    /**
     * Un visiteur utilise la voix (appel ou lecture à voix haute) : la préparation en arrière-plan attend deux minutes.
     */
    public static function markCallActivity(): void
    {
        Cache::put(self::CALL_ACTIVITY_CACHE, true, self::CALL_QUIET_SECONDS);
    }

    public static function callRecentlyActive(): bool
    {
        return Cache::get(self::CALL_ACTIVITY_CACHE) === true;
    }

    public static function splitLength(): int
    {
        [$default, $min, $max] = self::SPLIT_LENGTH;
        $value = config('site.voice_assistant.natural_voice.split_length');

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    /**
     * Quota du jour du fournisseur épuisé (refus du fournisseur) : plus aucun essai avant sa nouvelle journée.
     */
    public static function dailyBlocked(?string $provider = null): bool
    {
        return Cache::get(self::dailyBlockKey($provider ?? self::provider())) === true;
    }

    /**
     * Début de la prochaine journée du fournisseur, à l'heure de Lomé.
     */
    public static function nextReset(?string $provider = null): CarbonInterface
    {
        return ($provider ?? self::provider()) === self::CLOUDFLARE ? AssistantVoiceQuota::nextReset() : AssistantUsage::nextReset();
    }

    /**
     * Audio d'un morceau de réponse d'Ama, ou null : le navigateur lit alors avec la voix du téléphone (raison dans
     * $lastReason). Chaque cas est compté pour l'admin.
     */
    public function speak(string $text, ?User $user = null, ?string $visitorHash = null, bool $voiced = false): ?SpeechAudio
    {
        $this->lastReason = null;
        $this->provider = self::provider();
        $this->silentOnFailure = $voiced || ! self::phoneFallback();
        $text = $this->prepare($text, $user);

        if ($text === null) {
            return $this->fallback('empty');
        }

        if (! $this->isPrivate($text, $visitorHash)) {
            return $this->fallback('private', chars: mb_strlen($text));
        }

        return $this->produce($text, self::voice(), self::model(), $this->isPrewritten($text));
    }

    /**
     * Bouton « Écouter » de l'admin : la phrase d'exemple lue par le fournisseur choisi dans le formulaire (Google : avec
     * la voix et le modèle choisis). Gardée en mémoire : une seule demande par fournisseur (et, pour Google, par voix et
     * par modèle).
     */
    public function preview(string $voice, string $model, ?string $provider = null): ?SpeechAudio
    {
        $this->lastReason = null;
        $provider ??= self::GEMINI;

        if (! isset(self::PROVIDERS[$provider]) || ($provider === self::GEMINI && (! isset(self::VOICES[$voice]) || ! isset(self::MODELS[$model])))) {
            $this->lastReason = 'invalid_voice';

            return null;
        }

        return $this->produce(self::SAMPLE, $voice, $model, true, count: false, source: AssistantVoiceEvent::SOURCE_PREVIEW, provider: $provider);
    }

    /**
     * Le navigateur a lui-même abandonné la voix de Gemini : cause exacte (BROWSER_CAUSES), temps d'attente du téléphone
     * et issue (« phone » : voix du téléphone ; « silent » : réponse affichée sans voix). Aucun texte. Sans cause : page
     * ouverte avant la mise à jour (« old_page ») ; une cause inconnue n'est pas comptée (jamais envoyée par le site).
     */
    public static function recordBrowserFallback(?string $cause = null, ?int $waitedMs = null, ?string $outcome = null): void
    {
        if ($cause === null || $cause === '') {
            $cause = 'old_page';
        } elseif (! in_array($cause, self::BROWSER_CAUSES, true)) {
            return;
        }

        $silent = $outcome === 'silent';

        if (! $silent) {
            AssistantVoiceUsage::bump('fallbacks');
        }

        AssistantVoiceEvent::record(
            $silent ? AssistantVoiceEvent::SILENT : AssistantVoiceEvent::FALLBACK,
            $cause,
            $waitedMs === null ? null : min($waitedMs, 600000),
            provider: self::provider(),
        );
    }

    private function produce(string $text, string $voice, string $model, bool $cacheable, bool $count = true, string $source = AssistantVoiceEvent::SOURCE_SITE, ?string $provider = null): ?SpeechAudio
    {
        $provider ??= self::provider();
        $cloudflare = $provider === self::CLOUDFLARE;
        $this->provider = $provider;
        $path = $cacheable ? $this->cachePath($text, $voice, $model, $provider) : null;

        if ($path !== null) {
            $cached = $this->fromCache($path);

            if ($cached !== null) {
                if ($count) {
                    AssistantVoiceUsage::bump('spoken', 'cached');
                }

                return $cached;
            }
        }

        $chars = mb_strlen($text);
        $synthesizer = $this->synthesizer($provider);

        if (! $synthesizer->isConfigured()) {
            return $this->fallback($cloudflare ? 'cloudflare_no_key' : 'no_key', $count, $chars);
        }

        if (self::dailyBlocked($provider)) {
            return $this->fallback($cloudflare ? 'cloudflare_daily_quota' : 'daily_quota', $count, $chars);
        }

        // Cloudflare : plafond de minutes du jour (offre gratuite), en comptant la durée estimée de ce texte.
        if ($cloudflare && self::capReached($text)) {
            return $this->fallback('cloudflare_cap', $count, $chars);
        }

        if (! RateLimiter::attempt(self::limiterKey($provider), self::minuteLimit($provider), fn () => true, 60)) {
            return $this->fallback('site_minute_limit', $count, $chars);
        }

        AssistantVoiceUsage::bump('google_requests');
        $started = hrtime(true);

        try {
            $audio = $synthesizer->synthesize($text, $voice, $model);
        } catch (SpeechFailure $failure) {
            if ($failure->daily) {
                Cache::put(self::dailyBlockKey($provider), true, self::nextReset($provider));
            }

            $cause = match (true) {
                $failure->daily => 'daily_quota',
                $failure->quota => 'google_quota',
                $failure->timeout => 'google_timeout',
                default => 'google_error',
            };

            if ($cloudflare) {
                $cause = 'cloudflare_'.str_replace('google_', '', $cause);
            }

            AssistantVoiceUsage::bump('refusals', ...($count && ! $this->silentOnFailure ? ['fallbacks'] : []));
            AssistantVoiceEvent::record(AssistantVoiceEvent::GOOGLE, $cause, self::elapsedMs($started), $chars, $source, $provider);

            if ($count) {
                AssistantVoiceEvent::record($this->silentOnFailure ? AssistantVoiceEvent::SILENT : AssistantVoiceEvent::FALLBACK, $cause, chars: $chars, provider: $provider);
            }

            $this->lastReason = $cause;

            return null;
        }

        AssistantVoiceEvent::record(AssistantVoiceEvent::GOOGLE, null, self::elapsedMs($started), $chars, $source, $provider);

        // Cloudflare : durée réelle de l'audio fabriqué, comptée dans les minutes du jour (mémoire jamais comptée).
        if ($cloudflare) {
            AssistantVoiceQuota::add(self::CLOUDFLARE, AudioDuration::milliseconds($audio, $text));
        }

        if ($path !== null) {
            $this->toCache($path, $audio);
        }

        if ($count) {
            AssistantVoiceUsage::bump('spoken');
        }

        return $audio;
    }

    private function fallback(string $cause, bool $count = true, ?int $chars = null): null
    {
        $this->lastReason = $cause;

        if ($count) {
            if (! $this->silentOnFailure) {
                AssistantVoiceUsage::bump('fallbacks');
            }
            AssistantVoiceEvent::record($this->silentOnFailure ? AssistantVoiceEvent::SILENT : AssistantVoiceEvent::FALLBACK, $cause, chars: $chars, provider: $this->provider);
        }

        return null;
    }

    private static function elapsedMs(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
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
            self::CALL_ERROR,
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

    /**
     * Emplacement en mémoire, séparé par fournisseur : Google « modèle/voix/empreinte » (chemin d'origine, déjà rempli
     * sur le site), Cloudflare « cloudflare/melotts-fr/empreinte ».
     */
    private function cachePath(string $text, string $voice, string $model, string $provider): string
    {
        if ($provider === self::CLOUDFLARE) {
            return 'cloudflare/melotts-'.CloudflareSpeech::LANG.'/'.sha1(self::key($text));
        }

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
            $written = Storage::disk('ama_voice')->put($path.'.'.$audio->extension(), $audio->bytes);
        } catch (Throwable) {
            $written = false;
        }

        if ($written === false) {
            // Mémoire impossible à écrire : l'audio sera refait la prochaine fois. Signalé dans l'admin.
            AssistantVoiceEvent::record(AssistantVoiceEvent::MEMORY, 'write_failed');
        }
    }

    /**
     * Phrase déjà gardée en mémoire pour cette voix et ce modèle ?
     */
    public function isCached(string $text, ?string $voice = null, ?string $model = null, ?string $provider = null): bool
    {
        $path = $this->cachePath(self::normalizeSpaces($text), $voice ?? self::voice(), $model ?? self::model(), $provider ?? self::provider());

        try {
            $disk = Storage::disk('ama_voice');

            foreach (['wav', 'mp3', 'ogg'] as $extension) {
                if ($disk->exists($path.'.'.$extension)) {
                    return true;
                }
            }
        } catch (Throwable) {
            // Mémoire illisible : considérée comme vide.
        }

        return false;
    }

    /**
     * Textes exacts que le navigateur demandera pour les phrases écrites à l'avance lues à voix haute : accueil du mode
     * appel (« Bonjour » et « Bonsoir » ; le prénom n'est jamais envoyé, il est retiré avant), au revoir, demande de
     * répéter, messages de limite et d'indisponibilité, « C'est transmis ! ». Découpés comme le navigateur (pieces).
     *
     * @return list<string>
     */
    public static function preparedTexts(): array
    {
        $greetings = [];

        foreach (['Bonjour', 'Bonsoir'] as $word) {
            $greetings[] = (string) preg_replace('/^bonjour\b\s*,?\s*/iu', $word.', ', self::CALL_GREETING);
        }

        $phrases = [
            ...$greetings,
            self::CALL_GOODBYE,
            AmaPrompt::MISHEARD_REPLY,
            self::CALL_ERROR,
            ...array_map(fn (AssistantIncidentType $type): string => $type->visitorMessage(), AssistantIncidentType::cases()),
            FounderHandoff::confirmation(),
        ];

        $texts = [];

        foreach (array_unique($phrases) as $phrase) {
            foreach (self::pieces($phrase) as $piece) {
                $texts[self::key($piece)] = $piece;
            }
        }

        return array_slice(array_values($texts), 0, self::PREPARE_MAX);
    }

    /**
     * Phrases d'un texte, découpées comme createSentenceSplitter de resources/js/ama.js (fin de phrase suivie d'un espace).
     *
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?…:;])[»")\]]*\s+|\n+/u', trim($text)) ?: [];
        $sentences = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '' && preg_replace('/[\s.!?…:;•-]/u', '', $part) !== '') {
                $sentences[] = $part;
            }
        }

        return $sentences;
    }

    /**
     * Morceaux demandés par le navigateur pour un texte lu en mode appel (resources/js/ama-voice.js) : le texte entier
     * s'il ne dépasse pas « split_length » caractères, sinon la première phrase, puis toute la suite d'un bloc.
     *
     * @return list<string>
     */
    public static function pieces(string $text): array
    {
        $sentences = self::sentences($text);

        if ($sentences === []) {
            return [];
        }

        $whole = implode(' ', $sentences);

        if (count($sentences) === 1 || mb_strlen($whole) <= self::splitLength()) {
            return [$whole];
        }

        return [$sentences[0], implode(' ', array_slice($sentences, 1))];
    }

    /**
     * Phrases écrites à l'avance pas encore gardées en mémoire pour la voix et le modèle choisis.
     *
     * @return list<string>
     */
    public function missingPreparedTexts(): array
    {
        return array_values(array_filter(self::preparedTexts(), fn (string $text): bool => ! $this->isCached($text)));
    }

    /**
     * Préparation en arrière-plan : fabrique UNE phrase écrite à l'avance qui manque encore, seulement si aucun visiteur
     * n'a utilisé la voix depuis deux minutes (CALL_QUIET_SECONDS) et s'il reste au moins deux demandes libres dans la
     * minute (la dernière reste pour un visiteur). Renvoie le nombre de phrases qui
     * manquent encore, ou null s'il faut s'arrêter (voix éteinte, quota du jour épuisé, refus de Google).
     */
    public function prepareNext(): ?int
    {
        if (! $this->enabled() || self::dailyBlocked() || (self::provider() === self::CLOUDFLARE && self::capReached())) {
            return null;
        }

        $missing = $this->missingPreparedTexts();

        if ($missing === []) {
            return 0;
        }

        // Un appel a eu lieu dans les deux dernières minutes : les demandes de la minute restent aux visiteurs.
        if (self::callRecentlyActive()) {
            return count($missing);
        }

        if (RateLimiter::remaining(self::limiterKey(self::provider()), self::minuteLimit()) < 2) {
            return count($missing);
        }

        $audio = $this->produce($missing[0], self::voice(), self::model(), true, count: false, source: AssistantVoiceEvent::SOURCE_PREPARE);

        if ($audio === null && ! in_array($this->lastReason, ['site_minute_limit', 'google_quota', 'google_timeout', 'cloudflare_quota', 'cloudflare_timeout'], true)) {
            return null;
        }

        return count($missing) - ($audio !== null ? 1 : 0);
    }
}
