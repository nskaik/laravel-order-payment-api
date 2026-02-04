<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @group Orders
 *
 * @authenticated
 *
 * APIs for managing orders.
 * All endpoints require JWT authentication.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * List all orders
     *
     * Get a paginated list of orders for the authenticated user.
     *
     * @queryParam status string Optional filter by order status. Must be one of: pending, confirmed, cancelled. Example: pending
     * @queryParam per_page integer Number of items per page. Default: 15. Example: 10
     *
     * @response 200 scenario="Success" {
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "status": "pending",
     *       "total_amount": "99.99",
     *       "items": [],
     *       "payment": null
     *     }
     *   ],
     *   "links": {},
     *   "meta": {}
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = JWTAuth::parseToken()->authenticate();
        $status = null;

        if ($request->has('status')) {
            $status = OrderStatus::tryFrom($request->input('status'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $orders = $this->orderService->getOrdersForUser($user->id, $status, $perPage);

        return OrderResource::collection($orders);
    }

    /**
     * Create a new order
     *
     * Create a new order with the provided items.
     *
     * @bodyParam items object[] required Array of order items.
     * @bodyParam items[].product_name string required The name of the product. Example: M3 Laptop
     * @bodyParam items[].quantity integer required The quantity of the product. Must be at least 1. Example: 2
     * @bodyParam items[].unit_price number required The unit price of the product. Must be at least 0.01. Example: 29.99
     *
     * @response 201 scenario="Order created" {
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "status": "pending",
     *     "total_amount": "59.98",
     *     "items": [
     *       {
     *         "id": 1,
     *         "product_name": "M3 Laptop",
     *         "quantity": 2,
     *         "unit_price": "29.99",
     *         "subtotal": "59.98"
     *       }
     *     ],
     *     "payment": null
     *   }
     * }
     *
     * @response 422 scenario="Validation error" {
     *   "errors": {
     *     "items": ["At least one order item is required."]
     *   }
     * }
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $order = $this->orderService->createOrder($user->id, $request->validated()['items']);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a single order
     *
     * Retrieve a specific order by ID.
     *
     * @urlParam id integer required The ID of the order. Example: 1
     *
     * @response 200 scenario="Success" {
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "status": "pending",
     *     "total_amount": "59.98",
     *     "items": [],
     *     "payment": null
     *   }
     * }
     *
     * @response 403 scenario="Forbidden" {
     *   "error": "You do not have permission to access this order."
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": "Order not found."
     * }
     */
    public function show(int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $order = $this->orderService->getOrderById($id);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found.',
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'error' => 'You do not have permission to access this order.',
            ], 403);
        }

        return (new OrderResource($order))->response();
    }

    /**
     * Update an order
     *
     * Update an existing order's items. The total amount will be recalculated based on the new items.
     * Note: To update order status, use the dedicated confirm or cancel endpoints.
     *
     * @urlParam id integer required The ID of the order. Example: 1
     *
     * @bodyParam items object[] required Array of order items to replace existing items. Example: [{"product_name": "M3 Laptop", "quantity": 3, "unit_price": 29.99}]
     * @bodyParam items[].product_name string required The name of the product. Example: M3 Laptop
     * @bodyParam items[].quantity integer required The quantity of the product. Example: 3
     * @bodyParam items[].unit_price number required The unit price of the product. Example: 29.99
     *
     * @response 200 scenario="Order updated" {
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "status": "pending",
     *     "total_amount": "89.97",
     *     "items": [],
     *     "payment": null
     *   }
     * }
     *
     * @response 403 scenario="Forbidden" {
     *   "error": "You do not have permission to update this order."
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": "Order not found."
     * }
     *
     * @response 422 scenario="Validation error" {
     *   "errors": {
     *     "items": ["Items are required."]
     *   }
     * }
     */
    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $order = $this->orderService->getOrderById($id);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found.',
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'error' => 'You do not have permission to update this order.',
            ], 403);
        }

        $updatedOrder = $this->orderService->updateOrder($order, $request->validated());

        return (new OrderResource($updatedOrder))->response();
    }

    /**
     * Delete an order
     *
     * Delete an order. Orders with associated payments cannot be deleted.
     *
     * @urlParam id integer required The ID of the order. Example: 1
     *
     * @response 204 scenario="Order deleted"
     *
     * @response 403 scenario="Forbidden" {
     *   "error": "You do not have permission to delete this order."
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": "Order not found."
     * }
     *
     * @response 422 scenario="Has payments" {
     *   "error": "Cannot delete order with associated payments."
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $order = $this->orderService->getOrderById($id);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found.',
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'error' => 'You do not have permission to delete this order.',
            ], 403);
        }

        if ($this->orderService->hasPayments($order)) {
            return response()->json([
                'error' => 'Cannot delete order with associated payments.',
            ], 422);
        }

        $this->orderService->deleteOrder($order);

        return response()->json(null, 204);
    }

    /**
     * Confirm an order
     *
     * Update the order status to "confirmed".
     *
     * @urlParam id integer required The ID of the order. Example: 1
     *
     * @response 200 scenario="Order confirmed" {
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "status": "confirmed",
     *     "total_amount": "59.98",
     *     "items": [],
     *     "payment": null
     *   }
     * }
     *
     * @response 403 scenario="Forbidden" {
     *   "error": "You do not have permission to confirm this order."
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": "Order not found."
     * }
     *
     * @response 422 scenario="Invalid status" {
     *   "error": "Order cannot be confirmed because it is already confirmed or cancelled."
     * }
     */
    public function confirm(int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $order = $this->orderService->getOrderById($id);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found.',
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'error' => 'You do not have permission to confirm this order.',
            ], 403);
        }

        if ($order->status !== OrderStatus::Pending) {
            return response()->json([
                'error' => 'Order cannot be confirmed because it is already confirmed or cancelled.',
            ], 422);
        }

        $updatedOrder = $this->orderService->confirmOrder($order);

        return (new OrderResource($updatedOrder))->response();
    }

    /**
     * Cancel an order
     *
     * Update the order status to "cancelled".
     *
     * @urlParam id integer required The ID of the order. Example: 1
     *
     * @response 200 scenario="Order cancelled" {
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "status": "cancelled",
     *     "total_amount": "59.98",
     *     "items": [],
     *     "payment": null
     *   }
     * }
     *
     * @response 403 scenario="Forbidden" {
     *   "error": "You do not have permission to cancel this order."
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": "Order not found."
     * }
     *
     * @response 422 scenario="Invalid status" {
     *   "error": "Order cannot be cancelled because it is already cancelled or has associated payments."
     * }
     */
    public function cancel(int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $order = $this->orderService->getOrderById($id);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found.',
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'error' => 'You do not have permission to cancel this order.',
            ], 403);
        }

        if ($order->status === OrderStatus::Cancelled) {
            return response()->json([
                'error' => 'Order cannot be cancelled because it is already cancelled or has associated payments.',
            ], 422);
        }

        if ($this->orderService->hasPayments($order)) {
            return response()->json([
                'error' => 'Order cannot be cancelled because it is already cancelled or has associated payments.',
            ], 422);
        }

        $updatedOrder = $this->orderService->cancelOrder($order);

        return (new OrderResource($updatedOrder))->response();
    }
}

