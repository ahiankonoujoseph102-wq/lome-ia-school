<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssistantIncidentType;
use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantIncident;
use App\Models\AssistantUsage;
use App\Support\VoiceAssistant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * « Conversations d'Ama » (admin uniquement) : demandes envoyées à Google aujourd'hui, incidents (limites
 * atteintes, erreurs ou silence de Gemini) et conversations, sans aucune donnée personnelle (textes nettoyés
 * avant l'enregistrement, visiteur désigné par une empreinte). Tout est effacé après 90 jours.
 */
class AssistantConversationController extends Controller
{
    public function index(Request $request): View
    {
        $unansweredOnly = $request->query('filtre') === 'sans-reponse';
        $unansweredQuery = fn ($query) => $query->where('unanswered', true);

        $problems = AssistantIncidentType::problems();
        $incidentsToday = AssistantIncident::query()->whereIn('type', $problems)->where('created_at', '>=', now()->startOfDay());
        $timings = AssistantIncident::query()
            ->where('type', AssistantIncidentType::ResponseTiming)
            ->where('created_at', '>=', now()->startOfDay())
            ->latest('id')->limit(500)->get(['id', 'assistant_conversation_id', 'timings', 'created_at']);

        return view('site.admin.assistant.index', [
            'requestsToday' => AssistantUsage::requestsToday(),
            'failuresToday' => (int) AssistantUsage::query()->where('day', AssistantUsage::today())->value('failures'),
            'dailyLimit' => VoiceAssistant::limit('daily_limit'),
            'nextReset' => AssistantUsage::nextReset(),
            'conversationsToday' => AssistantConversation::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'incidentsTodayCount' => (clone $incidentsToday)->count(),
            'incidentsByType' => (clone $incidentsToday)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')
                ->mapWithKeys(fn (int|string $total, string $type): array => [AssistantIncidentType::from($type)->label() => (int) $total])
                ->all(),
            'incidents' => AssistantIncident::query()->whereIn('type', $problems)->with('conversation:id,public_id')->latest('id')->limit(30)->get(),
            'timingMedians' => self::medians($timings->pluck('timings')->all()),
            'timingsCount' => $timings->count(),
            'recentTimings' => $timings->take(15),
            'unansweredOnly' => $unansweredOnly,
            'unansweredCount' => AssistantConversation::query()->whereHas('messages', $unansweredQuery)->count(),
            'conversations' => AssistantConversation::query()
                ->withExists(['messages as has_unanswered' => $unansweredQuery])
                ->when($unansweredOnly, fn ($query) => $query->whereHas('messages', $unansweredQuery)->with(['messages' => fn ($messages) => $messages->orderBy('id')]))
                ->latest('last_message_at')->latest('id')
                ->paginate(30)->withQueryString(),
            'retentionDays' => AssistantConversation::RETENTION_DAYS,
        ]);
    }

    public function show(AssistantConversation $conversation): View
    {
        return view('site.admin.assistant.show', [
            'conversation' => $conversation->load('messages'),
            'incidents' => AssistantIncident::query()->where('assistant_conversation_id', $conversation->id)->whereIn('type', AssistantIncidentType::problems())->latest('id')->get(),
            'timings' => AssistantIncident::query()->where('assistant_conversation_id', $conversation->id)->where('type', AssistantIncidentType::ResponseTiming)->latest('id')->get(),
        ]);
    }

    /**
     * Durées mesurées (millisecondes), libellé pour l'admin.
     */
    public const array TIMING_LABELS = [
        'client_pause' => 'Attente du silence après la dernière parole (appel)',
        'prepare' => 'Préparation sur le serveur (contrôles et textes envoyés)',
        'first' => 'Premier morceau de réponse de Gemini (appel)',
        'total' => 'Réponse complète de Gemini',
        'client_first' => 'Premier morceau reçu par le téléphone (appel)',
        'client_voice' => 'Début de la voix d\'Ama après l\'envoi (appel)',
    ];

    /**
     * Médiane de chaque durée (null si aucune mesure). Une ligne illisible (pas un tableau) est ignorée.
     *
     * @param  array<mixed>  $rows
     * @return array<string, int|null>
     */
    public static function medians(array $rows): array
    {
        $result = [];

        foreach ([...array_keys(self::TIMING_LABELS), 'tokens'] as $key) {
            $values = collect($rows)->map(fn ($row): mixed => is_array($row) ? ($row[$key] ?? null) : null)->filter(fn ($value): bool => is_int($value))->sort()->values();
            $result[$key] = $values->isEmpty() ? null : (int) $values->median();
        }

        return $result;
    }
}
