<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

/**
 * @group Authentication
 *
 * APIs for user authentication including registration and login.
 * These endpoints do not require authentication.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Register a new user
     *
     * Create a new user account and receive a JWT token for authentication.
     *
     * @unauthenticated
     *
     * @bodyParam name string required The user's full name. Must not exceed 255 characters. Example: John Doe
     * @bodyParam email string required The user's email address. Must be unique and valid. Example: john@example.com
     * @bodyParam password string required The user's password. Must be at least 8 characters. Example: password123
     * @bodyParam password_confirmation string required Password confirmation. Must match the password field. Example: password123
     *
     * @response 201 scenario="Successful registration" {
     *   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYXBpL2F1dGgvcmVnaXN0ZXIiLCJpYXQiOjE3MDcwMDAwMDAsImV4cCI6MTcwNzAwMzYwMCwibmJmIjoxNzA3MDAwMDAwLCJqdGkiOiJhYmNkZWYxMjM0NTYiLCJzdWIiOiIxIiwicHJ2IjoiMjNiZDVjODk0OWY2MDBhZGIzOWU3MDFjNDAwODcyZGI3YTU5NzZmNyJ9.example_signature",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com"
     *   }
     * }
     *
     * @response 422 scenario="Validation error" {
     *   "errors": {
     *     "email": ["The email has already been taken."],
     *     "password": ["The password field confirmation does not match."]
     *   }
     * }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return (new AuthResource($result->user, $result->token))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Login user
     *
     * Authenticate a user with email and password to receive a JWT token.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password. Example: password123
     *
     * @response 200 scenario="Successful login" {
     *   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYXBpL2F1dGgvbG9naW4iLCJpYXQiOjE3MDcwMDAwMDAsImV4cCI6MTcwNzAwMzYwMCwibmJmIjoxNzA3MDAwMDAwLCJqdGkiOiJhYmNkZWYxMjM0NTYiLCJzdWIiOiIxIiwicHJ2IjoiMjNiZDVjODk0OWY2MDBhZGIzOWU3MDFjNDAwODcyZGI3YTU5NzZmNyJ9.example_signature",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com"
     *   }
     * }
     *
     * @response 401 scenario="Invalid credentials" {
     *   "error": "Invalid credentials"
     * }
     *
     * @response 422 scenario="Validation error" {
     *   "errors": {
     *     "email": ["The email field is required."],
     *     "password": ["The password field is required."]
     *   }
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result === null) {
            return response()->json([
                'error' => 'Invalid credentials',
            ], 401);
        }

        return (new AuthResource($result->user, $result->token))
            ->response()
            ->setStatusCode(200);
    }
}

