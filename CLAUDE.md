# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Simple Checkout Lite - модуль упрощенного оформления заказа (One Page Checkout) для ocStore 3.0.3.7 с интеграцией темы unishop2_free.

## Architecture

### OCMOD Structure
```
install.xml              # OCMOD XML модификатор (основной файл для установки)
upload/
├── admin/
│   ├── controller/extension/module/simple_checkout_lite.php
│   ├── language/{ru-ru,en-gb}/extension/module/simple_checkout_lite.php
│   └── view/template/extension/module/simple_checkout_lite.twig
├── catalog/
│   ├── controller/extension/module/simple_checkout_lite.php
│   ├── language/{ru-ru,en-gb}/extension/module/simple_checkout_lite.php
│   └── view/
│       ├── javascript/simple_checkout_lite.js
│       └── theme/
│           ├── default/template/extension/module/simple_checkout_lite{,_pay}.twig
│           └── unishop2_free/template/extension/module/simple_checkout_lite{,_pay}.twig
```

### Key Components

**Admin Controller** (`admin/controller/extension/module/simple_checkout_lite.php`):
- Settings management (fields visibility, steps visibility, default methods)
- Install/uninstall hooks with default settings

**Catalog Controller** (`catalog/controller/extension/module/simple_checkout_lite.php`):
- Main checkout page rendering
- AJAX endpoints: `save`, `shipping`, `payment`, `setShipping`, `setPayment`, `confirm`, `zone`, `pay`
- Order creation logic

**JavaScript** (`catalog/view/javascript/simple_checkout_lite.js`):
- AJAX updates for shipping/payment methods
- Form validation and submission
- Dynamic zone loading

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
2. `catalog/controller/common/cart.php` - update checkout link
3. `catalog/controller/checkout/cart.php` - update checkout link

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
| `module_simple_checkout_lite_step_{name}` | bool | Step visibility |
| `module_simple_checkout_lite_payment_default` | string | Default payment method code |
| `module_simple_checkout_lite_shipping_default` | string | Default shipping method code |

## Theme Integration

The module includes templates for both `default` and `unishop2_free` themes. The unishop2_free template uses custom CSS classes that match the theme's design:
- `.checkout-section` - section wrapper
- `.checkout-section-header` - section title with icon
- `.checkout-section-body` - section content
- `.custom-checkbox` - styled checkboxes
- `.btn-checkout` - primary action button
