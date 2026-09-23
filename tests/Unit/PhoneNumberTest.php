<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_it_normalizes_numbers_to_international_format(): void
    {
        $this->assertSame('+22890123456', PhoneNumber::normalize('90 12 34 56'));
        $this->assertSame('+22890123456', PhoneNumber::normalize('+228 90-12-34-56'));
        $this->assertSame('+22997001122', PhoneNumber::normalize('97001122', '+229'));
        $this->assertSame('+33612345678', PhoneNumber::normalize('06 12 34 56 78', '+33'));
        $this->assertSame('+233241234567', PhoneNumber::normalize('00233 24 123 4567'));
    }

    public function test_it_rejects_invalid_numbers(): void
    {
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize('1234'));
        $this->assertNull(PhoneNumber::normalize('+228 90 12 34'));
        $this->assertNull(PhoneNumber::normalize('abc'));
    }

    public function test_it_formats_numbers_for_display(): void
    {
        $this->assertSame('+228 90 12 34 56', PhoneNumber::format('+22890123456'));
        $this->assertSame(['+229', '97001122'], PhoneNumber::split('+22997001122'));
        $this->assertSame('+4915112345678', PhoneNumber::format('+4915112345678'));
    }
}
