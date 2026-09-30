<?php

namespace App\Http\Controllers\Site;

use App\Assistant\AmaChat;
use App\Assistant\FounderHandoff;
use App\Http\Controllers\Controller;
use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
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
     * Mode appel : même message, mêmes contrôles et mêmes limites, mais la réponse est envoyée en continu, une ligne
     * JSON par événement : {"type":"text","text":"…"} pour chaque morceau (lu à voix haute dès la première phrase),
     * puis {"type":"done",…} avec la réponse complète (mêmes champs que message()).
     * Hébergement qui retient la réponse jusqu'au bout : tout arrive d'un coup, l'appel fonctionne quand même.
     */
    public function call(Request $request, AmaChat $chat): StreamedResponse
    {
        $visitor = $this->visitor();

        return response()->stream(function () use ($request, $chat, $visitor): void {
            // Le visiteur coupe la parole à Ama (connexion fermée) : la réponse est quand même enregistrée,
            // pour que la conversation reste complète.
            ignore_user_abort(true);

            $emit = function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $result = $this->answer($request, $chat, fn (string $text) => $emit(['type' => 'text', 'text' => $text]), $visitor);
            $emit(['type' => 'done', ...$result]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
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

        if ($message === '') {
            return ['reply' => '', 'fallback' => false, 'conversation' => null, 'whatsapp' => null, 'notice' => $onText !== null
                ? 'Je ne vous ai pas entendu. Pouvez-vous répéter ?'
                : 'Écrivez votre question, puis touchez « Envoyer ».'];
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
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }
}
