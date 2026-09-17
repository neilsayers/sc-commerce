<?php

namespace SCCommerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SCCommerce\Support\Money;

final class MoneyTest extends TestCase
{
    public function test_round_rounds_to_two_decimal_places(): void
    {
        $this->assertSame(9.99, Money::round(9.994));
        $this->assertSame(10.0, Money::round(9.996));
    }

    public function test_round_fixes_the_classic_floating_point_imprecision(): void
    {
        // 0.1 + 0.2 is 0.30000000000000004 in binary float — exactly
        // the kind of drift line_total/total computations (unit_price
        // * quantity, summed across a basket) accumulate, and the
        // reason every money figure in this plugin passes through
        // Money::round() before being stored or displayed.
        $this->assertSame(0.3, Money::round(0.1 + 0.2));
    }

    public function test_format_uses_the_known_currency_symbols(): void
    {
        $this->assertSame('£9.99', Money::format(9.99, 'GBP'));
        $this->assertSame('$9.99', Money::format(9.99, 'USD'));
        $this->assertSame('€9.99', Money::format(9.99, 'EUR'));
    }

    public function test_format_falls_back_to_the_currency_code_for_an_unknown_currency(): void
    {
        $this->assertSame('JPY 1500.00', Money::format(1500, 'JPY'));
    }

    public function test_format_always_shows_two_decimal_places(): void
    {
        $this->assertSame('£5.00', Money::format(5, 'GBP'));
        $this->assertSame('£5.50', Money::format(5.5, 'GBP'));
    }

    public function test_format_uses_thousands_separators(): void
    {
        $this->assertSame('£1,234.56', Money::format(1234.56, 'GBP'));
    }
}
