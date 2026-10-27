# Razorpay Payment Flow

This project demonstrates a Razorpay Checkout payment using PHP.

## Current payment flow

1. `index.php` creates a Razorpay order and stores its order ID in the PHP session.
2. The customer starts checkout. If checkout reports a failure or is dismissed, the page offers a retry for that order.
3. On checkout success, the browser posts the payment ID, order ID, and signature to `verify.php`.
4. `verify.php` checks that the submitted order ID matches the order in the session and verifies the Razorpay payment signature.
5. After successful verification, the session is marked as paid. Further visits to `index.php` in that same session show a completed message instead of opening checkout.

## Session limitation

The paid flag is stored in a PHP session, so it only applies to that browser session. It is not a durable record of a purchase: clearing cookies, using another browser or device, or losing the session can allow another order to be created. A PHP session is suitable for a basic demo, but not as the only protection against duplicate payments in a real application.

## Recommended production approach

Use persistent storage to associate each purchase with a stable application order or user ID. Store at least:

- The application order or purchase ID
- The Razorpay order ID
- The payment ID, when available
- A payment status such as `pending`, `paid`, or `failed`

Before creating or retrying a payment, load the purchase record and prevent checkout when it is already paid. Verify payment responses on the server and update the record only after verification. Configure a Razorpay webhook and verify its webhook signature so payment status can also be updated if the customer closes the page before returning to the site. Make updates idempotent so duplicate callbacks or webhook deliveries cannot mark or process a purchase more than once.

Razorpay can provide payment/order details when your server has the corresponding Razorpay ID, but it does not replace the application's mapping between a customer or purchase and that ID. A database-backed purchase record is needed to enforce the paid state across sessions and devices.
