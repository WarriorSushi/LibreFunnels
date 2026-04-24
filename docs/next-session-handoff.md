# Next Session Handoff

## Current State
Workspace:
`C:\coding\cartflow-myown`

Git is initialized on branch `main`.

GitHub remote:
`https://github.com/WarriorSushi/LibreFunnels.git`

`main` has been pushed to GitHub.

Plugin scaffold exists under:
`librefunnels/`

Implemented so far:
- Main plugin file and constants.
- GPL/readme/uninstall basics.
- Composer/PHPCS/PHPUnit config placeholders.
- Runtime dependency checks for WordPress, PHP, and WooCommerce.
- WooCommerce HPOS compatibility declaration.
- WooCommerce compatibility plugin headers.
- Admin menu shell.
- Private REST-enabled funnel CPT: `librefunnels_funnel`.
- Private REST-enabled step CPT: `librefunnels_step`.
- Registered funnel/step meta for graph, start step, step ownership, step type, step order, and template slug.
- Routing core classes for start-step and next-step resolution.
- Pure graph validator tests for route behavior.
- Impeccable design context file for store-owner-first refined SaaS UI.
- Shortcode registry for `[librefunnels_funnel]` and `[librefunnels_step]`.
- Default thank-you step renderer and theme-overridable template.
- Versioned JSON funnel exporter.
- Import package validator/normalizer.
- Dynamic server-rendered block registration for funnel and step blocks.
- Checkout step product assignment metadata.
- Checkout product quantity, variation ID, and variation attribute support.
- Checkout step cart preparation and WooCommerce checkout rendering.
- Checkout step coupon metadata and cart application.
- Scoped checkout field customization metadata and filter.
- Disabled-by-default global checkout redirect foundation.
- Order bump metadata foundation on checkout steps.
- Order bump import/export normalization.
- Product-offer eligibility checks using WooCommerce product APIs without cart or order mutation.
- Order bump checkout rendering inside the WooCommerce checkout form.
- Selected order bump cart synchronization during checkout AJAX refresh and final checkout submission.
- Order bump fixed/percentage discount calculation and cart item price adjustment during WooCommerce totals calculation.
- Order bump attribution copied onto WooCommerce order line item metadata through WooCommerce item objects.
- Pre-checkout offer step metadata, import/export normalization, frontend template, accept/reject POST handler, route redirects, cart add, discount support, and line item attribution.
- Customer-scoped offer accept/reject state stored in WooCommerce session for replay protection and future analytics.
- Offer accept/reject routing now passes collected WooCommerce facts into the router so future conditional offer paths can use cart/order/customer context safely.
- Checkout product line item attribution copied from LibreFunnels-added cart items onto WooCommerce order items.
- Pure rule evaluator with `all`, `any`, cart product, cart subtotal, customer login, and always rules.
- WooCommerce fact collector for cart product IDs, variation IDs, subtotal, item count, and logged-in state.
- WooCommerce fact collector now also exposes HPOS-safe order facts from WooCommerce CRUD objects: order ID, product IDs, variation IDs, totals, item count, status, payment method, currency, and customer ID.
- Rule evaluator supports order-aware conditional rules for order product containment and order total thresholds.
- Conditional graph route resolution using edge rule objects and existing fallback behavior.
- Polished, scoped admin workspace shell for LibreFunnels status and next build areas.
- WordPress-native React admin canvas app loaded only on the LibreFunnels admin screen.
- `@wordpress/scripts` build tooling with committed build assets.
- Funnel list, create funnel action, step creation, start step assignment, node canvas, route edges, and inspector editing.
- Canvas validation for missing start step, missing page assignment, missing/foreign step nodes, broken edge endpoints, and incomplete conditional rules.
- Custom `librefunnels/v1` canvas REST endpoints for workspace loading, funnel creation, graph saves, step create/update/archive, page search, and draft page creation.
- Canvas workspace payloads always include every step for the selected funnel, preventing large or long-running sites from showing newly-created selected-funnel nodes as missing steps when the global recent-step list is capped.
- Canvas product search endpoint using WooCommerce product APIs with name/SKU lookup and compact serialization.
- Draggable node positioning with persisted graph coordinates.
- Canvas layout now uses a scrollable stage so the step inspector stays in its own column instead of covering nodes and routes.
- Empty funnels now offer a guided starter path that creates checkout and thank-you steps, connects the default continue route, and sets checkout as the start step.
- Canvas route edges now expose accessible route names, focus states, and keyboard activation.
- Canvas nodes expose visible input/output connection handles; dragging from a step's output handle to another step's input handle creates and saves a route.
- Canvas now includes a compact route-wiring cue above the stage so beginners can see that shopper paths are connected by dragging from the right handle of one step to the left handle of the next step.
- Explicit route source/target/label selectors and route delete control.
- Page search/create-and-assign controls instead of raw page ID entry.
- Beginner setup guidance in the canvas header with the next useful task.
- Setup progress checklist in the canvas header now names missing pages, draft pages that still need publishing, checkout product readiness, route readiness, and validation state from derived funnel data.
- Assigned pages now expose status-aware draft/published labels, edit-design links, and preview/view actions so store owners can create a funnel page and continue in their preferred page builder with clear publishing guidance.
- Step inspector is now sectioned into Details, Page, and Products/Offer tabs so page-builder handoff, basic step settings, and commerce controls do not crowd one long panel.
- Draft or missing pages keep the Page inspector tab active until the page is published; published checkout/offer steps can lead with commerce controls.
- Store-owner-friendly conditional rule builder for supported rule types.
- Rule builder choices are grouped by General, Cart, and Order conditions, with a live plain-language preview that updates as the selected rule/product/amount changes.
- Product pickers in checkout, order bump, offer, and product-rule inspector controls.
- Commerce inspector panels for multiple checkout product assignments, multiple order bumps, and primary offer configuration.
- Checkout product controls now expose quantity, variation ID, and optional variation attributes.
- Order bump controls now support multiple bump cards with quantity, variation ID, variation attributes, title, description, discount, and enabled state.
- Commerce inspector sections now show dirty badges and explicit save reminders before local edits are persisted.
- Upsell, downsell, and cross-sell steps now share the safe accept-and-confirm offer rendering path.
- Post-purchase offer payment adapter foundation exists under `LibreFunnels\Payments`, with adapter contracts, capability flags, safe fallback adapter, mock/test adapter, payment results, adapter registry, and offer payment service.
- Upsell, downsell, and cross-sell rendering now asks the payment service for the current strategy and explains one-click versus accept-and-confirm behavior on the public offer step.
- Offer accept handling now attempts adapter-backed one-click charging only when a validated order/order-key context and capable adapter are present; unknown gateways continue through WooCommerce checkout confirmation without mutating the original order.
- One-click adapter charges now create a separate WooCommerce child order through HPOS-safe order CRUD before the adapter runs, copy safe customer/currency/address context, add the discounted offer product, and store LibreFunnels offer/payment metadata on the child order.
- Local analytics event table and recorder for offer impressions, accepts, rejects, and attributed order revenue.
- WooCommerce checkout order revenue attribution that groups marked order lines by funnel, records `order_revenue` events, and marks orders through WooCommerce CRUD metadata for HPOS-safe idempotency.
- Capability-guarded analytics summary REST endpoint for local dashboard reads.
- Analytics summary REST responses now include bounded local revenue-source and step-level breakdowns derived from stored event rows and order line context.
- Analytics summary REST responses now include current-vs-previous period comparisons for revenue, orders, offer accept rate, and offer impressions.
- React analytics panels for the selected funnel show last-30-days attributed revenue, order count, offer accept rate, offer decisions, revenue mix, step signals, and a clear empty state until shopper/test-order data exists.
- Dashboard and analytics metric cards now show plain trend labels such as new revenue, flat activity, or the current delta versus the previous period.
- Sectioned LibreFunnels admin IA with WordPress submenus for Dashboard, Funnels, Templates, Analytics, Settings, and Setup.
- Dashboard, Templates, Analytics, Settings, and Setup now render distinct React section pages instead of all linking to the same builder screen.
- Funnel workspace tabs for Overview, Canvas, Steps, Products, Offers, Rules, Analytics, and Settings so the visual map no longer carries analytics, commerce summaries, rules, and setup guidance all at once.
- Dedicated Steps surface that makes landing, opt-in, checkout, upsell, downsell, thank-you, and custom steps visible to beginners before they open the focused canvas inspector.
- Dedicated Offers surface that separates upsell, downsell, cross-sell, and pre-checkout offer configuration from checkout product work.
- Bundled local funnel template library with starter checkout, product launch, lead offer, and downsell recovery patterns.
- REST endpoints for bundled template listing, template-to-funnel creation, JSON import, and JSON export.
- Import service that creates funnel posts, step posts, graph routing, and draft WordPress pages from validated JSON packages.
- Landing, opt-in, custom, and thank-you content steps now render safely through a shared content-step template instead of failing as unsupported public pages.
- Dashboard and Setup now use real site-readiness data for permalinks, WooCommerce checkout page presence, enabled gateways, currency, and product count.
- Starter-funnel creation from Dashboard, Setup, Templates, and empty canvas states now opens the exact funnel workspace via a deterministic `funnel_id` handoff instead of relying only on local storage.
- Templates and Setup include a guided starter panel that can preselect a WooCommerce checkout product, optionally preselect an offer product, create the bundled starter funnel with draft pages, and deep-link the user into the Steps workspace tab for edit-design and preview actions.
- Template-to-funnel creation accepts sanitized product selections and only applies existing WooCommerce products to the first matching checkout/offer step during import.
- Docker Compose local WordPress/WooCommerce rig with WP-CLI bootstrap and sample products.
- Playwright canvas smoke test for Docker WordPress admin mount, submenu screen rendering, guided template starter creation with checkout product preselection and Steps-tab handoff, bundled template JSON import/export, guided starter path, workspace tab switching, setup progress checks, analytics empty-state guidance, draft page creation with edit/preview handoff, multi-product checkout assignment, order bump saving, drag persistence, handle-based route creation, route/rule editing, imported broken-route recovery, product search, offer saving, published checkout rendering, full WooCommerce checkout order creation with attributed revenue/source/step analytics and visible period comparison, public offer fallback messaging, public offer reject routing, and public offer accept cart mutation.
- Local Docker Playwright runs now clean up known LibreFunnels smoke-test funnels, draft pages, step posts, and smoke products before and after the suite so the admin UI starts from a readable state and test artifacts do not pile up across sessions.
- Playwright coverage now exercises the tabbed step inspector path by keeping page edit/preview controls visible for draft pages and moving checkout products/order bumps behind the Products inspector tab.
- Playwright coverage now also verifies conditional-route rule previews for the always, missing-product, and selected-product states.
- Playwright coverage now verifies public post-purchase offer fallback copy and the accept-and-confirm cart path for unsupported gateways.
- Unit coverage for multiple checkout product and order bump metadata sanitization.
- Unit coverage for bundled template library responses and normalization.
- Unit coverage for funnel importer draft-page side effects, graph/start-step remapping, invalid package failures, and existing-product-only template option overrides.
- Unit coverage for analytics summary source-revenue, step-breakdown, and previous-period comparison shaping.
- Unit coverage for WooCommerce order fact collection and order-aware rule evaluation.
- Unit coverage for payment adapter resolution, mock/test gateway success and failure behavior, fallback confirmation behavior, order-key validation, and child-order creation that leaves the parent order untouched.

## User Intent
Build a full, free, open-source WooCommerce funnel builder that can compete with and improve on CartFlows.

## Confirmed Decisions
- Product name: LibreFunnels.
- Working plugin title: LibreFunnels for WooCommerce.
- Preferred plugin slug: `librefunnels`.
- Not limited to digital products.
- Supports physical, digital, services, mixed carts, and compatible subscriptions.
- Free feature parity is the priority.
- Big 1.0 launch preferred.
- Visual canvas builder preferred.
- Smart universal payment gateway policy selected.
- Use Impeccable for UI work.

## Next Implementation Steps
1. Add REST/integration coverage for template create/import/export endpoints and capability/nonce behavior once a WP integration runtime is available.
2. Continue analytics into dashboard snapshots, selectable date ranges, and step trend history after the current period-comparison, revenue-source, and step-signal panels.
3. Add child-order revenue attribution and adapter charge/failure analytics events, then begin WooPayments/Stripe capability detection behind the existing adapter contract.
4. Add import/export controls to a hardened settings or tools surface with nonces/capabilities for non-REST admin entry points if we expose them outside the React app.
5. Continue UI polish on the workspace list/sidebar and dashboard so large local datasets remain readable.

## Open User Decision
Brand personality and visual tone are now captured in `.impeccable.md`: store-owner-first, refined SaaS, top notch, and not confusing.
