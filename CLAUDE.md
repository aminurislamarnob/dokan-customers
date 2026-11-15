# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"Customer Management for Dokan" — a WordPress plugin (published on wordpress.org as `dokan-customers`) that adds a **Customers** list (`#customers`) and a **Customer Details** page (`#customers/:id`) to the Dokan React vendor dashboard, listing each Vendor's registered customers with order count, net total spend, registration date, billing address, and per-customer orders.

**Dependencies: WooCommerce and Dokan Lite (`>= 5.0.0`) only.** Both are declared in the `Requires Plugins: woocommerce, dokan-lite` header and re-checked at runtime in `DokanCustomers::check_dependencies()` and `DokanCustomers::is_supported_dokan_version()`. Dokan Pro is **not** a dependency. The plugin must work fully on Lite alone, so never call `dokan_pro()`, Pro classes (`WeDevs\DokanPro\*`) or Pro-only hooks unconditionally. If a Pro integration is ever added, guard it with `dokan()->is_pro_exists()`. Use only APIs that exist in Lite (`dokan()->order`, `dokan()->vendor`, `dokan()->rewrite`, `dokan_get_navigation_url()`, `dokan-react-components` and so on).

## Working preferences

- When interviewing, grilling or clarifying requirements, ask **one question at a time** (with a recommended answer) and wait for the reply before asking the next. Don't batch several questions into one message.

## Commands

```bash
composer install            # required: the plugin loads vendor/autoload.php (PSR-4, no manual requires)
npm install                 # install @wordpress/scripts and webpack dependency extraction tooling
npm run build               # required on a git checkout: compiles src/dashboard/* -> assets/js/ (gitignored)
npm run start               # watch mode for React dashboard development
composer phpcs              # lint PHP (WordPress Coding Standards, config in phpcs.xml)
composer phpcbf             # auto-fix PHP lint issues
vendor/bin/phpcs includes/REST/CustomersController.php   # lint a single PHP file
bin/build.sh                # runs npm ci + npm run build + composer install --no-dev -> build/dokan-customers.zip
```

Note: `assets/js/` is ignored in git (`.gitignore`) and built during packaging (`bin/build.sh` and `.github/workflows/deploy-on-pushing-a-new-tag.yml`). A fresh git checkout requires `npm install && npm run build` before the React Customers page will load.

There are no automated PHPUnit tests yet — PHPUnit/wp-phpunit are in `require-dev` but there is no `tests/` dir or `phpunit.xml`.

PHPCS notes: targets PHP 7.4+, text domain must be `dokan-customers`, space indentation is allowed, strict comparisons and strict `in_array` are errors, short array syntax is allowed.

## Architecture

Request flow:

1. `dokan-customers.php` defines `DOKAN_CUSTOMERS_FILE`, loads the Composer autoloader and boots the `DokanCustomers` singleton (namespace `PluginizeLab\DokanCustomers` → `includes/`).
2. `DokanCustomers` defines path/URL constants, and on `plugins_loaded` checks dependencies (`check_dependencies()`) and the minimum Dokan Lite version (`is_supported_dokan_version()`, `MIN_DOKAN_VERSION = '5.0.0'`, verified against a real Dokan Lite `5.0.0` install). If Dokan Lite is older than `5.0.0`, it shows an admin error notice and skips registering routes, scripts, and nav items.
3. On `init` (priority 4), `DokanCustomers` instantiates `Assets` and `ManageCustomers`, and hooks `dokan_rest_api_class_map` to register `REST\CustomersController`.
4. `ManageCustomers`:
   - Adds the `customers` query var (`dokan_query_var_filter`).
   - Adds the `Customers` sidebar nav item (`dokan_get_dashboard_nav`) with `react_route => 'customers'`, `icon_name => 'Users'`, `permission => 'dokan_view_order_menu'`, and `pos => 51` when `dokan_is_seller_enabled()` is true.
   - Redirects the legacy `/dashboard/customers/` endpoint (`template_redirect`) with a `301` redirect to `dokan_get_navigation_url( 'new' ) . '#/customers'` (the hash form Dokan's router itself emits via `navigate()`; Dokan's sidebar link `#customers` resolves to the same route) (preserving `pagenum`/`page`/`search`/`s` query args).
   - Rewrite rules are flushed **only** on plugin activation (`DokanCustomers::activate()`) and on `woocommerce_flush_rewrite_rules` (`DokanCustomers::flush_rewrite_rules()`).
5. `Assets` registers and enqueues `assets/js/dokan-customers-dashboard.js` and `assets/js/dokan-customers-dashboard.css` (using dependencies and version from `assets/js/dokan-customers-dashboard.asset.php`) on `wp_enqueue_scripts` **only** when `dokan_is_seller_dashboard()` is true and `$wp->query_vars['new']` is set.
6. `REST\CustomersController` (extends `WeDevs\Dokan\Abstracts\DokanRESTController`, namespace `dokan-customers/v1`, base `customers`):
   - `GET /dokan-customers/v1/customers`: returns a paginated, searchable, sortable list of registered customers who have placed orders with the current Vendor (`dokan_get_current_user_id()`) in counted statuses (`wc-processing`, `wc-completed`, `wc-on-hold`). Computes `orders_count` and `total_spent` (order totals minus child `shop_order_refund` amounts) in a single SQL query supporting both WooCommerce HPOS (`OrderUtil::is_hpos_enabled()`) and legacy CPT order storage.
   - `GET /dokan-customers/v1/customers/(?P<id>[\d]+)`: returns the single customer summary row for the current Vendor, or `404` if the customer has no counted orders with this Vendor.
7. Frontend (`src/dashboard/`, compiled via `webpack.config.js` + `webpack-dependency-mapping.js` externalizing `@dokan/*` to `window.dokan.*` / `dokan-react-components`):
   - `src/dashboard/index.tsx`: registers `/customers` (`CustomerList`) and `/customers/:id` (`CustomerDetails`) on the `dokan-dashboard-routes` filter (`window.wp.hooks.addFilter`) at the top level of the script (not inside `domReady`).
   - `src/dashboard/CustomerList.tsx`: renders the Customers `DataViews` table (search, sortable `name` / `orders_count` / `total_spent` / `date_registered` columns, pagination, and `View Orders` action).
   - `src/dashboard/CustomerDetails.tsx`: fetches customer summary from `/dokan-customers/v1/customers/:id` and renders the customer's orders via `DataViews` backed by Dokan Lite's `/dokan/v1/orders?customer_id=:id`, linking each order to Dokan's order details view.

## Dokan reference source

Full development checkouts of Dokan live next to this plugin, for reading only. Never edit them, and never load or require them from this plugin:

- `../reference-plugins/dokan-lite-develop/`: the dependency. Look here for the hooks, services and data APIs this plugin calls.
- `../reference-plugins/dokan-pro-develop/`: **not** a dependency. Read it as the main example of how an add-on extends Lite, since Pro and its `modules/*` are built entirely on Lite's hooks.

Both have their own `CLAUDE.md` and `.claude/skills/` (`dokan-backend-dev`, `dokan-pro-backend-dev`, `dokan-pro-modules`) with deeper conventions. `dokan-lite-develop/CONTEXT.md` is the Dokan glossary, so use its terms (Vendor, Suborder, Commission and so on). Lite's `docs/adr/` explains behaviour that looks like a bug but is intentional. The versions installed on the local site are `../dokan-lite` and `../dokan-pro`.

## Versioning & release

The version string lives in three places and must be bumped together:
- `dokan-customers.php` header `Version:`
- `DokanCustomers::$version` in `includes/DokanCustomers.php`
- `readme.txt` `Stable tag:` (also update `Tested up to:` when checking WP compatibility)

Release is via GitHub Actions to wordpress.org SVN:
- Pushing **any git tag** runs `.github/workflows/deploy-on-pushing-a-new-tag.yml`: sets up Node 20, runs `npm ci && npm run build`, runs `composer install --no-dev`, and deploys via `10up/action-wordpress-plugin-deploy`. Files excluded from the release are controlled by `.distignore`.
- Pushing to a `trunk` branch updates only the wordpress.org readme/assets.

## Local development

This checkout sits inside a Laravel Herd WordPress site (`bonsai-traders`). Run WP-CLI from the site root (`../../..`), e.g. `wp plugin get dokan-customers`.

When seeding test orders, note that `wp wc shop_order create` / REST-created orders do **not** fire Dokan's checkout hooks, so they won't appear in `dokan_orders` or on the Customers page. Create orders with `wc_create_order()` inside `wp eval` and call `dokan_sync_insert_order( $order_id )` (or fire `do_action( 'woocommerce_checkout_update_order_meta', $order_id, [] )`) to trigger Dokan's order-table sync. Vendor ownership of products is `post_author`.
