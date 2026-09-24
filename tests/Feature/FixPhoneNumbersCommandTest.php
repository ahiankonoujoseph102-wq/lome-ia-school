<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixPhoneNumbersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_numbers_that_lost_their_leading_zero(): void
    {
        $benin = User::factory()->create(['phone' => '+229194555914']);
        $burkina = User::factory()->create(['phone' => '+2267276381']);
        $togo = User::factory()->create(['phone' => '+22890123456']);

        $this->artisan('app:fix-phone-numbers', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('+229194555914', $benin->refresh()->phone);

        $this->artisan('app:fix-phone-numbers')->assertSuccessful();

        $this->assertSame('+2290194555914', $benin->refresh()->phone);
        $this->assertSame('+22607276381', $burkina->refresh()->phone);
        $this->assertSame('+22890123456', $togo->refresh()->phone);
    }

    public function test_it_never_creates_a_duplicate_number(): void
    {
        User::factory()->create(['phone' => '+2290194555914']);
        $old = User::factory()->create(['phone' => '+229194555914']);

        $this->artisan('app:fix-phone-numbers')->assertSuccessful();

        $this->assertSame('+229194555914', $old->refresh()->phone);
    }
}
