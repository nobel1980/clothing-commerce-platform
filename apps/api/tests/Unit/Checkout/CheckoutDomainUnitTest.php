<?php

namespace Tests\Unit\Checkout;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Shipping\Services\ShippingCalculationService;
use App\Shared\Money\Money;
use App\Shared\Support\BangladeshAddressValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CheckoutDomainUnitTest extends TestCase
{
    public function test_money_value_object_performs_exact_integer_subunit_arithmetic(): void
    {
        $price = new Money(250000, 'BDT'); // 2,500.00 BDT in paisa
        $multiplied = $price->multiply(3);  // 7,500.00 BDT in paisa
        $discount = new Money(75000, 'BDT'); // 750.00 BDT in paisa
        $net = $multiplied->subtract($discount);
        $shipping = new Money(7000, 'BDT');
        $total = $net->add($shipping);

        $this->assertSame(750000, $multiplied->amount);
        $this->assertSame(675000, $net->amount);
        $this->assertSame(682000, $total->amount);
        $this->assertSame('৳6,820.00', $total->format());
        $this->assertSame('6820.00', $total->toMajorUnits());

        // Subtracting larger amount clamps to 0 (never negative money)
        $clamped = $discount->subtract($multiplied);
        $this->assertSame(0, $clamped->amount);
    }

    public function test_money_rejects_cross_currency_addition(): void
    {
        $bdt = new Money(10000, 'BDT');
        $usd = new Money(10000, 'USD');

        $this->expectException(InvalidArgumentException::class);
        $bdt->add($usd);
    }

    public function test_bangladesh_phone_validation_and_normalization(): void
    {
        // Valid BD mobile numbers across operators 013-019
        $this->assertTrue(BangladeshAddressValidator::isValidPhone('01712345678'));
        $this->assertTrue(BangladeshAddressValidator::isValidPhone('+8801812345678'));
        $this->assertTrue(BangladeshAddressValidator::isValidPhone('8801912345678'));
        $this->assertTrue(BangladeshAddressValidator::isValidPhone('01312345678'));
        $this->assertTrue(BangladeshAddressValidator::isValidPhone('01512345678'));

        // Invalid BD mobile numbers
        $this->assertFalse(BangladeshAddressValidator::isValidPhone('01112345678')); // 011 invalid operator
        $this->assertFalse(BangladeshAddressValidator::isValidPhone('01212345678')); // 012 invalid operator
        $this->assertFalse(BangladeshAddressValidator::isValidPhone('0171234567'));  // Too short (10 digits)
        $this->assertFalse(BangladeshAddressValidator::isValidPhone('017123456789')); // Too long (12 digits)
        $this->assertFalse(BangladeshAddressValidator::isValidPhone('+15551234567')); // Non-BD country code

        // Normalization to canonical 11-digit 01XXXXXXXXX
        $this->assertSame('01712345678', BangladeshAddressValidator::normalizePhone('+8801712345678'));
        $this->assertSame('01812345678', BangladeshAddressValidator::normalizePhone('8801812345678'));
        $this->assertSame('01912345678', BangladeshAddressValidator::normalizePhone('01912-345678'));
    }

    public function test_bangladesh_address_snapshot_normalization(): void
    {
        $snapshot = BangladeshAddressValidator::normalizeSnapshot([
            'firstName'             => 'Nadia',
            'lastName'              => 'Karim',
            'email'                 => 'nadia@example.com',
            'phone'                 => '+8801711223344',
            'division'              => 'Chattogram',
            'district'              => 'Cox\'s Bazar',
            'upazila'               => 'Teknaf',
            'street'                => 'Marine Drive Road 4',
            'postalCode'            => '4760',
            'delivery_instructions' => 'Call before arrival',
        ]);

        $this->assertSame('Nadia Karim', $snapshot['name']);
        $this->assertSame('01711223344', $snapshot['phone']);
        $this->assertSame('Chattogram', $snapshot['division']);
        $this->assertSame('Cox\'s Bazar', $snapshot['district']);
        $this->assertSame('Teknaf', $snapshot['upazila_thana']);
        $this->assertSame('Marine Drive Road 4', $snapshot['street_address']);
        $this->assertSame('4760', $snapshot['postal_code']);
        $this->assertSame('BD', $snapshot['country']);
    }

    public function test_shipping_calculation_service_computes_bangladesh_zone_rates(): void
    {
        $service = new ShippingCalculationService();
        $subtotalBelowThreshold = new Money(250000, 'BDT'); // 2,500.00 BDT
        $subtotalAboveThreshold = new Money(500000, 'BDT'); // 5,000.00 BDT

        // 1. Inside Dhaka Metro (Dhaka Division + Dhaka District)
        $dhakaMetro = ['division' => 'Dhaka', 'district' => 'Dhaka'];
        $this->assertSame(7000, $service->calculate($subtotalBelowThreshold, 'standard', $dhakaMetro)->amount);
        $this->assertSame(15000, $service->calculate($subtotalBelowThreshold, 'express', $dhakaMetro)->amount);
        $this->assertSame(0, $service->calculate($subtotalAboveThreshold, 'standard', $dhakaMetro)->amount);

        // 2. Dhaka Sub-Area (Dhaka Division + Gazipur District)
        $dhakaSub = ['division' => 'Dhaka', 'district' => 'Gazipur'];
        $this->assertSame(10000, $service->calculate($subtotalBelowThreshold, 'standard', $dhakaSub)->amount);
        $this->assertSame(18000, $service->calculate($subtotalBelowThreshold, 'express', $dhakaSub)->amount);

        // 3. Outside Dhaka Division (Sylhet Division + Sylhet District)
        $outsideDhaka = ['division' => 'Sylhet', 'district' => 'Sylhet'];
        $this->assertSame(13000, $service->calculate($subtotalBelowThreshold, 'standard', $outsideDhaka)->amount);
        $this->assertSame(25000, $service->calculate($subtotalBelowThreshold, 'express', $outsideDhaka)->amount);
        $this->assertSame(0, $service->calculate($subtotalAboveThreshold, 'standard', $outsideDhaka)->amount);

        // 4. Store Pickup is always free
        $this->assertSame(0, $service->calculate($subtotalBelowThreshold, 'pickup', $outsideDhaka)->amount);
    }

    public function test_order_policy_enforces_customer_and_guest_ownership(): void
    {
        $policy = new OrderPolicy();

        $owner = new User();
        $owner->id = 10;
        $owner->is_staff = false;

        $otherUser = new User();
        $otherUser->id = 20;
        $otherUser->is_staff = false;

        $staffUser = new User();
        $staffUser->id = 99;
        $staffUser->is_staff = true;

        $customerOrder = new Order(['user_id' => 10, 'guest_token' => null]);
        $this->assertTrue($policy->view($owner, $customerOrder));
        $this->assertFalse($policy->view($otherUser, $customerOrder));
        $this->assertFalse($policy->view(null, $customerOrder));
        $this->assertTrue($policy->view($staffUser, $customerOrder));

        $guestOrder = new Order(['user_id' => null, 'guest_token' => 'secret-guest-token']);
        $this->assertTrue($policy->view(null, $guestOrder, 'secret-guest-token'));
        $this->assertFalse($policy->view(null, $guestOrder, 'wrong-guest-token'));
        $this->assertFalse($policy->view(null, $guestOrder, null));
    }
}

