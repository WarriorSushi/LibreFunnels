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
- Checkout product line item attribution copied from LibreFunnels-added cart items onto WooCommerce order items.
- Pure rule evaluator with `all`, `any`, cart product, cart subtotal, customer login, and always rules.
- WooCommerce fact collector for cart product IDs, variation IDs, subtotal, item count, and logged-in state.
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
- Empty funnels now offer a guided starter path that creates checkout and thank-you steps, connects the default continue route, and sets checkout as the start step.
- Canvas route edges now expose accessible route names, focus states, and keyboard activation.
- Explicit route source/target/label selectors and route delete control.
- Page search/create-and-assign controls instead of raw page ID entry.
- Beginner setup guidance in the canvas header with the next useful task.
- Setup progress checklist in the canvas header now names missing pages, draft pages that still need publishing, checkout product readiness, route readiness, and validation state from derived funnel data.
- Assigned pages now expose status-aware draft/published labels, edit-design links, and preview/view actions so store owners can create a funnel page and continue in their preferred page builder with clear publishing guidance.
- Store-owner-friendly conditional rule builder for supported rule types.
- Product pickers in checkout, order bump, offer, and product-rule inspector controls.
- Commerce inspector panels for multiple checkout product assignments, multiple order bumps, and primary offer configuration.
- Checkout product controls now expose quantity, variation ID, and optional variation attributes.
- Order bump controls now support multiple bump cards with quantity, variation ID, variation attributes, title, description, discount, and enabled state.
- Commerce inspector sections now show dirty badges and explicit save reminders before local edits are persisted.
- Upsell, downsell, and cross-sell steps now share the safe accept-and-confirm offer rendering path.
- Local analytics event table and recorder for offer impressions, accepts, rejects, and attributed order revenue.
- WooCommerce checkout order revenue attribution that groups marked order lines by funnel, records `order_revenue` events, and marks orders through WooCommerce CRUD metadata for HPOS-safe idempotency.
- Capability-guarded analytics summary REST endpoint for local dashboard reads.
- React canvas analytics summary panel for the selected funnel, showing last-30-days attributed revenue, order count, offer accept rate, offer decisions, and a clear empty state until shopper/test-order data exists.
- Docker Compose local WordPress/WooCommerce rig with WP-CLI bootstrap and sample products.
- Playwright canvas smoke test for Docker WordPress admin mount, funnel creation, guided starter path, setup progress checks, analytics empty-state guidance, draft page creation with edit/preview handoff, multi-product checkout assignment, order bump saving, drag persistence, route/rule editing, imported broken-route recovery, product search, offer saving, published checkout rendering, public offer reject routing, and public offer accept cart mutation.
- Unit coverage for multiple checkout product and order bump metadata sanitization.

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
1. Add a full checkout order creation smoke that verifies `order_revenue` appears through the analytics summary endpoint and the admin analytics panel.
2. Add REST/integration tests for canvas and analytics endpoints once a WP test runtime is available.
3. Add runtime WooCommerce facts to the public route resolver where conditional routing is needed.
4. Add database import service with nonces/capabilities once the admin flow exists.
5. Add integration tests for shortcode rendering, dynamic block rendering, template override loading, CPT/meta export, checkout cart preparation, and order bump cart sync once a WP test runtime is available.

## Open User Decision
Brand personality and visual tone are now captured in `.impeccable.md`: store-owner-first, refined SaaS, top notch, and not confusing.
