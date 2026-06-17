# Release Validation Checklist (v1.0.0)

## Security
- [x] AJAX endpoints validate nonces.
- [x] Admin save flow validates nonce and capability.
- [x] Inputs are sanitized in validation, transport, and persistence layers.
- [x] Cart/admin outputs use escaped rendering in templates/hooks.

## i18n
- [x] Text domain set to `nafloresta-buy`.
- [x] Plugin loads text domain on bootstrap.
- [x] Translation template header version updated to 1.0.0.

## Performance
- [x] Front app uses delegated listeners for dynamic lists.
- [x] Summary/submit rendering uses debounced updates.
- [x] State hashing avoids unnecessary full re-renders.

## Functional smoke checklist
- [ ] Product page builder works.
- [ ] Add-to-cart works (single/multiple variations).
- [ ] Cart shows customization data correctly.
- [ ] Checkout persists metadata.
- [ ] Admin order view shows persisted data.
- [ ] Presets save/apply works.
- [ ] Import/export works.
- [ ] No PHP or JS runtime errors.

> Manual verification items remain to be executed in a WordPress + WooCommerce runtime environment.
