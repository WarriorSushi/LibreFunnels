# Testing Strategy

## Unit Tests
- Rule engine
- Funnel routing
  - Start step resolution
  - Step ownership validation
  - Graph node and edge validation
  - Accept/reject/fallback route resolution
  - Unknown route failures
- Offer eligibility
- Order bump metadata normalization
- Discount calculations
- A/B assignment
- Analytics attribution
- Import/export package normalization
- Checkout product assignment sanitization
- Checkout coupon sanitization
- Checkout field rule sanitization

## Integration Tests
- Shortcode rendering
- Dynamic block rendering
- Theme template override loading
- Funnel export from CPT/meta records
- Funnel import with nonces and capabilities
- WooCommerce checkout
- Checkout step product cart preparation
- Checkout step coupon application
- Scoped checkout field customization
- Global checkout redirect opt-in behavior
- Protected WooCommerce checkout endpoints are not redirected
- Order bump rendering inside checkout
- Selected order bump cart sync and removal
- Order bump discounted cart item pricing
- Order bump order line item attribution
- Pre-checkout offer package normalization
- Pre-checkout offer accept/reject routing integration
- Pre-checkout offer cart item attribution
- HPOS on/off
- Order bump order creation
- Upsell/downsell order mutation
- Coupons
- Taxes/shipping
- Product variations

## E2E Tests
- Funnel creation
- Canvas editing
- Checkout flow
- Multiple order bumps
- Pre-checkout offer accept/reject flow
- Upsell accept/reject
- Downsell accept/reject
- Unknown gateway fallback
- Thank-you page

## Security Tests
- REST permissions
- Admin capabilities
- Nonces
- SQL preparation
- Sanitization
- Escaping
- Privacy consent
