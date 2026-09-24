<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les bonus sont désormais définis dans config/site.php (clé « bonuses »), vides par défaut.
     */
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn('bonuses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->json('bonuses')->nullable()->after('audience');
        });
    }
};
