<?php

namespace SCCommerce\Support;

/**
 * The checkout form's required-field and UK-postcode-shape checks,
 * pulled out of Frontend\CheckoutController for the same reason as
 * Money: plain, WP-independent logic that a unit test can exercise
 * directly, rather than being private methods on a class that also
 * does nonce checks and redirects. CheckoutController just calls in.
 */
final class CustomerValidator
{
    /**
     * Checked in a fixed order and only the first failure is reported,
     * since the checkout page has no way to redisplay the submitted
     * values or highlight several fields at once — one targeted
     * notice per re-attempt is simpler than a multi-error summary
     * here would be worth building.
     *
     * address_line2/address_county are deliberately not checked —
     * both are marked optional on the checkout form (see
     * template-functions.php's scc_the_checkout()), so an empty value
     * there is correct input, not missing input.
     *
     * @param array{name: string, email: string, address_line1: string, address_town: string, address_postcode: string} $customer
     */
    public static function firstInvalidField(array $customer): ?string
    {
        if ($customer['name'] === '') {
            return 'name';
        }

        if ($customer['email'] === '' || ! \is_email($customer['email'])) {
            return 'email';
        }

        if ($customer['address_line1'] === '') {
            return 'address_line1';
        }

        if ($customer['address_town'] === '') {
            return 'address_town';
        }

        if ($customer['address_postcode'] === '' || ! self::isValidUkPostcode($customer['address_postcode'])) {
            return 'address_postcode';
        }

        return null;
    }

    /**
     * Standard UK postcode shape (outward code + inward code); doesn't
     * check against Royal Mail's actual allocated code list, just that
     * it's shaped like a postcode — matching the fixed GB-only address
     * this plugin stores (PostTypes\OrderPostType::META_ADDRESS_COUNTRY).
     */
    public static function isValidUkPostcode(string $postcode): bool
    {
        return \preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', \trim($postcode)) === 1;
    }
}
