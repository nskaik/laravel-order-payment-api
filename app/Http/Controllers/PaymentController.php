<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @group Payments
 *
 * @authenticated
 *
 * APIs for managing payments.
 * All endpoints require JWT authentication.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService
    ) {}

    /**
     * List all payments
     *
     * Get a paginated list of payments for the authenticated user.
     *
     * @queryParam per_page integer Number of items per page. Default: 15. Example: 10
     *
     * @response 200 scenario="Success" {
     *   "data": [
     *     {
     *       "id": 1,
     *       "status": "successful",
     *       "payment_method": "credit_card",
     *       "amount": "99.99",
     *       "transaction_id": "CC_ABC123_1234567890",
     *       "created_at": "2026-02-04T12:00:00.000000Z",
     *       "updated_at": "2026-02-04T12:00:00.000000Z"
     *     }
     *   ],
     *   "links": {},
     *   "meta": {}
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = JWTAuth::parseToken()->authenticate();
        $perPage = (int) $request->input('per_page', 15);
        $payments = $this->paymentService->getPaymentsForUser($user->id, $perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Get a specific payment
     *
     * Retrieve a specific payment by ID.
     *
     * @urlParam id integer required The ID of the payment. Example: 1
     *
     * @response 200 scenario="Success" {
     *   "data": {
     *     "id": 1,
     *     "status": "successful",
     *     "payment_method": "credit_card",
     *     "amount": "99.99",
     *     "transaction_id": "CC_ABC123_1234567890",
     *     "created_at": "2026-02-04T12:00:00.000000Z",
     *     "updated_at": "2026-02-04T12:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Forbidden" {
     *   "error": "You do not have permission to access this payment."
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": "Payment not found."
     * }
     */
    public function show(int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $payment = $this->paymentService->getPaymentById($id);

        if (!$payment) {
            return response()->json([
                'error' => 'Payment not found.',
            ], 404);
        }

        if ($payment->order->user_id !== $user->id) {
            return response()->json([
                'error' => 'You do not have permission to access this payment.',
            ], 403);
        }

        return (new PaymentResource($payment))->response();
    }

    /**
     * Process a payment
     *
     * Process a payment for an order. The order must have status "confirmed".
     *
     * @bodyParam order_id integer required The ID of the order to pay for. Example: 1
     * @bodyParam payment_method string required The payment method. Must be one of: credit_card, debit_card, paypal, bank_transfer. Example: credit_card
     * @bodyParam card_number string Card number (required for credit_card/debit_card). Use ending in 0000 for guaranteed success, 9999 for guaranteed failure. Example: 4111111111110000
     * @bodyParam paypal_email string PayPal email (required for paypal). Use containing "success" for guaranteed success, "fail" for guaranteed failure. Example: user@success.com
     *
     * @response 201 scenario="Payment successful" {
     *   "data": {
     *     "id": 1,
     *     "status": "successful",
     *     "payment_method": "credit_card",
     *     "amount": "99.99",
     *     "transaction_id": "CC_ABC123_1234567890",
     *     "created_at": "2026-02-04T12:00:00.000000Z",
     *     "updated_at": "2026-02-04T12:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "error": "Order not found."
     * }
     *
     * @response 422 scenario="Order not confirmed" {
     *   "error": "Payments can only be processed for confirmed orders."
     * }
     *
     * @response 422 scenario="Order already has payment" {
     *   "error": "This order already has a payment."
     * }
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $validated = $request->validated();

        $order = $this->orderService->getOrderById($validated['order_id']);

        if (!$order) {
            return response()->json([
                'error' => 'Order not found.',
            ], 404);
        }

        if ($order->user_id !== $user->id) {
            return response()->json([
                'error' => 'You do not have permission to pay for this order.',
            ], 403);
        }

        try {
            $payment = $this->paymentService->processPayment(
                $order,
                $validated['payment_method'],
                $validated
            );

            return (new PaymentResource($payment))
                ->response()
                ->setStatusCode(201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get payment for a specific order
     *
     * Retrieve the payment associated with a specific order.
     *
     * @urlParam id integer required The ID of the order. Example: 1
     *
     * @response 200 scenario="Success" {
     *   "data": {
     *     "id": 1,
     *     "status": "successful",
     *     "payment_method": "credit_card",
     *     "amount": "99.99",
     *     "transaction_id": "CC_ABC123_1234567890",
     *     "created_at": "2026-02-04T12:00:00.000000Z",
     *     "updated_at": "2026-02-04T12:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Forbidden" {
     *   "error": "You do not have permission to access this order's payment."
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "error": "Order not found."
     * }
     *
     * @response 404 scenario="Payment not found" {
     *   "error": "No payment found for this order."
     * }
     */
    public function showForOrder(int $id): JsonResponse
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
                'error' => 'You do not have permission to access this order\'s payment.',
            ], 403);
        }

        $payment = $this->paymentService->getPaymentByOrderId($id);

        if (!$payment) {
            return response()->json([
                'error' => 'No payment found for this order.',
            ], 404);
        }

        return (new PaymentResource($payment))->response();
    }
}

