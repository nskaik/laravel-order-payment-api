<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function findByIdWithRelations(int $id, array $relations = []): ?Payment
    {
        return Payment::with($relations)->find($id);
    }

    public function findByOrderId(int $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)->first();
    }

    public function getPaginatedForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Payment::with('order')
            ->whereHas('order', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function orderHasPayment(Order $order): bool
    {
        return $order->payment()->exists();
    }
}

