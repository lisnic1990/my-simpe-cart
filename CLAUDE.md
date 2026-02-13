# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Simple Checkout Lite** - модуль упрощенного оформления заказа (One Page Checkout) для ocStore 3.0.3.7 с интеграцией темы unishop2_free.

**Версия:** 1.6.1

## Architecture

### OCMOD Structure
```
install.xml              # OCMOD XML модификатор (основной файл для установки)
upload/
├── admin/
│   ├── controller/extension/module/simple_checkout_lite.php
│   ├── language/{ru-ru,en-gb,ro-ro}/extension/module/simple_checkout_lite.php
│   └── view/template/extension/module/simple_checkout_lite.twig
├── catalog/
│   ├── controller/extension/module/simple_checkout_lite.php
│   ├── language/{ru-ru,en-gb,ro-ro}/extension/module/simple_checkout_lite.php
│   └── view/
│       ├── javascript/simple_checkout_lite.js  # Unified JS (shared by all themes)
│       └── theme/
│           ├── default/template/extension/module/simple_checkout_lite.twig
│           └── unishop2_free/template/extension/module/
│               ├── simple_checkout_lite.twig      # Основной шаблон чекаута
│               └── simple_checkout_lite_pay.twig  # Страница оплаты
```

### Key Components

**Admin Controller** (`admin/controller/extension/module/simple_checkout_lite.php`):
- Settings management (fields visibility, steps visibility, default methods, default country/zone)
- AJAX endpoint: `zone()` - returns zones by country_id (JSON)
- Install/uninstall hooks with default settings

**Catalog Controller** (`catalog/controller/extension/module/simple_checkout_lite.php`):
- Main checkout page rendering with cart products
- AJAX endpoints: `save`, `shipping`, `payment`, `totals`, `setShipping`, `setPayment`, `confirm`, `zone`
- `pay()` - Payment processing page
- `getTotalsHtml()` - Calculate and format order totals
- `autoSelectShippingMethod()` - Auto-select shipping when step is disabled
- Order creation logic

**Unified JavaScript** (`catalog/view/javascript/simple_checkout_lite.js`):
- Shared by both `default` and `unishop2_free` templates
- Reads config from global `SimpleCheckoutLite` object defined inline in each template
- Includes `escapeHtml()` for XSS prevention and `debounce()` for AJAX throttling
- All UI strings come from `SimpleCheckoutLite.text.*` (localized via language files)
- Fallback: loads totals separately when payment endpoint returns error

**Templates** (`catalog/view/theme/unishop2_free/template/extension/module/`):
- Define `SimpleCheckoutLite` config object inline, then include external JS file
- Flex-based form layout with `.form-row` / `.form-col` classes
- Product list display in sidebar with images, options, prices
- Custom styled checkboxes, buttons matching unishop2_free theme

### OpenCart 3.x Conventions

- Controllers extend `Controller` class
- Models loaded via `$this->load->model('path/to/model')`
- Languages loaded via `$this->load->language('path/to/language')`
- Views rendered via `$this->load->view('path/to/template', $data)`
- Config accessed via `$this->config->get('setting_name')`
- All module settings prefixed with `module_simple_checkout_lite_`

### OCMOD Modifications (install.xml)

The install.xml modifies:
1. `catalog/controller/checkout/checkout.php` - redirect to simple checkout
2. `catalog/controller/common/cart.php` - update checkout link in header cart
3. `catalog/controller/checkout/cart.php` - update checkout link in cart page
4. `admin/controller/common/column_left.php` - add admin menu item

## Build & Package

Create OCMOD archive:
```bash
cd "D:\Work\MyOCMODs\My Simpe Cart"
zip -r simple_checkout_lite.ocmod.zip install.xml upload/
```

## Installation

1. Upload `simple_checkout_lite.ocmod.zip` via Admin > Extensions > Installer
2. Go to Extensions > Modifications and click Refresh
3. Go to Extensions > Modules, find "Simple Checkout Lite" and Install
4. Configure settings and Enable

## Configuration Keys

| Key | Type | Description |
|-----|------|-------------|
| `module_simple_checkout_lite_status` | bool | Module enabled |
| `module_simple_checkout_lite_guest` | bool | Allow guest checkout |
| `module_simple_checkout_lite_field_{name}` | enum | Field visibility (required/visible/hidden) |
| `module_simple_checkout_lite_step_shipping_address` | bool | Show shipping address section |
| `module_simple_checkout_lite_step_shipping_method` | bool | Show shipping method selection |
| `module_simple_checkout_lite_step_payment_method` | bool | Show payment method selection |
| `module_simple_checkout_lite_step_comment` | bool | Show order comment field |
| `module_simple_checkout_lite_payment_default` | string | Default payment method code |
| `module_simple_checkout_lite_shipping_default` | string | Default shipping method code |
| `module_simple_checkout_lite_country_default` | int | Default country ID |
| `module_simple_checkout_lite_zone_default` | int | Default zone/region ID |

### Field Names
`firstname`, `lastname`, `email`, `telephone`, `company`, `address_1`, `address_2`, `city`, `postcode`, `country`, `zone`

## Theme Integration

The module includes templates for both `default` and `unishop2_free` themes. The unishop2_free template uses custom CSS classes that match the theme's design:

### CSS Classes
- `.checkout-section` - section wrapper with white background and border
- `.checkout-section-header` - section title with green icon badge
- `.checkout-section-body` - section content with padding
- `.form-row` / `.form-col` - flex-based form layout (50% columns)
- `.form-col-full` - full-width form column
- `.custom-checkbox` - styled checkboxes with green checkmark
- `.btn-checkout` - green primary action button
- `.checkout-product` - product item in sidebar (image, name, options, price)
- `.checkout-sidebar` - sticky sidebar with totals
- `.table-totals` - totals table styling

### Features
- Clean layout without sidebars (`column_left`, `column_right` hidden)
- No account menu in checkout (`content_top`, `content_bottom` cleared)
- Responsive design (mobile-friendly)
- Product list with thumbnails in order summary
- Auto-select shipping/payment when steps are disabled
- Simple payment methods (cod, free_checkout, cheque, bank_transfer) confirm directly using each method's configured `order_status_id`
- Client-side form validation - confirm button disabled until required fields filled
- Default country/zone pre-selection for local stores

## Changelog

### 1.6.1
- Fixed: Tag Manager (GA4/Facebook Pixel) Purchase event not firing on checkout/success — `addOrderHistory()` triggers Tag Manager event that sets `analytics_tracking.hit=1` prematurely; added `resetAnalyticsHit()` to reset `hit=0` before redirect in all order confirmation branches (`confirm()` and `pay()`)
- Added: `autoSelectPaymentMethod()` — auto-selects default or first available payment method when payment step is disabled
- Added: Tag Manager session/cookie compatibility (`session['tm_order_id']`, `gtm_orderid` cookie) for analytics tracking
- Safety: All Tag Manager compatibility code wrapped in try-catch — module works correctly even if Tag Manager is uninstalled

### 1.6.0
- Refactored: Unified JavaScript into single external file (`catalog/view/javascript/simple_checkout_lite.js`), eliminating ~1100 lines of duplicated inline JS across templates
- Security: Added `escapeHtml()` to all dynamic HTML rendering (zone names, error messages, shipping/payment titles, totals) to prevent XSS
- Security: Added server-side guest checkout validation in `index()`, `save()`, and `confirm()` methods — redirects unauthenticated users when guest checkout is disabled
- Fixed: Totals stuck on "Loading..." when payment endpoint returns error (added fallback to separate `/totals` endpoint)
- Fixed: `pay()` and `confirm()` now use payment method's own `order_status_id` (e.g. `payment_cod_order_status_id`) instead of generic `config_order_status_id`
- Fixed: `save()` no longer sets `session['guest']` for logged-in users (was overwriting session incorrectly)
- Fixed: `save()` now sets `session['account'] = 'guest'` for guest checkout (required by some payment extensions)
- Added: AJAX debounce (500ms) on address field changes to reduce unnecessary server requests
- Added: Localized all JS strings via template config object (`text_order_summary`, `text_select_option`, `text_processing`, `text_no_shipping`, `text_no_payment`, `text_error_loading`, `text_error_try_again`, `error_guest_disabled`)
- Fixed: Order total always zero in database — `$total_data` array must use `&` references (`&$totals`, `&$taxes`, `&$total`) because OpenCart's Proxy `__call` cannot preserve pass-by-reference semantics (fixed in `payment()`, `confirm()`, and `getTotalsHtml()`)
- Removed: All `console.log`/`console.error` calls from production code

### 1.5.2
- Fixed: Label alignment in checkout forms - labels were right-aligned due to Bootstrap 3 `form-horizontal` + `control-label` combination
- Added CSS override (`text-align: left`, `float: none`, `width: 100%`) in both unishop2_free and default templates

### 1.5.1
- Fixed: Admin menu duplicating across all menu items (OCMOD search pattern matched multiple times)
- Changed: OCMOD search from `$data['menus'][] = array(` to unique `$data['menus'] = array();`
- Fixed: Admin zone AJAX endpoint - added `zone()` method to admin controller (OpenCart admin has no built-in JSON zone endpoint)

### 1.5.0
- Added: Default country and region settings in admin panel
- Useful for local stores - no need to select country every time

### 1.4.0
- Added: Form validation - confirm button disabled until all required fields are filled
- Added: Client-side validation for required fields (firstname, email, telephone, etc.)
- Added: Agreement checkbox validation

### 1.3.2
- Added: Romanian language (ro-ro)
- Fixed: Agreement checkbox text layout (wrapping issue)
- Changed: Agreement text format - link on "Условия соглашения" / "Terms & Conditions"
- Fixed: error_agree message showing %s placeholder

### 1.3.1
- Fixed: Admin menu item not appearing (OCMOD bug)
- Fixed: Undefined variables for logged-in users without default address
- Fixed: Undefined index errors in save() when fields are hidden
- Added: try-catch in confirm() for totals calculation
- Added: Missing language keys (text_sub_total, text_total)

### 1.3.0
- Added product list display in "Ваш заказ" sidebar section
- Shows product image, name, options, quantity, price, total

### 1.2.1
- Fixed form field layout with flex-based system
- Fixed select field styling (Country, Zone, Region)

### 1.2.0
- Fixed validation when shipping/payment steps are disabled
- Added `autoSelectShippingMethod()` for hidden shipping step
- Added `totals()` endpoint for when payment is disabled

### 1.1.0
- Fixed empty payment form for simple methods (cod, free_checkout)
- Removed account menu from checkout page
- Added try-catch for payment/total calculations

### 1.0.0
- Initial release
- One-page checkout with AJAX updates
- Admin settings for field/step visibility
- Support for default and unishop2_free themes
