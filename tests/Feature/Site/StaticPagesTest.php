<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaticPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pages(): array
    {
        return [
            'à propos' => ['about', 'Joseph Ahiankonou'],
            'contact' => ['contact', 'academyjoseph71@gmail.com'],
            'consultation' => ['consultation', 'Réservez votre créneau'],
            'mentions légales' => ['legal.notice', 'Mentions légales'],
            'CGV' => ['legal.terms', 'Conditions générales de vente'],
            'confidentialité' => ['legal.privacy', 'Politique de confidentialité'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_is_displayed(string $route, string $expected): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertSee($expected)
            ->assertSee('<title>', false);
    }

    public function test_contact_page_lists_all_contact_details(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('+228 72 24 02 31')
            ->assertSee('+228 79 16 23 90')
            ->assertSee('+228 79 74 21 27')
            ->assertSee('tel:+22872240231', false)
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé, Togo');
    }

    public function test_consultation_button_opens_whatsapp_with_booking_message(): void
    {
        $this->get(route('consultation'))
            ->assertOk()
            ->assertSee('https://wa.me/22872240231?text='.rawurlencode(config('site.whatsapp.consultation_message')), false);
    }
}
