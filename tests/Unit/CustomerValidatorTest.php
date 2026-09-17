<?php

namespace SCCommerce\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SCCommerce\Support\CustomerValidator;

final class CustomerValidatorTest extends TestCase
{
    private function validCustomer(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'address_line1' => '1 High Street',
            'address_town' => 'Springfield',
            'address_postcode' => 'SW1A 1AA',
        ], $overrides);
    }

    public function test_a_fully_valid_customer_has_no_invalid_field(): void
    {
        $this->assertNull(CustomerValidator::firstInvalidField($this->validCustomer()));
    }

    public function test_blank_name_is_reported_first(): void
    {
        $this->assertSame('name', CustomerValidator::firstInvalidField($this->validCustomer(['name' => ''])));
    }

    public function test_blank_or_invalid_email_is_reported(): void
    {
        $this->assertSame('email', CustomerValidator::firstInvalidField($this->validCustomer(['email' => ''])));
        $this->assertSame('email', CustomerValidator::firstInvalidField($this->validCustomer(['email' => 'not-an-email'])));
    }

    public function test_blank_address_line1_is_reported(): void
    {
        $this->assertSame('address_line1', CustomerValidator::firstInvalidField($this->validCustomer(['address_line1' => ''])));
    }

    public function test_blank_town_is_reported(): void
    {
        $this->assertSame('address_town', CustomerValidator::firstInvalidField($this->validCustomer(['address_town' => ''])));
    }

    public function test_blank_or_malformed_postcode_is_reported(): void
    {
        $this->assertSame('address_postcode', CustomerValidator::firstInvalidField($this->validCustomer(['address_postcode' => ''])));
        $this->assertSame('address_postcode', CustomerValidator::firstInvalidField($this->validCustomer(['address_postcode' => '12345'])));
    }

    /**
     * address_line2/address_county aren't checked at all — both are
     * optional on the checkout form, so blank is correct input there,
     * unlike the required fields above.
     */
    public function test_optional_fields_are_never_the_reported_field(): void
    {
        $customer = $this->validCustomer();
        $customer['address_line2'] = '';
        $customer['address_county'] = '';

        $this->assertNull(CustomerValidator::firstInvalidField($customer));
    }

    /**
     * @dataProvider validUkPostcodeProvider
     */
    public function test_valid_uk_postcode_shapes_are_accepted(string $postcode): void
    {
        $this->assertTrue(CustomerValidator::isValidUkPostcode($postcode));
    }

    public function validUkPostcodeProvider(): array
    {
        return [
            'standard, with space' => ['SW1A 1AA'],
            'standard, no space' => ['SW1A1AA'],
            'lowercase' => ['sw1a 1aa'],
            'single-letter area' => ['M1 1AE'],
            'two-digit district' => ['B33 8TH'],
            'trims surrounding whitespace' => ['  EC1A 1BB  '],
        ];
    }

    /**
     * @dataProvider invalidPostcodeProvider
     */
    public function test_malformed_postcodes_are_rejected(string $postcode): void
    {
        $this->assertFalse(CustomerValidator::isValidUkPostcode($postcode));
    }

    public function invalidPostcodeProvider(): array
    {
        return [
            'empty' => [''],
            'random digits' => ['12345'],
            'us zip' => ['90210'],
            'missing inward code' => ['SW1A'],
        ];
    }
}
