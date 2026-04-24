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

## Current Implementation
- `LibreFunnels\Payments\Payment_Adapter_Interface` defines the adapter contract for capability checks, offer charging, refunds, and fallback behavior.
- `LibreFunnels\Payments\Adapter_Registry` resolves adapters from the WooCommerce order payment method and exposes the `librefunnels_payment_adapters` filter for future gateway integrations.
- `LibreFunnels\Payments\Fallback_Adapter` is the default for unknown gateways. It reports no one-click support and requires WooCommerce checkout confirmation instead of attempting a risky post-purchase charge.
- `LibreFunnels\Payments\Mock_Adapter` supports deterministic unit tests for one-click success, failure, and refund behavior without depending on a live gateway.
- Public upsell, downsell, and cross-sell steps now show whether the offer can be one-click charged or needs secure checkout confirmation. Current production behavior remains conservative for unsupported gateways.

## Next Gateway Work
1. Create child orders through WooCommerce CRUD APIs for accepted post-purchase offers.
2. Record adapter charge attempts and failures as local analytics events.
3. Add WooPayments and Stripe adapters that only enable one-click when a reusable/tokenized payment method is available.
4. Add explicit recovery screens for failed post-purchase charges so the original order stays unchanged.

## First-Class Gateway Targets
Plan first-class adapters for:
- WooPayments
- WooCommerce Stripe Payment Gateway
- PayPal Payments
- COD
- BACS
- Mock gateway for tests

Community adapters can add Razorpay, PayU, Mollie, Square, Authorize.net, and others.
