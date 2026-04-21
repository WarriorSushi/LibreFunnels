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
- Discount calculations
- A/B assignment
- Analytics attribution
- Import/export package normalization
- Checkout product assignment sanitization

## Integration Tests
- Shortcode rendering
- Dynamic block rendering
- Theme template override loading
- Funnel export from CPT/meta records
- Funnel import with nonces and capabilities
- WooCommerce checkout
- Checkout step product cart preparation
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
- Pre-checkout offer
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
