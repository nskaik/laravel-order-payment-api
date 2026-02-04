<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    public function test_can_process_payment_for_confirmed_order_with_credit_card(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000', // Ends in 0000 for guaranteed success
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'payment_method',
                    'amount',
                    'transaction_id',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.payment_method', 'credit_card')
            ->assertJsonPath('data.amount', $order->total_amount)
            ->assertJsonPath('data.status', 'successful');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'status' => 'successful',
        ]);
    }

    private function createOrder(User $user, OrderStatus $status = OrderStatus::Pending, string $totalAmount = '99.98'): Order
    {
        $order = Order::factory()
            ->forUser($user)
            ->state(['status' => $status, 'total_amount' => $totalAmount])
            ->create();

        OrderItem::factory()
            ->forOrder($order)
            ->state([
                'product_name' => 'Test Product',
                'quantity' => 2,
                'unit_price' => '49.99',
                'subtotal' => '99.98',
            ])
            ->create();

        return $order->fresh(['items', 'payment']);
    }

    public function test_can_process_payment_for_confirmed_order_with_paypal(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
            'paypal_email' => 'user@success.com', // Contains 'success' for guaranteed success
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'paypal')
            ->assertJsonPath('data.status', 'successful');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
            'status' => 'successful',
        ]);
    }

    public function test_payment_fails_with_declined_card(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111119999', // Ends in 9999 for guaranteed failure
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'failed',
        ]);
    }

    public function test_cannot_process_payment_for_pending_order(): void
    {
        $order = $this->createOrder($this->user);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Payments can only be processed for confirmed orders.']);
    }

    public function test_cannot_process_payment_for_cancelled_order(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Cancelled);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Payments can only be processed for confirmed orders.']);
    }

    public function test_cannot_process_duplicate_payment_for_order(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);
        $this->createPaymentForOrder($order);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'This order already has a payment.']);
    }

    private function createPaymentForOrder(Order $order, PaymentStatus $status = PaymentStatus::Successful): Payment
    {
        return Payment::factory()
            ->forOrder($order)
            ->state(['status' => $status])
            ->create();
    }

    public function test_cannot_process_payment_for_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'You do not have permission to pay for this order.']);
    }

    public function test_cannot_process_payment_for_non_existent_order(): void
    {
        $response = $this->postJson('/api/payments', [
            'order_id' => 99999,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['order_id']]);
    }

    public function test_store_payment_requires_authentication(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_payment_validates_payment_method(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'invalid_method',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['payment_method']]);
    }

    public function test_index_returns_paginated_payments_for_authenticated_user(): void
    {
        $order1 = $this->createOrder($this->user, OrderStatus::Confirmed);
        $order2 = $this->createOrder($this->user, OrderStatus::Confirmed);
        $this->createPaymentForOrder($order1);
        $this->createPaymentForOrder($order2);

        $response = $this->getJson('/api/payments', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'status', 'payment_method', 'amount', 'transaction_id'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_index_only_returns_payments_for_authenticated_user(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);
        $this->createPaymentForOrder($order);

        $otherUser = User::factory()->create();
        $otherOrder = $this->createOrder($otherUser, OrderStatus::Confirmed);
        $this->createPaymentForOrder($otherOrder);

        $response = $this->getJson('/api/payments', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_respects_per_page_parameter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $order = $this->createOrder($this->user, OrderStatus::Confirmed);
            $this->createPaymentForOrder($order);
        }

        $response = $this->getJson('/api/payments?per_page=2', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/payments');

        $response->assertStatus(401);
    }

    public function test_show_returns_payment_details(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);
        $payment = $this->createPaymentForOrder($order);

        $response = $this->getJson("/api/payments/{$payment->id}", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'payment_method',
                    'amount',
                    'transaction_id',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.id', $payment->id);
    }

    public function test_show_returns_404_for_non_existent_payment(): void
    {
        $response = $this->getJson('/api/payments/99999', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Payment not found.']);
    }

    public function test_show_returns_403_for_another_users_payment(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser, OrderStatus::Confirmed);
        $payment = $this->createPaymentForOrder($order);

        $response = $this->getJson("/api/payments/{$payment->id}", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'You do not have permission to access this payment.']);
    }

    public function test_show_requires_authentication(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);
        $payment = $this->createPaymentForOrder($order);

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(401);
    }

    public function test_show_for_order_returns_payment(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);
        $payment = $this->createPaymentForOrder($order);

        $response = $this->getJson("/api/orders/{$order->id}/payment", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'payment_method',
                    'amount',
                    'transaction_id',
                ],
            ])
            ->assertJsonPath('data.id', $payment->id);
    }

    public function test_show_for_order_returns_404_for_non_existent_order(): void
    {
        $response = $this->getJson('/api/orders/99999/payment', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Order not found.']);
    }

    public function test_show_for_order_returns_404_when_no_payment_exists(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->getJson("/api/orders/{$order->id}/payment", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'No payment found for this order.']);
    }

    public function test_show_for_order_returns_403_for_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser, OrderStatus::Confirmed);
        $this->createPaymentForOrder($order);

        $response = $this->getJson("/api/orders/{$order->id}/payment", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => "You do not have permission to access this order's payment."]);
    }

    public function test_show_for_order_requires_authentication(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);
        $this->createPaymentForOrder($order);

        $response = $this->getJson("/api/orders/{$order->id}/payment");

        $response->assertStatus(401);
    }

    public function test_can_process_payment_with_debit_card(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'debit_card',
            'card_number' => '4111111111110000',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'debit_card')
            ->assertJsonPath('data.status', 'successful');
    }

    public function test_paypal_payment_fails_with_fail_email(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
            'paypal_email' => 'user@fail.com',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_payment_amount_matches_order_total(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed, '150.00');

        $response = $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'card_number' => '4111111111110000',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', '150.00');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
    }
}

