<?php

namespace Tests\Feature\Packs;

use App\Enums\AdminAction;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Events\FormationAccessGranted;
use App\Models\Formation;
use App\Models\User;
use Tests\Feature\Admin\AdminTestCase;

/**
 * Admin : voir et modifier la liste des formations incluses dans une offre d'accompagnement.
 */
class PackAdminTest extends AdminTestCase
{
    private Formation $pack;

    private Formation $chatgpt;

    private Formation $saas;

    private Formation $vip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chatgpt = Formation::factory()->create(['title' => 'Formation ChatGPT & Claude', 'price' => 5500]);
        $this->saas = Formation::factory()->create(['title' => 'Création de SaaS avec l\'IA', 'price' => 20500]);
        $this->vip = Formation::query()->where('slug', 'groupe-vip')->sole();
        $this->pack = Formation::query()->where('slug', 'accompagnement-ia-niveau-1')->sole();
        $this->pack->includedFormations()->sync([$this->chatgpt->id => ['position' => 1], $this->vip->id => ['position' => 2]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'title' => $this->pack->title,
            'subtitle' => '',
            'description' => $this->pack->description,
            'price' => $this->pack->price,
            'category' => $this->pack->category->value,
            'outcomes' => [],
            'audience' => [],
            'bonuses' => [],
            'included_sent' => '1',
            'included' => [$this->chatgpt->id, $this->vip->id],
        ], $overrides);
    }

    public function test_the_list_shows_packs_apart_and_never_the_vip_group(): void
    {
        $this->actingAs($this->admin)->get(route('admin.formations.index'))
            ->assertOk()
            ->assertSee('Offres d\'accompagnement', false)
            ->assertSee('Accompagnement IA, niveau 1 : Créateur de contenu')
            ->assertSee('1 formation incluse + Groupe VIP')
            ->assertDontSee(route('admin.formations.edit', $this->vip));

        $this->actingAs($this->admin)->get(route('admin.formations.edit', $this->vip))->assertNotFound();
    }

    public function test_the_admin_sees_the_included_formations(): void
    {
        $this->actingAs($this->admin)->get(route('admin.formations.edit', $this->pack))
            ->assertOk()
            ->assertSee('Formations incluses')
            ->assertSee('Formation ChatGPT &amp; Claude', false)
            ->assertSee('Création de SaaS avec l&#039;IA', false)
            ->assertSee('Groupe VIP')
            ->assertSee('data-test="pack-included-'.$this->chatgpt->slug.'" checked', false)
            ->assertDontSee('data-test="pack-included-'.$this->saas->slug.'" checked', false)
            ->assertSee("5\u{00A0}500\u{00A0}FCFA");
    }

    public function test_the_admin_changes_the_included_formations(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.formations.update', $this->pack), $this->form(['included' => [$this->saas->id, $this->chatgpt->id, $this->vip->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->saas->id, $this->chatgpt->id, $this->vip->id], $this->pack->includedFormations()->pluck('formations.id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::FormationUpdated->value, 'description' => 'Offre d\'accompagnement modifiée : '.$this->pack->title.'. Formations incluses : Création de SaaS avec l\'IA ; Formation ChatGPT & Claude ; Groupe VIP.']);

        $this->actingAs($this->admin)
            ->put(route('admin.formations.update', $this->pack), $this->form(['included' => [$this->saas->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->saas->id], $this->pack->includedFormations()->pluck('formations.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_a_pack_cannot_include_another_pack_nor_cost_zero(): void
    {
        $level2 = Formation::query()->where('slug', 'accompagnement-ia-niveau-2')->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.formations.update', $this->pack), $this->form(['included' => [$level2->id]]))
            ->assertSessionHasErrors('included.0');
        $this->actingAs($this->admin)
            ->put(route('admin.formations.update', $this->pack), $this->form(['price' => 0]))
            ->assertSessionHasErrors('price');

        $this->assertSame([$this->chatgpt->id, $this->vip->id], $this->pack->includedFormations()->pluck('formations.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_an_added_formation_opens_for_students_who_already_have_the_pack(): void
    {
        $student = User::factory()->create();
        $student->enroll($this->pack, EnrollmentSource::Admin);
        FormationAccessGranted::dispatch($student->enrollments()->where('formation_id', $this->pack->id)->sole());
        $this->assertTrue($student->isEnrolledIn($this->chatgpt));
        $this->assertFalse($student->isEnrolledIn($this->saas));

        $this->actingAs($this->admin)
            ->put(route('admin.formations.update', $this->pack), $this->form(['included' => [$this->chatgpt->id, $this->saas->id, $this->vip->id]]))
            ->assertSessionHasNoErrors();
        $this->assertTrue($student->isEnrolledIn($this->saas));

        // Décochée : l'élève qui l'a déjà la garde.
        $this->actingAs($this->admin)
            ->put(route('admin.formations.update', $this->pack), $this->form(['included' => [$this->chatgpt->id, $this->vip->id]]))
            ->assertSessionHasNoErrors();
        $this->assertTrue($student->isEnrolledIn($this->saas));
    }

    public function test_an_ordinary_formation_form_is_unchanged(): void
    {
        $this->actingAs($this->admin)->get(route('admin.formations.edit', $this->chatgpt))
            ->assertOk()
            ->assertDontSee('Formations incluses')
            ->assertSee('Ce que vous allez apprendre');
    }

    public function test_moderators_cannot_edit_packs(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->get(route('admin.formations.edit', $this->pack))->assertForbidden();
        $this->actingAs($moderator)->put(route('admin.formations.update', $this->pack), $this->form(['included' => []]))->assertForbidden();
        $this->assertCount(2, $this->pack->includedFormations()->get());
    }
}
