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
- Pure rule evaluator with `all`, `any`, cart product, cart subtotal, customer login, and always rules.
- WooCommerce fact collector for cart product IDs, variation IDs, subtotal, item count, and logged-in state.
- Conditional graph route resolution using edge rule objects and existing fallback behavior.
- Polished, scoped admin workspace shell for LibreFunnels status and next build areas.
- WordPress-native React admin canvas app loaded only on the LibreFunnels admin screen.
- `@wordpress/scripts` build tooling with committed build assets.
- Funnel list, create funnel action, step creation, start step assignment, node canvas, route edges, and inspector editing.
- Canvas validation for missing start step, missing page assignment, missing/foreign step nodes, broken edge endpoints, and incomplete conditional rules.

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
1. Improve the canvas editing loop with draggable node positioning, explicit source/target selectors for routes, delete/archive controls, and optimistic save feedback.
2. Replace raw page ID entry with a WordPress page search/selector and clear "create page" path.
3. Replace raw conditional JSON with a store-owner-friendly rule builder using the existing rule engine schema.
4. Add custom REST endpoints for canvas save/load if the generic post endpoints become too awkward for validation or bulk updates.
5. Add safe upsell/downsell accept-and-confirm flow foundation for arbitrary gateways.
6. Add cross-sell/pre-checkout offer variants and placement rules.
7. Add offer analytics events for impressions, accepts, rejects, and revenue attribution.
8. Add runtime WooCommerce facts to the public route resolver where conditional routing is needed.
9. Add focused tests for meta sanitization, step type validation, and the WordPress-facing router once a WP test runtime is available.
10. Add database import service with nonces/capabilities once the admin flow exists.
11. Add integration tests for shortcode rendering, dynamic block rendering, template override loading, CPT/meta export, checkout cart preparation, and order bump cart sync once a WP test runtime is available.

## Open User Decision
Brand personality and visual tone are now captured in `.impeccable.md`: store-owner-first, refined SaaS, top notch, and not confusing.
