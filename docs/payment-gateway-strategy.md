# Payment Gateway Strategy

## Goal
Work with whatever WooCommerce gateway the store owner uses, including regional gateways such as Razorpay, PayU, Stripe, PayPal, WooPayments, bank transfer, COD, and others.

## Important Reality
WooCommerce gateways do not all expose a universal API for charging a customer again after checkout. Therefore true one-click post-purchase upsells cannot be guaranteed for every gateway.

## Smart Universal Policy
- Normal checkout: use any enabled WooCommerce gateway.
- Order bumps: work through normal checkout cart/order flow.
- Pre-checkout offers: work through normal checkout flow.
- Post-purchase upsell/downsell:
  - If gateway supports reusable payment/tokenized charge, use true one-click.
  - If gateway does not support it, use accept-and-confirm flow.
  - If gateway capability is unknown, default to accept-and-confirm.

## Gateway Adapter API
Create a payment adapter interface for:
- Capability detection
- True one-click charge support
- Offer order creation
- Payment confirmation fallback
- Refund/void support where available

## First-Class Gateway Targets
Plan first-class adapters for:
- WooPayments
- WooCommerce Stripe Payment Gateway
- PayPal Payments
- COD
- BACS
- Mock gateway for tests

Community adapters can add Razorpay, PayU, Mollie, Square, Authorize.net, and others.
