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
  - Draggable node positioning saved to graph metadata
  - Atomic `librefunnels/v1` REST endpoints for canvas workspace actions
  - Guided starter path for empty funnels that creates checkout and thank-you steps with a continue route
  - Page search and draft page creation for step assignment
  - Store-owner-friendly conditional rule builder
  - Recoverable step archive and route delete controls
  - WooCommerce product search for checkout, bump, offer, and product-rule assignment
  - Commerce inspector panels for multiple checkout products, multiple order bumps, and primary offer configuration
  - Per-section dirty indicators and save reminders in commerce controls
  - Derived setup progress checklist for missing pages, draft publish status, product readiness, routes, and validation
  - Sectioned LibreFunnels admin IA with WordPress submenus for Dashboard, Funnels, Templates, Analytics, Settings, and Setup
  - Funnel workspace tabs for Overview, Canvas, Steps, Products, Offers, Rules, Analytics, and Settings so the canvas does not carry every product surface
  - Dedicated step planning surface that makes landing, opt-in, checkout, upsell, downsell, thank-you, and custom step creation visible
  - Dedicated offers surface that keeps upsells, downsells, cross-sells, and pre-checkout offers out of the checkout products panel
  - Real site-readiness data on Dashboard and Setup for permalinks, checkout page presence, gateways, currency, and product count
  - Deterministic builder handoff with `funnel_id` and workspace `tab` deep-linking so starter/template flows reopen the correct funnel in the right surface
  - Guided starter panel on Templates and Setup that can preselect an existing WooCommerce checkout product, optionally preselect an offer product, create starter draft pages, and send the user to the Steps tab for page-builder handoff links
  - Public routing facts now include WooCommerce cart, customer, and HPOS-safe order context, with order product and order total conditional rule support
  - Playwright canvas smoke for Docker WordPress admin mount, submenu rendering, guided template starter product preselection, bundled template create/import/export, funnel creation, draft page status and edit/preview handoff, checkout products, order bump saving, imported broken-route recovery, public checkout rendering, public offer reject routing, and public offer accept cart mutation
- Smart routing
- Validation UI

Phase 4 remains the active product track, with the commerce-aware inspector now editing real product and bump arrays, the guided starter flow creating product-aware draft-page funnels, the page assignment flow showing draft/published state plus builder handoff actions, setup progress naming the remaining launch tasks, public routing facts including order context, and the selected funnel opening into a sectioned workspace instead of one crowded canvas. Backend work should continue where the canvas needs it, especially building deeper analytics drilldowns and preparing the payment adapter layer.

## Phase 5: Payments
- Gateway adapter API
- True one-click support for compatible gateways
- Accept-and-confirm fallback for unknown gateways
- Separate/child orders
- Refund-safe order handling

## Phase 6: Analytics and Testing
- Event tracking
  - Local events table
  - Offer impression events
  - Offer accept/reject events
- Revenue attribution from WooCommerce order line items
- Capability-guarded analytics summary REST reads
- React admin summary panel with empty states for selected-funnel revenue and offer activity
- Docker Playwright smoke that completes WooCommerce checkout and verifies `order_revenue` through the summary endpoint
- A/B testing
- Step analytics
- Dashboard reports

## Phase 7: Templates and Integrations
- Instant layouts
- Bundled local template library
- Template-to-funnel creation flow with draft page generation
- JSON funnel import/export with schema validation
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
