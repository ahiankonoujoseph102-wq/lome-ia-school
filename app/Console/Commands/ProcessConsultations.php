<?php

namespace App\Console\Commands;

use App\Enums\ConsultationStatus;
use App\Mail\ConsultationMail;
use App\Models\Consultation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Consultations gratuites, lancé toutes les 5 minutes par le cron :
 * - rappel la veille (si la réservation a été faite assez tôt) et rappel 1 h avant, par e-mail ;
 * - suppression des rendez-vous et des informations du formulaire après la durée de conservation.
 */
class ProcessConsultations extends Command
{
    protected $signature = 'app:process-consultations';

    protected $description = 'Rappels des consultations gratuites et suppression des anciens rendez-vous';

    public function handle(): int
    {
        $day = (int) config('consultations.reminders.day', 1440);
        $hour = (int) config('consultations.reminders.hour', 60);
        $sent = 0;

        $consultations = Consultation::query()
            ->where('status', ConsultationStatus::Booked)
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addMinutes($day))
            ->where(fn ($query) => $query->whereNull('day_reminder_sent_at')->orWhereNull('hour_reminder_sent_at'))
            ->get();

        foreach ($consultations as $consultation) {
            $minutesLeft = now()->diffInMinutes($consultation->starts_at);

            if ($minutesLeft <= $hour) {
                if ($consultation->hour_reminder_sent_at === null) {
                    Mail::to($consultation->email)->send(new ConsultationMail($consultation, 'reminder_hour'));
                    $sent++;
                }

                // Le rappel de la veille n'a plus de sens à 1 h du rendez-vous.
                $consultation->forceFill(['hour_reminder_sent_at' => $consultation->hour_reminder_sent_at ?? now(), 'day_reminder_sent_at' => $consultation->day_reminder_sent_at ?? now()])->save();

                continue;
            }

            if ($consultation->day_reminder_sent_at === null) {
                // Réservé moins de 12 h avant : l'e-mail de confirmation suffit.
                if ($consultation->updated_at === null || $consultation->updated_at->lte($consultation->starts_at->copy()->subHours(12))) {
                    Mail::to($consultation->email)->send(new ConsultationMail($consultation, 'reminder_day'));
                    $sent++;
                }

                $consultation->forceFill(['day_reminder_sent_at' => now()])->save();
            }
        }

        $deleted = Consultation::query()
            ->where('ends_at', '<', now()->subMonths(max(1, (int) config('consultations.retention_months', 12))))
            ->delete();

        $this->info($sent.' rappel(s) envoyé(s), '.$deleted.' ancien(s) rendez-vous supprimé(s).');

        return self::SUCCESS;
    }
}
