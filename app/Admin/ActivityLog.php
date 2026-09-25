<?php

namespace App\Admin;

use App\Enums\AdminAction;
use App\Mail\SecurityAlertMail;
use App\Models\AdminActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Journal d'activité de l'admin et des modérateurs. Les changements sensibles (moyens de paiement,
 * réglages d'affiliation, rôles) envoient en plus un e-mail d'alerte à l'adresse de l'admin.
 */
class ActivityLog
{
    /**
     * @param  array<string, mixed>  $properties  détails utiles (anciennes et nouvelles valeurs…)
     */
    public static function record(AdminAction $action, string $description, ?Model $subject = null, array $properties = [], ?User $by = null): AdminActivity
    {
        $by ??= auth()->user() instanceof User ? auth()->user() : null;

        $activity = AdminActivity::query()->create([
            'user_id' => $by?->id,
            'action' => $action,
            'description' => mb_substr($description, 0, 500),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
        ]);

        if ($action->isSensitive()) {
            self::alert($activity, $by);
        }

        return $activity;
    }

    /**
     * E-mail d'alerte à l'adresse de l'admin (et à l'ancienne adresse si elle vient d'être changée).
     */
    private static function alert(AdminActivity $activity, ?User $by): void
    {
        $recipients = array_filter(array_unique([
            (string) config('site.payments.admin_email'),
            (string) ($activity->properties['previous_admin_email'] ?? ''),
        ]));

        foreach ($recipients as $email) {
            Mail::to($email)->send(new SecurityAlertMail($activity, $by->name ?? 'Système', (string) request()->ip()));
        }
    }
}
