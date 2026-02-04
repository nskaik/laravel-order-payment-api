<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    public function test_index_returns_paginated_orders_for_authenticated_user(): void
    {
        $this->createOrder($this->user);
        $this->createOrder($this->user);

        $response = $this->getJson('/api/orders', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_id', 'status', 'total_amount', 'items', 'payment'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(2, 'data');
    }

    private function createOrder(User $user, OrderStatus $status = OrderStatus::Pending, array $items = []): Order
    {
        $order = Order::factory()
            ->forUser($user)
            ->state(['status' => $status, 'total_amount' => '0.00'])
            ->create();

        if (empty($items)) {
            $items = [
                ['quantity' => 2, 'unit_price' => '49.99'],
            ];
        }

        $totalAmount = '0.00';
        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? '10.00';
            $subtotal = bcmul($quantity, $unitPrice, 2);

            OrderItem::factory()
                ->forOrder($order)
                ->state([
                    'product_name' => $item['product_name'] ?? 'Test Product',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ])
                ->create();

            $totalAmount = bcadd($totalAmount, $subtotal, 2);
        }

        $order->update(['total_amount' => $totalAmount]);

        return $order->fresh(['items', 'payment']);
    }

    public function test_index_filters_orders_by_pending_status(): void
    {
        $this->createOrder($this->user, OrderStatus::Pending);
        $this->createOrder($this->user, OrderStatus::Confirmed);
        $this->createOrder($this->user, OrderStatus::Cancelled);

        $response = $this->getJson('/api/orders?status=pending', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_index_filters_orders_by_confirmed_status(): void
    {
        $this->createOrder($this->user, OrderStatus::Pending);
        $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->getJson('/api/orders?status=confirmed', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    public function test_index_filters_orders_by_cancelled_status(): void
    {
        $this->createOrder($this->user, OrderStatus::Pending);
        $this->createOrder($this->user, OrderStatus::Cancelled);

        $response = $this->getJson('/api/orders?status=cancelled', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_index_respects_custom_per_page_parameter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createOrder($this->user);
        }

        $response = $this->getJson('/api/orders?per_page=2', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_only_returns_orders_belonging_to_authenticated_user(): void
    {
        $this->createOrder($this->user);

        $otherUser = User::factory()->create();
        $this->createOrder($otherUser);

        $response = $this->getJson('/api/orders', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->user->id);
    }

    public function test_index_without_authentication_returns_401(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertStatus(401);
    }

    public function test_store_creates_order_with_valid_items(): void
    {
        $items = [
            ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 29.99],
            ['product_name' => 'Gadget', 'quantity' => 1, 'unit_price' => 49.99],
        ];

        $response = $this->postJson('/api/orders', ['items' => $items], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'status',
                    'total_amount',
                    'items' => [
                        '*' => ['id', 'product_name', 'quantity', 'unit_price', 'subtotal'],
                    ],
                ],
            ])
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    public function test_store_calculates_correct_total_amount(): void
    {
        $items = [
            ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 10.00],
            ['product_name' => 'Gadget', 'quantity' => 3, 'unit_price' => 5.00],
        ];

        $response = $this->postJson('/api/orders', ['items' => $items], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // 2 * 10.00 + 3 * 5.00 = 20.00 + 15.00 = 35.00
        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '35.00');
    }

    public function test_store_sets_default_status_to_pending(): void
    {
        $items = [
            ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 10.00],
        ];

        $response = $this->postJson('/api/orders', ['items' => $items], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_store_fails_with_missing_items(): void
    {
        $response = $this->postJson('/api/orders', [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items']]);
    }

    public function test_store_fails_with_empty_items_array(): void
    {
        $response = $this->postJson('/api/orders', ['items' => []], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items']]);
    }

    public function test_store_fails_with_invalid_item_data(): void
    {
        $items = [
            ['product_name' => '', 'quantity' => 0, 'unit_price' => -1],
        ];

        $response = $this->postJson('/api/orders', ['items' => $items], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_store_fails_with_missing_item_fields(): void
    {
        $items = [
            ['product_name' => 'Widget'],
        ];

        $response = $this->postJson('/api/orders', ['items' => $items], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_store_without_authentication_returns_401(): void
    {
        $items = [
            ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 10.00],
        ];

        $response = $this->postJson('/api/orders', ['items' => $items]);

        $response->assertStatus(401);
    }

    public function test_show_returns_order_with_items_and_payment(): void
    {
        $order = $this->createOrder($this->user);
        $this->createPaymentForOrder($order);

        $response = $this->getJson("/api/orders/{$order->id}", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'status',
                    'total_amount',
                    'items',
                    'payment',
                ],
            ])
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.user_id', $this->user->id);
    }

    private function createPaymentForOrder(Order $order): Payment
    {
        return Payment::factory()
            ->forOrder($order)
            ->create();
    }

    public function test_show_returns_404_for_non_existent_order(): void
    {
        $nonExistentOrderId = 99999;

        $response = $this->getJson("/api/orders/{$nonExistentOrderId}", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Order not found.']);
    }

    public function test_show_returns_403_when_accessing_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser);

        $response = $this->getJson("/api/orders/{$order->id}", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'You do not have permission to access this order.']);
    }

    public function test_show_without_authentication_returns_401(): void
    {
        $order = $this->createOrder($this->user);

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(401);
    }

    public function test_update_successfully_updates_order_items(): void
    {
        $order = $this->createOrder($this->user);

        $newItems = [
            ['product_name' => 'Updated Widget', 'quantity' => 5, 'unit_price' => 15.00],
        ];

        $response = $this->putJson("/api/orders/{$order->id}", [
            'items' => $newItems,
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // 5 * 15.00 = 75.00
        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '75.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'Updated Widget');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Updated Widget',
            'quantity' => 5,
        ]);
    }

    public function test_update_successfully_updates_items_and_recalculates_total(): void
    {
        $order = $this->createOrder($this->user);

        $newItems = [
            ['product_name' => 'New Widget', 'quantity' => 3, 'unit_price' => 20.00],
        ];

        $response = $this->putJson("/api/orders/{$order->id}", [
            'items' => $newItems,
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // 3 * 20.00 = 60.00
        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '60.00')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'New Widget');
    }

    public function test_update_returns_404_for_non_existent_order(): void
    {
        $nonExistentOrderId = 99999;

        $response = $this->putJson("/api/orders/{$nonExistentOrderId}", [
            'items' => [
                ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 10.00],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Order not found.']);
    }

    public function test_update_returns_403_when_updating_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'items' => [
                ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 10.00],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'You do not have permission to update this order.']);
    }

    public function test_update_fails_with_missing_items(): void
    {
        $order = $this->createOrder($this->user);

        $response = $this->putJson("/api/orders/{$order->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items']]);
    }

    public function test_update_fails_with_invalid_items_data(): void
    {
        $order = $this->createOrder($this->user);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'items' => [
                ['product_name' => '', 'quantity' => 0, 'unit_price' => -1],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_update_without_authentication_returns_401(): void
    {
        $order = $this->createOrder($this->user);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(401);
    }

    public function test_destroy_successfully_deletes_order(): void
    {
        $order = $this->createOrder($this->user);
        $orderId = $order->id;

        $response = $this->deleteJson("/api/orders/{$orderId}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
    }

    public function test_destroy_returns_404_for_non_existent_order(): void
    {
        $nonExistentOrderId = 99999;

        $response = $this->deleteJson("/api/orders/{$nonExistentOrderId}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Order not found.']);
    }

    public function test_destroy_returns_403_when_deleting_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser);

        $response = $this->deleteJson("/api/orders/{$order->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'You do not have permission to delete this order.']);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_destroy_returns_422_when_order_has_associated_payments(): void
    {
        $order = $this->createOrder($this->user);
        $this->createPaymentForOrder($order);

        $response = $this->deleteJson("/api/orders/{$order->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Cannot delete order with associated payments.']);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_destroy_without_authentication_returns_401(): void
    {
        $order = $this->createOrder($this->user);

        $response = $this->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_confirm_their_own_pending_order(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Pending);

        $response = $this->patchJson("/api/orders/{$order->id}/confirm", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'status',
                    'total_amount',
                ],
            ])
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_confirming_non_existent_order_returns_404(): void
    {
        $nonExistentOrderId = 99999;

        $response = $this->patchJson("/api/orders/{$nonExistentOrderId}/confirm", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Order not found.',
            ]);
    }

    public function test_user_cannot_confirm_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser, OrderStatus::Pending);

        $response = $this->patchJson("/api/orders/{$order->id}/confirm", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'You do not have permission to confirm this order.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_confirming_already_confirmed_order_returns_422(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Confirmed);

        $response = $this->patchJson("/api/orders/{$order->id}/confirm", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Order cannot be confirmed because it is already confirmed or cancelled.',
            ]);
    }

    public function test_confirming_cancelled_order_returns_422(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Cancelled);

        $response = $this->patchJson("/api/orders/{$order->id}/confirm", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Order cannot be confirmed because it is already confirmed or cancelled.',
            ]);
    }

    public function test_confirm_order_without_authentication_returns_401(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Pending);

        $response = $this->patchJson("/api/orders/{$order->id}/confirm");

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_cancel_their_own_pending_order(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Pending);

        $response = $this->patchJson("/api/orders/{$order->id}/cancel", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'status',
                    'total_amount',
                ],
            ])
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancelling_non_existent_order_returns_404(): void
    {
        $nonExistentOrderId = 99999;

        $response = $this->patchJson("/api/orders/{$nonExistentOrderId}/cancel", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Order not found.',
            ]);
    }

    public function test_user_cannot_cancel_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = $this->createOrder($otherUser, OrderStatus::Pending);

        $response = $this->patchJson("/api/orders/{$order->id}/cancel", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'You do not have permission to cancel this order.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_cancelling_already_cancelled_order_returns_422(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Cancelled);

        $response = $this->patchJson("/api/orders/{$order->id}/cancel", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Order cannot be cancelled because it is already cancelled or has associated payments.',
            ]);
    }

    public function test_cancelling_order_with_associated_payments_returns_422(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Pending);
        $this->createPaymentForOrder($order);

        $response = $this->patchJson("/api/orders/{$order->id}/cancel", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Order cannot be cancelled because it is already cancelled or has associated payments.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_cancel_order_without_authentication_returns_401(): void
    {
        $order = $this->createOrder($this->user, OrderStatus::Pending);

        $response = $this->patchJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(401);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
    }
}

