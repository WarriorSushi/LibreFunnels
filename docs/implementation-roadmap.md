# Implementation Roadmap

## Phase 0: Foundation
- Plugin scaffold
- Composer/PHPCS setup
- WordPress.org readme
- WooCommerce dependency checks
- HPOS compatibility baseline
- Admin menu shell

## Phase 1: Funnel Core
- Funnel data model: private REST-enabled `librefunnels_funnel` CPT
- Step model: private REST-enabled `librefunnels_step` CPT
- Registered funnel/step metadata schema
- Basic routing: start-step and next-step resolver with graph validation
- Shortcode rendering: `[librefunnels_funnel]` and `[librefunnels_step]`
- Thank-you step: default plugin template with theme override support
- Block rendering: dynamic server-rendered funnel and step blocks
- Import/export foundation: versioned JSON export and import package validation

## Phase 2: Checkout Core
- Funnel checkout page: checkout step rendering through WooCommerce checkout
- Product assignment: checkout step product metadata and cart preparation
- Coupon support: checkout step coupon metadata and cart application
- Field customization: scoped checkout field labels, placeholders, required flags, and hiding
- Quantity/product option support: quantity, variation ID, and variation attributes in checkout product assignments
- Checkout takeover/global checkout: disabled-by-default redirect foundation using configured funnel and step page ID

## Phase 3: Offers
- Order bump metadata and eligibility foundation
- Order bump checkout rendering and selected-bump cart sync
- Multiple bumps metadata and selection support
- Order bump fixed and percentage discount calculation
- Pre-checkout offers
- Cross-sells
- Upsells
- Downsells
- Offer discounts
- Accept/reject routing

## Phase 4: Rules and Canvas
- Rule engine
- Conditional offers
- Visual canvas builder
- Smart routing
- Validation UI

## Phase 5: Payments
- Gateway adapter API
- True one-click support for compatible gateways
- Accept-and-confirm fallback for unknown gateways
- Separate/child orders
- Refund-safe order handling

## Phase 6: Analytics and Testing
- Event tracking
- Revenue attribution
- A/B testing
- Step analytics
- Dashboard reports

## Phase 7: Templates and Integrations
- Instant layouts
- Template library
- Gutenberg blocks
- Shortcodes
- Page-builder compatibility
- Pixel integrations with consent

## Phase 8: WordPress.org Release
- Security audit
- Performance audit
- Accessibility audit
- Translation pass
- Readme/screenshots
- ZIP submission
- SVN release after approval
