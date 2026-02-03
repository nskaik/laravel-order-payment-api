# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_JWT_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Obtain your JWT token by registering or logging in via the Authentication endpoints. Include the token in the Authorization header as: <code>Bearer {token}</code>
