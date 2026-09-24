<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un groupe de discussion par formation, réservé aux acheteurs et à l'équipe.
     */
    public function up(): void
    {
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('announcements_only')->default(false);
            $table->text('rules')->nullable();
            $table->timestamp('rules_updated_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        // Groupes des formations déjà en place.
        $now = now();
        DB::table('formations')->orderBy('id')->pluck('id')->each(function (int $formationId) use ($now): void {
            DB::table('chat_groups')->insert([
                'formation_id' => $formationId,
                'rules' => config('chat.default_rules'),
                'rules_updated_at' => $now,
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
        Schema::dropIfExists('chat_groups');
    }
};
