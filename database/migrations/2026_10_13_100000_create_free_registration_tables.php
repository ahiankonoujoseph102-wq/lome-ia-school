<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscriptions gratuites : options d'inscription à places limitées (présentiel, en ligne…) et inscrits.
 * Uniquement pour les formations à 0 FCFA ; rien ne change pour les formations payantes.
 *
 * Reprise sans erreur, quel que soit l'état de la base : chaque colonne, table, index et clé étrangère
 * n'est ajouté que s'il n'existe pas encore. MySQL n'annule pas une modification de structure : la première
 * mise en ligne a échoué sur un nom d'index de 65 caractères (MySQL en accepte 64) après avoir déjà ajouté
 * les colonnes, les tables, l'index unique et les clés étrangères. Les index portent donc des noms courts.
 */
return new class extends Migration
{
    public const string OPTION_POSITION_INDEX = 'free_reg_options_formation_position_index';

    public const string REGISTRATION_UNIQUE = 'free_reg_formation_user_unique';

    public const string REGISTRATION_OPTION_INDEX = 'free_reg_option_cancelled_index';

    public function up(): void
    {
        // Date et lieu de l'événement d'une formation gratuite (affichés sur la page et dans l'e-mail).
        if (! Schema::hasColumn('formations', 'event_date')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->date('event_date')->nullable()->after('bonuses');
            });
        }

        if (! Schema::hasColumn('formations', 'event_location')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->string('event_location')->nullable()->after('event_date');
            });
        }

        if (! Schema::hasTable('free_registration_options')) {
            Schema::create('free_registration_options', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('formation_id');
                $table->string('label', 120);
                $table->string('schedule', 160)->nullable();
                $table->string('mode', 20);
                $table->unsignedInteger('capacity');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        $this->addIndex('free_registration_options', ['formation_id', 'position'], self::OPTION_POSITION_INDEX);
        $this->addForeignKey('free_registration_options', 'formation_id', 'formations', cascade: true);

        if (! Schema::hasTable('free_registrations')) {
            Schema::create('free_registrations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('formation_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('free_registration_option_id')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        // Une seule inscription (donc une seule option) par personne et par formation.
        $this->addIndex('free_registrations', ['formation_id', 'user_id'], self::REGISTRATION_UNIQUE, unique: true);
        $this->addIndex('free_registrations', ['free_registration_option_id', 'cancelled_at'], self::REGISTRATION_OPTION_INDEX);
        $this->addForeignKey('free_registrations', 'formation_id', 'formations', cascade: true);
        $this->addForeignKey('free_registrations', 'user_id', 'users', cascade: true);
        $this->addForeignKey('free_registrations', 'free_registration_option_id', 'free_registration_options', cascade: false);
    }

    public function down(): void
    {
        Schema::dropIfExists('free_registrations');
        Schema::dropIfExists('free_registration_options');

        foreach (['event_location', 'event_date'] as $column) {
            if (Schema::hasColumn('formations', $column)) {
                Schema::table('formations', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    /**
     * Index ajouté seulement si aucun index ne couvre déjà exactement ces colonnes (quel que soit son nom :
     * une tentative précédente a pu le créer sous son nom automatique).
     *
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, array $columns, string $name, bool $unique = false): void
    {
        $exists = collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => $index['columns'] === $columns && (! $unique || $index['unique']),
        );

        if ($exists) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name));
    }

    /**
     * Clé étrangère ajoutée seulement si la colonne n'en a pas déjà une.
     */
    private function addForeignKey(string $table, string $column, string $on, bool $cascade): void
    {
        $exists = collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => $key['columns'] === [$column]);

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $on, $cascade): void {
            $foreign = $blueprint->foreign($column)->references('id')->on($on);
            $cascade ? $foreign->cascadeOnDelete() : $foreign->restrictOnDelete();
        });
    }
};
