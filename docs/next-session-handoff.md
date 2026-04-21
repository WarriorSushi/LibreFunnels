# Next Session Handoff

## Current State
Workspace:
`C:\coding\cartflow-myown`

Git is initialized on branch `main`.

GitHub remote:
`https://github.com/WarriorSushi/LibreFunnels.git`

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
1. Add safe upsell/downsell accept-and-confirm flow foundation for arbitrary gateways.
2. Add cross-sell/pre-checkout offer variants and placement rules.
3. Add offer analytics events for impressions, accepts, rejects, and revenue attribution.
4. Add WooCommerce fact collector for the rule engine.
5. Connect conditional graph routes to evaluated rules.
6. Add focused tests for meta sanitization, step type validation, and the WordPress-facing router once a WP test runtime is available.
7. Add database import service with nonces/capabilities once the admin flow exists.
8. Add integration tests for shortcode rendering, dynamic block rendering, template override loading, CPT/meta export, checkout cart preparation, and order bump cart sync once a WP test runtime is available.

## Open User Decision
Brand personality and visual tone are now captured in `.impeccable.md`: store-owner-first, refined SaaS, top notch, and not confusing.
