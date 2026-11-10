<?php

namespace Tests\Unit\OrdersAndPayments;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Payments\Services\GatewayAuditLogger;
use App\Modules\Payments\Services\PaymentGatewayFactory;
use App\Shared\Exceptions\Payments\PaymentConfigurationException;
use App\Shared\Support\SensitiveDataRedactor;
use Tests\TestCase;

class OrdersAndPaymentsUnitTest extends TestCase
{
    public function test_sensitive_data_redactor_scrubs_nested_credentials_and_tokens(): void
    {
        $rawPayload = [
            'app_key'      => 'bkash-public-app-key',
            'app_secret'   => 'super-secret-bkash-secret-123',
            'password'     => 'merchant-password-xyz',
            'store_passwd' => 'sslc-secret-passwd',
            'amount'       => '2500.00',
            'currency'     => 'BDT',
            'nested'       => [
                'id_token'      => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.secret',
                'refresh_token' => 'refresh-secret-token',
                'private_key'   => '-----BEGIN PRIVATE KEY-----MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC...',
                'signature'     => 'signed- cryptographic-digest',
                'trxID'         => 'BKASH987654321',
            ],
            'headers' => [
                'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
                'X-Signature'   => 'hmac-sha256-secret',
                'Accept'        => 'application/json',
            ],
        ];

        $redacted = SensitiveDataRedactor::redact($rawPayload);

        $this->assertSame('[REDACTED]', $redacted['app_key']);
        $this->assertSame('2500.00', $redacted['amount']);
        $this->assertSame('BDT', $redacted['currency']);
        $this->assertSame('BKASH987654321', $redacted['nested']['trxID']);
        $this->assertSame('application/json', $redacted['headers']['Accept']);

        $this->assertSame('[REDACTED]', $redacted['app_secret']);
        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['store_passwd']);
        $this->assertSame('[REDACTED]', $redacted['nested']['id_token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['refresh_token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['private_key']);
        $this->assertSame('[REDACTED]', $redacted['nested']['signature']);
        $this->assertSame('[REDACTED]', $redacted['headers']['Authorization']);
        $this->assertSame('[REDACTED]', $redacted['headers']['X-Signature']);
    }

    public function test_mock_gateway_is_strictly_blocked_when_disabled_or_in_production(): void
    {
        $factory = new PaymentGatewayFactory(new GatewayAuditLogger());

        // 1. Allowed in testing environment by default
        config(['payment.allow_mock_gateway' => true]);
        $adapter = $factory->resolve('mock');
        $this->assertSame('mock', $adapter->gatewayName());

        // 2. Blocked when allow_mock_gateway is false
        config(['payment.allow_mock_gateway' => false]);

        try {
            $factory->resolve('mock');
            $this->fail('Expected PaymentConfigurationException when mock gateway is disabled.');
        } catch (PaymentConfigurationException $e) {
            $this->assertStringContainsString('strictly forbidden', $e->getMessage());
        } finally {
            config(['payment.allow_mock_gateway' => true]);
        }

        // 3. Blocked when APP_ENV is production even if allow_mock_gateway is true
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $factory->resolve('mock');
            $this->fail('Expected PaymentConfigurationException when resolving mock gateway in production.');
        } catch (PaymentConfigurationException $e) {
            $this->assertStringContainsString('production', $e->getMessage());
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_order_policy_enforces_customer_ownership_and_staff_access(): void
    {
        $policy = new OrderPolicy();

        $customerA = new User();
        $customerA->id = 101;
        $customerA->role = 'customer';

        $customerB = new User();
        $customerB->id = 202;
        $customerB->role = 'customer';

        $staffUser = new User();
        $staffUser->id = 900;
        $staffUser->role = 'order_manager';

        $order = new Order([
            'user_id' => 101,
        ]);

        $this->assertTrue($policy->view($customerA, $order));
        $this->assertTrue($policy->cancel($customerA, $order));

        $this->assertFalse($policy->view($customerB, $order));
        $this->assertFalse($policy->cancel($customerB, $order));
        $this->assertFalse($policy->view(null, $order));

        $this->assertTrue($policy->view($staffUser, $order));
        $this->assertTrue($policy->manage($staffUser));
        $this->assertFalse($policy->manage($customerA));
    }
}
