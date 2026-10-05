<?php

namespace App\Http\Controllers\Site;

use App\Assistant\AmaChat;
use App\Assistant\AmaPrompt;
use App\Assistant\FounderHandoff;
use App\Assistant\NaturalVoice;
use App\Enums\AssistantIncidentType;
use App\Http\Controllers\Controller;
use App\Models\AssistantIncident;
use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fenêtre de discussion d'Ama (Gemini) : un message du visiteur, une réponse d'Ama (d'un bloc à l'écrit, en continu
 * dans le mode appel).
 *
 * Toujours une réponse JSON 200, jamais d'erreur technique : message trop long, limite atteinte ou Gemini
 * indisponible donnent un message clair (et, sauf message trop long, le bouton « Écrivez-nous sur WhatsApp »).
 * Le visiteur est reconnu par un identifiant aléatoire (cookie « ama_visitor »), dont seule une empreinte HMAC
 * est enregistrée ; son adresse IP ne sert qu'à la limite par minute, jamais enregistrée.
 */
class AssistantController extends Controller
{
    public const string VISITOR_COOKIE = 'ama_visitor';

    public function message(Request $request, AmaChat $chat): JsonResponse
    {
        return $this->json($this->answer($request, $chat, null));
    }

    /**
     * Mode appel : même message, mêmes contrôles et mêmes limites, mais la réponse est envoyée en continu, au format
     * « text/event-stream » (celui que les serveurs intermédiaires, Nginx ou Cloudflare, transmettent sans attendre) :
     * une ligne « data: {JSON} » par événement, {"type":"text","text":"…"} pour chaque morceau (lu à voix haute dès la
     * première phrase), puis {"type":"done",…} avec la réponse complète (mêmes champs que message()). Une ligne de
     * commentaire part tout de suite pour ouvrir la connexion. Hébergement qui retient la réponse jusqu'au bout : tout
     * arrive d'un coup, l'appel fonctionne quand même.
     */
    public function call(Request $request, AmaChat $chat): StreamedResponse
    {
        $visitor = $this->visitor();

        return response()->stream(function () use ($request, $chat, $visitor): void {
            // Le visiteur coupe la parole à Ama (connexion fermée) : la réponse est quand même enregistrée,
            // pour que la conversation reste complète.
            ignore_user_abort(true);

            $flush = function (string $text): void {
                echo $text;

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };
            $emit = fn (array $event) => $flush('data: '.json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n");
            $flush(": ama\n\n");

            $result = $this->answer($request, $chat, fn (string $text) => $emit(['type' => 'text', 'text' => $text]), $visitor);
            $emit(['type' => 'done', ...$result]);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            // Demande aux serveurs intermédiaires (Nginx) de transmettre chaque morceau sans attendre.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * « Transmettre au fondateur » : envoi de la demande, seulement au toucher de « Envoyer à Coach Joseph » (carte de
     * confirmation) ou du bouton du formulaire de relève. Format de la coordonnée vérifié ici ; 3 demandes au plus par
     * visiteur et par jour ; champ piège « website » contre les robots (réponse neutre, rien d'enregistré).
     */
    public function handoff(Request $request, FounderHandoff $handoff): JsonResponse
    {
        $visitor = $this->visitor();

        // Ne dépend pas de Gemini (clé absente, panne, limite) : seulement d'Ama activée dans l'admin.
        if (! (bool) config('site.voice_assistant.enabled', false) || ! VoiceAssistant::usesGemini()) {
            return $this->json(['ok' => false, 'reply' => 'Ama n\'est pas disponible pour le moment. Notre équipe vous répond sur WhatsApp.', 'whatsapp' => WhatsApp::url()]);
        }

        // Robot : le champ piège, invisible pour les visiteurs, est rempli.
        if (filled($request->input('website'))) {
            return $this->json(['ok' => true, 'reply' => FounderHandoff::confirmation()]);
        }

        $context = $request->input('context');

        return $this->json($handoff->submit(
            $request->only(['first_name', 'channel', 'contact', 'question', 'conversation', 'source']),
            is_array($context) ? $context : [],
            hash_hmac('sha256', $visitor, (string) config('app.key')),
            hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ));
    }

    /**
     * Voix naturelle d'Ama : audio d'un morceau de sa réponse (fabriqué par Google avec la clé du serveur, ou tiré de la
     * mémoire pour une phrase écrite à l'avance). Sinon {"fallback": true} : le navigateur lit aussitôt avec la voix du
     * téléphone. Jamais d'erreur : toujours une réponse 200.
     */
    public function voice(Request $request, NaturalVoice $voice): Response
    {
        if (! VoiceAssistant::enabled() || ! $voice->enabled()) {
            return $this->json(['fallback' => true]);
        }

        $text = $request->input('text');
        $visitor = $request->cookie(self::VISITOR_COOKIE);
        $visitorHash = is_string($visitor) && $visitor !== '' ? hash_hmac('sha256', $visitor, (string) config('app.key')) : null;
        $audio = is_string($text) ? $voice->speak($text, $request->user(), $visitorHash) : null;

        if ($audio === null) {
            return $this->json(['fallback' => true]);
        }

        return response($audio->bytes, 200, [
            'Content-Type' => $audio->mime,
            'Content-Length' => (string) strlen($audio->bytes),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Le navigateur a repris la voix du téléphone de lui-même (audio pas prêt après 4 secondes, lecture impossible) :
     * compté pour l'admin, sans aucun texte.
     */
    public function voiceFallback(NaturalVoice $voice): JsonResponse
    {
        if ($voice->enabled()) {
            NaturalVoice::recordBrowserFallback();
        }

        return $this->json(['ok' => true]);
    }

    /**
     * Identifiant aléatoire du visiteur (cookie « ama_visitor », renouvelé pour un an).
     */
    private function visitor(): string
    {
        $visitor = request()->cookie(self::VISITOR_COOKIE);
        $visitor = is_string($visitor) && preg_match('/^[A-Za-z0-9]{40}$/', $visitor) === 1 ? $visitor : Str::random(40);
        Cookie::queue(self::VISITOR_COOKIE, $visitor, 60 * 24 * 365);

        return $visitor;
    }

    /**
     * @param  (callable(string): void)|null  $onText
     * @return array<string, mixed>
     */
    private function answer(Request $request, AmaChat $chat, ?callable $onText, ?string $visitor = null): array
    {
        $visitor ??= $this->visitor();

        if (! VoiceAssistant::enabled() || ! VoiceAssistant::usesGemini()) {
            return ['reply' => 'Ama n\'est pas disponible pour le moment. Notre équipe vous répond sur WhatsApp.', 'fallback' => true, 'conversation' => null, 'whatsapp' => WhatsApp::url()];
        }

        $message = $request->input('message');
        $message = is_string($message) ? trim($message) : '';
        $max = VoiceAssistant::limit('max_length');

        // Appel : phrase vide ou faite d'hésitations (mal entendue) : Ama demande de répéter, sans appeler Gemini et sans
        // compter de message.
        if ($onText !== null && AmaChat::isMisheard($message)) {
            return ['reply' => '', 'fallback' => false, 'conversation' => null, 'whatsapp' => null, 'misheard' => true, 'notice' => AmaPrompt::MISHEARD_REPLY];
        }

        if ($message === '') {
            return ['reply' => '', 'fallback' => false, 'conversation' => null, 'whatsapp' => null, 'notice' => 'Écrivez votre question, puis touchez « Envoyer ».'];
        }

        if (mb_strlen($message) > $max) {
            return ['reply' => '', 'fallback' => false, 'conversation' => null, 'whatsapp' => null, 'notice' => $onText !== null
                ? 'Votre question est un peu longue. Pouvez-vous la dire en plus court ?'
                : 'Votre message est trop long : '.$max.' caractères au maximum. Raccourcissez-le un peu, puis renvoyez-le.'];
        }

        $conversation = $request->input('conversation');
        $context = $request->input('context');

        return $chat->handle(
            $message,
            is_string($conversation) ? $conversation : null,
            is_array($context) ? $context : [],
            $request->boolean('voice') || $onText !== null,
            hash_hmac('sha256', $visitor, (string) config('app.key')),
            hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            $onText,
            $onText !== null && $request->boolean('interrupted'),
        );
    }

    /**
     * Mode appel : durées mesurées par le navigateur pour une réponse (attente du silence, premier morceau reçu, début de
     * la voix), en millisecondes, ajoutées à la mesure du serveur (incident « Mesure des durées de réponse »). Seulement
     * pour une conversation de ce visiteur, dans les 10 minutes ; aucun texte n'est reçu ni enregistré.
     */
    public function timing(Request $request): JsonResponse
    {
        $visitorHash = hash_hmac('sha256', $this->visitor(), (string) config('app.key'));
        $incident = AssistantIncident::query()
            ->whereKey((int) $request->input('timing'))
            ->where('type', AssistantIncidentType::ResponseTiming)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->whereHas('conversation', fn ($query) => $query
                ->where('public_id', (string) $request->input('conversation'))
                ->where('visitor_hash', $visitorHash))
            ->first();

        if ($incident === null) {
            return $this->json(['ok' => false]);
        }

        $client = [];
        foreach (['pause' => 'client_pause', 'first' => 'client_first', 'voice' => 'client_voice'] as $field => $key) {
            $value = $request->input($field);
            if (is_numeric($value) && $value >= 0 && $value <= 120000) {
                $client[$key] = (int) $value;
            }
        }

        $incident->forceFill(['timings' => [...(array) $incident->timings, ...$client]])->save();

        return $this->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }
}
