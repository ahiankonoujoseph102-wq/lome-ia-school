<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function validNumbers(): array
    {
        return [
            'togo' => ['90 12 34 56', null, '+22890123456'],
            'togo avec indicatif' => ['+228 90-12-34-56', null, '+22890123456'],
            'bénin, 10 chiffres' => ['01 94 55 59 14', '+229', '+2290194555914'],
            'bénin, complet' => ['+229 01 94 55 59 14', null, '+2290194555914'],
            'bénin, ancien numéro à 8 chiffres' => ['97001122', '+229', '+2290197001122'],
            'burkina faso, 0 initial conservé' => ['07 27 63 81', '+226', '+22607276381'],
            'burkina faso, complet' => ['+226 03 53 21 76', null, '+22603532176'],
            'côte d\'ivoire' => ['07 07 12 34 56', '+225', '+2250707123456'],
            'sénégal' => ['77 123 45 67', '+221', '+221771234567'],
            'guinée' => ['620 12 34 56', '+224', '+224620123456'],
            'ghana, préfixe 0 retiré' => ['024 123 4567', '+233', '+233241234567'],
            'ghana, avec 00' => ['00233 24 123 4567', null, '+233241234567'],
            'nigeria, préfixe 0 retiré' => ['0803 123 4567', '+234', '+2348031234567'],
            'france, préfixe 0 retiré' => ['06 12 34 56 78', '+33', '+33612345678'],
            'france, 0 après l\'indicatif' => ['+33 06 12 34 56 78', null, '+33612345678'],
            'pays hors liste' => ['+49 151 12345678', null, '+4915112345678'],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_it_normalizes_numbers_without_losing_digits(string $input, ?string $dialCode, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input, $dialCode));
    }

    public function test_it_rejects_invalid_numbers(): void
    {
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize('1234'));
        $this->assertNull(PhoneNumber::normalize('+228 90 12 34'));
        $this->assertNull(PhoneNumber::normalize('abc'));
        $this->assertNull(PhoneNumber::normalize('07 27 63', '+226'));
        $this->assertNull(PhoneNumber::normalize('01 94 55 59 14 99', '+229'));
    }

    public function test_it_formats_numbers_for_display(): void
    {
        $this->assertSame('+228 90 12 34 56', PhoneNumber::format('+22890123456'));
        $this->assertSame('+229 01 94 55 59 14', PhoneNumber::format('+2290194555914'));
        $this->assertSame('+226 07 27 63 81', PhoneNumber::format('+22607276381'));
        $this->assertSame('+221 771 23 45 67', PhoneNumber::format('+221771234567'));
        $this->assertSame(['+229', '0194555914'], PhoneNumber::split('+2290194555914'));
        $this->assertSame('+4915112345678', PhoneNumber::format('+4915112345678'));
    }

    public function test_it_repairs_numbers_saved_by_the_old_normalization(): void
    {
        // Ancienne normalisation : 01 94 55 59 14 (Bénin) était enregistré sans le 0.
        $this->assertSame('+2290194555914', PhoneNumber::repair('+229194555914'));
        $this->assertSame('+2290197001122', PhoneNumber::repair('+22997001122'));
        $this->assertSame('+22607276381', PhoneNumber::repair('+2267276381'));
        $this->assertSame('+2250707123456', PhoneNumber::repair('+225707123456'));
        $this->assertNull(PhoneNumber::repair('+22890123456'));
        $this->assertNull(PhoneNumber::repair('+2290194555914'));
        $this->assertNull(PhoneNumber::repair('+33612345678'));
    }
}
