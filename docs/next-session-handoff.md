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
- Checkout step cart preparation and WooCommerce checkout rendering.
- Checkout step coupon metadata and cart application.

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
1. Install PHP/Composer locally or make them available on PATH.
2. Run `composer install` in `librefunnels/`.
3. Run `composer lint` and `composer test` once dependencies are installed.
4. Add focused tests for meta sanitization, step type validation, and the WordPress-facing router once a WP test runtime is available.
5. Continue Phase 2 with field customization metadata.
6. Add database import service with nonces/capabilities once the admin flow exists.
7. Add integration tests for shortcode rendering, dynamic block rendering, template override loading, CPT/meta export, and checkout cart preparation once a WP test runtime is available.
8. Start the admin UI shell only after routing/rendering behavior has passing tests.
9. Design polished block editor controls during the Impeccable-backed admin UI phase.

## Open User Decision
Brand personality and visual tone are now captured in `.impeccable.md`: store-owner-first, refined SaaS, top notch, and not confusing.
