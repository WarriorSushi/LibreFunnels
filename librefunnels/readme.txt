=== LibreFunnels for WooCommerce ===
Contributors: librefunnels
Tags: woocommerce, checkout, funnels, upsells, order bumps
Requires at least: 6.5
Requires PHP: 7.4
Tested up to: 6.5
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free WooCommerce funnels, checkout, upsells, downsells, order bumps, and smart routing.

== Description ==

LibreFunnels is a 100% free, GPL-compatible WooCommerce funnel builder for WordPress.

The project goal is full-featured funnel building for physical products, digital products, services, mixed carts, subscriptions where feasible, order bumps, upsells, downsells, cross-sells, checkout replacement, smart routing, analytics, A/B testing, templates, and a visual canvas builder.

LibreFunnels does not copy CartFlows code, templates, branding, UI, or wording.

== Requirements ==

* WordPress 6.5 or newer.
* PHP 7.4 or newer.
* WooCommerce 8.2 or newer.

== Installation ==

1. Upload the `librefunnels` folder to `/wp-content/plugins/`.
2. Activate WooCommerce.
3. Activate LibreFunnels for WooCommerce.
4. Open LibreFunnels from the WordPress admin menu.

== Frequently Asked Questions ==

= Is LibreFunnels free? =

Yes. The product direction is free feature parity without paid locks or trialware.

= Does LibreFunnels support every payment gateway? =

Normal checkout and order-bump flows use WooCommerce gateways. Post-purchase offers will use true one-click charges only where a gateway safely supports reusable or tokenized charges. Other gateways use an accept-and-confirm flow.

= Are order bumps supported? =

The current foundation can store order bump definitions, render eligible bumps during checkout, add selected bump products to the WooCommerce cart, and apply configured fixed or percentage bump discounts during WooCommerce totals calculation. Analytics attribution is still being built.

= Which shortcodes are available first? =

The initial foundation includes `[librefunnels_funnel id="123"]` for rendering a funnel's configured start step and `[librefunnels_step id="456"]` for rendering a specific step. Checkout and thank-you steps are renderable in the current foundation.

= Are blocks available? =

The initial foundation registers dynamic server-rendered funnel and step blocks. Polished editor controls will be added with the visual admin experience.

= Can themes override LibreFunnels templates? =

Yes. Default checkout, thank-you, and order-bump templates can be overridden by placing matching files under `librefunnels/` in the active theme or child theme.

== Changelog ==

= 0.1.0 =

* Initial foundation scaffold.
