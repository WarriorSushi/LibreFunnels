# Architecture

## Core Principle
Use WooCommerce and WordPress APIs wherever possible. Avoid replacing payment, tax, shipping, stock, or order logic unless funnel behavior requires it.

## Main Subsystems
- Plugin bootstrap and dependency checks
- Funnel custom post type and metadata model
- Funnel routing service
- Visual canvas admin app
- Step renderer
- Checkout renderer
- Offer engine
- Rule engine
- Payment adapter layer
- Analytics/event tracker
- A/B test allocator
- Template/import/export system
- Blocks/shortcodes
- Integration hooks

## Data Model
Use custom post types for human-managed content:
- Funnel: `librefunnels_funnel`
- Funnel step: `librefunnels_step`

The first implementation uses private, REST-enabled CPTs rather than public WP admin screens. The React canvas/admin app should be the primary editing surface. Funnel and step records are hidden from the default admin menu until a purpose-built UI exists.

Initial registered metadata:
- Funnel graph: `_librefunnels_graph`
- Funnel start step: `_librefunnels_start_step_id`
- Step owner funnel ID: `_librefunnels_funnel_id`
- Step type: `_librefunnels_step_type`
- Step order: `_librefunnels_step_order`
- Step template slug: `_librefunnels_template_slug`

Initial step types:
- `landing`
- `optin`
- `checkout`
- `pre_checkout_offer`
- `order_bump`
- `upsell`
- `downsell`
- `cross_sell`
- `thank_you`
- `custom`

## Routing Core
The routing layer resolves funnel navigation without mutating orders, carts, or checkout state.

Initial route labels:
- `next`
- `accept`
- `reject`
- `conditional`
- `fallback`

Routing results always return explicit state:
- `success`
- `step_id`
- `code`
- `message`

Validation rules:
- A funnel ID must point to `librefunnels_funnel`.
- A step ID must point to `librefunnels_step`.
- A step must be owned by the funnel through `_librefunnels_funnel_id`.
- A graph node must reference a real step in the same funnel.
- A graph edge must reference existing source and target nodes.
- Unknown routes fail instead of guessing.
- Missing requested routes may use a `fallback` edge from the same source node.

Use custom tables for high-volume event data:
- Funnel events
- A/B assignments
- Revenue attribution

Use WooCommerce CRUD APIs for:
- Orders
- Order items
- Coupons
- Products
- Customers
- HPOS compatibility

## Frontend Rendering
Provide both:
- Blocks for Gutenberg/block themes
- Shortcodes for Elementor, Divi, Beaver, Bricks, classic editor, and custom builders

Initial shortcode support:
- `[librefunnels_funnel id="123"]` renders a funnel's configured start step through the router.
- `[librefunnels_step id="456"]` renders a specific funnel step.

Initial dynamic block support:
- `librefunnels/funnel` renders a funnel's configured start step through the router.
- `librefunnels/step` renders a specific funnel step.

The first block implementation is server-rendered and shares the shortcode rendering path. Polished editor controls are deferred to the visual admin/UI phase so they can be designed with Impeccable instead of becoming a generic block sidebar.

Initial renderable step type:
- `thank_you`

Frontend templates are theme-overridable from:
- `librefunnels/steps/thank-you.php`

Plugin fallback templates live under:
- `librefunnels/templates/`

Shortcode callbacks must return strings, never echo directly. Invalid shortcode state should stay silent for customers and show safe diagnostic messages only to users who can manage WooCommerce.

## Import and Export
LibreFunnels uses a versioned JSON package format for funnel portability.

Initial package shape:
- `format`: `librefunnels.funnel`
- `version`: `1`
- `generatedBy`
- `funnel`
- `steps`

The exporter reads the funnel CPT, graph meta, start step meta, and owned step CPT records. The package validator normalizes decoded JSON before any future import writes happen. Actual database import is deferred until the admin flow has capability checks, nonces, confirmation UI, and integration tests.

## Admin Rendering
Use a React admin app loaded only on plugin admin pages.
Canvas nodes represent funnel steps and offer routes.
Edges represent accept/reject/conditional paths.
