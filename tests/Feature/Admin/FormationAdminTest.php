<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminAction;
use App\Enums\PurchaseStatus;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FormationAdminTest extends AdminTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Exemple : Montage vidéo avec l\'IA',
            'subtitle' => 'Apprenez à monter vos vidéos',
            'description' => 'Une description de la formation.',
            'price' => 15000,
            'category' => 'creative',
            'outcomes' => ['Monter une vidéo courte', ''],
            'audience' => ['Les créateurs de contenu'],
            'bonuses' => [],
            'is_active' => '1',
            'is_featured' => '1',
        ], $overrides);
    }

    public function test_admin_creates_a_formation_with_its_discussion_group(): void
    {
        $this->actingAs($this->admin)->get(route('admin.formations.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('admin.formations.store'), $this->form(['price' => '15 000']))->assertSessionHasErrors('price');

        $this->actingAs($this->admin)->post(route('admin.formations.store'), $this->form())->assertSessionHasNoErrors();

        $formation = Formation::query()->where('slug', 'exemple-montage-video-avec-lia')->firstOrFail();
        $this->assertSame(15000, $formation->price);
        $this->assertSame(['Monter une vidéo courte'], $formation->outcomes);
        $this->assertSame([], $formation->bonuses);
        $this->assertTrue($formation->is_featured);
        $this->assertNotNull(ChatGroup::query()->where('formation_id', $formation->id)->first());
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::FormationCreated->value]);

        $this->get(route('formations.index'))->assertOk()->assertSee('Exemple : Montage vidéo avec l&#039;IA', false);
        $this->get(route('home'))->assertOk()->assertSee('Exemple : Montage vidéo avec l&#039;IA', false);
    }

    public function test_price_change_never_changes_existing_purchases(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);
        $purchase = Purchase::query()->create([
            'user_id' => User::factory()->create()->id,
            'formation_id' => $formation->id,
            'amount' => 25000,
            'gateway' => 'manual_mobile_money',
            'status' => PurchaseStatus::Validated,
        ]);

        $this->actingAs($this->admin)->put(route('admin.formations.update', $formation), $this->form(['title' => $formation->title, 'price' => 30000]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'les achats déjà faits gardent leur montant'));

        $this->assertSame(30000, $formation->refresh()->price);
        $this->assertSame(25000, $purchase->refresh()->amount);
        $this->get(route('formations.show', $formation))->assertOk()->assertSee("30\u{00A0}000\u{00A0}FCFA");
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::FormationUpdated->value]);
    }

    public function test_image_is_cropped_and_compressed_and_bonuses_are_shown(): void
    {
        Storage::fake('public');
        $formation = Formation::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.formations.update', $formation), $this->form([
            'title' => $formation->title,
            'image' => UploadedFile::fake()->image('photo.jpg', 3000, 3000),
            'bonuses' => ['Exemple de bonus réellement fourni'],
        ]))->assertSessionHasNoErrors();

        $formation->refresh();
        Storage::disk('public')->assertExists((string) $formation->image);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get((string) $formation->image)) ?: [0, 0];
        $this->assertSame([1600, 1000], [$width, $height]);

        $this->get(route('formations.show', $formation))->assertOk()->assertSee((string) $formation->image_url)->assertSee('Exemple de bonus réellement fourni');
    }

    public function test_hidden_formation_leaves_the_catalogue_and_order_can_change(): void
    {
        $first = Formation::factory()->create(['title' => 'Formation A', 'sort_order' => 1]);
        $second = Formation::factory()->create(['title' => 'Formation B', 'sort_order' => 2]);

        $this->actingAs($this->admin)->post(route('admin.formations.move', [$second, 'up']))->assertRedirect();
        $this->assertSame(['Formation B', 'Formation A'], Formation::query()->published()->pluck('title')->all());

        $this->actingAs($this->admin)->put(route('admin.formations.update', $first), $this->form(['title' => 'Formation A', 'is_active' => null]))->assertSessionHasNoErrors();
        $this->get(route('formations.index'))->assertOk()->assertDontSee('Formation A')->assertSee('Formation B');
        $this->get(route('formations.show', $first))->assertNotFound();

        $this->actingAs($this->admin)->get(route('admin.formations.index'))->assertOk()->assertSee('Formation A')->assertSee('Masquée');
    }
}
