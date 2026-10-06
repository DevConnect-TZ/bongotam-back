# Dynamic Payment Gateway Switching

Admins can change the active payment gateway between `sonicpesa` and `mobilipa` at runtime:
- Settings stored in `settings` table key `active_payment_gateway`.
- Managed via `POST /api/admin/gateway`.
- Automatically selects driver in `PaymentController`.
