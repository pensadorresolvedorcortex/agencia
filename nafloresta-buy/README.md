# NaFloresta Buy (v1.0.0)

NaFloresta Buy is a WooCommerce plugin that enables a matrix-style product builder for variable products, including per-item student name personalization and batch add-to-cart.

## Features
- Matrix variation selection
- Per-quantity student name personalization
- Batch add-to-cart via AJAX
- Cart and order snapshot persistence
- Product-level builder settings with preset save/apply
- Optional debug logging mode

## Installation
1. Copy the `nafloresta-buy` folder into `wp-content/plugins/`.
2. Activate **NaFloresta Buy** in WordPress admin.
3. Ensure WooCommerce is installed and active.
4. Edit a variable product and configure the builder in the **NaFlorestaBuy** product tab.

## Usage
1. Enable builder on a variable product.
2. Select allowed variations and adjust field schema.
3. Save product settings.
4. On the product page, choose quantities, fill student names, and submit to cart.

## Notes
- Text domain: `nafloresta-buy`
- Compatible mode: matrix (MVP)
- Uninstall removes plugin options (`nafb_presets`, `nafb_insights`) only.
