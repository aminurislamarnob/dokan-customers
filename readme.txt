=== Customer Management for Dokan ===
Contributors: aminurislam01
Tags: dokan, multi vendor, dokan customer, woocommerce, dokan multi vendor
Requires at least: 5.0.0
Tested up to: 6.8.2
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin simply enable vendor customer's management feature on the dokan vendor dashboard.

== Description ==

This is an extension plugin for the popular multi-vendor plugin Dokan. It enables a feature that displays a list of each vendor's customers on the vendor panel. By clicking "Customers" menu from vendor dashboard, vendor can view customer's names, email addresses, phone numbers, total orders by each customer, total spending by customers, registration date, and billing address. Vendors can also access the list of orders for each customer by clicking on the 'View Orders' button in each table row.
This plugin requires WooCommerce and Dokan Lite (Free) 5.0.0+ plugins to be activated in order to work.

== Installation ==

= FOR STANDARD INSTALLATION: =
Installing this plugin is very easy just like any other WordPress plugin. Please follow these instructions:
1. In your WordPress admin panel, go to Plugins > New Plugin, search for "Dokan Customers" and click on "Install Now"
2. Alternatively, download the plugin and upload the dokan-customers.zip to your plugins directory, which usually is /wp-content/plugins/.
3. Activate the plugin from plugins page.

== Changelog ==

= 2.0.0 =
* **New:** Moved the Customers page into Dokan's React vendor dashboard (`#customers`) using Dokan's `DataViews` table component, with server-side pagination, search by customer name or email, and sorting by Name, Orders, Total Spend, and Registered At.
* **New:** Added a per-customer details page (`#customers/:id`) showing a customer summary card and two `DataViews` tables of the orders placed with the current Vendor: **Paid Orders** (`processing`, `completed`, `on-hold`, the ones counted in the totals) and **Other Orders** (`pending`, `failed`, `cancelled`, `refunded`). Each order links to Dokan's order details view, and a partially refunded order shows its original total struck through next to the net amount so the column adds up to Total Spend.
* **New:** Added a CSV **Export** button to the Customers list with **Export All** and **Export Filtered** (the current search and sort).
* **Changed:** Requires Dokan Lite `5.0.0` or newer. Shows an admin notice and skips dashboard registration when an older Dokan Lite version is active.
* **Changed:** Customer order counts (`orders_count`) and spend (`total_spent`) now count only paid/active order statuses (`processing`, `completed`, and `on-hold`) and subtract refunded amounts, computed in a single SQL query (supporting both WooCommerce HPOS and legacy post storage).
* **Changed:** Visiting the legacy `/dashboard/customers/` endpoint now 301-redirects to the React dashboard (`#/customers`), keeping `pagenum` and `search`. A page number past the last page (for example an old bookmark) opens the last page instead of an empty one.
* **Changed:** Rewrite rules are flushed only on plugin activation and on `woocommerce_flush_rewrite_rules`, never on every request. Activation now registers the `customers` endpoint through Dokan's own flush, so the legacy redirect works immediately after installing the plugin.
* **Changed:** The Customers table wraps long emails and billing addresses so it fits the dashboard card instead of scrolling sideways.
* **Changed:** Dropped the empty admin and storefront scripts and styles. The plugin now loads a single dashboard bundle, and only on the React vendor dashboard page.
* **Removed:** Removed the legacy `templates/customers.php` template and the unused `ManageCustomers` static helper methods (`get_formatted_address`, `get_vendor_orders`, `get_vendor_customers`, `get_total_spend_by_seller_customer`).
