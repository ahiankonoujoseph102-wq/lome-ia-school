<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consultation gratuite : réglages (dont le lien Google Meet fixe), horaires, plages bloquées, rendez-vous.
     */
    public function up(): void
    {
        Schema::create('consultation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('meet_url')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('buffer_minutes')->default(15);
            $table->unsignedSmallInteger('min_notice_hours')->default(3);
            $table->unsignedSmallInteger('max_days_ahead')->default(30);
            $table->unsignedSmallInteger('max_per_day')->nullable();
            $table->unsignedSmallInteger('manage_deadline_hours')->default(3);
            $table->timestamps();
        });

        Schema::create('consultation_hours', function (Blueprint $table) {
            $table->id();
            // 1 = lundi … 7 = dimanche.
            $table->unsignedTinyInteger('weekday');
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->timestamps();

            $table->index('weekday');
        });

        Schema::create('consultation_blocks', function (Blueprint $table) {
            $table->id();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email');
            $table->text('topic');
            $table->foreignId('formation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // Créneau réservé (vide si annulé) : un index unique empêche deux réservations du même créneau.
            $table->string('slot_key', 20)->nullable()->unique();
            $table->string('status', 20);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 20)->nullable();
            $table->text('cancellation_message')->nullable();
            // Lien de gestion : jeton chiffré (pour les e-mails) et son empreinte (pour la recherche).
            $table->text('manage_token');
            $table->string('manage_token_hash', 64)->unique();
            $table->string('attendance', 20)->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('recommended_formation_id')->nullable()->constrained('formations')->nullOnDelete();
            $table->timestamp('recommendation_sent_at')->nullable();
            $table->timestamp('day_reminder_sent_at')->nullable();
            $table->timestamp('hour_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('phone');
            $table->index('email');
        });

        // Premier réglage : lundi à samedi, 8 h – 20 h (modifiable dans l'admin).
        $now = now();
        DB::table('consultation_settings')->insert([
            'duration_minutes' => (int) config('consultations.defaults.duration_minutes', 30),
            'buffer_minutes' => (int) config('consultations.defaults.buffer_minutes', 15),
            'min_notice_hours' => (int) config('consultations.defaults.min_notice_hours', 3),
            'max_days_ahead' => (int) config('consultations.defaults.max_days_ahead', 30),
            'max_per_day' => config('consultations.defaults.max_per_day'),
            'manage_deadline_hours' => (int) config('consultations.defaults.manage_deadline_hours', 3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ((array) config('consultations.defaults.hours', []) as $weekday => [$start, $end]) {
            DB::table('consultation_hours')->insert(['weekday' => $weekday, 'starts_at' => $start, 'ends_at' => $end, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('consultation_blocks');
        Schema::dropIfExists('consultation_hours');
        Schema::dropIfExists('consultation_settings');
    }
};
