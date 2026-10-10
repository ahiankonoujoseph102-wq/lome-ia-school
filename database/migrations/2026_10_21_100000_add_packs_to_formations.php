<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offres d'accompagnement (« packs ») : une offre est une formation d'un type particulier qui contient
 * une liste de formations incluses ; l'acheter ouvre l'accès à toutes ces formations.
 *
 * - formations.kind : « course » (formation habituelle, valeur de toutes les formations existantes),
 *   « pack » (offre) ou « group » (groupe de discussion seul, comme le Groupe VIP).
 * - formation_pack_items : formations incluses dans chaque offre, dans l'ordre.
 * - enrollments.pack_id : offre qui a ouvert cet accès (vide pour un achat, une inscription gratuite
 *   ou un accès donné par l'équipe : ces accès ne sont jamais retirés avec l'offre).
 *
 * Uniquement des ajouts (aucune colonne ni donnée existante modifiée). Reprise sans erreur, quel que soit
 * l'état de la base : chaque colonne, table, index et clé étrangère n'est ajouté que s'il manque
 * (MySQL n'annule pas une modification de structure). Noms d'index courts (64 caractères au plus).
 */
return new class extends Migration
{
    public const string KIND_INDEX = 'formations_kind_index';

    public const string ITEM_UNIQUE = 'pack_items_pack_formation_unique';

    public const string ITEM_FORMATION_INDEX = 'pack_items_formation_index';

    public const string ENROLLMENT_PACK_INDEX = 'enrollments_pack_id_index';

    public function up(): void
    {
        if (! Schema::hasColumn('formations', 'kind')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->string('kind', 20)->default('course')->after('slug');
            });
        }

        $this->addIndex('formations', ['kind'], self::KIND_INDEX);

        if (! Schema::hasTable('formation_pack_items')) {
            Schema::create('formation_pack_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('pack_id');
                $table->unsignedBigInteger('formation_id');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        $this->addIndex('formation_pack_items', ['pack_id', 'formation_id'], self::ITEM_UNIQUE, unique: true);
        $this->addIndex('formation_pack_items', ['formation_id'], self::ITEM_FORMATION_INDEX);
        $this->addForeignKey('formation_pack_items', 'pack_id', 'formations', onDelete: 'cascade');
        $this->addForeignKey('formation_pack_items', 'formation_id', 'formations', onDelete: 'cascade');

        if (! Schema::hasColumn('enrollments', 'pack_id')) {
            Schema::table('enrollments', function (Blueprint $table): void {
                $table->unsignedBigInteger('pack_id')->nullable()->after('purchase_id');
            });
        }

        $this->addIndex('enrollments', ['pack_id'], self::ENROLLMENT_PACK_INDEX);
        $this->addForeignKey('enrollments', 'pack_id', 'formations', onDelete: 'null');
    }

    public function down(): void
    {
        if (Schema::hasColumn('enrollments', 'pack_id')) {
            Schema::table('enrollments', function (Blueprint $table): void {
                if (collect(Schema::getForeignKeys('enrollments'))->contains(fn (array $key): bool => $key['columns'] === ['pack_id'])) {
                    $table->dropForeign(['pack_id']);
                }
                $table->dropIndex(self::ENROLLMENT_PACK_INDEX);
                $table->dropColumn('pack_id');
            });
        }

        Schema::dropIfExists('formation_pack_items');

        if (Schema::hasColumn('formations', 'kind')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->dropIndex(self::KIND_INDEX);
                $table->dropColumn('kind');
            });
        }
    }

    /**
     * Index ajouté seulement si aucun index ne couvre déjà exactement ces colonnes (quel que soit son nom).
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
    private function addForeignKey(string $table, string $column, string $on, string $onDelete): void
    {
        $exists = collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => $key['columns'] === [$column]);

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $on, $onDelete): void {
            $foreign = $blueprint->foreign($column)->references('id')->on($on);
            $onDelete === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
        });
    }
};
