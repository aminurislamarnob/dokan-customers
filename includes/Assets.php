<?php

namespace PluginizeLab\DokanCustomers;

class Assets {
    /**
     * Script and style handle for the React dashboard bundle.
     *
     * @var string
     */
    const DASHBOARD_HANDLE = 'dokan-customers-dashboard';

    /**
     * The constructor.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_all_scripts' ], 10 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_scripts' ] );
    }

    /**
     * Register plugin scripts and styles for the React vendor dashboard.
     *
     * @return void
     */
    public function register_all_scripts() {
        $asset_file = DOKAN_CUSTOMERS_DIR . '/assets/js/dokan-customers-dashboard.asset.php';

        if ( ! file_exists( $asset_file ) ) {
            return;
        }

        $asset        = include $asset_file;
        $dependencies = array_unique(
            array_merge(
                $asset['dependencies'] ?? [],
                [ 'dokan-react-frontend', 'dokan-react-components' ]
            )
        );
        $version      = $asset['version'] ?? DOKAN_CUSTOMERS_PLUGIN_VERSION;

        wp_register_script(
            self::DASHBOARD_HANDLE,
            DOKAN_CUSTOMERS_PLUGIN_ASSET . '/js/dokan-customers-dashboard.js',
            $dependencies,
            $version,
            true
        );

        wp_set_script_translations( self::DASHBOARD_HANDLE, 'dokan-customers' );

        $style_file = DOKAN_CUSTOMERS_DIR . '/assets/js/dokan-customers-dashboard.css';
        if ( file_exists( $style_file ) ) {
            wp_register_style(
                self::DASHBOARD_HANDLE,
                DOKAN_CUSTOMERS_PLUGIN_ASSET . '/js/dokan-customers-dashboard.css',
                [ 'dokan-react-components' ],
                $version
            );
        }
    }

    /**
     * Enqueue scripts and styles only on the React vendor dashboard page.
     *
     * @return void
     */
    public function enqueue_front_scripts() {
        global $wp;

        if ( ! pluginizelab_dokan_customers()->is_supported_dokan_version() ) {
            return;
        }

        if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() || ! isset( $wp->query_vars['new'] ) ) {
            return;
        }

        wp_enqueue_script( self::DASHBOARD_HANDLE );
        wp_enqueue_style( self::DASHBOARD_HANDLE );
    }
}
