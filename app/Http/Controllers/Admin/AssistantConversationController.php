<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssistantIncidentType;
use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantIncident;
use App\Models\AssistantUsage;
use App\Support\VoiceAssistant;
use Illuminate\Contracts\View\View;

/**
 * « Conversations d'Ama » (admin uniquement) : demandes envoyées à Google aujourd'hui, incidents (limites
 * atteintes, erreurs ou silence de Gemini) et conversations, sans aucune donnée personnelle (textes nettoyés
 * avant l'enregistrement, visiteur désigné par une empreinte). Tout est effacé après 90 jours.
 */
class AssistantConversationController extends Controller
{
    public function index(): View
    {
        $incidentsToday = AssistantIncident::query()->where('created_at', '>=', now()->startOfDay());

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
            'incidents' => AssistantIncident::query()->with('conversation:id,public_id')->latest('id')->limit(30)->get(),
            'conversations' => AssistantConversation::query()->latest('last_message_at')->latest('id')->paginate(30),
            'retentionDays' => AssistantConversation::RETENTION_DAYS,
        ]);
    }

    public function show(AssistantConversation $conversation): View
    {
        return view('site.admin.assistant.show', [
            'conversation' => $conversation->load('messages'),
            'incidents' => AssistantIncident::query()->where('assistant_conversation_id', $conversation->id)->latest('id')->get(),
        ]);
    }
}
