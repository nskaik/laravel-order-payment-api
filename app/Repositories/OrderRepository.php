<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    /**
     * Create a new order with items.
     */
    public function createWithItems(array $orderData, array $items): Order
    {
        return DB::transaction(function () use ($orderData, $items) {
            $order = Order::create($orderData);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            return $order->load('items');
        });
    }

    /**
     * Find an order by ID.
     */
    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    /**
     * Find an order by ID with relationships.
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Order
    {
        return Order::with($relations)->find($id);
    }

    /**
     * Get paginated orders for a user.
     */
    public function getPaginatedForUser(int $userId, ?OrderStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::with(['items', 'payment'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Update an order.
     */
    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->fresh();
    }

    /**
     * Update an order with items.
     */
    public function updateWithItems(Order $order, array $orderData, ?array $items = null): Order
    {
        return DB::transaction(function () use ($order, $orderData, $items) {
            $order->update($orderData);

            if ($items !== null) {
                // Delete existing items and create new ones
                $order->items()->delete();

                foreach ($items as $item) {
                    $order->items()->create($item);
                }
            }

            return $order->load('items', 'payment');
        });
    }

    /**
     * Delete an order.
     */
    public function delete(Order $order): bool
    {
        return $order->delete();
    }

    /**
     * Check if an order has associated payments.
     */
    public function hasPayments(Order $order): bool
    {
        return $order->payment()->exists();
    }
}

