Since you want to **reverse engineer the WordPress/WooCommerce installation** (without admin access), the objective is to collect enough evidence to infer:

* database schema
* custom post types
* taxonomies
* metadata
* plugins
* business logic
* custom tables
* REST APIs
* AJAX endpoints
* frontend architecture

Below is every field from your JSON and **how to obtain it**.

---

# 1. Website technologies

## Hosting

Current

```
Hostinger Premium or Business
```

How to identify

* nslookup
* dig
* whois
* Wappalyzer
* BuiltWith
* SecurityTrails
* crt.sh

Look for

* DNS
* IP owner
* CDN
* Host headers
* SSL issuer

---

## CMS

Current

```
WordPress
```

Evidence

```
/wp-content/
/wp-includes/
/wp-json/
meta generator
```

Also inspect

```
view-source
```

Search

```
wp-content
wp-includes
generator
```

---

## WooCommerce

Evidence

```
/product/
/categoria-producto/
/cart/
/checkout/
/my-account/
```

Also

```
wc-ajax
```

requests

---

## WordPress version

Currently empty.

How to get

### Method 1

```
view-source
```

Search

```
generator
```

Example

```
<meta name="generator" content="WordPress 6.8.2">
```

---

### Method 2

```
/feed/
```

---

### Method 3

```
readme.html
```

Sometimes exposed

```
https://site/readme.html
```

---

### Method 4

Look at

```
wp-includes/js/wp-emoji-release.min.js?ver=6.8.2
```

The

```
?ver=
```

often equals WP version.

---

## WooCommerce version

Search source

```
woocommerce
```

or

```
?ver=
```

inside

```
woocommerce.min.js
```

Example

```
woocommerce.min.js?ver=9.1.0
```

---

Also inspect

```
/wp-json
```

Sometimes version appears.

---

## Theme

Look for

```
wp-content/themes/
```

Example

```
wp-content/themes/astra/
```

Then theme

```
astra
```

---

Can also inspect

```
style.css
```

---

## Page Builder

Search source for

```
elementor
bricks
oxygen
wpbakery
beaver
divi
breakdance
gutenberg
```

Look for JS

```
elementor-frontend.js
```

or CSS.

---

## Plugins

Search source

```
wp-content/plugins/
```

Example

```
wp-content/plugins/woocommerce/
wp-content/plugins/elementor/
wp-content/plugins/contact-form-7/
```

Network tab helps too.

---

## Server headers

Use

```
curl -I
```

Look for

```
Server
X-Powered-By
Cache-Control
cf-cache-status
LiteSpeed
nginx
Apache
```

---

# 2. Site structure

---

## Main menu

Inspect HTML

Usually

```
<nav>
```

or

```
ul.menu
```

---

## Footer links

Already collected.

---

## Categories

Can obtain from

```
/categoria-producto/
```

or

```
wp-json
```

taxonomy endpoints.

---

## Subcategories

Visit

```
categoria-producto/software
```

Look at filters.

Or

```
REST API
```

---

## Brands

Need to determine

Is brand

* taxonomy?

or

* attribute?

or

* plugin?

Inspect

```
product page
```

Search HTML

```
Marca
```

If URLs

```
/marca/tp-link/
```

then taxonomy.

If attribute

```
pa_marca
```

then product attribute.

---

## Custom pages

Already identified.

Also inspect sitemap.

---

# 3. Pages

Need URLs.

---

## Homepage

Already.

---

## Category

Already.

---

## Product

Need several.

Collect

* simple
* variable
* downloadable
* virtual
* out of stock

Inspect HTML.

---

## Cart

Usually

```
/carrito/
```

or

```
/cart/
```

Add item first.

---

## Checkout

Usually

```
/finalizar-compra/
```

or

```
/checkout/
```

---

## My Account

Usually

```
/mi-cuenta/
```

or

```
/my-account/
```

---

# 4. Network

Probably the MOST important section.

Open

```
F12
Network
```

Reload.

Save

```
HAR
```

---

Homepage HAR

Contains

* JS
* CSS
* APIs
* plugins
* images

---

Product HAR

Contains

* variation AJAX
* cart requests

---

Category HAR

Contains

* pagination
* sorting
* filters

---

Captured AJAX

Filter

```
admin-ajax.php
```

Example

```
?action=...
```

Every action usually maps to

```
add_action('wp_ajax...')
```

inside plugin.

Very valuable.

---

Captured REST

Filter

```
wp-json
```

---

Captured JSON

Every JSON response.

Often

```
products
cart
shipping
variation
search
```

---

# 5. API

---

## wp-json

Visit

```
/wp-json/
```

It exposes namespaces.

Example

```
wp/v2
wc/store
wc/v3
contact-form-7
elementor
```

---

## REST endpoints

Collect every endpoint.

Can infer plugins.

---

## AJAX endpoints

Usually

```
admin-ajax.php
```

Collect

```
action=
```

values.

---

# 6. Browser

---

Cookies

Collect

```
wordpress_logged_in
woocommerce_cart_hash
woocommerce_items_in_cart
wp_woocommerce_session
```

---

Local Storage

Inspect

Application

↓

Local Storage

---

Session Storage

Same.

---

# 7. Product samples

Need at least

---

Simple product

Already.

Inspect

```
HTML
```

---

Variable product

Inspect

Variation JSON.

Usually embedded.

Contains

```
variation_id
price
sku
stock
attributes
```

Huge clue.

---

Discount product

Inspect

```
sale_price
regular_price
```

---

Out of stock

Find one.

Useful for

```
stock_status
```

logic.

---

# 8. Additional observations

Already.

Need more.

Examples

```
shipping providers

payment gateways

coupon support

wishlist

compare

recently viewed

tracking

ERP integration

POS integration

WhatsApp integration

Google Analytics

Facebook Pixel

Meta Pixel

Tag Manager

Cloudflare

LiteSpeed Cache

Redis

Object Cache

```

---

# 9. Database inference

This is your actual goal.

Based on evidence infer

```
wp_posts

wp_postmeta

wp_terms

wp_term_taxonomy

wp_term_relationships

wp_options

wp_users

wp_usermeta

wp_comments

wp_commentmeta

```

WooCommerce

```
wp_wc_orders

wp_wc_order_addresses

wp_wc_order_operational_data

wp_wc_order_product_lookup

wp_wc_customer_lookup

wp_wc_product_meta_lookup

wp_wc_tax_rate_classes

wp_wc_download_log

wp_wc_reserved_stock

wp_wc_order_stats

wp_wc_order_coupon_lookup

wp_wc_order_tax_lookup

wp_wc_category_lookup

wp_wc_product_download_directories
```

Infer custom plugin tables

Example

```
wp_yith_*

wp_elementor_*

wp_rank_math_*

wp_cf7*

wp_litespeed_*

wp_mailpoet_*

wp_wc_booking_*

```

---

# 10. Business logic (most valuable)

Infer workflows such as:

```
Customer

↓

Product search

↓

Category

↓

Product

↓

Variation selection

↓

Add to cart

↓

Session

↓

Cart

↓

Shipping calculation

↓

Checkout

↓

Payment gateway

↓

Order creation

↓

Stock reduction

↓

Email

↓

Invoice

↓

Technical support

↓

Warranty process
```

Then identify:

* which steps are pure WooCommerce
* which appear customized
* which likely involve custom code or plugins

---

# Missing evidence I would collect before attempting a database reverse engineering

1. Full HTML of the homepage
2. Full HTML of at least 10 product pages
3. Full HTML of one variable product
4. Full HTML of the cart
5. Full HTML of the checkout
6. Complete HAR of homepage
7. Complete HAR of adding a product to the cart
8. Complete HAR of changing a product variation
9. `/wp-json/` response
10. `/robots.txt`
11. `sitemap.xml`
12. View Source of every page type
13. All JavaScript bundle URLs
14. All CSS URLs
15. Screenshots of the Network tab (XHR/Fetch)
16. All cookies
17. Local Storage and Session Storage contents
18. All detected AJAX actions (`admin-ajax.php?action=...`)
19. All exposed REST endpoints (`/wp-json/...`)
20. A crawl of the site's internal URLs (e.g., with a tool like Screaming Frog)

Once you have that evidence, you can infer the logical schema with surprisingly high confidence—often around **85–95%** for a standard WooCommerce installation, and **70–90%** even when custom plugins are involved.
