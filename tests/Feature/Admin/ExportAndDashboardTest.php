<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminAction;
use App\Enums\PurchaseStatus;
use App\Models\Consultation;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\LessonView;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Testing\TestResponse;

class ExportAndDashboardTest extends AdminTestCase
{
    private function purchase(User $student, Formation $formation, int $amount, PurchaseStatus $status, ?string $createdAt = null): Purchase
    {
        $purchase = Purchase::query()->create(['user_id' => $student->id, 'formation_id' => $formation->id, 'amount' => $amount, 'gateway' => 'manual_mobile_money', 'status' => $status]);

        if ($createdAt !== null) {
            $purchase->forceFill(['created_at' => $createdAt])->save();
        }

        return $purchase;
    }

    /**
     * @return list<list<string>>
     */
    private function csv(TestResponse $response): array
    {
        $content = (string) $response->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}", $content, 'BOM UTF-8 pour Excel');

        return array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), array_values(array_filter(explode("\n", substr($content, 3)))));
    }

    public function test_sales_export_is_excel_compatible_and_filtered_by_dates(): void
    {
        $student = User::factory()->create(['name' => 'Élève Accentué']);
        $formation = Formation::factory()->create(['title' => 'Création d\'affiches']);
        $this->purchase($student, $formation, 25000, PurchaseStatus::Validated, '2026-09-10 10:00:00');
        $this->purchase($student, Formation::factory()->create(['title' => 'Ancienne vente']), 10000, PurchaseStatus::Validated, '2026-08-01 10:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.exports.download', ['type' => 'ventes', 'from' => '2026-09-01', 'to' => '2026-09-30']));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $rows = $this->csv($response);

        $this->assertSame('Référence', $rows[0][0]);
        $this->assertCount(2, $rows);
        $this->assertSame('Élève Accentué', $rows[1][2]);
        $this->assertSame('Création d\'affiches', $rows[1][5]);
        $this->assertSame('25000', $rows[1][10]);
        $this->assertSame('Validé', $rows[1][11]);
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::Export->value]);
    }

    public function test_other_exports(): void
    {
        User::factory()->create(['name' => '=Formule dangereuse']);
        Consultation::query()->forceCreate([
            'name' => 'Visiteur Exemple', 'phone' => '+22890000000', 'email' => 'visiteur@exemple.test', 'topic' => 'Besoin',
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30), 'status' => 'booked', 'manage_token' => 'x', 'manage_token_hash' => hash('sha256', 'x'),
        ]);

        $students = $this->csv($this->actingAs($this->admin)->get(route('admin.exports.download', 'eleves'))->assertOk());
        $this->assertSame(['Nom', 'WhatsApp', 'E-mail', 'Rôle', 'Inscrit le', 'Formations', 'Accepte les annonces', 'Bloqué'], $students[0]);
        $this->assertContains("'=Formule dangereuse", array_column($students, 0), 'Les formules Excel sont neutralisées.');

        $consultations = $this->csv($this->actingAs($this->admin)->get(route('admin.exports.download', 'consultations'))->assertOk());
        $this->assertSame('Visiteur Exemple', $consultations[1][1]);

        $this->actingAs($this->admin)->get(route('admin.exports.download', 'commissions'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.exports.download', 'retraits'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.exports.download', 'inconnu'))->assertNotFound();
    }

    public function test_dashboard_separates_validated_and_provisional_amounts(): void
    {
        $formation = Formation::factory()->create(['title' => 'Formation la plus vendue']);
        $student = User::factory()->create();
        $this->purchase($student, $formation, 25000, PurchaseStatus::Validated);
        $this->purchase(User::factory()->create(), $formation, 25000, PurchaseStatus::Validated);
        $this->purchase(User::factory()->create(), $formation, 7000, PurchaseStatus::Provisional);

        $this->actingAs($this->admin)->get(route('admin.index'))
            ->assertOk()
            ->assertSee("50\u{00A0}000\u{00A0}FCFA")
            ->assertSee('2 ventes validées')
            ->assertSee("À part : 7\u{00A0}000\u{00A0}FCFA provisoire ou en attente de vérification")
            ->assertSee('Formation la plus vendue')
            ->assertSee('aria-label="Paiements à vérifier : 1"', false)
            ->assertSee('Ventes validées sur 30 jours');
    }

    public function test_active_students_are_counted_over_the_last_30_days(): void
    {
        $lesson = Lesson::factory()->create();
        $view = fn (User $user, int $daysAgo) => LessonView::query()->create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'first_viewed_at' => now()->subDays($daysAgo), 'last_viewed_at' => now()->subDays($daysAgo)]);
        $view(User::factory()->create(), 2);
        $view(User::factory()->create(), 29);
        $view(User::factory()->create(), 31);

        $content = (string) $this->actingAs($this->admin)->get(route('admin.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<span class="text-2xl font-bold text-white">2<\/span>\s*<span class="text-sm text-slate-300">sur les 30 derniers jours/', $content);
        $this->assertStringNotContainsString('sur 7 jours', $content);
    }
}
