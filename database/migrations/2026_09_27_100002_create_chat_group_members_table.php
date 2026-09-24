<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membres des groupes : élèves ayant accès à la formation, admin et modérateurs.
     * Un membre retiré garde sa ligne (removed_at + motif) : une exclusion n'est jamais annulée par un nouvel accès.
     */
    public function up(): void
    {
        Schema::create('chat_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('removed_at')->nullable();
            $table->string('removal_reason', 30)->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->foreignId('muted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->unsignedBigInteger('last_digested_message_id')->nullable();
            $table->boolean('notifications_muted')->default(false);
            $table->timestamp('rules_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_group_id', 'user_id']);
            $table->index(['user_id', 'removed_at']);
        });

        // Élèves ayant déjà accès à une formation (comptes non bloqués).
        $now = now();
        DB::table('enrollments')
            ->join('chat_groups', 'chat_groups.formation_id', '=', 'enrollments.formation_id')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->whereNull('users.blocked_at')
            ->orderBy('enrollments.id')
            ->get(['chat_groups.id as group_id', 'enrollments.user_id'])
            ->each(function (object $row) use ($now): void {
                DB::table('chat_group_members')->insertOrIgnore([
                    'chat_group_id' => $row->group_id,
                    'user_id' => $row->user_id,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_group_members');
    }
};
