<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_formats_amounts_in_fcfa_with_non_breaking_spaces(): void
    {
        $this->assertSame("5\u{00A0}500\u{00A0}FCFA", Money::fcfa(5500));
        $this->assertSame("300\u{00A0}000\u{00A0}FCFA", Money::fcfa(300000));
        $this->assertSame("0\u{00A0}FCFA", Money::fcfa(0));
    }
}
