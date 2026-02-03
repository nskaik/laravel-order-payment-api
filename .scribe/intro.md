# Introduction

RESTful API for managing orders and payments with JWT authentication.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

    Welcome to the Laravel Order Payment API documentation.

    This API provides endpoints for user authentication, order management, and payment processing.

    ## Authentication
    This API uses **JWT (JSON Web Token)** authentication. To access protected endpoints, you must include a valid JWT token in the `Authorization` header as a Bearer token.

    To obtain a token:
    1. Register a new account using the `/api/auth/register` endpoint, or
    2. Login with existing credentials using the `/api/auth/login` endpoint

    Both endpoints will return a JWT token that you can use for subsequent authenticated requests.

    <aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
    You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>

