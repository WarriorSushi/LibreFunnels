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
- Rule evaluation
  - All/any groups
  - Cart product facts
  - Cart subtotal thresholds
  - Customer logged-in facts
  - WooCommerce fact collection
- Conditional graph route rule matching and fallback
- A/B assignment
- Analytics attribution
- Import/export package normalization
- Checkout product assignment sanitization
- Checkout coupon sanitization
- Checkout field rule sanitization
- Multiple checkout product and order bump metadata sanitization

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
- Offer accept/reject session state
- HPOS on/off
- Order bump order creation
- Upsell/downsell order mutation
- Coupons
- Taxes/shipping
- Product variations
- React admin assets enqueue only on the LibreFunnels admin screen
- Built canvas assets load with the PHP fallback still present for no-build installs
- Canvas REST workspace loads funnels, steps, and pages behind `manage_woocommerce`
- Canvas REST graph save updates graph and start-step meta together
- Canvas page creation inserts `[librefunnels_step]` and assigns the page to the step
- Canvas product search returns WooCommerce products through supported product APIs and keeps assigned products visible
- Canvas step update persists checkout products, order bumps, and primary offer metadata through sanitizers
- Local analytics events table is installed on activation/schema-version changes
- Offer impression and accept/reject hooks write local events

## E2E Tests
- Funnel creation
- Admin workspace loading
- Canvas editing
- Canvas creates a funnel, adds steps, connects a route, and saves graph meta
- Canvas shows validation for missing start step, missing page ID, broken route target, and invalid conditional route
- Canvas drags a node and persists the new position
- Canvas creates and assigns a draft page without exposing raw page IDs
- Canvas builds a conditional route without editing JSON
- Canvas selects products for checkout, order bump, offer, and product-based conditional rules
- Docker admin canvas smoke logs in, verifies the React canvas replaced the PHP fallback, creates a funnel page, saves multiple checkout products, and saves an order bump
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

## Local Docker Harness
The repository includes a Docker Compose WordPress rig for manual and browser testing:
- `compose.yaml` starts MariaDB, WordPress, and WP-CLI.
- `tools/docker/init-wordpress.ps1` installs WordPress, installs and activates WooCommerce, activates LibreFunnels, and seeds sample products.
- `npm run test:e2e:canvas` from `librefunnels/` runs the Playwright smoke against `http://localhost:8080` by default. Override with `LIBREFUNNELS_WP_BASE_URL`, `LIBREFUNNELS_WP_ADMIN_USER`, and `LIBREFUNNELS_WP_ADMIN_PASSWORD` when needed.
- Privacy consent

## Local Checks
Run these before commits that touch PHP, build tooling, or the admin app:
- `composer lint`
- `composer test`
- `npm run build`
- `npm run test:e2e:canvas` when Docker WordPress is running and the admin app changes

The first Playwright smoke confirms the React app mounts on the LibreFunnels admin page and replaces the PHP fallback. Expand it next for drag persistence, route editing, rule builder behavior, and offer-step product controls.
