<?php

namespace PluginizeLab\DokanCustomers;

/**
 * Manage Customers dashboard navigation and legacy endpoint redirect.
 */
class ManageCustomers {

    /**
     * The constructor.
     */
    public function __construct() {
        add_filter( 'dokan_query_var_filter', [ $this, 'register_customers_query_var' ] );
        add_filter( 'dokan_get_dashboard_nav', [ $this, 'dokan_add_customers_menu' ] );
        add_filter( 'dokan_get_vendor_orders_args', [ $this, 'filter_vendor_orders_status_args' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'redirect_legacy_customers_page' ], 1 );
    }

    /**
     * Support `paid`, `other`, and comma-separated status values when querying vendor orders.
     *
     * @param array<string, mixed> $query_args Parsed WC_Order_Query arguments.
     * @param array<string, mixed> $args       Raw arguments passed to `dokan()->order->all()`.
     * @return array<string, mixed>
     */
    public function filter_vendor_orders_status_args( $query_args, $args ) {
        if ( empty( $args['status'] ) || ! is_string( $args['status'] ) ) {
            return $query_args;
        }

        $status  = trim( $args['status'] );
        $counted = REST\CustomersController::COUNTED_STATUSES;

        if ( 'paid' === $status ) {
            $query_args['status'] = $counted;
        } elseif ( 'other' === $status ) {
            $all_statuses         = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : [];
            $other_statuses       = array_values( array_diff( $all_statuses, $counted ) );
            $query_args['status'] = ! empty( $other_statuses )
                ? $other_statuses
                : [ 'wc-pending', 'wc-cancelled', 'wc-refunded', 'wc-failed' ];
        } elseif ( false !== strpos( $status, ',' ) ) {
            $query_args['status'] = array_values(
                array_filter(
                    array_map( 'sanitize_text_field', explode( ',', $status ) )
                )
            );
        }

        return $query_args;
    }

    /**
     * Register the `customers` query var with Dokan's rewrite rules.
     *
     * @param array<string, string> $query_vars Dokan query vars.
     * @return array<string, string>
     */
    public function register_customers_query_var( $query_vars ) {
        $query_vars['customers'] = 'customers';
        return $query_vars;
    }

    /**
     * Add Customers menu item on the Dokan vendor dashboard.
     *
     * @param array<string, array<string, mixed>> $urls Dashboard navigation items.
     * @return array<string, array<string, mixed>>
     */
    public function dokan_add_customers_menu( $urls ) {
        if ( ! pluginizelab_dokan_customers()->is_supported_dokan_version() ) {
            return $urls;
        }

        if ( ! dokan_is_seller_enabled( dokan_get_current_user_id() ) ) {
            return $urls;
        }

        $urls['customers'] = [
            'title'       => __( 'Customers', 'dokan-customers' ),
            'icon'        => '<i class="fas fa-users"></i>',
            'url'         => dokan_get_navigation_url( 'customers' ),
            'pos'         => 51,
            'icon_name'   => 'Users',
            'permission'  => 'dokan_view_order_menu',
            'react_route' => 'customers',
        ];

        return $urls;
    }

    /**
     * Redirect the legacy `/dashboard/customers/` endpoint to the React dashboard `#/customers` route.
     *
     * @return void
     */
    public function redirect_legacy_customers_page() {
        global $wp;

        if ( ! isset( $wp->query_vars['customers'] ) ) {
            return;
        }

        if ( ! function_exists( 'dokan_get_navigation_url' ) ) {
            return;
        }

        $target_hash = '#/customers';
        $preserved   = [];

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        foreach ( [ 'pagenum', 'page', 'search', 's' ] as $param ) {
            if ( isset( $_GET[ $param ] ) && '' !== $_GET[ $param ] ) {
                $preserved[ $param ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( ! empty( $preserved ) ) {
            $target_hash .= '?' . http_build_query( $preserved );
        }

        $redirect_url = dokan_get_navigation_url( 'new' ) . $target_hash;

        wp_safe_redirect( $redirect_url, 301 );
        exit;
    }
}
