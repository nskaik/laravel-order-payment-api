# Payment Gateway Integration Guide

This guide explains how to extend the payment gateway system by adding new payment providers. The architecture follows the **Open/Closed Principle** — open for extension, closed for modification — allowing you to add new gateways with minimal changes to existing code.

## Architecture Overview

The payment system uses a **Strategy Pattern** with the following components:

- **`PaymentGatewayInterface`** — Contract that all gateways must implement
- **`PaymentResult`** — DTO returned by gateways containing the payment outcome
- **`PaymentService`** — Orchestrates payment processing and gateway resolution
- **`config/payment.php`** — Maps payment methods to gateway classes

```
┌─────────────────────┐     ┌──────────────────────────┐
│   PaymentService    │────▶│  PaymentGatewayInterface │
└─────────────────────┘     └──────────────────────────┘
         │                            ▲
         │                  ┌─────────┴─────────┐
         ▼                  │                   │
┌─────────────────────┐     │                   │
│  config/payment.php │     ▼                   ▼
│  (gateway mapping)  │  ┌──────────┐    ┌────────────┐
└─────────────────────┘  │CreditCard│    │  PayPal    │
                         │ Gateway  │    │  Gateway   │
                         └──────────┘    └────────────┘
```

## Step-by-Step Guide

### Step 1: Create the Gateway Class

Create a new class in `app/Gateways/` that implements `PaymentGatewayInterface`:

```php
<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Str;

class StripeGateway implements PaymentGatewayInterface
{
    private readonly array $config;

    public function __construct()
    {
        $this->config = config('payment.gateway_config.stripe');
    }

    public function process(Order $order, array $paymentData): PaymentResult
    {
        // Access configuration
        $apiKey = $this->config['api_key'];
        $secretKey = $this->config['secret_key'];
        
        try {
            // Implement your Stripe API integration here
            // Example: Create a PaymentIntent, charge the customer, etc.
            
            $transactionId = $this->processStripePayment($order, $paymentData);
            
            return new PaymentResult(
                status: PaymentStatus::Successful,
                transactionId: $transactionId
            );
        } catch (\Exception $e) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                errorMessage: $e->getMessage()
            );
        }
    }

    private function processStripePayment(Order $order, array $paymentData): string
    {
        // Your Stripe SDK integration logic here
        // This is a placeholder - implement actual Stripe API calls
        
        return 'STRIPE_' . strtoupper(Str::random(16)) . '_' . time();
    }
}
```

### Step 2: Register the Gateway in Configuration

Add your gateway to `config/payment.php` under the `gateways` array:

```php
'gateways' => [
    'credit_card' => \App\Gateways\CreditCardGateway::class,
    'debit_card' => \App\Gateways\CreditCardGateway::class,
    'paypal' => \App\Gateways\PayPalGateway::class,
    'stripe' => \App\Gateways\StripeGateway::class,  // Add this line
],
```

### Step 3: Add Gateway-Specific Configuration

Add configuration for your gateway under `gateway_config` in `config/payment.php`:

```php
'gateway_config' => [
    // ... existing configurations ...

    'stripe' => [
        'api_key' => env('STRIPE_API_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'mode' => env('STRIPE_MODE', 'test'),
    ],
],
```

### Step 4: Add Environment Variables

Add the required environment variables to `.env.example`:

```env
# Stripe Gateway
STRIPE_API_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_MODE=test
```

Then add actual values to your `.env` file.

### Step 5: Update Validation Rules

Update `app/Http/Requests/StorePaymentRequest.php` to include your new payment method:

```php
public function rules(): array
{
    return [
        'order_id' => 'required|integer|exists:orders,id',
        'payment_method' => 'required|string|in:credit_card,debit_card,paypal,bank_transfer,stripe',
        'card_number' => 'required_if:payment_method,credit_card,debit_card|string|nullable',
        'paypal_email' => 'required_if:payment_method,paypal|email|nullable',
        // Add Stripe-specific validation
        'stripe_token' => 'required_if:payment_method,stripe|string|nullable',
    ];
}
```

Also update the custom error messages:

```php
public function messages(): array
{
    return [
        // ... existing messages ...
        'payment_method.in' => 'Payment method must be one of: credit_card, debit_card, paypal, bank_transfer, stripe.',
        'stripe_token.required_if' => 'Stripe token is required for Stripe payments.',
    ];
}
```

## Understanding the Interface Contract

### PaymentGatewayInterface

All gateways must implement this interface:

```php
<?php

namespace App\Contracts;

use App\DataTransferObjects\PaymentResult;
use App\Models\Order;

interface PaymentGatewayInterface
{
    /**
     * Process a payment for the given order.
     */
    public function process(Order $order, array $paymentData): PaymentResult;
}
```

**Parameters:**
- `$order` — The Order model containing amount, customer info, and order details
- `$paymentData` — Array of payment-specific data (card numbers, tokens, emails, etc.)

**Returns:** A `PaymentResult` DTO with the outcome of the payment attempt.

### PaymentResult DTO

The `PaymentResult` is a readonly Data Transfer Object that encapsulates the payment outcome:

```php
<?php

namespace App\DataTransferObjects;

use App\Enums\PaymentStatus;

readonly class PaymentResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $transactionId = null,
        public ?string $errorMessage = null
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
```

**Properties:**
- `status` — A `PaymentStatus` enum value: `Successful`, `Failed`, or `Pending`
- `transactionId` — Unique identifier from the payment provider (required for successful payments)
- `errorMessage` — Human-readable error message (for failed payments)

### PaymentStatus Enum

```php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
}
```

## Complete Example: Bank Transfer Gateway

Here's a complete example implementing a bank transfer gateway:

```php
<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Str;

class BankTransferGateway implements PaymentGatewayInterface
{
    private readonly array $config;

    public function __construct()
    {
        $this->config = config('payment.gateway_config.bank_transfer');
    }

    public function process(Order $order, array $paymentData): PaymentResult
    {
        // Bank transfers are typically pending until confirmed
        $accountNumber = $paymentData['account_number'] ?? null;
        $routingNumber = $paymentData['routing_number'] ?? null;

        if (!$accountNumber || !$routingNumber) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                errorMessage: 'Account number and routing number are required.'
            );
        }

        // Validate account format (example validation)
        if (!$this->validateAccountNumber($accountNumber)) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                errorMessage: 'Invalid account number format.'
            );
        }

        // Bank transfers typically return pending status
        // The actual transfer confirmation happens asynchronously
        return new PaymentResult(
            status: PaymentStatus::Pending,
            transactionId: $this->generateTransactionId()
        );
    }

    private function validateAccountNumber(string $accountNumber): bool
    {
        return preg_match('/^\d{8,17}$/', $accountNumber) === 1;
    }

    private function generateTransactionId(): string
    {
        return 'BT_' . strtoupper(Str::random(16)) . '_' . time();
    }
}
```

**Configuration in `config/payment.php`:**

```php
'gateways' => [
    // ... existing gateways ...
    'bank_transfer' => \App\Gateways\BankTransferGateway::class,
],

'gateway_config' => [
    // ... existing configs ...
    'bank_transfer' => [
        'bank_name' => env('BANK_TRANSFER_BANK_NAME', 'Default Bank'),
        'timeout_days' => env('BANK_TRANSFER_TIMEOUT_DAYS', 3),
    ],
],
```

## Summary of Required Changes

Adding a new payment gateway requires changes to only **4 files**:

| File | Change |
|------|--------|
| `app/Gateways/YourGateway.php` | Create new gateway class |
| `config/payment.php` | Register gateway and add configuration |
| `.env.example` | Document required environment variables |
| `app/Http/Requests/StorePaymentRequest.php` | Add validation rules |

**No changes required to:**
- `PaymentService` — Automatically resolves gateways from configuration
- `PaymentController` — Works with any registered gateway
- Database migrations — Payment method is stored as a string
- Existing gateway implementations — Completely isolated

## Testing Recommendations

### Unit Tests for Your Gateway

Create a unit test for your gateway in `tests/Unit/`:

```php
<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Gateways\StripeGateway;
use App\Models\Order;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    public function test_successful_payment_returns_successful_result(): void
    {
        $gateway = new StripeGateway();
        $order = Order::factory()->make(['total_amount' => '100.00']);

        $result = $gateway->process($order, [
            'stripe_token' => 'tok_visa_success',
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertNotNull($result->transactionId);
        $this->assertNull($result->errorMessage);
    }

    public function test_failed_payment_returns_failed_result(): void
    {
        $gateway = new StripeGateway();
        $order = Order::factory()->make(['total_amount' => '100.00']);

        $result = $gateway->process($order, [
            'stripe_token' => 'tok_declined',
        ]);

        $this->assertTrue($result->isFailed());
        $this->assertNull($result->transactionId);
        $this->assertNotNull($result->errorMessage);
    }
}
```

### Feature Tests for API Integration

Add feature tests in `tests/Feature/PaymentTest.php`:

```php
public function test_can_process_payment_with_stripe(): void
{
    $order = $this->createOrder($this->user, OrderStatus::Confirmed);

    $response = $this->postJson('/api/payments', [
        'order_id' => $order->id,
        'payment_method' => 'stripe',
        'stripe_token' => 'tok_visa_success',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.payment_method', 'stripe')
        ->assertJsonPath('data.status', 'successful');

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'payment_method' => 'stripe',
        'status' => 'successful',
    ]);
}
```

### Mocking External APIs

For gateways that call external APIs, use mocking to avoid real API calls in tests:

```php
use Mockery;

public function test_gateway_handles_api_timeout(): void
{
    // Mock the HTTP client or SDK
    $this->mock(StripeClient::class, function ($mock) {
        $mock->shouldReceive('paymentIntents->create')
            ->andThrow(new \Exception('Connection timeout'));
    });

    $gateway = app(StripeGateway::class);
    $order = Order::factory()->make();

    $result = $gateway->process($order, ['stripe_token' => 'tok_test']);

    $this->assertTrue($result->isFailed());
    $this->assertStringContainsString('timeout', $result->errorMessage);
}
```

## Best Practices

1. **Always return a `PaymentResult`** — Never throw exceptions from the `process()` method; catch them and return a failed result instead.

2. **Generate unique transaction IDs** — Use a prefix that identifies your gateway (e.g., `STRIPE_`, `PP_`, `BT_`).

3. **Log payment attempts** — Consider logging all payment attempts for debugging and audit purposes.

4. **Handle timeouts gracefully** — External APIs can be slow; implement appropriate timeout handling.

5. **Validate input data** — Validate payment-specific data before making API calls.

6. **Use environment variables** — Never hardcode API keys or secrets; always use configuration.

