<?php

namespace Tests\Feature\Packs;

use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Seuil de l'accès provisoire : réglage modifiable dans Réglages du site > Seuils et délais (50 000 FCFA par défaut),
 * appliqué aux offres d'accompagnement comme aux formations.
 */
class PackThresholdTest extends TestCase
{
    use RefreshDatabase;

    private int $captures = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');
        Mail::fake();
    }

    private function packAt(int $price): Formation
    {
        $pack = Formation::factory()->pack()->create(['price' => $price]);
        $pack->includedFormations()->attach(Formation::factory()->create(['price' => 5500])->id, ['position' => 1]);

        return $pack;
    }

    private function pay(Formation $pack): Purchase
    {
        $student = User::factory()->create();
        $this->actingAs($student)->post(route('checkout.pay', $pack), [
            'payment_method' => 'tg-mixx',
            'proof' => UploadedFile::fake()->image('capture.png', 600 + ++$this->captures, 1200),
        ])->assertSessionHasNoErrors();

        return Purchase::query()->where('user_id', $student->id)->sole();
    }

    public function test_the_default_threshold_is_50000_fcfa(): void
    {
        $this->assertSame(50000, (require config_path('site.php'))['payments']['verification_threshold']);
        $this->assertSame(50000, config('site.payments.verification_threshold'));
    }

    public function test_packs_follow_the_threshold(): void
    {
        $level1 = $this->pay($this->packAt(10000));
        $level3 = $this->pay($this->packAt(75000));

        $this->assertSame(PurchaseStatus::Provisional, $level1->status);
        $this->assertTrue($level1->user->isEnrolledIn($level1->formation->includedFormations->first()));
        $this->assertSame(PurchaseStatus::Pending, $level3->status);
        $this->assertFalse($level3->user->isEnrolledIn($level3->formation->includedFormations->first()));
    }

    public function test_the_threshold_is_changed_from_the_site_settings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'regles'))
            ->assertOk()
            ->assertSee('Seuil d\'accès provisoire');

        $this->actingAs($admin)->put(route('admin.settings.update', 'regles'), [
            'verification_threshold' => 80000,
            'verification_hours' => 'du lundi au samedi, de 8 h à 20 h',
            'fees_notice' => 'Les frais de transfert sont à la charge de l\'expéditeur.',
            'default_location' => 'Adidogomé, Lycée moderne 2, Lomé',
            'default_duration' => 120,
            'cancel_deadline_hours' => 24,
            'join_window_minutes' => 15,
            'payment_hold_hours' => 24,
        ])->assertSessionHasNoErrors();

        $this->assertSame(80000, config('site.payments.verification_threshold'));
        $this->assertSame(PurchaseStatus::Provisional, $this->pay($this->packAt(75000))->status);
    }
}
