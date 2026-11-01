# Razorpay payment practice (PHP + MySQL)

## Setup (XAMPP)
1. Copy this folder to `htdocs/razorpay_app/`.
2. phpMyAdmin > `demo_pagination` > SQL tab > paste `sql/schema.sql` > Go.
   (Existing rows in `payment` must be deleted first because every payment now belongs to a user. Duplicate usernames in `users` must be removed first because `username` becomes unique.)
3. Copy `config.example.php` to `config.php`, then add your Razorpay test key ID and secret. `config.php` is ignored by Git and stays on your computer.
4. Open http://localhost/razorpay_app/ and register, log in, pay.

## Files
| File | Job |
|---|---|
| includes/bootstrap.php | session, database connection, authentication, CSRF, flash messages, and response helpers |
| includes/layout.php | shared page header, navigation, styles, and footer |
| register.php / login.php / logout.php | accounts (password_hash, CSRF, session regeneration, login brake) |
| dashboard.php | shows paid / failed / locked state, the Pay button, and full attempt history |
| create_order.php | server creates the Razorpay order (price comes from config, not the browser) |
| log_attempt.php | browser reports "failed" or "closed popup"; server double-checks with Razorpay |
| verify.php | signature check, then fetch payment from Razorpay, compare amount/order, then mark success |
| webhook.php | Razorpay tells the server about payments even if the user closed the tab |
| includes/payments.php | ALL status rules in one place (`payment_transition`) |

## Status values in `payment.status`
`created` > `success` | `failed` | `cancelled` | `expired` | `duplicate`
success/duplicate are final and can never be downgraded.

## Your three conditions
1. Success -> dashboard shows "You have already made the payment", no Pay button, and create_order.php refuses.
2. Failure -> red message with the reason, Pay button comes back.
3. Multiple failures -> history table lists every attempt; "View JSON" shows the stored response for each.

## Extra practice conditions included
- Lockout after 3 failures within 15 minutes (config.php `max_failed_attempts`, `lockout_minutes`).
- Popup closed without paying -> `cancelled` (not counted as a failure).
- Unfinished orders older than 30 minutes -> `expired`.
- Forged/invalid signature -> `failed` with `SIGNATURE_MISMATCH`.
- Amount/order mismatch with Razorpay's record -> `failed` with `GATEWAY_MISMATCH`.
- Authorized-but-not-captured payment -> captured automatically.
- Paying twice (two tabs) -> second one stored as `duplicate`, flagged for refund.
- Another user's order id can never be read or changed by you.
- Webhook with signature check and idempotent handling.

## Test it in Razorpay test mode
- Success card: 4111 1111 1111 1111, any future expiry, any CVV, OTP step: choose Success.
- Failure: in the test popup choose the "Failure" option on the bank page (or UPI `failure@razorpay`).
- UPI success: `success@razorpay`.

## Webhook on localhost (optional)
Expose localhost (ngrok / cloudflared), add `https://<tunnel>/razorpay_app/webhook.php` in Dashboard > Settings > Webhooks,
events `payment.captured`, `payment.failed`, `order.paid`, and put the secret in `config.php` `webhook_secret`.
