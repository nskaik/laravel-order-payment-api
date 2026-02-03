<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * @group User
 *
 * APIs for managing the authenticated user's information.
 */
class UserController extends Controller
{
    /**
     * Get authenticated user
     *
     * Retrieve the currently authenticated user's profile information.
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {
     *   "id": 1,
     *   "name": "John Doe",
     *   "email": "john@example.com",
     *   "email_verified_at": null,
     *   "created_at": "2024-02-03T12:00:00.000000Z",
     *   "updated_at": "2024-02-03T12:00:00.000000Z"
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function show(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json(new UserResource($user));
    }
}

