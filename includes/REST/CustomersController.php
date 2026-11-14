<?php

namespace PluginizeLab\DokanCustomers\REST;

use WC_Customer;
use WeDevs\Dokan\Abstracts\DokanRESTController;
use WeDevs\Dokan\Utilities\OrderUtil;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Customers REST API Controller.
 */
class CustomersController extends DokanRESTController {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'dokan-customers/v1';

    /**
     * Route base.
     *
     * @var string
     */
    protected $base = 'customers';

    /**
     * Route base (WP_REST_Controller compatibility).
     *
     * @var string
     */
    protected $rest_base = 'customers';

    /**
     * Order statuses that count toward a vendor's customer list and spend.
     *
     * @var array<int, string>
     */
    const COUNTED_STATUSES = [
        'wc-processing',
        'wc-completed',
        'wc-on-hold',
    ];

    /**
     * Register REST routes for customers.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'get_customers_permissions_check' ],
                    'args'                => $this->get_collection_params(),
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/(?P<id>[\d]+)',
            [
                'args'   => [
                    'id' => [
                        'description'       => __( 'Unique identifier for the customer.', 'dokan-customers' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_customers_permissions_check' ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );
    }

    /**
     * Check whether the current user has permission to view vendor customers.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error
     */
    public function get_customers_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
        $vendor_id = is_user_logged_in() ? dokan_get_current_user_id() : 0;

        if ( empty( $vendor_id ) || ! dokan_is_seller_enabled( $vendor_id ) || ! current_user_can( 'dokan_view_order_menu' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
            return new WP_Error(
                'dokan_rest_cannot_view',
                __( 'Sorry, you are not allowed to view customers.', 'dokan-customers' ),
                [ 'status' => rest_authorization_required_code() ]
            );
        }

        return true;
    }

    /**
     * Get a paginated collection of customers for the current vendor.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_items( $request ) {
        $vendor_id = dokan_get_current_user_id();
        $page      = max( 1, absint( $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1 ) );
        $per_page  = max( 1, min( 100, absint( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 10 ) ) );
        $search    = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $orderby   = $this->sanitize_orderby( (string) $request->get_param( 'orderby' ) );
        $order     = $this->sanitize_order( (string) $request->get_param( 'order' ) );

        $result = $this->query_vendor_customers(
            [
                'vendor_id' => $vendor_id,
                'page'      => $page,
                'per_page'  => $per_page,
                'search'    => $search,
                'orderby'   => $orderby,
                'order'     => $order,
            ]
        );

        $data = [];
        foreach ( $result['items'] as $row ) {
            $item   = $this->prepare_item_for_response( $row, $request );
            $data[] = $this->prepare_response_for_collection( $item );
        }

        $response = rest_ensure_response( $data );

        return $this->format_collection_response( $response, $request, $result['total'] );
    }

    /**
     * Get a single customer summary for the current vendor.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_item( $request ) {
        $vendor_id   = dokan_get_current_user_id();
        $customer_id = absint( $request->get_param( 'id' ) );

        $result = $customer_id > 0
            ? $this->query_vendor_customers(
                [
                    'vendor_id'   => $vendor_id,
                    'customer_id' => $customer_id,
                    'page'        => 1,
                    'per_page'    => 1,
                ]
            )
            : [ 'items' => [] ];

        if ( empty( $result['items'] ) ) {
            return new WP_Error(
                'dokan_rest_customer_not_found',
                __( 'Customer not found.', 'dokan-customers' ),
                [ 'status' => 404 ]
            );
        }

        return $this->prepare_item_for_response( $result['items'][0], $request );
    }

    /**
     * Query aggregated customer rows for a vendor in a single SQL query.
     *
     * @param array<string, mixed> $args Query arguments.
     * @return array{items: array<int, object>, total: int}
     */
    protected function query_vendor_customers( array $args ) {
        global $wpdb;

        $vendor_id   = absint( $args['vendor_id'] ?? 0 );
        $customer_id = absint( $args['customer_id'] ?? 0 );
        $page        = max( 1, absint( $args['page'] ?? 1 ) );
        $per_page    = max( 1, min( 100, absint( $args['per_page'] ?? 10 ) ) );
        $search      = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
        $orderby     = $this->sanitize_orderby( (string) ( $args['orderby'] ?? 'total_spent' ) );
        $order       = strtoupper( $this->sanitize_order( (string) ( $args['order'] ?? 'desc' ) ) );
        $offset      = ( $page - 1 ) * $per_page;

        $dokan_orders_table = $wpdb->prefix . 'dokan_orders';
        $users_table        = $wpdb->users;

        $orderby_map = [
            'name'            => 'u.display_name',
            'orders_count'    => 'orders_count',
            'total_spent'     => 'total_spent',
            'date_registered' => 'u.user_registered',
        ];
        $order_sql   = $orderby_map[ $orderby ] . ' ' . $order . ', u.ID ASC';

        $where_params = [
            $vendor_id,
            self::COUNTED_STATUSES[0],
            self::COUNTED_STATUSES[1],
            self::COUNTED_STATUSES[2],
        ];

        $extra_where = '';

        if ( $customer_id > 0 ) {
            $extra_where   .= ' AND u.ID = %d';
            $where_params[] = $customer_id;
        }

        if ( '' !== $search ) {
            $like           = '%' . $wpdb->esc_like( $search ) . '%';
            $extra_where   .= ' AND ( u.display_name LIKE %s OR u.user_email LIKE %s )';
            $where_params[] = $like;
            $where_params[] = $like;
        }

        if ( class_exists( OrderUtil::class ) && OrderUtil::is_hpos_enabled() ) {
            $orders_table = OrderUtil::get_order_table_name();

            $from_and_where = "
                FROM {$dokan_orders_table} AS do
                INNER JOIN {$orders_table} AS o
                    ON o.id = do.order_id
                    AND o.type = 'shop_order'
                    AND o.customer_id > 0
                INNER JOIN {$users_table} AS u
                    ON u.ID = o.customer_id
                LEFT JOIN (
                    SELECT parent_order_id, SUM( ABS( total_amount ) ) AS refund_total
                    FROM {$orders_table}
                    WHERE type = 'shop_order_refund'
                    GROUP BY parent_order_id
                ) AS refunds
                    ON refunds.parent_order_id = o.id
                WHERE do.seller_id = %d
                    AND do.order_status IN ( %s, %s, %s )
                    {$extra_where}
            ";

            $select_total_spent = 'COALESCE( SUM( GREATEST( 0, o.total_amount - COALESCE( refunds.refund_total, 0 ) ) ), 0 )';
        } else {
            $posts_table    = $wpdb->posts;
            $postmeta_table = $wpdb->postmeta;

            $from_and_where = "
                FROM {$dokan_orders_table} AS do
                INNER JOIN {$posts_table} AS p
                    ON p.ID = do.order_id
                    AND p.post_type = 'shop_order'
                INNER JOIN {$postmeta_table} AS pm_customer
                    ON pm_customer.post_id = do.order_id
                    AND pm_customer.meta_key = '_customer_user'
                INNER JOIN {$postmeta_table} AS pm_total
                    ON pm_total.post_id = do.order_id
                    AND pm_total.meta_key = '_order_total'
                INNER JOIN {$users_table} AS u
                    ON u.ID = CAST( pm_customer.meta_value AS UNSIGNED )
                LEFT JOIN (
                    SELECT rp.post_parent AS parent_order_id, SUM( ABS( CAST( rpm.meta_value AS DECIMAL(26,8) ) ) ) AS refund_total
                    FROM {$posts_table} AS rp
                    INNER JOIN {$postmeta_table} AS rpm
                        ON rpm.post_id = rp.ID
                        AND rpm.meta_key = '_refund_amount'
                    WHERE rp.post_type = 'shop_order_refund'
                    GROUP BY rp.post_parent
                ) AS refunds
                    ON refunds.parent_order_id = do.order_id
                WHERE do.seller_id = %d
                    AND do.order_status IN ( %s, %s, %s )
                    AND CAST( pm_customer.meta_value AS UNSIGNED ) > 0
                    {$extra_where}
            ";

            $select_total_spent = 'COALESCE( SUM( GREATEST( 0, CAST( pm_total.meta_value AS DECIMAL(26,8) ) - COALESCE( refunds.refund_total, 0 ) ) ), 0 )';
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count_sql = $wpdb->prepare(
            "SELECT COUNT( DISTINCT u.ID ) {$from_and_where}",
            $where_params
        );
        $total     = (int) $wpdb->get_var( $count_sql );

        if ( 0 === $total ) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        $query_params = array_merge( $where_params, [ $per_page, $offset ] );
        $items_sql    = $wpdb->prepare(
            "SELECT
                u.ID AS customer_id,
                u.display_name,
                u.user_login,
                u.user_email,
                u.user_registered,
                COUNT( DISTINCT do.order_id ) AS orders_count,
                {$select_total_spent} AS total_spent
            {$from_and_where}
            GROUP BY u.ID, u.display_name, u.user_login, u.user_email, u.user_registered
            ORDER BY {$order_sql}
            LIMIT %d OFFSET %d",
            $query_params
        );

        $items = $wpdb->get_results( $items_sql );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return [
            'items' => is_array( $items ) ? $items : [],
            'total' => $total,
        ];
    }

    /**
     * Prepare a single customer row for the REST response.
     *
     * @param object          $item    Aggregated database row.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function prepare_item_for_response( $item, $request ) {
        $customer_id = absint( $item->customer_id );
        $customer    = new WC_Customer( $customer_id );

        $name = ! empty( $item->display_name ) ? (string) $item->display_name : (string) $item->user_login;

        $address = $customer->get_billing();
        unset(
            $address['first_name'],
            $address['last_name'],
            $address['company'],
            $address['email'],
            $address['tel'],
            $address['phone']
        );

        $formatted_address = '';
        if ( function_exists( 'WC' ) && isset( WC()->countries ) ) {
            $formatted_address = WC()->countries->get_formatted_address( $address, ', ' );
            $formatted_address = wp_strip_all_tags( html_entity_decode( (string) $formatted_address, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
        }

        $total_spent           = (float) wc_format_decimal( $item->total_spent, wc_get_price_decimals() );
        $total_spent_formatted = wp_strip_all_tags( html_entity_decode( (string) wc_price( $total_spent ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

        $data = [
            'id'                    => $customer_id,
            'name'                  => $name,
            'email'                 => (string) $item->user_email,
            'phone'                 => (string) $customer->get_billing_phone(),
            'orders_count'          => (int) $item->orders_count,
            'total_spent'           => $total_spent,
            'total_spent_formatted' => $total_spent_formatted,
            'date_registered'       => (string) $item->user_registered,
            'billing_address'       => $formatted_address,
        ];

        $context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data     = $this->add_additional_fields_to_object( $data, $request );
        $data     = $this->filter_response_by_context( $data, $context );
        $response = rest_ensure_response( $data );

        return $response;
    }

    /**
     * Sanitize and normalize the orderby parameter.
     *
     * @param string $orderby Raw orderby value.
     * @return string
     */
    public function sanitize_orderby( $orderby ) {
        $allowed = [ 'name', 'orders_count', 'total_spent', 'date_registered' ];
        $orderby = strtolower( trim( (string) $orderby ) );

        return in_array( $orderby, $allowed, true ) ? $orderby : 'total_spent';
    }

    /**
     * Sanitize and normalize the order direction parameter.
     *
     * @param string $order Raw order direction value.
     * @return string
     */
    public function sanitize_order( $order ) {
        $order = strtolower( trim( (string) $order ) );

        return 'asc' === $order ? 'asc' : 'desc';
    }

    /**
     * Get the query params for collections.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();

        $params['page']['default']     = 1;
        $params['per_page']['default'] = 10;

        $params['search'] = [
            'description'       => __( 'Search customers by display name or email.', 'dokan-customers' ),
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'rest_validate_request_arg',
        ];

        $params['orderby'] = [
            'description'       => __( 'Sort collection by customer attribute.', 'dokan-customers' ),
            'type'              => 'string',
            'default'           => 'total_spent',
            'sanitize_callback' => [ $this, 'sanitize_orderby' ],
        ];

        $params['order'] = [
            'description'       => __( 'Order sort attribute ascending or descending.', 'dokan-customers' ),
            'type'              => 'string',
            'default'           => 'desc',
            'sanitize_callback' => [ $this, 'sanitize_order' ],
        ];

        return $params;
    }

    /**
     * Get the Customer schema, conforming to JSON Schema.
     *
     * @return array<string, mixed>
     */
    public function get_item_schema() {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'customer',
            'type'       => 'object',
            'properties' => [
                'id'              => [
                    'description' => __( 'Unique identifier for the customer.', 'dokan-customers' ),
                    'type'        => 'integer',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'name'            => [
                    'description' => __( 'Customer display name.', 'dokan-customers' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'email'           => [
                    'description' => __( 'Customer email address.', 'dokan-customers' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'phone'           => [
                    'description' => __( 'Customer billing phone.', 'dokan-customers' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'orders_count'    => [
                    'description' => __( 'Counted orders placed with the current vendor.', 'dokan-customers' ),
                    'type'        => 'integer',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'total_spent'           => [
                    'description' => __( 'Net amount spent with the current vendor after refunds.', 'dokan-customers' ),
                    'type'        => 'number',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'total_spent_formatted' => [
                    'description' => __( 'Formatted net amount spent with the current vendor.', 'dokan-customers' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'date_registered' => [
                    'description' => __( 'Customer registration date.', 'dokan-customers' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'billing_address' => [
                    'description' => __( 'Formatted one-line billing address.', 'dokan-customers' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
            ],
        ];

        return $this->add_additional_fields_schema( $schema );
    }
}
