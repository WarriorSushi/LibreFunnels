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
- Step page ID: `_librefunnels_step_page_id`
- Checkout products: `_librefunnels_checkout_products`
- Checkout coupons: `_librefunnels_checkout_coupons`
- Checkout field rules: `_librefunnels_checkout_fields`
- Order bumps: `_librefunnels_order_bumps`
- Step offer: `_librefunnels_step_offer`

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

Conditional edges can include a `rule` object. When resolving the `conditional` route, the router evaluates conditional edges from the current source node against supplied facts and selects the first matching rule edge. If no conditional rule matches, the router may use the source node's `fallback` edge. Rule evaluation remains side-effect free.

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
- `checkout`
- `thank_you`

Frontend templates are theme-overridable from:
- `librefunnels/steps/checkout.php`
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

## Checkout Core
The first checkout implementation renders checkout steps without taking over the global WooCommerce checkout. Checkout steps can assign products, variation IDs, variation attributes, and quantities through `_librefunnels_checkout_products`; coupons through `_librefunnels_checkout_coupons`; and scoped field rules through `_librefunnels_checkout_fields`. When a checkout step renders, LibreFunnels ensures assigned products are present in the current WooCommerce cart, applies configured coupons, scopes checkout field customizations to the current render pass, then renders WooCommerce checkout markup through the normal checkout shortcode.

The initial cart preparation does not empty the existing cart. Cart replacement, checkout takeover, quantity controls, and global checkout routing are separate Phase 2 slices.

Global checkout takeover foundation:
- Option: `librefunnels_global_checkout_funnel_id`
- The option is disabled when empty or `0`.
- When enabled, LibreFunnels resolves the funnel start step and redirects the default WooCommerce checkout page to the step's `_librefunnels_step_page_id`.
- Protected WooCommerce endpoints such as order pay and order received are never redirected.
- The current implementation is a backend foundation. A polished admin toggle/selector must be added before this is presented to store owners.

## Offer Core
The first offer implementation stores order bump definitions on checkout steps through `_librefunnels_order_bumps`. Each bump can reference a product, variation, quantity, variation attributes, title, description, enabled state, and an optional discount shape (`none`, `percentage`, or `fixed`). The offer layer validates whether a configured offer references a real purchasable WooCommerce product before any cart or order mutation.

Order bumps render inside the WooCommerce checkout form through the `woocommerce_review_order_before_payment` hook while a LibreFunnels checkout step is active. Selected bump IDs are protected by a step-scoped nonce and synced during checkout AJAX refreshes and final checkout submission. Accepted bumps are added to the WooCommerce cart with marker cart item data so they can be removed when unselected and attributed later.

Configured order bump discounts are applied during WooCommerce cart total calculation by adjusting the marked bump cart item's product price. This keeps tax, totals, shipping, and checkout behavior inside WooCommerce's normal cart pipeline instead of writing order totals directly.

Pre-checkout offer steps store their primary product offer in `_librefunnels_step_offer`. The default offer step template renders a product-led accept/reject page, protects actions with a step-scoped nonce, adds accepted pre-checkout offers to the WooCommerce cart, and then resolves the funnel's `accept` or `reject` route through the routing core. The target step must have `_librefunnels_step_page_id` assigned so the handler can redirect to the correct public page.

Offer accept/reject actions are recorded in the WooCommerce customer session under `librefunnels_offer_actions`. This state is intentionally small and customer-scoped: step ID, offer ID, action, and timestamp. It gives replay protection, post-purchase flows, and analytics a single state source without storing sensitive customer behavior remotely.

When WooCommerce creates order line items, LibreFunnels copies order bump and pre-checkout offer attribution from marked cart items onto the line item through WooCommerce's order item object API. This stores offer/bump ID, source step ID, discount type, discount amount, and original price without direct order postmeta access.

Checkout products added by LibreFunnels are also marked in the cart and copied onto order line items with checkout step ID and funnel ID metadata. Revenue attribution reads those line item markers through WooCommerce order item CRUD APIs after checkout order creation. Offer logic must continue to use WooCommerce product/cart/order APIs. Direct order post or postmeta access is not allowed because the payment and post-purchase phases must remain HPOS-compatible.

## Rule Core
The first rule engine is pure PHP and evaluates structured rule trees against supplied facts. WooCommerce state is collected by a separate fact collector so conditional routing stays testable and prevents hidden cart/order mutation inside rule evaluation.

Offer accept/reject handlers pass collected facts into the router before resolving the next step. Facts are read from WooCommerce cart/session objects and order CRUD APIs such as `wc_get_order()`/`WC_Order` methods so conditional offer paths can use cart, customer, and order context while remaining HPOS-compatible.

Initial rule types:
- `always`
- `all`
- `any`
- `cart_contains_product`
- `cart_subtotal_gte`
- `cart_subtotal_lte`
- `order_contains_product`
- `order_total_gte`
- `order_total_lte`
- `customer_logged_in`

Initial facts:
- `cart_product_ids`
- `cart_variation_ids`
- `cart_subtotal`
- `cart_item_count`
- `order_id`
- `order_product_ids`
- `order_variation_ids`
- `order_total`
- `order_subtotal`
- `order_item_count`
- `order_status`
- `order_payment_method`
- `order_currency`
- `customer_id`
- `customer_logged_in`

Graph edge rule shape is intentionally the same structured rule tree used by the rule evaluator. Import/export and REST meta sanitization preserve supported rule fields while dropping invalid rule data.

## Admin Rendering
Use a React admin app loaded only on plugin admin pages.
Canvas nodes represent funnel steps and offer routes.
Edges represent accept/reject/conditional paths.

LibreFunnels now uses a sectioned product-console direction in the WordPress admin. The top-level LibreFunnels menu owns submenu pages for Dashboard, Funnels, Templates, Analytics, Settings, and Setup. The PHP layer passes the active section and admin URLs to the React bundle. The React app then renders distinct section pages for Dashboard, Templates, Analytics, Settings, and Setup, while the Funnels submenu owns the full builder workspace. This prevents the submenu IA from feeling like multiple links to the same canvas.

The current admin page keeps a server-rendered workspace fallback inside `#librefunnels-admin-app`, then replaces it with the React app when built assets are available. Assets are enqueued only on registered LibreFunnels admin screens through WordPress admin enqueue APIs. The PHP layer passes REST endpoints, meta keys, route labels, step labels, the active admin section, admin page URLs, and a `wp_rest` nonce to the app before the script runs.

The first React canvas uses WordPress REST-enabled CPTs and registered meta as its persistence layer:
- Create and list funnels.
- Create basic steps owned by the selected funnel.
- Store canvas nodes in `_librefunnels_graph`.
- Store route edges for `next`, `accept`, `reject`, `conditional`, and `fallback`.
- Assign the funnel start step through `_librefunnels_start_step_id`.
- Edit step title, type, and page ID.
- Store conditional edge rule objects.

Canvas writes now use first-party REST endpoints under `librefunnels/v1` for atomic builder actions. The generic WordPress post endpoints remain useful for compatibility, but the builder uses dedicated endpoints for workspace loading, funnel creation, graph saves, step create/update/archive, page search, draft page creation, and WooCommerce product search. Workspace payloads always include every step for the selected funnel, plus a recent global step set for surrounding context, so larger stores and long-running test sites do not turn newly-created canvas nodes into missing-step placeholders. Product lookup uses WooCommerce product APIs (`wc_get_products` and product objects) and searches both product text and SKU before serializing only the compact fields the canvas needs. This keeps validation and error messages aligned with funnel concepts instead of raw post-meta failures.

Validation is visible in the canvas and inspector. Missing start steps, missing page assignments, nodes that point to missing or foreign steps, broken edge source/target IDs, and incomplete conditional rules stay visible instead of being hidden. The design direction remains store-owner-first refined SaaS: calm, explicit, and powerful without becoming a WordPress meta-box maze.

The Funnels React workspace separates funnel work into Overview, Canvas, Steps, Products, Rules, Analytics, and Settings tabs. Overview gives first-run guidance and progress. Canvas keeps the visual journey and contextual inspector together. Steps exposes landing, opt-in, checkout, upsell, downsell, thank-you, and custom step creation without hiding those concepts behind a checkout-only starter path. Products and Rules summarize commerce/routing work and jump back to the focused canvas inspector for editing. Analytics is no longer rendered inside the canvas path by default; it has its own submenu page and remains available as a funnel workspace tab when editing a selected funnel.

The JavaScript app is built with `@wordpress/scripts` from `librefunnels/src/index.js`. Built files under `librefunnels/build/` are committed so WordPress installs do not require Node tooling.

Canvas interaction expectations:
- Nodes can be dragged and saved back to graph position metadata.
- The canvas renders as a scrollable stage inside the canvas column. Large graphs should scroll within that stage instead of overflowing underneath the inspector.
- Route edges expose accessible names, focus states, and keyboard activation so they can be selected without relying on precise SVG pointer hit testing. Route source, target, and label are edited through explicit selectors.
- Nodes expose visible input/output connection handles. Dragging from an output handle to another node's input handle creates a persisted `next` route and selects the route inspector so the store owner can adjust the route label or condition.
- Conditional routes use a rule builder backed by the existing rule schema instead of raw JSON.
- Empty funnels offer a guided starter path action that creates checkout and thank-you steps, connects them with a `next` route, and marks checkout as the start step.
- The canvas header derives a setup progress checklist from funnel, graph, page, product, route, and validation state. It names missing pages, draft pages that still need editing/publishing, checkout product readiness, route readiness, and validation issues without storing redundant UI state.
- Step page assignment uses page search and a draft page creation path that inserts the `[librefunnels_step]` shortcode. Assigned pages expose status-aware labels, edit-design links, and preview/view links so store owners can continue in their preferred page builder and understand when a page still needs publishing.
- Checkout steps can edit multiple WooCommerce product assignments from the inspector, including quantity, variation ID, and optional variation attributes. The same commerce panel can edit multiple order bumps with product, quantity, variation details, title, description, discount, and enabled state.
- Checkout commerce panels show per-section dirty indicators and short save reminders so store owners know when local edits have not yet been persisted through the canvas REST endpoint.
- Offer steps (`pre_checkout_offer`, `upsell`, `downsell`, `cross_sell`) can choose an offer product, title, short description, discount type/amount, and enabled state from the inspector.
- Product-based conditional rules use the same product picker instead of raw product IDs.
- Route deletion and step archiving are recoverable builder actions rather than silent data removal.

## Analytics Core
LibreFunnels records first-party analytics in a local custom table, never to a remote service. The initial table is `{$wpdb->prefix}librefunnels_events`, installed through the plugin activation path and schema-version checks during boot.

Initial events:
- `offer_impression`
- `offer_accept`
- `offer_reject`
- `order_revenue`

Events store funnel ID, step ID, route, object type/ID, optional value/currency, a hashed WooCommerce session identifier when available, customer ID for logged-in users, JSON context, and UTC timestamp. `order_revenue` is recorded once per WooCommerce order per attributed funnel after checkout order processing. The recorder groups attributed order lines by funnel, stores line sources in event context, and marks the order through WooCommerce CRUD metadata to prevent duplicates while remaining HPOS-compatible.

Admin analytics reads are exposed through `GET /wp-json/librefunnels/v1/analytics/summary` behind `manage_woocommerce`. The first summary returns event counts, attributed revenue, store currency, order count, offer accept rate, bounded revenue-source totals, and step-level signals for a selected period and optional funnel ID. Source and step breakdowns are derived from stored local event rows and `order_revenue` line context; the endpoint does not scan WooCommerce orders live. The React analytics surfaces read this endpoint for the selected funnel and render compact last-30-days cards, revenue mix, step signals, and beginner-friendly empty guidance. Future dashboard UI should continue reading this endpoint instead of scanning WooCommerce orders or calling external analytics services.
