# Prompt 01 Foundation and catalogue protection

Read the specification sections 1 to 3 and 14. Implement the theme shell, core plugin and standalone MU catalogue guard in the inspected repository. Preserve existing changes. Add namespaced bootstrapping, dependency notices, assets, configuration defaults, migration/version boundaries and clear ownership of theme versus business logic.

Catalogue mode must fail closed when configuration is absent and remain active if the theme or core plugin changes. Make public products and variations non purchasable. Prevent legacy query/form/AJAX purchasing and Store API checkout/cart side effects; audit read dependencies before broad blocking. Do not disable WordPress REST or authenticated WooCommerce product administration. No fake zero prices, purchase controls or commerce schema. Ensure safe handling when WooCommerce is absent.

Verify activation, theme switching, core plugin deactivation and administrative product editing. Exercise add-to-cart query strings, legacy AJAX, direct checkout, Store API cart/checkout/batch and variation attempts using the installed APIs. Assert that bypass tests do not create customer carts, draft orders, payment attempts or email. Use fixtures and mail capture. Report exact tested paths and remaining installed-extension exposure. Update BUILD_STATUS.md. Do not build every page yet.
