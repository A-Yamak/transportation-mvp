<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Callback;

use App\Models\Business;
use App\Models\BusinessPayloadSchema;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Services\Callback\CallbackService;
use App\Services\PayloadSchema\SchemaTransformer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('services')]
#[Group('callback')]
class CallbackServiceTest extends TestCase
{
    private CallbackService $callbackService;

    protected function setUp(): void
    {
        parent::setUp();

        $transformer = new SchemaTransformer();
        $this->callbackService = new CallbackService($transformer);
    }

    #[Test]
    public function sends_callback_with_transformed_payload(): void
    {
        Http::fake([
            'https://erp.example.com/*' => Http::response(['success' => true], 200),
        ]);

        $business = Business::factory()->create([
            'callback_url' => 'https://erp.example.com/delivery-callback',
            'callback_api_key' => 'test_api_key_123',
        ]);

        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
            'callback_schema' => BusinessPayloadSchema::defaultCallbackSchema(),
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'external_id' => 'ORDER-12345',
            'recipient_name' => 'John Doe',
        ]);

        $result = $this->callbackService->sendCompletionCallback($destination);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://erp.example.com/delivery-callback'
                && $request->hasHeader('Authorization', 'Bearer test_api_key_123')
                && $request['external_id'] === 'ORDER-12345'
                && $request['status'] === 'completed'
                && $request['recipient_name'] === 'John Doe';
        });
    }

    #[Test]
    public function sends_callback_with_custom_schema(): void
    {
        Http::fake([
            'https://custom-erp.com/*' => Http::response(['ok' => true], 200),
        ]);

        $business = Business::factory()->create([
            'callback_url' => 'https://custom-erp.com/webhook',
            'callback_api_key' => 'custom_key_456',
        ]);

        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
            'callback_schema' => [
                'order_id' => 'external_id',
                'delivery_status' => 'status',
                'delivered_at' => 'completed_at',
            ],
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'external_id' => 'ORD-999',
        ]);

        $result = $this->callbackService->sendCompletionCallback($destination);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request['order_id'] === 'ORD-999'
                && $request['delivery_status'] === 'completed'
                && isset($request['delivered_at']);
        });
    }

    #[Test]
    public function returns_false_when_no_callback_url_configured(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('No callback URL configured for business', \Mockery::any());

        $business = Business::factory()->create([
            'callback_url' => null, // No callback URL
        ]);

        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $result = $this->callbackService->sendCompletionCallback($destination);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    #[Test]
    public function returns_false_when_no_schema_configured(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('No payload schema configured for business', \Mockery::any());

        $business = Business::factory()->create([
            'callback_url' => 'https://erp.example.com/webhook',
        ]);

        // No schema created for this business

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $result = $this->callbackService->sendCompletionCallback($destination);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    #[Test]
    public function sends_callback_without_api_key(): void
    {
        Http::fake([
            'https://public-erp.com/*' => Http::response(['success' => true], 200),
        ]);

        $business = Business::factory()->create([
            'callback_url' => 'https://public-erp.com/webhook',
            'callback_api_key' => null, // No API key
        ]);

        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $result = $this->callbackService->sendCompletionCallback($destination);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('Authorization');
        });
    }

    #[Test]
    public function returns_false_when_callback_fails(): void
    {
        Http::fake([
            'https://erp.example.com/*' => Http::response(['error' => 'Invalid payload'], 422),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Callback sent to ERP', \Mockery::on(function ($context) {
                return $context['success'] === false && $context['status'] === 422;
            }));

        $business = Business::factory()->create([
            'callback_url' => 'https://erp.example.com/webhook',
        ]);

        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $result = $this->callbackService->sendCompletionCallback($destination);

        $this->assertFalse($result);
    }

    #[Test]
    public function handles_network_errors_gracefully(): void
    {
        Http::fake([
            'https://down-erp.com/*' => function () {
                throw new \Exception('Connection timeout');
            },
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to send callback to ERP', \Mockery::on(function ($context) {
                return str_contains($context['error'], 'Connection timeout');
            }));

        $business = Business::factory()->create([
            'callback_url' => 'https://down-erp.com/webhook',
        ]);

        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $result = $this->callbackService->sendCompletionCallback($destination);

        $this->assertFalse($result);
    }

    #[Test]
    public function sends_test_callback_successfully(): void
    {
        Http::fake([
            'https://test-erp.com/*' => Http::response(['received' => true], 200),
        ]);

        $result = $this->callbackService->sendTestCallback(
            'https://test-erp.com/test-webhook',
            'test_key_789'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertStringContainsString('successfully', $result['message']);
        $this->assertArrayHasKey('response_body', $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://test-erp.com/test-webhook'
                && $request->hasHeader('Authorization', 'Bearer test_key_789')
                && $request['external_id'] === 'TEST-123'
                && $request['message'] === 'This is a test callback from Transportation MVP';
        });
    }

    #[Test]
    public function test_callback_handles_failures(): void
    {
        Http::fake([
            'https://bad-erp.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->callbackService->sendTestCallback(
            'https://bad-erp.com/webhook',
            'invalid_key'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(401, $result['status']);
        $this->assertStringContainsString('failed with status 401', $result['message']);
    }

    #[Test]
    public function test_callback_handles_exceptions(): void
    {
        Http::fake([
            'https://error-erp.com/*' => function () {
                throw new \Exception('DNS resolution failed');
            },
        ]);

        $result = $this->callbackService->sendTestCallback(
            'https://error-erp.com/webhook'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['status']);
        $this->assertStringContainsString('Exception', $result['message']);
        $this->assertStringContainsString('DNS resolution failed', $result['message']);
    }

    #[Test]
    public function logs_successful_callback_attempts(): void
    {
        Http::fake([
            'https://erp.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Callback sent to ERP', \Mockery::on(function ($context) {
                return $context['url'] === 'https://erp.example.com/webhook'
                    && $context['status'] === 200
                    && $context['success'] === true
                    && isset($context['payload']);
            }));

        $business = Business::factory()->create([
            'callback_url' => 'https://erp.example.com/webhook',
        ]);

        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $this->callbackService->sendCompletionCallback($destination);
    }
}
