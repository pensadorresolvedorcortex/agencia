# NaFlorestaBuy v1.0.0 — Onboarding Guide

## 1) Installation
1. Upload `nafloresta-buy/` to `wp-content/plugins/`.
2. Activate **NaFloresta Buy** in WordPress.
3. Confirm WooCommerce is active.

## 2) Product Setup (Variable Product + Builder)
1. Create or edit a **Variable product**.
2. Add attributes and generate variations.
3. Open the **NaFlorestaBuy** tab in product data.
4. Enable builder, choose allowed `variation_ids`, and keep mode as `matrix`.
5. Save product.

## 3) Common Pitfalls
- Builder enabled with no variations selected.
- Variation IDs in config not matching existing product variations.
- Quantity > number of student names.
- Nonce or cache plugins interfering with AJAX requests.

## 4) Testing Checklist
- Product page shows builder UI.
- Single variation add-to-cart works.
- Multi-variation add-to-cart works.
- Cart displays personalization rows.
- Checkout persists metadata to order items.
- Admin order view shows personalization snapshot.
- Preset save/apply and import/export behave as expected.
