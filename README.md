# Laravel Order Payment API

A RESTful API built with Laravel for managing orders and processing payments through multiple payment gateways. This application provides a robust backend solution with JWT-based authentication, comprehensive order management, and an extensible payment processing system.

## Features

- **JWT-based Authentication** — Secure user registration, login, logout, and token refresh functionality
- **Order Management** — Full CRUD operations for creating, reading, updating, and deleting orders
- **Payment Processing** — Extensible gateway system supporting multiple payment providers:
  - Credit Card payments
  - PayPal payments
  - Easily extendable for additional gateways (Stripe, Bank Transfer, etc.)
- **RESTful API Design** — Clean, consistent API endpoints following REST conventions
- **Comprehensive API Documentation** — Auto-generated documentation using Scribe

## Requirements

- **PHP** ^8.2
- **Laravel** ^12.0
- **Composer** 2.x
- **Node.js** and npm (for frontend assets)
- **Database** — SQLite (default), MySQL, PostgreSQL, or any Laravel-supported database
- **PHP Extensions:**
  - BCMath

## Installation

### Quick Setup

Clone the repository and run the setup command:

```bash
git clone <repository-url>
cd laravel-order-payment-api
composer setup
```

The `composer setup` command will automatically:
1. Install PHP dependencies
2. Copy `.env.example` to `.env` (if not exists)
3. Generate application key
4. Run database migrations
5. Install npm dependencies
6. Build frontend assets

### Manual Setup

If you prefer to set up manually:

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret

# Run database migrations
php artisan migrate

# Install and build frontend assets
npm install
npm run build
```

## Configuration

Configure the following environment variables in your `.env` file:

### Database

```env
DB_CONNECTION=sqlite
# For MySQL/PostgreSQL:
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

### JWT Authentication

```env
JWT_SECRET=your-jwt-secret-key
```

### Payment Gateways

```env
# Credit Card Gateway
CREDIT_CARD_API_KEY=
CREDIT_CARD_SECRET=
CREDIT_CARD_ENDPOINT=https://api.creditcard-gateway.com
CREDIT_CARD_TIMEOUT=30

# PayPal Gateway
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYPAL_MODE=sandbox
PAYPAL_ENDPOINT=https://api.sandbox.paypal.com
```

## Running the Application

### Development Server

Start the development server with all services using:

```bash
composer dev
```

This command runs concurrently:
- Laravel development server (`php artisan serve`)
- Queue worker (`php artisan queue:listen`)
- Log viewer (`php artisan pail`)
- Vite development server (`npm run dev`)

### Individual Commands

Alternatively, run services individually:

```bash
# Start the Laravel server
php artisan serve

# Start the queue worker (in a separate terminal)
php artisan queue:listen

# Start Vite for frontend development (in a separate terminal)
npm run dev
```

The application will be available at `http://localhost:8000`.

## API Documentation

API documentation is automatically generated using [Scribe](https://scribe.knuckles.wtf/).

### Accessing Documentation

- **HTML Documentation:** [http://localhost:8000/docs](http://localhost:8000/docs)
- **OpenAPI Specification:** `public/docs/openapi.yaml`
- **Postman Collection:** `public/docs/collection.json`

### Regenerating Documentation

After making changes to API endpoints, regenerate the documentation:

```bash
php artisan scribe:generate
```

## Testing

Run the test suite using:

```bash
composer test
```

Or directly with Artisan:

```bash
php artisan test
```

## Payment Gateway Integration

The payment system is designed with extensibility in mind, using the Strategy Pattern to support multiple payment providers.

For detailed instructions on adding new payment gateways, see the [Payment Gateway Integration Guide](docs/PAYMENT_GATEWAY_INTEGRATION.md).

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
