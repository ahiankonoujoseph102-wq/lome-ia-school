<?php

namespace App\Http\Controllers\Site;

use App\Assistant\AmaChat;
use App\Http\Controllers\Controller;
use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Fenêtre de discussion d'Ama (Gemini) : un message du visiteur, une réponse d'Ama.
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
        $visitor = $request->cookie(self::VISITOR_COOKIE);
        $visitor = is_string($visitor) && preg_match('/^[A-Za-z0-9]{40}$/', $visitor) === 1 ? $visitor : Str::random(40);
        Cookie::queue(self::VISITOR_COOKIE, $visitor, 60 * 24 * 365);

        if (! VoiceAssistant::enabled() || ! VoiceAssistant::usesGemini()) {
            return $this->json(['reply' => 'Ama n\'est pas disponible pour le moment. Notre équipe vous répond sur WhatsApp.', 'fallback' => true, 'conversation' => null, 'whatsapp' => WhatsApp::url()]);
        }

        $message = $request->input('message');
        $message = is_string($message) ? trim($message) : '';
        $max = VoiceAssistant::limit('max_length');

        if ($message === '') {
            return $this->json(['reply' => '', 'fallback' => false, 'conversation' => null, 'whatsapp' => null, 'notice' => 'Écrivez votre question, puis touchez « Envoyer ».']);
        }

        if (mb_strlen($message) > $max) {
            return $this->json(['reply' => '', 'fallback' => false, 'conversation' => null, 'whatsapp' => null, 'notice' => 'Votre message est trop long : '.$max.' caractères au maximum. Raccourcissez-le un peu, puis renvoyez-le.']);
        }

        $conversation = $request->input('conversation');
        $context = $request->input('context');

        return $this->json($chat->handle(
            $message,
            is_string($conversation) ? $conversation : null,
            is_array($context) ? $context : [],
            $request->boolean('voice'),
            hash_hmac('sha256', $visitor, (string) config('app.key')),
            hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }
}
