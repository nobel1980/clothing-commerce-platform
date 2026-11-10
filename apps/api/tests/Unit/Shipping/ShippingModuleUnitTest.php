<?php

namespace Tests\Unit\Shipping;

use App\Modules\Shipping\Couriers\PathaoCourierAdapter;
use App\Modules\Shipping\Couriers\RedxCourierAdapter;
use App\Modules\Shipping\Couriers\SteadfastCourierAdapter;
use App\Modules\Shipping\Services\CourierAuditLogger;
use App\Modules\Shipping\Services\CourierProviderFactory;
use App\Shared\Exceptions\Shipping\ShippingException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ShippingModuleUnitTest extends TestCase
{
    public function test_courier_adapters_map_external_statuses_to_canonical_internal_statuses(): void
    {
        $logger = new CourierAuditLogger();
        $pathao = new PathaoCourierAdapter($logger);
        $steadfast = new SteadfastCourierAdapter($logger);
        $redx = new RedxCourierAdapter($logger);

        // Pathao status mappings
        $this->assertSame('created', $pathao->mapExternalStatus('Pending'));
        $this->assertSame('created', $pathao->mapExternalStatus('Pickup_Requested'));
        $this->assertSame('picked_up', $pathao->mapExternalStatus('Picked_Up'));
        $this->assertSame('in_transit', $pathao->mapExternalStatus('At_the_Sorting_Hub'));
        $this->assertSame('in_transit', $pathao->mapExternalStatus('In_Transit'));
        $this->assertSame('out_for_delivery', $pathao->mapExternalStatus('Assigned_for_Delivery'));
        $this->assertSame('delivered', $pathao->mapExternalStatus('Delivered'));
        $this->assertSame('delivered', $pathao->mapExternalStatus('order.delivered'));
        $this->assertSame('failed', $pathao->mapExternalStatus('Delivery_Failed'));
        $this->assertSame('returning', $pathao->mapExternalStatus('Return'));
        $this->assertSame('returned', $pathao->mapExternalStatus('Returned'));
        $this->assertSame('cancelled', $pathao->mapExternalStatus('Cancelled'));

        // Steadfast status mappings
        $this->assertSame('created', $steadfast->mapExternalStatus('in_review'));
        $this->assertSame('created', $steadfast->mapExternalStatus('pending'));
        $this->assertSame('delivered', $steadfast->mapExternalStatus('delivered_approval_pending'));
        $this->assertSame('failed', $steadfast->mapExternalStatus('hold'));
        $this->assertSame('returned', $steadfast->mapExternalStatus('returned'));
        $this->assertSame('cancelled', $steadfast->mapExternalStatus('cancelled'));

        // REDX status mappings
        $this->assertSame('created', $redx->mapExternalStatus('pickup-pending'));
        $this->assertSame('picked_up', $redx->mapExternalStatus('parcel-picked-up'));
        $this->assertSame('in_transit', $redx->mapExternalStatus('in-transit'));
        $this->assertSame('delivered', $redx->mapExternalStatus('delivered'));
        $this->assertSame('failed', $redx->mapExternalStatus('agent-hold'));
        $this->assertSame('returned', $redx->mapExternalStatus('returned'));
        $this->assertSame('cancelled', $redx->mapExternalStatus('cancelled'));
    }

    public function test_courier_provider_factory_resolves_supported_providers_and_rejects_unknown(): void
    {
        $factory = new CourierProviderFactory(new CourierAuditLogger());

        $this->assertSame('pathao', $factory->resolve('pathao')->providerName());
        $this->assertSame('steadfast', $factory->resolve('steadfast')->providerName());
        $this->assertSame('redx', $factory->resolve('redx')->providerName());

        $this->expectException(ShippingException::class);
        $factory->resolve('unknown_courier');
    }

    public function test_orders_module_contains_no_courier_specific_assumptions(): void
    {
        $ordersPath = base_path('app/Modules/Orders');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ordersPath));

        $forbiddenPatterns = [
            'Pathao',
            'Steadfast',
            'Redx',
            'App\\Modules\\Shipping\\Couriers',
            'App\\Modules\\Shipping\\Models',
        ];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $contents,
                    "Orders module file {$file->getFilename()} must not contain courier-specific reference '{$pattern}'."
                );
            }
        }
    }
}

