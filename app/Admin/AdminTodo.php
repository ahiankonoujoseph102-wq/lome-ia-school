<?php

namespace App\Admin;

use App\Enums\ConsultationStatus;
use App\Enums\ReportStatus;
use App\Enums\WithdrawalStatus;
use App\Models\AffiliateWithdrawal;
use App\Models\AssistantHandoff;
use App\Models\ChatMessageReport;
use App\Models\Consultation;
use App\Models\Purchase;

/**
 * « À traiter » : nombres affichés sur l'accueil admin et dans le menu (calculés une fois par requête).
 */
class AdminTodo
{
    public static function paymentsToVerify(): int
    {
        return once(fn (): int => Purchase::query()->awaitingVerification()->count());
    }

    public static function withdrawalsToPay(): int
    {
        return once(fn (): int => AffiliateWithdrawal::query()->where('status', WithdrawalStatus::Requested)->count());
    }

    public static function openReports(): int
    {
        return once(fn (): int => ChatMessageReport::query()->where('status', ReportStatus::Open->value)->distinct()->count('chat_message_id'));
    }

    public static function consultationsToday(): int
    {
        return once(fn (): int => Consultation::query()
            ->where('status', ConsultationStatus::Booked)
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->count());
    }

    public static function refundsDue(): int
    {
        return once(fn (): int => Purchase::query()->refundDue()->count());
    }

    /**
     * Questions transmises à Coach Joseph par Ama, pas encore traitées.
     */
    public static function handoffsToAnswer(): int
    {
        return once(fn (): int => AssistantHandoff::query()->where('status', AssistantHandoff::PENDING)->count());
    }
}
