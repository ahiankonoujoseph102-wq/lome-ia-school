<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sessions ponctuelles d'une formation : en direct (Google Meet) ou en présentiel à Lomé.
     */
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('format', 20);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamp('ends_at');
            $table->string('meet_url')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('price_type', 20)->default('included');
            $table->unsignedInteger('supplement_amount')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('announcement_message_id')->nullable();
            $table->timestamp('day_reminder_sent_at')->nullable();
            $table->timestamp('hour_reminder_sent_at')->nullable();
            $table->timestamp('announcement_unpinned_at')->nullable();
            $table->timestamps();

            $table->index(['ends_at', 'cancelled_at']);
            $table->index(['formation_id', 'starts_at']);
        });

        Schema::create('live_session_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('waitlisted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 40)->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->unique(['live_session_id', 'user_id']);
            $table->index(['live_session_id', 'status']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('live_session_id')->nullable()->after('formation_id')->constrained()->nullOnDelete();
            // Supplément à rembourser : l'élève a annulé son inscription dans le délai autorisé.
            $table->timestamp('refund_due_at')->nullable()->after('refunded_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('session_email_enabled')->default(true)->after('chat_digest_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('session_email_enabled');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('refund_due_at');
            $table->dropConstrainedForeignId('live_session_id');
        });

        Schema::dropIfExists('live_session_registrations');
        Schema::dropIfExists('live_sessions');
    }
};
