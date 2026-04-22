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
- Order bump line item attribution metadata
- Pre-checkout offer metadata, rendering, accept/reject routing, and cart sync
- Customer-scoped offer accept/reject session state
- Cross-sells
- Upsells
- Downsells
- Offer discounts
- Accept/reject routing

## Phase 4: Rules and Canvas
- Pure rule evaluator for conditional routing facts
- WooCommerce cart/customer fact collector
- Conditional graph route resolution with edge rules
- Conditional offers
- Polished admin workspace shell
- First React visual canvas builder
  - WordPress-native admin enqueueing
  - `@wordpress/scripts` build pipeline
  - Funnel list and create action
  - Canvas nodes backed by funnel step records
  - Route edges for `next`, `accept`, `reject`, `conditional`, and `fallback`
  - Right-side inspector for funnel, step, and route editing
  - Inline validation for missing start step, page assignment, broken routes, and conditional rule gaps
- Smart routing
- Validation UI

Phase 4 is now the active product track. Backend work should continue where the canvas needs it, especially safer REST edit flows, page search, route creation controls, drag positioning, and store-owner-friendly rule builders.

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
